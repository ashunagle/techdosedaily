#!/bin/bash
# TechDoseDaily — load or remove the [Sample] review dataset on STAGING ONLY (owner decision 2026-10-05:
# representative content for visual/performance review). Samples are noindex and out of the sitemaps
# (Core: _tdd_fixture), and gates D1/U1/E2 block production until they are removed.
#
#   bash ~/release-<v>/staging-samples.sh load     # fixtures from ./fixtures next to this script
#   bash ~/release-<v>/staging-samples.sh remove
#
# Loaded: phase2-content, phase3-article, phase3-sections, phase3-home, phase6-seo (stories, sample
# authors, images, topics, placements, view counts). NOT loaded: phase1-menus (example.com links),
# phase2-gallery (component demo page), phase4-pages (page copy and contact/settings values).
set -euo pipefail
MODE=${1:-}
HERE=$(dirname "$(readlink -f "$0")")
FX="$HERE/fixtures"
cd "$HOME/domains/techdosedaily.com/public_html/staging"
say() { printf '\n== %s\n' "$*"; }

say "Safety checks"
case "$(wp option get home)" in *://staging.*) ;; *) echo "Not the staging site. Stopping." >&2; exit 1 ;; esac
[ "$(wp option get blog_public)" = "0" ] || { echo "Staging must be noindex. Stopping." >&2; exit 1; }
[ "$(wp eval 'echo wp_get_environment_type();')" = "staging" ] || { echo "WP_ENVIRONMENT_TYPE is not staging. Stopping." >&2; exit 1; }
ADMIN=$(wp user list --role=administrator --field=user_login --number=1)
case "$MODE" in load|remove) ;; *) echo "Usage: $0 load|remove" >&2; exit 2 ;; esac

say "Database backup"
BK="$HOME/backups/tdd-staging-$(date +%Y%m%d-%H%M%S)-samples-$MODE"
mkdir -p "$BK" && chmod 700 "$HOME/backups" "$BK"
export MYSQL_PWD="$(wp config get DB_PASSWORD)"   # wp db export fails silently on this host
mysqldump --single-transaction --no-tablespaces -h "$(wp config get DB_HOST)" -u "$(wp config get DB_USER)" "$(wp config get DB_NAME)" > "$BK/db.sql"
unset MYSQL_PWD
chmod 600 "$BK/db.sql"; echo "backup: $BK"

if [ "$MODE" = load ]; then
  [ -d "$FX/images" ] || { echo "Fixtures not found in $FX." >&2; exit 1; }
  say "Record what the samples will change"
  wp eval 'if ( false === get_option( "tdd_sample_preexisting_topics" ) ) { update_option( "tdd_sample_preexisting_topics", get_terms( array( "taxonomy" => "tdd_topic", "hide_empty" => false, "fields" => "ids" ) ), false ); } $ai = get_term_by( "slug", "ai", "category" ); if ( $ai && false === get_option( "tdd_sample_ai_description" ) ) { update_option( "tdd_sample_ai_description", $ai->description, false ); } echo "recorded\n";'
  for f in phase2-content phase3-article phase3-sections phase3-home phase6-seo; do
    say "$f"
    wp eval-file "$FX/$f.php" --user="$ADMIN"
  done
else
  say "Remove samples"
  wp eval-file "$FX/remove-samples.php" --user="$ADMIN"
fi

wp eval 'do_action( "litespeed_purge_all" ); echo "cache purged\n";'
say "Fixture rows now: $(wp eval 'global $wpdb; echo (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = \"_tdd_fixture\"" );')"
