<?php
/**
 * Contact routes, settings and form processing.
 *
 * Security (approved Phase 1 requirements): WordPress nonce (the contact page is never cached),
 * signed time token (minimum fill time), honeypot, hashed-IP throttling, server-side validation,
 * sanitised values, escaped output, Post/Redirect/Get after a successful no-JS send, configurable role
 * inboxes, the `tdd_core_is_spam` filter, and no submitted content in logs or the database.
 * The no-JS form posts to the Contact page: success redirects (PRG); a failure re-renders the page
 * in the same response with the entered values, so nothing has to be kept between requests.
 * Messages are delivered by wp_mail() only; if delivery fails the reader is told, never shown
 * a false "sent".
 *
 * Options:
 *   tdd_contact_inboxes       [ route => email ]  role inboxes (private; never printed)
 *   tdd_contact_expectations  [ {title, text} ]   "What to expect" — only true, current commitments
 *   tdd_contact_notes         [ route => text ]   optional small note under a route's link
 *   tdd_secure_tip            HTML                real secure-tip instructions; empty = none offered
 *   tdd_media_kit_url         URL                 media kit, when one exists
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Routes in the approved order. Titles and descriptions are the approved copy; links are only
 * printed when their target exists.
 *
 * @return array<string, array{title:string, desc:string, cta:string, policy:string, policy_label:string}>
 */
function tdd_core_contact_routes(): array {
	return apply_filters(
		'tdd_core_contact_routes',
		array(
			'editorial'   => array( 'title' => __( 'Editorial', 'techdosedaily-core' ), 'desc' => __( 'Questions about our coverage or a published story.', 'techdosedaily-core' ), 'cta' => __( 'Write to the editors →', 'techdosedaily-core' ), 'policy' => '', 'policy_label' => '', 'topic' => __( 'Editorial', 'techdosedaily-core' ) ),
			'correction'  => array( 'title' => __( 'Corrections', 'techdosedaily-core' ), 'desc' => __( 'Report a factual error in a story. Include the link and what is wrong.', 'techdosedaily-core' ), 'cta' => __( 'Report a correction →', 'techdosedaily-core' ), 'policy' => 'corrections-policy', 'policy_label' => __( 'Corrections Policy', 'techdosedaily-core' ), 'topic' => __( 'Correction', 'techdosedaily-core' ) ),
			'tip'         => array( 'title' => __( 'Tips', 'techdosedaily-core' ), 'desc' => __( 'Story tips, on the record or confidential.', 'techdosedaily-core' ), 'cta' => __( 'Send a tip →', 'techdosedaily-core' ), 'policy' => '#secure', 'policy_label' => __( 'Sensitive tip guidance', 'techdosedaily-core' ), 'topic' => __( 'Tip', 'techdosedaily-core' ) ),
			'partnership' => array( 'title' => __( 'Partnerships', 'techdosedaily-core' ), 'desc' => __( 'Advertising, sponsorships and the media kit.', 'techdosedaily-core' ), 'cta' => __( 'Contact partnerships →', 'techdosedaily-core' ), 'policy' => 'media-kit', 'policy_label' => __( 'Media kit (PDF)', 'techdosedaily-core' ), 'topic' => __( 'Partnership', 'techdosedaily-core' ) ),
			'general'     => array( 'title' => __( 'General', 'techdosedaily-core' ), 'desc' => __( 'Anything else, including feedback about the site.', 'techdosedaily-core' ), 'cta' => __( 'Send a message →', 'techdosedaily-core' ), 'policy' => '', 'policy_label' => '', 'topic' => __( 'General', 'techdosedaily-core' ) ),
		)
	);
}

/** Inbox for a route: the configured role inbox, else the site admin address (always a real inbox). */
function tdd_core_contact_inbox( string $route ): string {
	$inboxes = (array) get_option( 'tdd_contact_inboxes', array() );
	$email   = sanitize_email( (string) ( $inboxes[ $route ] ?? '' ) );
	return is_email( $email ) ? $email : (string) get_option( 'admin_email' );
}

/** True once at least one role inbox is configured (used for docs / admin notices). */
function tdd_core_contact_configured(): bool {
	return (bool) array_filter( (array) get_option( 'tdd_contact_inboxes', array() ), 'is_email' );
}

/** @return array<int, array{title:string, text:string}> Only what the newsroom has written. */
function tdd_core_contact_expectations(): array {
	$rows = (array) get_option( 'tdd_contact_expectations', array() );
	return array_values( array_filter( array_map( static fn( $r ) => empty( $r['title'] ) ? null : array( 'title' => sanitize_text_field( $r['title'] ), 'text' => sanitize_text_field( $r['text'] ?? '' ) ), $rows ) ) );
}

function tdd_core_contact_note( string $route ): string {
	$notes = (array) get_option( 'tdd_contact_notes', array() );
	return sanitize_text_field( (string) ( $notes[ $route ] ?? '' ) );
}

/** Real secure-tip instructions (HTML) or '' when no secure channel exists. */
function tdd_core_secure_tip(): string {
	return wp_kses_post( (string) get_option( 'tdd_secure_tip', '' ) );
}

/** Field limits and copy (approved ContactForm). */
function tdd_core_contact_messages(): array {
	return array(
		'name'    => __( 'Enter your name', 'techdosedaily-core' ),
		'email'   => __( 'Enter an email address like name@example.com', 'techdosedaily-core' ),
		'topic'   => __( 'Choose a topic', 'techdosedaily-core' ),
		'url'     => __( 'Enter a full link, starting with https://', 'techdosedaily-core' ),
		'message' => __( 'Enter your message', 'techdosedaily-core' ),
	);
}

/** Success copy per route (approved: the correction text from ContactForm-States). */
function tdd_core_contact_success( string $route ): string {
	$map = array(
		'correction' => __( 'Thanks. Your correction report has reached the standards desk. If we change the story, the correction note will say what changed.', 'techdosedaily-core' ),
	);
	$routes = tdd_core_contact_routes();
	/* translators: %s: route name, e.g. "Editorial". */
	return $map[ $route ] ?? sprintf( __( 'Thanks. Your message has been sent to our %s inbox.', 'techdosedaily-core' ), $routes[ $route ]['title'] ?? __( 'General', 'techdosedaily-core' ) );
}

/**
 * Validate + deliver. Never stores or logs the submission.
 *
 * @return array{state:string, code:int, errors?:array, route?:string, message?:string}|WP_Error
 */
function tdd_core_contact_process( WP_REST_Request $request ) {
	if ( ! wp_verify_nonce( (string) $request->get_param( '_tdd_nonce' ), 'tdd_contact' ) ) {
		return new WP_Error( 'tdd_nonce', __( 'Please reload the page and try again.', 'techdosedaily-core' ), array( 'status' => 400 ) );
	}
	$guard = tdd_core_guard_submission( $request, 'contact', 5, 10 * MINUTE_IN_SECONDS );
	if ( is_wp_error( $guard ) ) {
		return $guard;
	}
	$msgs   = tdd_core_contact_messages();
	$routes = tdd_core_contact_routes();
	$name   = trim( str_replace( array( "\r", "\n" ), ' ', sanitize_text_field( (string) $request->get_param( 'name' ) ) ) );
	$email  = sanitize_email( (string) $request->get_param( 'email' ) );
	$topic  = sanitize_key( (string) $request->get_param( 'topic' ) );
	$url    = trim( (string) $request->get_param( 'url' ) );
	$msg    = tdd_core_sanitize_message( (string) $request->get_param( 'message' ) );
	$ack    = (bool) $request->get_param( 'not_urgent' );

	$errors = array();
	if ( '' === $name || mb_strlen( $name ) > 100 ) {
		$errors['name'] = $msgs['name'];
	}
	if ( ! is_email( $email ) || mb_strlen( $email ) > 254 ) {
		$errors['email'] = $msgs['email'];
	}
	if ( ! isset( $routes[ $topic ] ) ) {
		$errors['topic'] = $msgs['topic'];
	}
	if ( '' !== $url ) {
		$clean = esc_url_raw( $url, array( 'http', 'https' ) );
		if ( '' === $clean || ! wp_http_validate_url( $clean ) || mb_strlen( $clean ) > 500 ) {
			$errors['url'] = $msgs['url'];
		}
		$url = $clean;
	}
	if ( '' === $msg || mb_strlen( $msg ) > 5000 ) {
		$errors['message'] = $msgs['message'];
	}
	if ( $errors ) {
		return array( 'state' => 'invalid', 'code' => 422, 'errors' => $errors );
	}
	// Same filter as the shared guard (false, request, bucket); 'contact:content' runs on the cleaned fields.
	if ( apply_filters( 'tdd_core_is_spam', false, $request, 'contact:content' ) ) {
		// Look like success to the sender; deliver nothing.
		return array( 'state' => 'sent', 'code' => 200, 'route' => $topic, 'message' => tdd_core_contact_success( $topic ) );
	}

	$route = $routes[ $topic ];
	/* translators: 1: route, 2: first words of the message. */
	$subject = sprintf( __( '[Tech Dose Daily · %1$s] %2$s', 'techdosedaily-core' ), $route['title'], wp_html_excerpt( (string) preg_replace( '/\s+/', ' ', $msg ), 60, '…' ) );
	$body    = implode(
		"\n",
		array(
			/* translators: %s: route. */
			sprintf( __( 'Route: %s', 'techdosedaily-core' ), $route['title'] ),
			/* translators: %s: name. */
			sprintf( __( 'Name: %s', 'techdosedaily-core' ), $name ),
			/* translators: %s: email. */
			sprintf( __( 'Email: %s', 'techdosedaily-core' ), $email ),
			/* translators: %s: URL. */
			sprintf( __( 'Article URL: %s', 'techdosedaily-core' ), '' !== $url ? $url : '—' ),
			/* translators: %s: yes/no. */
			sprintf( __( 'Confirmed not an urgent security report: %s', 'techdosedaily-core' ), $ack ? __( 'yes', 'techdosedaily-core' ) : __( 'no', 'techdosedaily-core' ) ),
			'',
			$msg,
		)
	);
	$headers = array( 'Reply-To: "' . trim( str_replace( array( '"', ',', ';', '<', '>', '\\' ), ' ', $name ) ) . '" <' . $email . '>' ); // Name cannot add addresses or headers.
	$plain   = static fn() => 'text/plain'; // Never let a site-wide HTML mail setting interpret reader text.
	$from    = sanitize_email( (string) get_option( 'tdd_contact_from', '' ) );
	$from_cb = static fn( $f ) => is_email( $from ) ? $from : $f; // Site mailbox; the reader is only ever Reply-To.
	add_filter( 'wp_mail_content_type', $plain, 99 );
	add_filter( 'wp_mail_from', $from_cb, 99 );
	$sent = wp_mail( tdd_core_contact_inbox( $topic ), $subject, $body, $headers );
	remove_filter( 'wp_mail_content_type', $plain, 99 );
	remove_filter( 'wp_mail_from', $from_cb, 99 );
	do_action( 'tdd_core_contact_result', $sent ? 'sent' : 'error', $topic ); // No personal data passed on.
	if ( ! $sent ) {
		return array( 'state' => 'error', 'code' => 500, 'message' => __( 'Your message could not be sent. Please try again later.', 'techdosedaily-core' ) );
	}
	return array( 'state' => 'sent', 'code' => 200, 'route' => $topic, 'message' => tdd_core_contact_success( $topic ) );
}

/**
 * Plain-text message: valid UTF-8, normalised line breaks, no control characters. Angle brackets
 * and code are kept (readers report broken markup and code); the text is only ever escaped on
 * output and sent as text/plain, never interpreted as HTML.
 */
function tdd_core_sanitize_message( string $v ): string {
	$v = wp_check_invalid_utf8( $v, true );
	$v = str_replace( array( "\r\n", "\r" ), "\n", $v );
	$v = (string) preg_replace( '/[^\P{C}\n\t]/u', '', $v );
	return trim( $v );
}

/**
 * Form field names. The page form uses cf_* names because several plain names (e.g. "name") are
 * public WordPress query vars that would change the page request; REST also accepts plain names.
 */
const TDD_CORE_CONTACT_FIELDS = array( 'name', 'email', 'topic', 'url', 'message', 'not_urgent' );

/**
 * Collect the contact fields into a request.
 *
 * @param array $src     REST params or $_POST.
 * @param bool  $slashed True for $_POST (WordPress adds slashes); false for REST params.
 */
function tdd_core_contact_request( array $src, bool $slashed = false ): WP_REST_Request {
	$r    = new WP_REST_Request( 'POST' );
	$read = static function ( string $k ) use ( $src, $slashed ) {
		$v = $src[ 'cf_' . $k ] ?? ( $src[ $k ] ?? '' );
		$v = is_scalar( $v ) ? (string) $v : '';
		return $slashed ? wp_unslash( $v ) : $v;
	};
	foreach ( TDD_CORE_CONTACT_FIELDS as $k ) {
		$r->set_param( $k, 'message' === $k ? tdd_core_sanitize_message( $read( $k ) ) : sanitize_text_field( $read( $k ) ) );
	}
	foreach ( array( 'tdd_token', 'tdd_hp', '_tdd_nonce' ) as $k ) {
		$v = isset( $src[ $k ] ) && is_scalar( $src[ $k ] ) ? (string) $src[ $k ] : '';
		$r->set_param( $k, sanitize_text_field( $slashed ? wp_unslash( $v ) : $v ) );
	}
	return $r;
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'tdd/v1',
			'/contact',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // Public form; protected by nonce, token, honeypot and throttle.
				'callback'            => static function ( WP_REST_Request $request ) {
					$out = tdd_core_contact_process( tdd_core_contact_request( $request->get_params() ) );
					if ( is_wp_error( $out ) ) {
						return $out;
					}
					$code = $out['code'];
					unset( $out['code'] );
					return new WP_REST_Response( $out, $code );
				},
			)
		);
	}
);

/**
 * No-JS path: the form posts to the Contact page itself.
 *   sent    → 303 redirect to ?tdd_cf=sent (Post/Redirect/Get, so a reload never re-sends)
 *   failure → the page renders in the same response with every entered field (message included)
 *             re-filled from this request only. Nothing is stored, cached or logged.
 */
add_action(
	'template_redirect',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in tdd_core_contact_process().
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['tdd_contact'] ) || ! is_singular( 'page' ) ) {
			return;
		}
		$req = tdd_core_contact_request( $_POST, true );
		// phpcs:enable
		$out  = tdd_core_contact_process( $req );
		$page = get_permalink( get_queried_object_id() );
		if ( ! is_wp_error( $out ) && 'sent' === $out['state'] ) {
			wp_safe_redirect( add_query_arg( array( 'tdd_cf' => 'sent', 'topic' => $out['route'] ), $page ) . '#form', 303 );
			exit;
		}
		$state = 'error';
		if ( is_wp_error( $out ) ) {
			$state = in_array( $out->get_error_code(), array( 'tdd_too_fast', 'tdd_rate' ), true ) ? 'retry' : 'error';
		} elseif ( 'invalid' === $out['state'] ) {
			$state = 'invalid';
		}
		$values = array();
		foreach ( TDD_CORE_CONTACT_FIELDS as $k ) {
			$values[ $k ] = (string) $req->get_param( $k );
		}
		$GLOBALS['tdd_core_contact_posted'] = array(
			'state'  => $state,
			'errors' => ( ! is_wp_error( $out ) && 'invalid' === $out['state'] ) ? array_keys( $out['errors'] ) : array(),
			'values' => $values,
		);
		nocache_headers();
	},
	5
);

/**
 * The submission being re-rendered in this request (no-JS failure), or an empty array.
 *
 * @return array{state?:string, errors?:string[], values?:array<string,string>}
 */
function tdd_core_contact_posted(): array {
	return (array) ( $GLOBALS['tdd_core_contact_posted'] ?? array() );
}
