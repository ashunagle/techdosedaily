<?php
/**
 * tdd/newsletter-hero — Newsletter hero + sign-up.
 *
 * @package techdosedaily
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

defined( 'ABSPATH' ) || exit;

$tdd_p = get_post();
if ( $tdd_p instanceof WP_Post ) {
	wp_enqueue_script( generate_block_asset_handle( 'tdd/newsletter-cta', 'viewScript' ) );
	echo tdd_newsletter_hero( $tdd_p, (array) $attributes['facts'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
