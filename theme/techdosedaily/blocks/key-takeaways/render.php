<?php
/**
 * tdd/key-takeaways — KeyTakeaways box around one list (inner core/list). Omitted when empty.
 * Leaves a marker so the article body can place the mobile TOC right after it.
 *
 * @package techdosedaily
 * @var array  $attributes
 * @var string $content
 */

defined( 'ABSPATH' ) || exit;

if ( ! str_contains( (string) $content, '<li' ) ) {
	return;
}
$tdd_list = preg_replace( '#<(ul|ol)\b[^>]*>#', '<ul>', (string) $content, 1 );
$tdd_list = preg_replace( '#</ol>\s*$#', '</ul>', trim( $tdd_list ) );
echo '<section class="tdd-takeaways" aria-label="' . esc_attr( $attributes['title'] ) . '"><div class="tdd-takeaways__title">' . esc_html( $attributes['title'] ) . '</div>' . $tdd_list . '</section><!--tdd:after-takeaways-->'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inner blocks are filtered post content.
