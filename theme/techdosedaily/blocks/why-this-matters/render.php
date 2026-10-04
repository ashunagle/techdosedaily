<?php
/**
 * tdd/why-this-matters — WhyThisMatters box around paragraphs (inner blocks). Omitted when empty.
 *
 * @package techdosedaily
 * @var array  $attributes
 * @var string $content
 */

defined( 'ABSPATH' ) || exit;

if ( '' === trim( wp_strip_all_tags( (string) $content ) ) ) {
	return;
}
echo '<section class="tdd-why tdd-block" aria-label="' . esc_attr( $attributes['title'] ) . '"><div class="tdd-why__title">' . tdd_icon( 'info', '' ) . esc_html( $attributes['title'] ) . '</div>' . $content . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inner blocks are filtered post content.
