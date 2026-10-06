#!/bin/bash
# TechDoseDaily — Most Read counting ceilings (Core ≥ 0.9.3) and the origin client-IP retest, on STAGING.
#
#   bash ~/release-<v>/staging-popularity.sh      # prompts for the hPanel directory login (never stored)
#
# Runs tests/perf/popularity_ceiling_test.py on the server (it removes everything it creates), after a
# database snapshot. Output: ~/release-<v>/out/popularity-<time>.log
set -uo pipefail
HERE=$(dirname "$(readlink -f "$0")")
SITE="$HOME/domains/techdosedaily.com/public_html/staging"
PY=/opt/alt/python311/bin/python3
BASE=https://staging.techdosedaily.com
ORIGIN=145.79.58.37
mkdir -p "$HERE/out"
if [ -z "${TDD_POP_LOG:-}" ]; then   # re-run through tee (no /dev/fd for process substitution on this host)
  export TDD_POP_LOG="$HERE/out/popularity-$(date -u +%Y%m%d-%H%M%SZ).log"
  bash "$0" "$@" 2>&1 | tee -a "$TDD_POP_LOG"
  exit "${PIPESTATUS[0]}"
fi
cd "$SITE" || exit 1
say() { printf '\n== %s\n' "$*"; }

say "Safety checks"
case "$(wp option get home)" in *://staging.*) ;; *) echo "Not the staging site. Stopping."; exit 1 ;; esac
[ "$(wp option get blog_public)" = "0" ] || { echo "Staging must be noindex. Stopping."; exit 1; }
grep -q "PWPROTECTID" .htaccess || { echo "Directory protection missing. Stopping."; exit 1; }
[ "$(wp eval 'echo function_exists("tdd_core_view_ceilings") ? 1 : 0;')" = 1 ] || { echo "Core without counting ceilings (needs 0.9.3+). Stopping."; exit 1; }
echo "Core $(wp plugin get techdosedaily-core --field=version)"

read -rsp "Staging directory login as username:password > " TDD_BASIC_AUTH; echo; export TDD_BASIC_AUTH
code=$($PY -c 'import os,requests; u,p=os.environ["TDD_BASIC_AUTH"].split(":",1); print(requests.get("'$BASE'/",auth=(u,p),allow_redirects=False,timeout=30).status_code)' 2>/dev/null)
[ -n "$code" ] && [ "$code" != 401 ] || { echo "Login rejected (HTTP ${code:-error}). Nothing was changed."; exit 1; }

say "Snapshot (database)"
BK="$HOME/backups/tdd-staging-$(date +%Y%m%d-%H%M%S)-pre-popularity"
mkdir -p "$BK" && chmod 700 "$HOME/backups" "$BK"
export MYSQL_PWD="$(wp config get DB_PASSWORD)"   # wp db export fails silently on this host
mysqldump --single-transaction --no-tablespaces -h "$(wp config get DB_HOST)" -u "$(wp config get DB_USER)" "$(wp config get DB_NAME)" > "$BK/db.sql"
unset MYSQL_PWD; chmod 600 "$BK/db.sql"; echo "backup: $BK"

say "popularity_ceiling_test.py"
BASE=$BASE WP="wp --path=$SITE" TDD_ORIGIN_IP=$ORIGIN $PY "$HERE/perf/popularity_ceiling_test.py"; RC=$?
unset TDD_BASIC_AUTH
wp eval 'do_action( "litespeed_purge_all" );' >/dev/null 2>&1
echo "exit=$RC · log: $TDD_POP_LOG"
exit $RC
