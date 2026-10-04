<?php
/**
 * Newsletter: provider resolution + POST /wp-json/tdd/v1/subscribe.
 *
 * Request:  email, tdd_token (signed time token), tdd_hp (honeypot, must be empty)
 * Response: { state: pending|invalid|unavailable|error, title, message }
 * New, already-subscribed and awaiting-confirmation addresses all get the same neutral `pending` answer
 * (Phase 8: no subscriber enumeration). The real outcome stays internal.
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
	$started  = microtime( true );
	$provider = tdd_core_newsletter_provider();
	$result   = $provider->subscribe( $email, array( 'source' => esc_url_raw( (string) wp_get_referer() ) ) );
	if ( $result->detail ) {
		error_log( 'TDD newsletter provider error (' . $provider->id() . '): ' . $result->detail ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions -- no submitted data is logged.
	}
	do_action( 'tdd_core_newsletter_result', $result->status, $provider->id() ); // Internal outcome (never sent to the reader).
	$titles = tdd_core_newsletter_titles();
	if ( tdd_core_newsletter_is_neutral( $result->status ) || Result::UNSUBSCRIBED === $result->status ) {
		tdd_core_newsletter_pad( $started );
		// One public answer: same state, title, message and status code (and the same no-JS redirect).
		return array( 'state' => Result::PENDING, 'title' => $titles[ Result::PENDING ], 'message' => $messages[ Result::PENDING ], 'code' => 200 );
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
