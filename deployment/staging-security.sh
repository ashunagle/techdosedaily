#!/bin/bash
# TechDoseDaily — run the security and XSS suites against STAGING (gate E2), then remove every trace.
#
#   bash ~/release-<v>/staging-security.sh        # prompts for the hPanel directory login (never stored)
#
# Before: safety checks (staging, noindex, directory protection on), database + files snapshot.
# During: two harness MU-plugins (checksums logged) so no mail is sent and no subscriber is created:
#   mu-capture-mail.php            wp_mail is captured to wp-content/tdd-mail-test.log, never sent
#   mu-fake-newsletter-provider.php newsletter sign-ups go to a fake provider, never MailPoet
#   The suites refuse to run unless both are active. All test addresses are @example.invalid/.com.
# After (always, even on failure): harness, mail log, test accounts, suite fixtures, throttles and the
# temporary Pillow copy removed; leftovers checked; full gate run.
# Output: ~/release-<v>/out/security-<time>.log
set -uo pipefail
HERE=$(dirname "$(readlink -f "$0")")
SITE="$HOME/domains/techdosedaily.com/public_html/staging"
PY=/opt/alt/python311/bin/python3
BASE=https://staging.techdosedaily.com
MU="$SITE/wp-content/mu-plugins"
HARNESS="mu-capture-mail.php mu-fake-newsletter-provider.php"
PYLIB="$HERE/.pylib-security"
mkdir -p "$HERE/out"; LOG="$HERE/out/security-$(date -u +%Y%m%d-%H%M%SZ).log"
exec > >(tee -a "$LOG") 2>&1
cd "$SITE" || exit 1
say() { printf '\n== %s\n' "$*"; }

say "Safety checks"
case "$(wp option get home)" in *://staging.*) ;; *) echo "Not the staging site. Stopping."; exit 1 ;; esac
[ "$(wp option get blog_public)" = "0" ] || { echo "Staging must be noindex. Stopping."; exit 1; }
[ "$(wp eval 'echo wp_get_environment_type();')" = "staging" ] || { echo "Not WP_ENVIRONMENT_TYPE=staging. Stopping."; exit 1; }
grep -q "PWPROTECTID" .htaccess || { echo "Directory protection missing from .htaccess. Stopping."; exit 1; }
ADMIN_ID=$(wp user list --role=administrator --field=ID --number=1)
for f in $HARNESS; do [ -f "$HERE/fixtures/$f" ] || { echo "Missing $HERE/fixtures/$f. Stopping."; exit 1; }; done
[ -f "$HERE/security/security_test.py" ] && [ -f "$HERE/security/xss_test.py" ] || { echo "Suites missing in $HERE/security. Stopping."; exit 1; }

read -rsp "Staging directory login as username:password > " TDD_BASIC_AUTH; echo; export TDD_BASIC_AUTH
code=$($PY -c 'import os,requests; u,p=os.environ["TDD_BASIC_AUTH"].split(":",1); print(requests.get("'$BASE'/",auth=(u,p),allow_redirects=False,timeout=30).status_code)' 2>/dev/null)
[ -n "$code" ] && [ "$code" != 401 ] || { echo "Login rejected (HTTP ${code:-error}). Nothing was changed."; exit 1; }

say "Snapshot (database + files)"
BK="$HOME/backups/tdd-staging-$(date +%Y%m%d-%H%M%S)-pre-security"
mkdir -p "$BK" && chmod 700 "$HOME/backups" "$BK"
export MYSQL_PWD="$(wp config get DB_PASSWORD)"   # wp db export fails silently on this host
mysqldump --single-transaction --no-tablespaces -h "$(wp config get DB_HOST)" -u "$(wp config get DB_USER)" "$(wp config get DB_NAME)" > "$BK/db.sql"
unset MYSQL_PWD
tar -czf "$BK/files.tgz" -C "$SITE/.." "$(basename "$SITE")"
chmod 600 "$BK"/*; ls -la "$BK"

cleanup() {
  say "Cleanup"
  for f in $HARNESS; do rm -f "$MU/$f"; done
  rm -f "$SITE/wp-content/tdd-mail-test.log"
  rm -rf "$PYLIB" "$HERE/security/__pycache__"
  for u in $(wp user list --field=user_login | grep -E '^tdd-(sec|xss)-' || true); do wp user delete "$u" --yes --reassign="$ADMIN_ID"; done
  wp eval 'foreach ( get_posts( array( "post_type" => "any", "post_status" => "any", "numberposts" => -1, "fields" => "ids", "meta_key" => "_tdd_fixture", "meta_value" => "phase8" ) ) as $id ) { wp_delete_post( $id, true ); }
    global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE \"\\_transient%tdd\\_t%\" OR option_name LIKE \"\\_transient\\_timeout%tdd\\_t%\"" );
    do_action( "litespeed_purge_all" ); echo "suite fixtures, throttles and cache cleared\n";'
  say "Leftover check"
  wp plugin list --status=must-use,dropin,inactive --fields=name,status --format=csv
  echo "test users: $(wp user list --field=user_login | grep -cE '^tdd-(sec|xss|cache)-' || true)"
  wp eval 'global $wpdb; echo "phase8 fixtures: ", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = \"_tdd_fixture\" AND meta_value = \"phase8\"" ), "\n";
    echo "MailPoet subscribers at test domains: ", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mailpoet_subscribers WHERE email LIKE \"%@example.invalid\" OR email LIKE \"%@example.com\"" ), "\n";'
  echo "mail log: $([ -e "$SITE/wp-content/tdd-mail-test.log" ] && echo PRESENT || echo absent)"
  echo "harness: $(ls "$MU" | grep -cE '^mu-' || true) mu-* files"
}
trap cleanup EXIT

say "Temporary Pillow (removed afterwards)"
$PY -m pip install --quiet --target "$PYLIB" Pillow && export PYTHONPATH="$PYLIB"
$PY -c 'import PIL; print("Pillow", PIL.__version__)'

say "Install harness"
mkdir -p "$MU"
for f in $HARNESS; do cp "$HERE/fixtures/$f" "$MU/$f"; sha256sum "$MU/$f"; done

export BASE TDD_ADMIN_ID="$ADMIN_ID" WP="wp --path=$SITE"
say "security_test.py"; $PY "$HERE/security/security_test.py"; SEC=$?
say "xss_test.py";      $PY "$HERE/security/xss_test.py";      XSS=$?
echo "security exit=$SEC xss exit=$XSS"

trap - EXIT
cleanup
say "Gates (D1, P2, P6 and the rest)"
WP="wp --path=$SITE" $PY "$HERE/gates.py" --stage staging
unset TDD_BASIC_AUTH
echo "log: $LOG"
