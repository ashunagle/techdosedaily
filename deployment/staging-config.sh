#!/usr/bin/env bash
# TechDoseDaily — apply wp-config constants, fresh salts and .htaccess rules to the STAGING site.
# Run over SSH in the staging WordPress root, after staging-bootstrap.sh:
#
#   cd ~/domains/techdosedaily.com/public_html/staging
#   bash ~/release-0.9.0/staging-config.sh
#
# Implements deployment/wp-config-additions.php, htaccess-root-additions.txt and htaccess-uploads.txt
# (SECURITY.md §8) without hand-editing. Safe to re-run: constants are set idempotently and the
# .htaccess rules live between "# BEGIN TechDoseDaily" / "# END TechDoseDaily" markers.
# Nothing secret is printed: salts are generated on the server and never echoed.
set -euo pipefail
say() { printf '\n== %s\n' "$*"; }

say "Safety checks"
HOME_URL=$(wp option get home)
echo "home=$HOME_URL"
case "$HOME_URL" in *://staging.*) ;; *) echo "Not the staging site. Stopping." >&2; exit 1 ;; esac
[ "$(wp option get blog_public)" = "0" ] || { echo "Staging must be noindex (blog_public=0). Stopping." >&2; exit 1; }
[ -f wp-config.php ] || { echo "wp-config.php not found here. Stopping." >&2; exit 1; }

STAMP=$(date +%Y%m%d-%H%M%S)
BK="$HOME/backups/tdd-staging-$STAMP"
mkdir -p "$BK" && chmod 700 "$HOME/backups" "$BK"
cp -p wp-config.php "$BK/wp-config.php"
[ -f .htaccess ] && cp -p .htaccess "$BK/htaccess-root"
[ -f wp-content/uploads/.htaccess ] && cp -p wp-content/uploads/.htaccess "$BK/htaccess-uploads"
echo "backups (outside the web root): $BK"

say "wp-config constants (wp-config-additions.php)"
mkdir -p "$HOME/logs" && chmod 700 "$HOME/logs"
LOG="$HOME/logs/wp-debug-staging.log"     # outside public_html — never web-readable
wp config set WP_ENVIRONMENT_TYPE staging --type=constant
wp config set WP_DEBUG false --raw --type=constant
wp config set WP_DEBUG_DISPLAY false --raw --type=constant
wp config set WP_DEBUG_LOG "$LOG" --type=constant
wp config set DISALLOW_FILE_EDIT true --raw --type=constant
wp config set FORCE_SSL_ADMIN true --raw --type=constant
wp config set WP_AUTO_UPDATE_CORE minor --type=constant
wp config set DISABLE_WP_CRON true --raw --type=constant
# display_errors is already off in hPanel → PHP options; WP_DEBUG_DISPLAY=false covers WordPress itself.

say "Salts: new, staging-only (the staging copy inherited production's)"
wp config shuffle-salts >/dev/null && echo "salts regenerated (not printed). Everyone is logged out of staging — sign in again."

say "Root .htaccess (htaccess-root-additions.txt, HTTPS redirect left to hPanel Force HTTPS)"
BLOCK=$(cat <<'EOF'
# BEGIN TechDoseDaily
# SECURITY.md §8 — managed by deployment/staging-config.sh; edits inside this block are overwritten.
Options -Indexes
<Files xmlrpc.php>
  Require all denied
</Files>
<FilesMatch "^(wp-config\.php|wp-config-sample\.php|readme\.html|license\.txt)$">
  Require all denied
</FilesMatch>
RedirectMatch 403 ^/\.(?!well-known/).*
<IfModule mod_headers.c>
  Header always set X-Content-Type-Options "nosniff"
  # Hostinger sends "Content-Security-Policy: upgrade-insecure-requests" from the server, which replaces
  # Core's CSP (hardening.php). Send the merged policy here instead; it also covers wp-admin (LiteSpeed does not
  # match SetEnvIf on wp-admin requests here, so an exception there never took effect — gate S2 records it).
  # oEmbed /embed/ cards drop frame-ancestors because they are meant to be framed.
  SetEnvIf Request_URI "/embed/?$" TDD_CSP_SKIP TDD_EMBED
  Header always set Content-Security-Policy "upgrade-insecure-requests; frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'" env=!TDD_CSP_SKIP
  Header always set Content-Security-Policy "upgrade-insecure-requests; base-uri 'self'; object-src 'none'; form-action 'self'" env=TDD_EMBED
</IfModule>
# END TechDoseDaily
EOF
)
WPBLOCK=$(cat <<'EOF'
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
EOF
)
touch .htaccess
# Drop any previous TechDoseDaily block, keep everything else (hPanel password protection, LiteSpeed, WordPress).
REST=$(awk '/^# BEGIN TechDoseDaily/{skip=1} !skip{print} /^# END TechDoseDaily/{skip=0}' .htaccess)
# Hostinger puts a mod_expires section in the WordPress block (CSS/JS 1 month). Being last, it overrides
# LiteSpeed's one-year browser cache (PERFORMANCE.md, gate C4) — drop it from that block only.
REST=$(printf '%s\n' "$REST" | awk '/^# BEGIN WordPress/{wp=1} /^# END WordPress/{wp=0} wp && /<IfModule mod_expires\.c>/{skip=1} !skip{print} skip && /<\/IfModule>/{skip=0}')
if ! printf '%s\n' "$REST" | grep -q '^# BEGIN WordPress'; then
  echo "WordPress rewrite block missing — adding the standard one (wp-cli cannot write it on this host)."
  REST=$(printf '%s\n\n%s\n' "$REST" "$WPBLOCK")
fi
printf '%s\n\n%s\n' "$BLOCK" "$REST" > .htaccess.tdd-new && mv .htaccess.tdd-new .htaccess
chmod 644 .htaccess
echo "sections now in .htaccess:"; grep -E '^# (BEGIN|END) ' .htaccess || true
grep -qiE '^AuthType|AuthUserFile' .htaccess && echo "hPanel password protection: present" \
  || echo "!! No AuthType/AuthUserFile line here — check hPanel → Password Protect Directories for /staging (it may live in a parent .htaccess)."

say "Uploads .htaccess (htaccess-uploads.txt)"
mkdir -p wp-content/uploads
cat > wp-content/uploads/.htaccess <<'EOF'
# TechDoseDaily — SECURITY.md §8 (managed by deployment/staging-config.sh). Nothing in uploads may execute.
<FilesMatch "\.(php|phtml|phar|pl|py|cgi|sh|shtml)$">
  Require all denied
</FilesMatch>
Options -Indexes -ExecCGI
EOF
chmod 644 wp-content/uploads/.htaccess
echo "written"

say "Verify"
wp eval 'printf("environment=%s debug=%s display=%s file_edit_disallowed=%s cron_disabled=%s ssl_admin=%s\n",
  wp_get_environment_type(), var_export(WP_DEBUG,true), var_export(WP_DEBUG_DISPLAY,true),
  var_export(defined("DISALLOW_FILE_EDIT")&&DISALLOW_FILE_EDIT,true), var_export(defined("DISABLE_WP_CRON")&&DISABLE_WP_CRON,true),
  var_export(defined("FORCE_SSL_ADMIN")&&FORCE_SSL_ADMIN,true));'
wp eval 'echo "imagick=" . (extension_loaded("imagick")?"yes":"no") . " php=" . PHP_VERSION . " expose_php=" . ini_get("expose_php") . "\n";'
echo "wp-config.php mode: $(stat -c %a wp-config.php)"

say "Done. Next: personal administrator account + Two Factor, LiteSpeed and MailPoet settings (LAUNCH-GATES.md C/D)"
