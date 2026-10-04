<?php
/**
 * tdd/section-top — pinned top stories: split feature + 3 stories (desktop) / feature + 3 compact
 * rows (approved mobile). Slots resolve active placement → next valid placement → newest story.
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

$tdd_term = get_queried_object();
if ( ! $tdd_term instanceof WP_Term || 'category' !== $tdd_term->taxonomy || is_paged() ) {
	return;
}
$tdd_posts = array_values( array_filter( array_map( 'get_post', tdd_section_top_ids( (int) $tdd_term->term_id ) ) ) );
if ( ! $tdd_posts ) {
	return;
}
foreach ( $tdd_posts as $tdd_p ) {
	tdd_mark_shown( $tdd_p->ID );
}
$tdd_lead  = array_shift( $tdd_posts );
$tdd_ltags = array( 'topic' => true, 'breaking' => true, 'wrap' => true );
$tdd_deck  = tdd_deck( $tdd_lead );
$tdd_lmeta = tdd_meta( $tdd_lead, tdd_card_meta_parts( $tdd_lead ) === array( 'ago' ) ? array( 'ago' ) : array( 'author', 'updated', 'read' ) );
$tdd_html  = '<article class="tdd-split tdd-story m-feature">' . tdd_media( $tdd_lead, 'tdd-16x9-1200', '(max-width: 767px) 100vw, 720px', true, true )
	. '<div class="tdd-split__body">' . tdd_labels( $tdd_lead, $tdd_ltags )
	. '<h2 class="tdd-story__title"><a href="' . esc_url( get_permalink( $tdd_lead ) ) . '">' . esc_html( get_the_title( $tdd_lead ) ) . '</a></h2>'
	. ( '' !== $tdd_deck ? '<p class="tdd-story__summary">' . esc_html( $tdd_deck ) . '</p>' : '' ) . $tdd_lmeta . '</div></article>';

if ( $tdd_posts ) {
	$tdd_cards = '';
	$tdd_rows  = '';
	foreach ( $tdd_posts as $tdd_p ) {
		$tdd_o      = array( 'meta' => tdd_card_meta_parts( $tdd_p ), 'labels' => $tdd_ltags );
		$tdd_cards .= tdd_story_card( $tdd_p, 'medium', $tdd_o + array( 'sizes' => '(max-width: 1199px) 33vw, 400px' ) );
		$tdd_rows  .= tdd_story_card( $tdd_p, 'compact', $tdd_o + array( 'thumb' => true, 'size' => 'tdd-4x3', 'sizes' => '96px' ) );
	}
	$tdd_html .= '<div class="cp-sec3 tdd-hide-m">' . $tdd_cards . '</div><div class="m-list tdd-sec-toplist tdd-only-m is-block">' . $tdd_rows . '</div>';
}
/* translators: %s: section name. */
echo '<section class="tdd-sec-top" aria-label="' . esc_attr( sprintf( __( 'Top %s stories', 'techdosedaily' ), $tdd_term->name ) ) . '">' . $tdd_html . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped helpers.
