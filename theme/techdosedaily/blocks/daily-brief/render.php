<?php
/**
 * tdd/daily-brief — items come from the daily_brief placement (TechDoseDaily Core). Hidden until at least three items are placed.
 *
 * @package techdosedaily
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tdd_core_fill_placement' ) ) {
	return;
}
$tdd_posts = tdd_core_fill_placement( 'daily_brief', 10, array( 'fallback' => false ) );
if ( count( $tdd_posts ) < 3 ) {
	return;
}
$tdd_items = '';
foreach ( $tdd_posts as $tdd_post ) {
	$tdd_items .= '<li class="tdd-brief__item"><a href="' . esc_url( get_permalink( $tdd_post ) ) . '">' . esc_html( tdd_core_short_title( $tdd_post ) ) . '</a></li>';
}
$tdd_count   = count( $tdd_posts );
/* translators: %s: number of items. */
$tdd_title   = sprintf( __( '%s things worth knowing today', 'techdosedaily' ), $tdd_count );
$tdd_updated = tdd_core_placement_updated( 'daily_brief' );
$tdd_meta    = '<span>' . esc_html__( 'Compiled by the TDD desk', 'techdosedaily' ) . '</span>' . ( $tdd_updated ? '<span>' . esc_html( sprintf( /* translators: %s: time */ __( 'Updated %s', 'techdosedaily' ), wp_date( 'g:i A T', $tdd_updated ) ) ) . '</span>' : '' );
$tdd_link    = $attributes['linkUrl'] ? '<a class="tdd-btn tdd-btn--text" href="' . esc_url( $attributes['linkUrl'] ) . '">' . esc_html( $attributes['linkText'] ) . '</a>' : '';
printf(
	'<div class="tdd-brief"><div class="tdd-brief__head"><div><div class="tdd-brief__kicker"><img src="%1$s" alt="" width="18" height="18">TDD <span>·</span> %2$s</div><h2 class="tdd-brief__title">%3$s</h2></div><div class="tdd-brief__edition"><b>%4$s</b></div></div><ol class="tdd-brief__list">%5$s</ol><div class="tdd-brief__foot"><span class="tdd-meta">%6$s</span>%7$s</div></div>',
	tdd_asset( 'images/tdd-mark.svg' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	esc_html__( 'Daily Tech Brief', 'techdosedaily' ),
	esc_html( $tdd_title ),
	esc_html( wp_date( 'l, F j' ) ),
	$tdd_items, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$tdd_meta, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$tdd_link // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);
