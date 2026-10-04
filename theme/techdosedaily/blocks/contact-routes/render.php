<?php
/**
 * tdd/contact-routes — ContactRoute list.
 *
 * @package techdosedaily
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

defined( 'ABSPATH' ) || exit;

echo tdd_contact_routes_html( (string) $attributes['variant'], array_map( 'sanitize_key', (array) $attributes['only'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
