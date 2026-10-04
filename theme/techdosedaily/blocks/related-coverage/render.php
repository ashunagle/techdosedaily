<?php
/**
 * tdd/related-coverage — RelatedCoverage. Desktop: four medium cards in a row; phones: compact
 * rows (approved mobile list). Collapses entirely when there are no related stories.
 *
 * @package techdosedaily
 * @var array    $attributes
 * @var WP_Block $block
 */

defined( 'ABSPATH' ) || exit;

$tdd_post = get_post( $block->context['postId'] ?? get_the_ID() );
if ( ! $tdd_post || 'post' !== $tdd_post->post_type ) {
	return;
}
$tdd_posts = tdd_related_posts( $tdd_post, max( 1, min( 8, (int) $attributes['count'] ) ) );
if ( ! $tdd_posts ) {
	return;
}
$tdd_cards = '';
$tdd_rows  = '';
foreach ( $tdd_posts as $tdd_p ) {
	$tdd_cards .= tdd_story_card( $tdd_p, 'medium', array( 'meta' => array( 'date' ), 'labels' => array( 'one' => true ), 'sizes' => '(max-width: 1199px) 50vw, 300px' ) );
	$tdd_rows  .= tdd_compact_row( $tdd_p, 'date' );
}
$tdd_section = tdd_section( $tdd_post );
$tdd_desc    = tdd_related_desc( $tdd_post );
/* translators: %s: section name. */
$tdd_more = $tdd_section ? '<a class="tdd-btn tdd-btn--text tdd-sh__more tdd-hide-m" href="' . esc_url( get_term_link( $tdd_section ) ) . '">' . esc_html( sprintf( __( 'More in %s →', 'techdosedaily' ), $tdd_section->name ) ) . '</a>' : '';

echo '<section class="tdd-art-related" aria-labelledby="tdd-related-title"><div class="tdd-sh"><div><h2 class="tdd-sh__title" id="tdd-related-title">' . esc_html__( 'Related Coverage', 'techdosedaily' ) . '</h2>'
	. ( '' !== $tdd_desc ? '<p class="tdd-sh__desc tdd-hide-m">' . esc_html( $tdd_desc ) . '</p>' : '' ) . '</div>' . $tdd_more . '</div>'
	. '<div class="tdd-related tdd-hide-m">' . $tdd_cards . '</div><div class="m-list m-list--after-sh tdd-only-m is-block">' . $tdd_rows . '</div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped helpers.
