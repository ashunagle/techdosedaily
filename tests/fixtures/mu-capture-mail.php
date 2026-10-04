<?php
/**
 * LOCAL TEST FIXTURE ONLY (copy into wp-content/mu-plugins on a local site).
 * Captures wp_mail instead of sending, so the contact form can be tested without a mail server.
 * Writes recipient + subject + headers (no body) to wp-content/tdd-mail-test.log.
 * fail@example.com as the sender's address simulates a delivery failure.
 */
add_filter(
	'pre_wp_mail',
	static function ( $null, array $atts ) {
		$fail = str_contains( implode( "\n", (array) $atts['headers'] ), 'fail@example.com' );
		file_put_contents( WP_CONTENT_DIR . '/tdd-mail-test.log', wp_json_encode( array( 'to' => $atts['to'], 'subject' => $atts['subject'], 'headers' => $atts['headers'], 'failed' => $fail ) ) . "\n", FILE_APPEND ); // phpcs:ignore
		return ! $fail;
	},
	10,
	2
);
