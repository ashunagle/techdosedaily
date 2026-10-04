<?php
/**
 * tdd/trending — Appearance → Menus → "Trending". Hidden when the menu is empty.
 *
 * @package techdosedaily
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$tdd_items = tdd_menu_items( 'trending' );
if ( ! $tdd_items ) {
	return;
}
$tdd_links = array();
foreach ( $tdd_items as [ $tdd_label, $tdd_url ] ) {
	$tdd_links[] = '<a href="' . esc_url( $tdd_url ) . '">' . esc_html( $tdd_label ) . '</a>';
}
printf(
	'<nav class="tdd-trending%5$s" aria-label="%1$s"><div class="tdd-container tdd-trending__inner"><span class="tdd-trending__title">%2$s</span>%3$s%4$s</div></nav>',
	esc_attr__( 'Trending topics', 'techdosedaily' ),
	esc_html__( 'Trending', 'techdosedaily' ),
	implode( '<span class="tdd-trending__sep" aria-hidden="true">·</span>', $tdd_links ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	( $attributes['showDate'] ?? true ) ? '<span class="tdd-trending__date">' . esc_html( wp_date( 'l, F j, Y' ) ) . '</span>' : '',
	! empty( $attributes['hideMobile'] ) ? ' tdd-hide-m' : '' // Section pages (approved mobile): the topic nav is the only horizontal scroller.
);
