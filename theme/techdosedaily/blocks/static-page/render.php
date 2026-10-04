<?php
/**
 * tdd/static-page — the static page (see inc/static.php).
 *
 * @package techdosedaily
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

defined( 'ABSPATH' ) || exit;

$tdd_p = get_post();
if ( ! $tdd_p instanceof WP_Post ) {
	return;
}
wp_enqueue_script( generate_block_asset_handle( 'tdd/article-body', 'viewScript' ) ); // Contents scroll-spy.
if ( 'about' === $attributes['end'] ) {
	wp_enqueue_script( generate_block_asset_handle( 'tdd/newsletter-cta', 'viewScript' ) );
}
echo tdd_static_page( $tdd_p, (string) $attributes['variant'], (string) $attributes['end'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the builders.
