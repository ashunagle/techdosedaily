<?php
/**
 * tdd/deflist — definition list.
 *
 * @package techdosedaily
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

defined( 'ABSPATH' ) || exit;

echo tdd_deflist( (array) $attributes['rows'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
