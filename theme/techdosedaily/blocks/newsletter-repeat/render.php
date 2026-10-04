<?php
/**
 * tdd/newsletter-repeat — closing sign-up.
 *
 * @package techdosedaily
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_script( generate_block_asset_handle( 'tdd/newsletter-cta', 'viewScript' ) );
echo tdd_newsletter_repeat( (string) $attributes['title'], (string) $attributes['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
