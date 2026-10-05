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
FLIP="$HERE/out/.pages-published-for-run"   # draft pages published for the run, set back to draft in cleanup
mkdir -p "$HERE/out"
if [ -z "${TDD_SEC_LOG:-}" ]; then   # re-run through tee (no /dev/fd for process substitution on this host)
  export TDD_SEC_LOG="$HERE/out/security-$(date -u +%Y%m%d-%H%M%SZ).log"
  bash "$0" "$@" 2>&1 | tee -a "$TDD_SEC_LOG"
  exit "${PIPESTATUS[0]}"
fi
LOG=$TDD_SEC_LOG
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
  if [ -s "$FLIP" ]; then
    while read -r id; do wp post update "$id" --post_status=draft >/dev/null 2>&1 && echo "back to draft: page #$id"; done < "$FLIP"
  fi
  rm -f "$FLIP"
  for u in $(wp user list --field=user_login | grep -E '^tdd-(sec|xss)-' || true); do wp user delete "$u" --yes --reassign="$ADMIN_ID"; done
  wp eval 'foreach ( get_posts( array( "post_type" => "any", "post_status" => "any", "numberposts" => -1, "fields" => "ids", "meta_key" => "_tdd_fixture", "meta_value" => "phase8" ) ) as $id ) { wp_delete_post( $id, true ); }
    global $wpdb;
    foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE \"%TDDX%\" OR post_excerpt LIKE \"%TDDX%\" OR post_content LIKE \"%TDDX%\"" ) as $id ) { wp_delete_post( (int) $id, true ); echo "deleted untagged XSS post #$id\n"; }
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE \"\\_transient%tdd\\_t%\" OR option_name LIKE \"\\_transient\\_timeout%tdd\\_t%\"" );
    $ids = array_map( "intval", (array) $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}mailpoet_subscribers WHERE email LIKE \"%@example.invalid\"" ) );
    if ( $ids ) { \MailPoet\DI\ContainerWrapper::getInstance()->get( \MailPoet\Subscribers\SubscribersRepository::class )->bulkDelete( $ids ); }
    echo "MailPoet test subscribers deleted: ", count( $ids ), "\n";
    do_action( "litespeed_purge_all" ); echo "suite fixtures, throttles and cache cleared\n";'
  say "Leftover check"
  wp plugin list --status=must-use,dropin,inactive --fields=name,status --format=csv
  echo "test users: $(wp user list --field=user_login | grep -cE '^tdd-(sec|xss|cache)-' || true)"
  wp eval 'global $wpdb; echo "phase8 fixtures: ", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = \"_tdd_fixture\" AND meta_value = \"phase8\"" ), "\n";
    echo "MailPoet subscribers @example.invalid (suites; must be 0): ", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mailpoet_subscribers WHERE email LIKE \"%@example.invalid\"" ), "\n";
    echo "MailPoet subscribers -sample@example.com (sample authors, until samples are removed): ", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mailpoet_subscribers WHERE email LIKE \"%-sample@example.com\"" ), "\n";
    echo "XSS marker TDDX in posts/meta/terms/options (must be 0): ", (int) $wpdb->get_var( "SELECT (SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title LIKE \"%TDDX%\" OR post_excerpt LIKE \"%TDDX%\" OR post_content LIKE \"%TDDX%\") + (SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE \"%TDDX%\") + (SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_value LIKE \"%TDDX%\") + (SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_value LIKE \"%TDDX%\") + (SELECT COUNT(*) FROM {$wpdb->terms} WHERE name LIKE \"%TDDX%\") + (SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE \"%TDDX%\")" ), "\n";
    echo "MailPoet sending queues (must be 0): ", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mailpoet_sending_queues" ), "\n";'
  echo "published pages (expect only home, latest): $(wp post list --post_type=page --post_status=publish --field=post_name | tr '\n' ' ')"
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

say "Pages the suites test (published for this run only; still behind Basic Auth and noindex; no wording added)"
for slug in contact newsletter; do
  id=$(wp post list --post_type=page --name="$slug" --post_status=draft --field=ID)
  if [ -n "$id" ]; then wp post update "$id" --post_status=publish >/dev/null 2>&1 && echo "$id" >> "$FLIP" && echo "published empty draft /$slug/ (#$id)"; fi
done
if [ -z "$(wp post list --post_type=page --name=about --post_status=any --field=ID)" ]; then
  id=$(wp post create --post_type=page --post_status=publish --post_name=about --post_title=About --page_template=page-about --post_content='<!-- wp:tdd/team /-->' --porcelain 2>/dev/null | grep -oE '^[0-9]+' | head -1)
  wp post meta set "$id" _tdd_fixture phase8 >/dev/null 2>&1 && echo "temporary /about/ with only the team block (#$id, deleted with the suite fixtures)"
fi

export BASE TDD_ADMIN_ID="$ADMIN_ID" WP="wp --path=$SITE" TDD_BLOCKS_DIR="$SITE/wp-content/themes/techdosedaily/blocks"
say "security_test.py"; $PY "$HERE/security/security_test.py"; SEC=$?
say "xss_test.py";      $PY "$HERE/security/xss_test.py";      XSS=$?
echo "security exit=$SEC xss exit=$XSS"

trap - EXIT
cleanup
say "Gates (D1, P2, P6 and the rest)"
WP="wp --path=$SITE" $PY "$HERE/gates.py" --stage staging
unset TDD_BASIC_AUTH
echo "log: $LOG"
