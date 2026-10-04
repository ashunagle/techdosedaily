<?php
/**
 * tdd/contact-page — the Contact page (see inc/pages.php).
 *
 * @package techdosedaily
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

defined( 'ABSPATH' ) || exit;

$tdd_p = get_post();
if ( $tdd_p instanceof WP_Post ) {
	echo tdd_contact_page( $tdd_p ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the builders.
}
