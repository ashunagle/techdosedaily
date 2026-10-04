<?php
/**
 * tdd/home-guides — Practical Guides: compact stack (desktop sidebar) or swipe row (approved mobile).
 *
 * @package techdosedaily
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$tdd_posts = tdd_home_guides_posts( max( 1, min( 6, (int) $attributes['count'] ) ) );
if ( ! $tdd_posts ) {
	return;
}
$tdd_url  = tdd_guides_url();
$tdd_head = '<div class="tdd-sh tdd-sh--small"><h2 class="tdd-sh__title">' . esc_html__( 'Practical Guides', 'techdosedaily' ) . '</h2>' . ( $tdd_url ? '<a class="tdd-btn tdd-btn--text tdd-sh__more" href="' . esc_url( $tdd_url ) . '">' . esc_html__( 'All guides →', 'techdosedaily' ) . '</a>' : '' ) . '</div>';
$tdd_html = '';
foreach ( $tdd_posts as $tdd_p ) {
	if ( 'swipe' === $attributes['variant'] ) {
		/* translators: %d: minutes. */
		$tdd_html .= '<article class="tdd-story">' . tdd_media( $tdd_p, 'tdd-16x9-400', '236px' ) . '<span class="tdd-label tdd-label--guide">' . esc_html( sprintf( __( 'Guide · %d min', 'techdosedaily' ), tdd_reading_time( $tdd_p ) ) ) . '</span><h3 class="tdd-story__title"><a href="' . esc_url( get_permalink( $tdd_p ) ) . '">' . esc_html( get_the_title( $tdd_p ) ) . '</a></h3></article>';
	} else {
		$tdd_html .= tdd_story_card( $tdd_p, 'compact', array( 'thumb' => true, 'meta' => array( 'read' ), 'size' => 'tdd-4x3', 'sizes' => '96px', 'labels' => array( 'one' => true ) ) );
	}
}
if ( 'swipe' === $attributes['variant'] ) {
	echo '<section class="m-section tdd-only-m is-block hp-guides-swipe" aria-label="' . esc_attr__( 'Practical Guides', 'techdosedaily' ) . '">' . $tdd_head . '<div class="m-swipe">' . $tdd_html . '</div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
} else {
	echo '<div class="hp-guides">' . $tdd_head . '<div class="tdd-stack">' . $tdd_html . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
