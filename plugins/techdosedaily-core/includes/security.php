<?php
/**
 * Shared protections for public form endpoints (newsletter, contact).
 * - Signed time token: proves the form was rendered by us and not submitted too fast.
 *   Works on fully cached pages (unlike a nonce), valid 3 s … 7 days.
 * - Honeypot field.
 * - Throttling by a salted hash of the client IP (the raw IP is never stored).
 * - `tdd_core_is_spam` filter: hook for a future CAPTCHA / spam service.
 * Endpoints that run on uncached pages (contact) additionally verify a WP nonce.
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

function tdd_core_client_ip(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return (string) apply_filters( 'tdd_core_client_ip', $ip ); // Behind a CDN, map the trusted header here.
}

function tdd_core_form_token(): string {
	$ts = (string) time();
	return $ts . '.' . substr( hash_hmac( 'sha256', $ts, wp_salt( 'nonce' ) ), 0, 32 );
}

/** @return true|WP_Error */
function tdd_core_check_form_token( string $token, int $min_seconds = 3, int $max_seconds = WEEK_IN_SECONDS ) {
	$parts = explode( '.', $token );
	if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
		return new WP_Error( 'tdd_token', __( 'Please reload the page and try again.', 'techdosedaily-core' ), array( 'status' => 400 ) );
	}
	$expected = substr( hash_hmac( 'sha256', $parts[0], wp_salt( 'nonce' ) ), 0, 32 );
	$age      = time() - (int) $parts[0];
	if ( ! hash_equals( $expected, $parts[1] ) || $age > $max_seconds ) {
		return new WP_Error( 'tdd_token', __( 'Please reload the page and try again.', 'techdosedaily-core' ), array( 'status' => 400 ) );
	}
	if ( $age < $min_seconds ) {
		return new WP_Error( 'tdd_too_fast', __( 'Please try again in a moment.', 'techdosedaily-core' ), array( 'status' => 429 ) );
	}
	return true;
}

/** True when the request is within $limit per $window for this bucket and client. */
function tdd_core_throttle( string $bucket, int $limit, int $window ): bool {
	$key   = 'tdd_t_' . substr( hash_hmac( 'sha256', $bucket . '|' . tdd_core_client_ip(), wp_salt( 'auth' ) ), 0, 32 );
	$count = (int) get_transient( $key );
	if ( $count >= $limit ) {
		return false;
	}
	set_transient( $key, $count + 1, $window );
	return true;
}

/**
 * Site-wide ceiling for one bucket (all clients together). Stops a distributed flood from filling
 * the newsroom inbox or a mailing list even when every request comes from a different address.
 */
function tdd_core_throttle_global( string $bucket, int $limit, int $window ): bool {
	$key   = 'tdd_tg_' . md5( $bucket . '|' . (int) floor( time() / max( 1, $window ) ) );
	$count = (int) get_transient( $key );
	if ( $count >= $limit ) {
		return false;
	}
	set_transient( $key, $count + 1, $window );
	return true;
}

/** Site-wide ceilings per bucket: [ limit, window ]. Filter `tdd_core_global_limits` to tune. */
function tdd_core_global_limit( string $bucket ): array {
	$limits = (array) apply_filters(
		'tdd_core_global_limits',
		array(
			'contact'   => array( 60, HOUR_IN_SECONDS ),
			'subscribe' => array( 200, HOUR_IN_SECONDS ),
		)
	);
	return $limits[ $bucket ] ?? array( 0, 0 );
}

/**
 * Common checks for a public submission. Returns true or WP_Error.
 *
 * @param WP_REST_Request $request Must carry `tdd_token` and the `tdd_hp` honeypot.
 */
function tdd_core_guard_submission( WP_REST_Request $request, string $bucket, int $limit, int $window ) {
	if ( '' !== (string) $request->get_param( 'tdd_hp' ) ) {
		return new WP_Error( 'tdd_spam', __( 'Submission rejected.', 'techdosedaily-core' ), array( 'status' => 400 ) );
	}
	$token = tdd_core_check_form_token( (string) $request->get_param( 'tdd_token' ) );
	if ( is_wp_error( $token ) ) {
		return $token;
	}
	if ( ! tdd_core_throttle( $bucket, $limit, $window ) ) {
		return new WP_Error( 'tdd_rate', __( 'Too many attempts. Please wait a few minutes and try again.', 'techdosedaily-core' ), array( 'status' => 429 ) );
	}
	[ $g_limit, $g_window ] = tdd_core_global_limit( $bucket );
	if ( $g_limit && ! tdd_core_throttle_global( $bucket, $g_limit, $g_window ) ) {
		return new WP_Error( 'tdd_rate', __( 'Too many attempts. Please wait a few minutes and try again.', 'techdosedaily-core' ), array( 'status' => 429 ) );
	}
	if ( apply_filters( 'tdd_core_is_spam', false, $request, $bucket ) ) {
		return new WP_Error( 'tdd_spam', __( 'Submission rejected.', 'techdosedaily-core' ), array( 'status' => 400 ) );
	}
	return true;
}
