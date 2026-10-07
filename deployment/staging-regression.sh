#!/bin/bash
# TechDoseDaily — regression suites against STAGING, then put staging back exactly as it was.
#
#   bash ~/release-<v>/staging-regression.sh [markup] [seo] [cache]     # default: all three
#
# Before: safety checks (staging, noindex, directory protection), login check, database + files snapshot.
# During: Contact / Newsletter / Editorial Standards drafts published (empty, still behind Basic Auth and
#   noindex); for the SEO suite the harness MU-plugin mu-seo-audit.php (checksum logged).
# After (always): harness removed, a post-run database dump kept for investigation, then the DATABASE IS
#   RESTORED FROM THE PRE-RUN SNAPSHOT (the SEO suite edits sample stories, owner settings and Yoast state;
#   the cache suite creates stories and placements), caches purged, leftover check, full gate run.
# Output: ~/release-<v>/out/regression-<time>.log
set -uo pipefail
HERE=$(dirname "$(readlink -f "$0")")
SITE="$HOME/domains/techdosedaily.com/public_html/staging"
PY=/opt/alt/python311/bin/python3
BASE=https://staging.techdosedaily.com
MU="$SITE/wp-content/mu-plugins"
SUITES=("$@"); [ ${#SUITES[@]} -eq 0 ] && SUITES=(markup seo cache)
mkdir -p "$HERE/out"
if [ -z "${TDD_REG_LOG:-}" ]; then   # re-run through tee (no /dev/fd for process substitution on this host)
  export TDD_REG_LOG="$HERE/out/regression-$(date -u +%Y%m%d-%H%M%SZ).log"
  bash "$0" "$@" 2>&1 | tee -a "$TDD_REG_LOG"
  exit "${PIPESTATUS[0]}"
fi
cd "$SITE" || exit 1
say() { printf '\n== %s\n' "$*"; }
db() { export MYSQL_PWD="$(wp config get DB_PASSWORD)"; "$@" -h "$(wp config get DB_HOST)" -u "$(wp config get DB_USER)" "$(wp config get DB_NAME)"; local rc=$?; unset MYSQL_PWD; return $rc; }

say "Safety checks"
case "$(wp option get home)" in *://staging.*) ;; *) echo "Not the staging site. Stopping."; exit 1 ;; esac
[ "$(wp option get blog_public)" = "0" ] || { echo "Staging must be noindex. Stopping."; exit 1; }
[ "$(wp eval 'echo wp_get_environment_type();')" = "staging" ] || { echo "Not WP_ENVIRONMENT_TYPE=staging. Stopping."; exit 1; }
grep -q "PWPROTECTID" .htaccess || { echo "Directory protection missing. Stopping."; exit 1; }
ADMIN_ID=$(wp user list --role=administrator --field=ID --number=1)
echo "suites: ${SUITES[*]} · Core $(wp plugin get techdosedaily-core --field=version)"

read -rsp "Staging directory login as username:password > " TDD_BASIC_AUTH; echo; export TDD_BASIC_AUTH
code=$($PY -c 'import os,requests; u,p=os.environ["TDD_BASIC_AUTH"].split(":",1); print(requests.get("'$BASE'/",auth=(u,p),allow_redirects=False,timeout=30).status_code)' 2>/dev/null)
[ -n "$code" ] && [ "$code" != 401 ] || { echo "Login rejected (HTTP ${code:-error}). Nothing was changed."; exit 1; }

say "Snapshot (database + files)"
BK="$HOME/backups/tdd-staging-$(date +%Y%m%d-%H%M%S)-pre-regression"
mkdir -p "$BK" && chmod 700 "$HOME/backups" "$BK"
db mysqldump --single-transaction --no-tablespaces > "$BK/db.sql" || { echo "Database snapshot failed. Nothing was changed."; exit 1; }
tar -czf "$BK/files.tgz" -C "$SITE/.." "$(basename "$SITE")"
chmod 600 "$BK"/*; ls -la "$BK"
OWNER_BEFORE=$(wp option get tdd_core_schema_owner 2>/dev/null)
FIX_BEFORE=$(wp eval 'global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = \"_tdd_fixture\"");')

restore() {
  say "Restore staging"
  rm -f "$MU/mu-seo-audit.php"
  db mysqldump --single-transaction --no-tablespaces > "$BK/db-after-run.sql" && chmod 600 "$BK/db-after-run.sql" && echo "post-run dump kept: $BK/db-after-run.sql"
  if db mysql < "$BK/db.sql"; then echo "database restored from the pre-run snapshot"; else echo "!! DATABASE RESTORE FAILED — restore $BK/db.sql by hand"; fi
  wp cache flush >/dev/null 2>&1; wp eval 'do_action( "litespeed_purge_all" );' >/dev/null 2>&1; echo "object cache flushed, page cache purged"
  say "Leftover check"
  echo "mu-plugins: $(ls "$MU" | tr '\n' ' ')"
  echo "published pages (expect home, latest): $(wp post list --post_type=page --post_status=publish --field=post_name | tr '\n' ' ')"
  echo "test users (expect 0): $(wp user list --field=user_login | grep -cE '^(tdd-(sec|xss|cache)-|seo-empty)' || true)"
  echo "schema owner (expect $OWNER_BEFORE): $(wp option get tdd_core_schema_owner 2>/dev/null)"
  echo "Yoast active: $(wp plugin is-active wordpress-seo && echo yes || echo NO)"
  echo "fixture rows (expect $FIX_BEFORE): $(wp eval 'global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = \"_tdd_fixture\"");')"
}
trap restore EXIT
trap 'exit 1' HUP INT TERM   # a dropped SSH session still restores

say "Pages the suites test (published for this run only; still behind Basic Auth and noindex; no wording added)"
for slug in contact newsletter editorial-standards; do
  id=$(wp post list --post_type=page --name="$slug" --post_status=draft --field=ID)
  [ -n "$id" ] && wp post update "$id" --post_status=publish >/dev/null 2>&1 && echo "published empty draft /$slug/ (#$id)"
done

export BASE TDD_ADMIN_ID="$ADMIN_ID" WP="$HERE/wp-clean" TDD_WP_PATH="$SITE" TDD_THEME_DIR="$SITE/wp-content/themes/techdosedaily"
RC=0
for s in "${SUITES[@]}"; do
  case "$s" in
    markup) say "markup_test.py"; $PY "$HERE/perf/markup_test.py" || RC=1 ;;
    seo)    say "seo_test.py (harness mu-seo-audit.php for this suite only)"
            cp "$HERE/fixtures/mu-seo-audit.php" "$MU/" && sha256sum "$MU/mu-seo-audit.php"
            $PY "$HERE/seo/seo_test.py" || RC=1
            rm -f "$MU/mu-seo-audit.php" ;;
    cache)  say "cache_test.py (about 3 minutes: waits for real time boundaries)"; $PY "$HERE/perf/cache_test.py" || RC=1 ;;
    *)      echo "unknown suite: $s" ;;
  esac
done
echo "suites exit=$RC"

trap - EXIT
restore
say "Gates"
WP="wp --path=$SITE" $PY "$HERE/gates.py" --stage staging
unset TDD_BASIC_AUTH
echo "log: $TDD_REG_LOG"
