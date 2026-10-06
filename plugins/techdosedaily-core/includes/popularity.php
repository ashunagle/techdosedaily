<?php
/**
 * First-party, anonymous view counts for Most Read.
 *
 * A tiny beacon (theme JS, published article pages only) POSTs /tdd/v1/view. We store one
 * aggregated row per post per UTC hour — no IPs, no user IDs, no cookies, no per-visit rows.
 *
 * Not counted:
 *  - bots/crawlers/headless browsers and empty user agents;
 *  - previews (the theme never prints the beacon on previews; the endpoint only accepts
 *    published posts);
 *  - logged-in users who can edit posts (editors, authors, admins) — checked server-side from
 *    the auth cookie, and the theme also omits the beacon for them;
 *  - repeated refreshes: one count per reader (hashed IP + post, salted) per 30 minutes,
 *    plus a per-IP request throttle.
 *
 * Counting ceilings per UTC hour (option tdd_core_view_ceilings, filter of the same name):
 *   story 1000 views per story · site 5000 views across all stories. They bound what scripted traffic
 *   can do to the ranking even when it rotates (or forges) client addresses. Each ceiling is enforced by
 *   one atomic SQL statement, so concurrent beacons cannot overshoot it. Views over a ceiling are
 *   discarded silently (the beacon still answers 204; pages are never affected); a warning is logged at
 *   most once per ceiling type per hour and `tdd_core_view_ceiling_reached` fires for monitoring.
 *   The site-wide counter is the post_id 0 row of each hourly bucket (Most Read joins real posts only).
 *
 * Windows are configurable (option tdd_core_most_read_windows, hours):
 *   home 24 · section 168 (7 days) · author 720 (30 days).
 * Most Read only renders when real data exists (min. views filterable); never faked.
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

const TDD_CORE_VIEWS_DB = '2';

function tdd_core_views_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'tdd_view_buckets';
}

function tdd_core_views_install(): void {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = tdd_core_views_table();
	dbDelta(
		"CREATE TABLE {$table} (
			post_id bigint(20) unsigned NOT NULL,
			bucket datetime NOT NULL,
			views int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (post_id,bucket),
			KEY bucket (bucket)
		) {$wpdb->get_charset_collate()};"
	);
	// v1 stored per-day rows in {prefix}tdd_views (pre-launch only); superseded by hourly buckets.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tdd_views" );
	update_option( 'tdd_core_views_db', TDD_CORE_VIEWS_DB, false );
}
add_action(
	'plugins_loaded',
	static function () {
		if ( TDD_CORE_VIEWS_DB !== get_option( 'tdd_core_views_db' ) ) {
			tdd_core_views_install();
		}
	}
);

/** Configured windows in hours: [ home, section, author ]. */
function tdd_core_most_read_windows(): array {
	$defaults = array( 'home' => 24, 'section' => 168, 'author' => 720 );
	$saved    = (array) get_option( 'tdd_core_most_read_windows', array() );
	$out      = array();
	foreach ( $defaults as $k => $v ) {
		$out[ $k ] = isset( $saved[ $k ] ) ? max( 1, min( 8760, (int) $saved[ $k ] ) ) : $v;
	}
	return apply_filters( 'tdd_core_most_read_windows', $out );
}

function tdd_core_most_read_window( string $context ): int {
	$w = tdd_core_most_read_windows();
	return (int) ( $w[ $context ] ?? $w['home'] );
}

/** Counting ceilings per UTC hour: [ story => views per story, site => views across all stories ]. */
function tdd_core_view_ceilings(): array {
	$defaults = array( 'story' => 1000, 'site' => 5000 );
	$saved    = (array) get_option( 'tdd_core_view_ceilings', array() );
	$out      = array();
	foreach ( $defaults as $k => $v ) {
		$out[ $k ] = isset( $saved[ $k ] ) ? (int) $saved[ $k ] : $v;
	}
	$out = (array) apply_filters( 'tdd_core_view_ceilings', $out );
	foreach ( $defaults as $k => $v ) {
		$out[ $k ] = max( 1, min( 1000000, (int) ( $out[ $k ] ?? $v ) ) );
	}
	return $out;
}

/** The hourly bucket a view is counted in (UTC). Filterable for tests of the hour boundary. */
function tdd_core_view_bucket(): string {
	return (string) apply_filters( 'tdd_core_view_bucket', gmdate( 'Y-m-d H:00:00' ) );
}

/** A ceiling discarded a view: tell monitoring every time, the log at most once per type per hour. */
function tdd_core_view_ceiling_reached( string $kind, int $post_id, string $bucket, int $ceiling ): void {
	do_action( 'tdd_core_view_ceiling_reached', $kind, $post_id, $bucket, $ceiling );
	$key = 'tdd_vcw_' . $kind . '_' . md5( $bucket );
	if ( get_transient( $key ) ) {
		return;
	}
	set_transient( $key, 1, HOUR_IN_SECONDS );
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- operational warning, rate-limited above.
	error_log(
		sprintf(
			'TechDoseDaily Core: %1$s view-count ceiling (%2$d per hour) reached for %3$s in the %4$s UTC bucket. Further views are not counted until the next hour; pages are unaffected.',
			'site' === $kind ? 'site-wide' : 'per-story',
			$ceiling,
			'site' === $kind ? 'all stories' : 'story #' . $post_id,
			$bucket
		)
	);
}

/** Should this request be counted at all? (bots, editorial users). */
function tdd_core_view_countable(): bool {
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	// Crawlers, link previews, scripts — and performance/uptime tools (Lighthouse/PageSpeed, WebPageTest
	// "PTST", GTmetrix, Pingdom, SpeedCurve, Calibre, DebugBear, sitespeed.io, LiteSpeed's cache crawler),
	// so lab tests never inflate Most Read. view-beacon.js also skips automated browsers and prerenders.
	if ( '' === $ua || preg_match( '/bot|crawl|spider|slurp|preview|headless|lighthouse|pagespeed|ptst|gtmetrix|pingdom|speedcurve|calibre|debugbear|sitespeed|lscache|uptime|monitor|facebookexternalhit|embedly|curl|wget|python|httpclient|java\//i', $ua ) ) {
		return false;
	}
	// Lab tools that look like a normal browser (local Lighthouse 10+ no longer says so in its user
	// agent) send this header on every request, beacon included — see tests/perf/lh.mjs.
	if ( ! empty( $_SERVER['HTTP_X_TDD_PERF_TEST'] ) ) {
		return false;
	}
	// REST requests without a nonce run as logged-out, so read the auth cookie directly.
	$uid = (int) wp_validate_auth_cookie( '', 'logged_in' );
	if ( $uid && user_can( $uid, 'edit_posts' ) ) {
		return false;
	}
	return (bool) apply_filters( 'tdd_core_view_countable', true );
}

function tdd_core_record_view( int $post_id ): bool {
	global $wpdb;
	if ( 'publish' !== get_post_status( $post_id ) || 'post' !== get_post_type( $post_id ) || ! tdd_core_view_countable() ) {
		return false;
	}
	$seen = 'tdd_v_' . substr( hash_hmac( 'sha256', tdd_core_client_ip() . '|' . $post_id, wp_salt( 'auth' ) ), 0, 32 );
	if ( get_transient( $seen ) ) {
		return false;
	}
	set_transient( $seen, 1, 30 * MINUTE_IN_SECONDS );
	$table  = tdd_core_views_table();
	$bucket = tdd_core_view_bucket();
	$ceil   = tdd_core_view_ceilings();
	// One statement per counter: the row only grows while below its ceiling, so concurrent beacons can
	// never push it past. Affected rows: 1 inserted, 2 incremented, 0 already at the ceiling.
	$count = "INSERT INTO {$table} (post_id, bucket, views) VALUES (%d, %s, 1) ON DUPLICATE KEY UPDATE views = IF(views < %d, views + 1, views)";
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	$site = $wpdb->query( $wpdb->prepare( $count, 0, $bucket, $ceil['site'] ) );
	if ( false === $site ) {
		return false;
	}
	if ( 0 === $site ) {
		tdd_core_view_ceiling_reached( 'site', 0, $bucket, $ceil['site'] );
		return false;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	$story = $wpdb->query( $wpdb->prepare( $count, $post_id, $bucket, $ceil['story'] ) );
	if ( ! $story ) {
		// Not counted for the story, so not for the site either.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET views = views - 1 WHERE post_id = 0 AND bucket = %s AND views > 0", $bucket ) );
		if ( 0 === $story ) {
			tdd_core_view_ceiling_reached( 'story', $post_id, $bucket, $ceil['story'] );
		}
		return false;
	}
	return true;
}

/**
 * Most-read post IDs over the last $hours hours, optionally within a section or by an author.
 *
 * @return int[]
 */
function tdd_core_most_read_ids( int $limit = 5, int $section_id = 0, int $hours = 24, int $author_id = 0 ): array {
	global $wpdb;
	$hours = max( 1, $hours );
	$tkey  = 'tdd_' . md5( "mr2:{$limit}:{$section_id}:{$hours}:{$author_id}" );
	$ids   = get_transient( $tkey );
	if ( false !== $ids ) {
		return tdd_core_prime_posts( (array) $ids );
	}
	$table = tdd_core_views_table();
	$since = gmdate( 'Y-m-d H:00:00', time() - ( $hours - 1 ) * HOUR_IN_SECONDS );
	$join  = '';
	$where = '';
	if ( $section_id ) {
		$join = $wpdb->prepare( " INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = v.post_id AND pm.meta_key = 'tdd_primary_section' AND pm.meta_value = %s", (string) $section_id );
	}
	if ( $author_id ) {
		$where = $wpdb->prepare( ' AND p.post_author = %d', $author_id );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT v.post_id FROM {$table} v INNER JOIN {$wpdb->posts} p ON p.ID = v.post_id AND p.post_status = 'publish' AND p.post_type = 'post'{$join} WHERE v.bucket >= %s{$where} GROUP BY v.post_id HAVING SUM(v.views) >= %d ORDER BY SUM(v.views) DESC, v.post_id DESC LIMIT %d", $since, (int) apply_filters( 'tdd_core_most_read_min_views', 5 ), $limit ) ) );
	set_transient( $tkey, $ids, 10 * MINUTE_IN_SECONDS );
	return tdd_core_prime_posts( $ids );
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'tdd/v1',
			'/view',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // Public by design; stores no personal data.
				'args'                => array( 'id' => array( 'type' => 'integer', 'required' => true ) ),
				'callback'            => static function ( WP_REST_Request $r ) {
					if ( tdd_core_throttle( 'view', 120, MINUTE_IN_SECONDS ) ) {
						tdd_core_record_view( (int) $r['id'] );
					}
					return new WP_REST_Response( null, 204 );
				},
			)
		);
	}
);

/** Purge buckets older than 400 days (keeps a year for annual round-ups). */
add_action(
	'tdd_core_daily',
	static function () {
		global $wpdb;
		$table = tdd_core_views_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE bucket < %s", gmdate( 'Y-m-d H:00:00', time() - 400 * DAY_IN_SECONDS ) ) );
	}
);
add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'tdd_core_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'tdd_core_daily' );
		}
	}
);
