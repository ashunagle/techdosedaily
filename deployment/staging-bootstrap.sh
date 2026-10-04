#!/usr/bin/env bash
# TechDoseDaily — clean staging bootstrap (run over SSH in the staging WordPress root).
#
#   cd ~/domains/<staging-domain>/public_html          # the STAGING site's root (check twice)
#   bash staging-bootstrap.sh /path/to/release-dir      # dir with the two 0.9.0 zips + SHA256SUMS
#
# Installs only what PLUGIN-DECISIONS allows, applies the documented settings (SEO-SCHEMA.md §7,
# PERFORMANCE.md, SECURITY.md §8), removes WordPress demo content, and NEVER creates sample stories,
# people or example.com data. Safe to re-run. It refuses to run on a site that is open to search
# engines unless --production is given (and production is out of scope until the launch gates pass).
set -euo pipefail

REL="${1:?usage: staging-bootstrap.sh <release dir> [--production]}"
MODE="${2:-}"
TZ_STRING="${TDD_TIMEZONE:-Asia/Kolkata}"
say() { printf '\n== %s\n' "$*"; }

say "Environment"
wp core version
wp eval 'echo "WP_ENVIRONMENT_TYPE=" . wp_get_environment_type() . "  home=" . home_url() . "\n";'
if [ "$(wp option get blog_public)" = "1" ] && [ "$MODE" != "--production" ]; then
  echo "This site is open to search engines (blog_public=1). Staging must be noindex. Stopping." >&2
  exit 1
fi

say "Release integrity"
( cd "$REL" && sha256sum -c SHA256SUMS )

say "Plugins: only the approved set (PLUGIN-DECISIONS.md)"
wp plugin install wordpress-seo litespeed-cache two-factor --activate
wp plugin install mailpoet            # Activated and configured in its own wizard (double opt-in ON).
wp plugin install "$REL"/techdosedaily-core-*.zip --force --activate
wp plugin delete hello akismet 2>/dev/null || true

say "Theme"
wp theme install "$REL"/techdosedaily-*.zip --force --activate
# Keep exactly one default theme as a recovery fallback; remove the rest.
for t in $(wp theme list --status=inactive --field=name); do
  case "$t" in twentytwentyfive) ;; *) wp theme delete "$t" ;; esac
done

say "WordPress demo content (never real content)"
for id in $(wp post list --post_type=post,page --name='hello-world' --field=ID) $(wp post list --post_type=page --name='sample-page' --field=ID) $(wp post list --post_type=page --name='privacy-policy' --post_status=draft --field=ID); do
  wp post delete "$id" --force
done
wp comment delete $(wp comment list --field=comment_ID) --force 2>/dev/null || true

say "Core settings"
wp option update timezone_string "$TZ_STRING"
wp option update users_can_register 0
wp option update default_role subscriber
wp option update default_comment_status closed
wp option update default_ping_status closed
wp option update default_pingback_flag 0
wp option update blog_public 0          # staging: discourage search engines (launch gate flips this)
wp rewrite structure '/%category%/%postname%/' --hard
wp rewrite flush --hard

say "Front page + posts page (structural pages only, no copy)"
home=$(wp post list --post_type=page --name=home --field=ID); [ -n "$home" ] || home=$(wp post create --post_type=page --post_status=publish --post_title='Home' --post_name=home --porcelain)
latest=$(wp post list --post_type=page --name=latest --field=ID); [ -n "$latest" ] || latest=$(wp post create --post_type=page --post_status=publish --post_title='Latest' --post_name=latest --porcelain)
wp option update show_on_front page
wp option update page_on_front "$home"
wp option update page_for_posts "$latest"

say "Yoast SEO (SEO-SCHEMA.md §7)"
wp eval '
$t = get_option( "wpseo_titles", array() );
$set = array(
  "disable-date" => true, "noindex-tax-post_tag" => true, "disable-post_format" => true, "breadcrumbs-enable" => false,
  "disable-attachment" => true, "noindex-author-noposts-wpseo" => true, "disable-author" => false, "stripcategorybase" => false,
  "noindex-tax-category" => false, "noindex-tax-tdd_topic" => false, "noindex-tax-tdd_format" => false,
  "title-tax-category" => "%%term_title%% %%page%% %%sep%% %%sitename%%", "title-tax-tdd_topic" => "%%term_title%% %%page%% %%sep%% %%sitename%%",
  "title-tax-tdd_format" => "%%term_title%% %%page%% %%sep%% %%sitename%%", "title-author-wpseo" => "%%name%% %%page%% %%sep%% %%sitename%%",
  "title-404-wpseo" => "Page not found · %%sitename%%", "company_or_person" => "company", "company_name" => "Tech Dose Daily",
);
update_option( "wpseo_titles", array_merge( (array) $t, $set ) );
$s = get_option( "wpseo_social", array() ); $s["og_default_image"] = ""; $s["og_default_image_id"] = ""; update_option( "wpseo_social", $s );
echo "Yoast titles/social applied\n";'
wp option update blogname 'Tech Dose Daily'

say "Structured data owner (keep Yoast as owner unless decided otherwise)"
wp option update tdd_core_schema_owner yoast

say "Accounts"
wp user list --fields=ID,user_login,roles
if wp user get admin >/dev/null 2>&1; then echo "!! An account called 'admin' exists — replace it with personal administrator accounts (launch gate)."; fi

say "Done. Next: wp-config constants, .htaccess rules, LiteSpeed + MailPoet + Two Factor settings (LAUNCH-GATES.md), then run tests/staging/gates.py"
