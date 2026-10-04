#!/usr/bin/env bash
# TechDoseDaily — LiteSpeed Cache (PERFORMANCE.md §3.4) and MailPoet (double opt-in) on STAGING.
# Run over SSH in the staging WordPress root, after staging-config.sh:
#
#   cd ~/domains/techdosedaily.com/public_html/staging
#   bash ~/release-0.9.0/staging-plugins.sh
#
# Safe to re-run. Each setting is reported; a key this LiteSpeed version does not know is reported
# as "skipped" instead of stopping the script. Sender address and list are chosen in the MailPoet
# screens by the site owner (a real mailbox is needed) — this script never invents one.
set -uo pipefail
say() { printf '\n== %s\n' "$*"; }

case "$(wp option get home)" in *://staging.*) ;; *) echo "Not the staging site. Stopping." >&2; exit 1 ;; esac

say "LiteSpeed Cache (PERFORMANCE.md §3.4)"
wp plugin is-active litespeed-cache || { echo "LiteSpeed Cache is not active. Stopping." >&2; exit 1; }
ls_set() { if wp litespeed-option set "$1" "$2" >/dev/null 2>&1; then printf '  %-22s %s\n' "$1" "$2"; else printf '  %-22s skipped (unknown in this version)\n' "$1"; fi; }
ls_set cache               true
ls_set cache-priv          false     # never cache logged-in users
ls_set cache-commenter     false
ls_set cache-mobile        false     # one responsive DOM
ls_set cache-rest          true
ls_set cache-ttl_pub       3600      # Core overrides per page
ls_set cache-ttl_frontpage 300
ls_set cache-exc           "/contact/"
ls_set cache-exc_qs        "$(printf 'tdd_nl\ntdd_cf')"
ls_set cache-browser       true
ls_set cache-ttl_browser   31536000
for k in optm-css_min optm-js_min optm-css_comb optm-js_comb optm-css_async optm-ccss_gen optm-ucss \
         optm-html_min optm-qs_rm optm-ggfonts_rm optm-ggfonts_async optm-emoji_rm optm-js_defer \
         media-lazy media-iframe_lazy media-add_missing_sizes media-placeholder_resp \
         img_optm-auto img_optm-webp guest guest_optm esi; do
  ls_set "$k" false
done
wp litespeed-purge all >/dev/null 2>&1 && echo "  cache purged"

say "Object cache (PERFORMANCE.md §3.4: on if the host offers Redis/Memcached)"
wp litespeed-option get object 2>/dev/null | sed 's/^/  object = /' || true
wp litespeed-option get object-kind 2>/dev/null | sed 's/^/  object-kind (0 memcached, 1 redis) = /' || true
[ -f wp-content/object-cache.php ] && head -5 wp-content/object-cache.php | sed 's/^/  drop-in: /'

say "MailPoet: activate, double opt-in ON"
wp plugin is-active mailpoet || wp plugin activate mailpoet
wp eval '
try {
  $s = \MailPoet\Settings\SettingsController::getInstance();
  $s->set( "signup_confirmation.enabled", "1" );
  echo "  double opt-in: ", $s->get( "signup_confirmation.enabled" ) ? "on" : "OFF", "\n";
  $from = (array) $s->get( "sender" );
  echo "  sender: ", ! empty( $from["address"] ) ? $from["address"] : "NOT SET — choose the site mailbox in MailPoet → Settings → Basics", "\n";
} catch ( \Throwable $e ) { echo "  MailPoet settings not reachable: ", $e->getMessage(), "\n"; }'

say "Host-managed extras (reported for LAUNCH-GATES.md P6)"
wp plugin list --status=must-use,dropin --fields=name,status,title 2>/dev/null || true
for f in wp-content/mu-plugins/*.php; do [ -f "$f" ] && { echo "  $f:"; grep -m3 -iE 'Plugin Name|Description' "$f" | sed 's/^/    /'; }; done

say "Done. Next: MailPoet → Settings → Basics (sender = site mailbox) and choose the list in Site settings; then run tests/staging/gates.py"
