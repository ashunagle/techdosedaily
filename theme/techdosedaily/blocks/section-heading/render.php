<?php
/**
 * tdd/section-heading
 *
 * @package techdosedaily
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

if ( '' === trim( $attributes['title'] ) ) {
	return;
}
echo tdd_section_heading( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
