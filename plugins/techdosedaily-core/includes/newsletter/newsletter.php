<?php
/**
 * Newsletter: provider resolution + POST /wp-json/tdd/v1/subscribe.
 *
 * Request:  email, tdd_token (signed time token), tdd_hp (honeypot, must be empty)
 * Response: { state: subscribed|already|pending|invalid|unavailable|error, message }
 * States map to the approved NewsletterFormStates. Messages are the approved copy.
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

/** Approved copy for each state (NewsletterFormStates). */
function tdd_core_newsletter_messages(): array {
	return array(
		Result::SUBSCRIBED  => __( 'Your first Tech Dose Daily email will arrive on the next scheduled send.', 'techdosedaily-core' ),
		Result::ALREADY     => __( 'This address is already subscribed. Nothing else to do.', 'techdosedaily-core' ),
		// Double opt-in (production): never say "subscribed" before the address is confirmed.
		Result::PENDING     => __( 'We sent a confirmation link to your email address. Confirm it to start receiving Tech Dose Daily.', 'techdosedaily-core' ),
		Result::INVALID     => __( 'Enter an email address like name@example.com', 'techdosedaily-core' ),
		Result::UNAVAILABLE => __( 'Sign-up is temporarily unavailable. Please try again later.', 'techdosedaily-core' ),
		Result::ERROR       => __( 'Something went wrong. Please try again.', 'techdosedaily-core' ),
	);
}

/** Panel titles for the two success states (the other states are inline notes). */
function tdd_core_newsletter_titles(): array {
	return array(
		Result::SUBSCRIBED => __( 'You’re subscribed', 'techdosedaily-core' ), // Single opt-in providers only.
		Result::PENDING    => __( 'Check your inbox', 'techdosedaily-core' ),  // Double opt-in (MailPoet production setting).
	);
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
	$provider = tdd_core_newsletter_provider();
	$result   = $provider->subscribe( $email, array( 'source' => esc_url_raw( (string) wp_get_referer() ) ) );
	if ( Result::ERROR === $result->status && $result->detail ) {
		error_log( 'TDD newsletter provider error (' . $provider->id() . '): ' . $result->detail ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions -- no submitted data is logged.
	}
	do_action( 'tdd_core_newsletter_result', $result->status, $provider->id() );
	$code = $result->ok() ? 200 : ( Result::UNAVAILABLE === $result->status ? 503 : 500 );
	$titles = tdd_core_newsletter_titles();
	return array( 'state' => $result->status, 'title' => $titles[ $result->status ] ?? '', 'message' => $messages[ $result->status ] ?? $messages[ Result::ERROR ], 'code' => $code );
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
