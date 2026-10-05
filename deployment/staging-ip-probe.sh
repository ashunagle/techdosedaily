#!/bin/bash
# TechDoseDaily — gate C5: which client IP reaches PHP behind Hostinger's CDN (throttles and view dedupe key
# on tdd_core_client_ip(), which is REMOTE_ADDR unless mapped). STAGING ONLY. Removes itself.
#
#   bash ~/release-<v>/staging-ip-probe.sh        # prompts for the hPanel directory login (never stored)
#
# 1. Installs a temporary MU-plugin that answers only requests carrying ?tdd_ip_probe=<random token>: it records
#    REMOTE_ADDR, tdd_core_client_ip() and the proxy headers (X-Forwarded-For, X-Real-IP, …) in a temporary
#    option, sends no-store, answers 204 and stops. Nothing else on the site changes.
# 2. Probes from the server through the CDN, from the server straight to the origin, and from your browser.
# 3. Prints the comparison with addresses masked (a.b.x.x), then deletes the MU-plugin and the option.
set -uo pipefail
HERE=$(dirname "$(readlink -f "$0")")
SITE="$HOME/domains/techdosedaily.com/public_html/staging"
HOST=staging.techdosedaily.com
ORIGIN=145.79.58.37
MUF="$SITE/wp-content/mu-plugins/tdd-ip-probe.php"
CF="$HOME/.tdd-probe-curl.$$"
cd "$SITE" || exit 1
say() { printf '\n== %s\n' "$*"; }

say "Safety checks"
case "$(wp option get home)" in *://staging.*) ;; *) echo "Not the staging site. Stopping."; exit 1 ;; esac
[ "$(wp option get blog_public)" = "0" ] || { echo "Staging must be noindex. Stopping."; exit 1; }
grep -q "PWPROTECTID" .htaccess || { echo "Directory protection missing. Stopping."; exit 1; }

read -rsp "Staging directory login as username:password > " AUTH; echo
BS='\'; AUTH=${AUTH//"$BS"/"$BS$BS"}; AUTH=${AUTH//\"/"$BS\""}   # escape \ and " for curl's config syntax
umask 077; printf 'user = "%s"\n' "$AUTH" > "$CF"; unset AUTH BS
code=$(curl -s -o /dev/null -w '%{http_code}' --config "$CF" "https://$HOST/")
[ "$code" != 401 ] && [ "$code" != 000 ] || { rm -f "$CF"; echo "Login rejected (HTTP $code). Nothing was changed."; exit 1; }

cleanup() {
  say "Cleanup"
  rm -f "$MUF" "$CF"
  wp option delete tdd_ip_probe >/dev/null 2>&1
  echo "probe removed: $([ -e "$MUF" ] && echo NO || echo yes) · option: $(wp option get tdd_ip_probe >/dev/null 2>&1 && echo PRESENT || echo deleted) · mu-plugins: $(ls "$SITE/wp-content/mu-plugins" | tr '\n' ' ')"
}
trap cleanup EXIT
trap 'exit 1' HUP INT TERM   # a dropped SSH session still runs cleanup

TOKEN=$(head -c 16 /dev/urandom | od -An -tx1 | tr -d ' \n')
say "Install temporary probe"
cat > "$MUF" <<PHP
<?php
// TEMPORARY gate C5 probe (deployment/staging-ip-probe.sh) — removed when the script ends; deletes itself after 15 minutes.
if ( time() - (int) @filemtime( __FILE__ ) > 900 ) { @unlink( __FILE__ ); return; }
if ( isset( \$_GET['tdd_ip_probe'] ) && hash_equals( '$TOKEN', (string) \$_GET['tdd_ip_probe'] ) ) {
	add_action( 'plugins_loaded', static function () {
		\$h = array();
		foreach ( \$_SERVER as \$k => \$v ) {
			if ( 'REMOTE_ADDR' === \$k || preg_match( '/^HTTP_.*(IP|FORWARD|CLIENT|REAL|VIA|CDN|HCDN|CONNECTING)/', \$k ) ) {
				\$h[ \$k ] = substr( (string) \$v, 0, 300 );
			}
		}
		\$h['tdd_core_client_ip()'] = function_exists( 'tdd_core_client_ip' ) ? tdd_core_client_ip() : '(Core inactive)';
		\$all = (array) get_option( 'tdd_ip_probe', array() );
		\$all[ preg_replace( '/[^a-z-]/', '', (string) ( \$_GET['label'] ?? 'x' ) ) ] = \$h;
		update_option( 'tdd_ip_probe', \$all, false );
		nocache_headers();
		status_header( 204 );
		exit;
	}, 0 );
}
PHP
echo "installed $(basename "$MUF") ($(sha256sum "$MUF" | cut -c1-16)…)"

say "Probe from the server"
URL="https://$HOST/?tdd_ip_probe=$TOKEN"
echo "via CDN:        HTTP $(curl -s -o /dev/null -w '%{http_code}' --config "$CF" "$URL&label=server-cdn")"
echo "via CDN, IPv4:  HTTP $(curl -s -4 -o /dev/null -w '%{http_code}' --config "$CF" "$URL&label=server-cdn-v4")"
echo "direct origin:  HTTP $(curl -s -o /dev/null -w '%{http_code}' --config "$CF" -k --resolve "$HOST:443:$ORIGIN" "$URL&label=server-origin")"   # -k: our own origin IP
rm -f "$CF"; echo "temporary login file deleted"

say "Probe from your browser"
echo "Open this address in the browser where you are signed in to staging (it shows a blank page):"
echo "  $URL&label=browser"
read -t 600 -rp "Press Enter after the page has loaded (continues by itself after 10 minutes) > " _ || echo

say "Result (addresses masked)"
wp eval '
$mask = static function ( $s ) { return preg_replace_callback( "/\b(\d{1,3})\.(\d{1,3})\.\d{1,3}\.\d{1,3}\b/", static fn( $m ) => "$m[1].$m[2].x.x", (string) $s ); };
$all  = (array) get_option( "tdd_ip_probe", array() );
if ( ! $all ) { echo "No probe request reached PHP.\n"; return; }
foreach ( $all as $label => $h ) {
	echo "[$label]\n";
	foreach ( $h as $k => $v ) { echo "  $k: ", $mask( $v ), "\n"; }
}
$ra = array_map( static fn( $h ) => $h["REMOTE_ADDR"] ?? "", $all );
if ( isset( $ra["server-cdn"], $ra["browser"] ) ) {
	echo "\nREMOTE_ADDR same for server and browser (through the CDN): ", $ra["server-cdn"] === $ra["browser"] ? "YES — every visitor would share one address" : "no — distinct clients are told apart", "\n";
}
foreach ( array( "server-cdn", "browser" ) as $l ) {
	if ( empty( $all[ $l ]["HTTP_X_FORWARDED_FOR"] ) ) { continue; }
	$first = trim( explode( ",", $all[ $l ]["HTTP_X_FORWARDED_FOR"] )[0] );
	echo "[$l] first X-Forwarded-For equals REMOTE_ADDR: ", $first === $ra[ $l ] ? "yes" : "no", "\n";
}
if ( isset( $ra["server-cdn-v4"] ) ) {
	echo "server via CDN over IPv4: REMOTE_ADDR equals this server IPv4: ", $ra["server-cdn-v4"] === "'"$ORIGIN"'" ? "YES — the CDN passes the real client address" : "no — PHP sees a CDN address", "\n";
}
if ( isset( $ra["server-cdn"], $ra["server-origin"] ) ) {
	echo "server via CDN vs direct to origin, same REMOTE_ADDR: ", $ra["server-cdn"] === $ra["server-origin"] ? "yes (the CDN hands PHP the real client address)" : "no", "\n";
}'
