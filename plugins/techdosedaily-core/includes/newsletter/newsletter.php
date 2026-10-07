<?php
/**
 * Newsletter: provider resolution + POST /wp-json/tdd/v1/subscribe.
 *
 * Request:  email, tdd_token (signed time token), tdd_hp (honeypot, must be empty)
 * Response: { state: pending|invalid|unavailable|error, title, message }
 * New, already-subscribed and awaiting-confirmation addresses all get the same neutral `pending` answer
 * (Phase 8: no subscriber enumeration). The real outcome stays internal.
 * Limits: per IP, site-wide, and per address (3 per 24 h, 15 min apart; hashed key). An address over its
 * limit is not sent to the provider and gets the same answer in the same minimum time.
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

use TDD\Core\Newsletter\Provider;
use TDD\Core\Newsletter\Result;

function tdd_core_newsletter_provider(): Provider {
	static $provider = null;
	if ( null === $provider ) {
		$default  = class_exists( '\MailPoet\API\API' ) ? new TDD\Core\Newsletter\MailPoet_Provider() : new TDD\Core\Newsletter\Null_Provider();
		$provider = apply_filters( 'tdd_core_newsletter_provider', $default );
		if ( ! $provider instanceof Provider ) {
			$provider = new TDD\Core\Newsletter\Null_Provider();
		}
	}
	return $provider;
}

/**
 * Public copy. Phase 8 (approved): a new address, an address that is already subscribed and an address
 * awaiting confirmation all get the SAME neutral answer, so the form can't be used to find out who is
 * on the list. Which of the three it really was stays internal (provider + `tdd_core_newsletter_result`).
 */
function tdd_core_newsletter_neutral_message(): string {
	return __( 'If this address can be subscribed, check your inbox for the next step.', 'techdosedaily-core' );
}

/** Copy for each state as readers see it. */
function tdd_core_newsletter_messages(): array {
	$neutral = tdd_core_newsletter_neutral_message();
	return array(
		Result::SUBSCRIBED  => $neutral,
		Result::ALREADY     => $neutral,
		Result::PENDING     => $neutral,
		Result::INVALID     => __( 'Enter an email address like name@example.com', 'techdosedaily-core' ),
		Result::UNAVAILABLE => __( 'Sign-up is temporarily unavailable. Please try again later.', 'techdosedaily-core' ),
		Result::ERROR       => __( 'Something went wrong. Please try again.', 'techdosedaily-core' ),
	);
}

/** Panel title for the (single, neutral) success answer. */
function tdd_core_newsletter_titles(): array {
	$title = __( 'Check your inbox', 'techdosedaily-core' );
	return array(
		Result::SUBSCRIBED => $title,
		Result::ALREADY    => $title,
		Result::PENDING    => $title,
	);
}

/** Internal outcomes that are answered with the neutral public state. */
function tdd_core_newsletter_is_neutral( string $status ): bool {
	return in_array( $status, array( Result::SUBSCRIBED, Result::ALREADY, Result::PENDING ), true );
}

/**
 * Accepted sign-ups take at least this long (seconds) before answering, so the response time does
 * not reveal whether the provider created a subscriber or found an existing one. Filter
 * `tdd_core_newsletter_min_seconds` (0 disables).
 */
function tdd_core_newsletter_pad( float $started ): void {
	$min  = (float) apply_filters( 'tdd_core_newsletter_min_seconds', 1.2 );
	$left = $min - ( microtime( true ) - $started );
	if ( $left > 0 ) {
		usleep( (int) round( $left * 1000000 ) );
	}
}

/**
 * Per-address limit (owner decision 2026-10-07), on top of the per-IP and site-wide limits: at most
 * 3 sign-up attempts per address per 24 hours, at least 15 minutes apart. Filter
 * `tdd_core_newsletter_email_limits` ( max, window, cooldown in seconds ).
 */
function tdd_core_newsletter_email_limits(): array {
	$l = (array) apply_filters( 'tdd_core_newsletter_email_limits', array( 'max' => 3, 'window' => DAY_IN_SECONDS, 'cooldown' => 15 * MINUTE_IN_SECONDS ) );
	return array(
		'max'      => max( 1, (int) ( $l['max'] ?? 3 ) ),
		'window'   => max( 60, (int) ( $l['window'] ?? DAY_IN_SECONDS ) ),
		'cooldown' => max( 0, (int) ( $l['cooldown'] ?? 15 * MINUTE_IN_SECONDS ) ),
	);
}

/**
 * Storage key for an address: an HMAC of the normalised address (the address itself is never stored).
 * Normalised = lowercase, without a +tag; Gmail ignores dots and googlemail.com is gmail.com — every
 * variant that reaches the same inbox shares one limit.
 */
function tdd_core_newsletter_email_key( string $email ): string {
	$email = strtolower( trim( $email ) );
	$at    = strrpos( $email, '@' );
	if ( false === $at || 0 === $at ) {
		return '';
	}
	$local  = explode( '+', substr( $email, 0, $at ), 2 )[0];
	$domain = substr( $email, $at + 1 );
	if ( in_array( $domain, array( 'gmail.com', 'googlemail.com' ), true ) ) {
		$local  = str_replace( '.', '', $local );
		$domain = 'gmail.com';
	}
	return 'tdd_nle_' . substr( hash_hmac( 'sha256', $local . '@' . $domain, wp_salt( 'auth' ) ), 0, 40 );
}

/**
 * May this address go to the provider now? Records the attempt when it may. Attempts are stored as
 * timestamps under the hashed key; a unique-row lock (INSERT IGNORE) makes concurrent requests for one
 * address decide one at a time, so the limit holds under parallel submissions.
 */
function tdd_core_newsletter_email_allowed( string $email, ?int $now = null ): bool {
	global $wpdb;
	$key = tdd_core_newsletter_email_key( $email );
	if ( '' === $key ) {
		return false;
	}
	$now  = $now ?? time();
	$l    = tdd_core_newsletter_email_limits();
	$lock = $key . '_lock';
	$auto = version_compare( get_bloginfo( 'version' ), '6.6', '>=' ) ? 'off' : 'no';
	$take = static fn() => (bool) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)", $lock, (string) $now, $auto ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( ! $take() ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$held = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $lock ) );
		if ( $held > $now - 30 ) {
			return false; // Another request for this address is being decided right now.
		}
		$wpdb->delete( $wpdb->options, array( 'option_name' => $lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- stale lock.
		if ( ! $take() ) {
			return false;
		}
	}
	wp_cache_delete( $key, 'options' );
	$seen = array_values( array_filter( array_map( 'intval', (array) get_option( $key, array() ) ), static fn( $t ) => $t > $now - $l['window'] ) );
	$ok   = count( $seen ) < $l['max'] && ( ! $seen || $now - max( $seen ) >= $l['cooldown'] );
	if ( $ok ) {
		$seen[] = $now;
		update_option( $key, $seen, false );
	}
	$wpdb->delete( $wpdb->options, array( 'option_name' => $lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return $ok;
}

/** Daily: forget per-address records with no attempt inside the window (and stale locks). */
add_action(
	'tdd_core_daily',
	static function () {
		global $wpdb;
		$window = tdd_core_newsletter_email_limits()['window'];
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'tdd\\_nle\\_%'" ) as $row ) {
			$v     = maybe_unserialize( $row->option_value );
			$stale = str_ends_with( $row->option_name, '_lock' ) ? (int) $v < time() - 300 : ! array_filter( (array) $v, static fn( $t ) => (int) $t > time() - $window );
			if ( $stale ) {
				delete_option( $row->option_name );
			}
		}
	}
);

/** The one public success answer (new, already subscribed, pending, or held back by the per-address limit). */
function tdd_core_newsletter_neutral_answer(): array {
	return array( 'state' => Result::PENDING, 'title' => tdd_core_newsletter_titles()[ Result::PENDING ], 'message' => tdd_core_newsletter_messages()[ Result::PENDING ], 'code' => 200 );
}

/**
 * Shared processing for REST and the no-JS form post.
 *
 * @return array{state:string,message:string,code:int}|WP_Error
 */
function tdd_core_newsletter_process( WP_REST_Request $request ) {
	$guard = tdd_core_guard_submission( $request, 'subscribe', 5, 10 * MINUTE_IN_SECONDS );
	if ( is_wp_error( $guard ) ) {
		return $guard;
	}
	$messages = tdd_core_newsletter_messages();
	$email    = sanitize_email( (string) $request->get_param( 'email' ) );
	if ( ! is_email( $email ) ) {
		return array( 'state' => Result::INVALID, 'message' => $messages[ Result::INVALID ], 'code' => 422 );
	}
	$started = microtime( true );
	if ( ! tdd_core_newsletter_email_allowed( $email ) ) {
		// Over the per-address limit: not sent to the provider; same answer, same minimum time.
		do_action( 'tdd_core_newsletter_email_limited' ); // Internal only; carries nothing about the address.
		tdd_core_newsletter_pad( $started );
		return tdd_core_newsletter_neutral_answer();
	}
	$provider = tdd_core_newsletter_provider();
	$result   = $provider->subscribe( $email, array( 'source' => esc_url_raw( (string) wp_get_referer() ) ) );
	if ( $result->detail ) {
		error_log( 'TDD newsletter provider error (' . $provider->id() . '): ' . $result->detail ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions -- no submitted data is logged.
	}
	do_action( 'tdd_core_newsletter_result', $result->status, $provider->id() ); // Internal outcome (never sent to the reader).
	if ( tdd_core_newsletter_is_neutral( $result->status ) || Result::UNSUBSCRIBED === $result->status ) {
		tdd_core_newsletter_pad( $started );
		// One public answer: same state, title, message and status code (and the same no-JS redirect).
		return tdd_core_newsletter_neutral_answer();
	}
	$code = Result::UNAVAILABLE === $result->status ? 503 : 500;
	return array( 'state' => Result::UNAVAILABLE === $result->status ? Result::UNAVAILABLE : Result::ERROR, 'title' => '', 'message' => $messages[ $result->status ] ?? $messages[ Result::ERROR ], 'code' => $code );
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'tdd/v1',
			'/subscribe',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'args'                => array(
					'email'     => array( 'type' => 'string', 'required' => true ),
					'tdd_token' => array( 'type' => 'string', 'required' => true ),
					'tdd_hp'    => array( 'type' => 'string', 'default' => '' ),
				),
				'callback'            => static function ( WP_REST_Request $request ) {
					$out = tdd_core_newsletter_process( $request );
					if ( is_wp_error( $out ) ) {
						return $out;
					}
					return new WP_REST_Response( array( 'state' => $out['state'], 'title' => $out['title'] ?? '', 'message' => $out['message'] ), $out['code'] );
				},
			)
		);
	}
);

/** No-JS fallback: classic POST → process → redirect back (Post/Redirect/Get). */
function tdd_core_newsletter_admin_post(): void {
	$request = new WP_REST_Request( 'POST' );
	foreach ( array( 'email', 'tdd_token', 'tdd_hp' ) as $key ) {
		$request->set_param( $key, isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification -- signed time token + honeypot + throttle (pages are cached).
	}
	$out   = tdd_core_newsletter_process( $request );
	$state = is_wp_error( $out ) ? 'error' : $out['state'];
	$back  = wp_get_referer() ? wp_get_referer() : home_url( '/' );
	wp_safe_redirect( add_query_arg( 'tdd_nl', rawurlencode( $state ), remove_query_arg( 'tdd_nl', $back ) ) . '#newsletter', 303 );
	exit;
}
add_action( 'admin_post_nopriv_tdd_subscribe', 'tdd_core_newsletter_admin_post' );
add_action( 'admin_post_tdd_subscribe', 'tdd_core_newsletter_admin_post' );
