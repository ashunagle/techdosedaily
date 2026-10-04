<?php
/**
 * tdd/nl-sample — today's real Daily Tech Brief in the issue frame.
 *
 * @package techdosedaily
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

defined( 'ABSPATH' ) || exit;

echo tdd_nl_sample( (string) $attributes['title'], (string) $attributes['how'], (array) $attributes['delivery'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
