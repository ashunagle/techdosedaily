<?php
/**
 * tdd/section-analysis — Analysis & Explainers (author-led): 1 lead + up to 4 items on desktop,
 * lead + 3 items + "All analysis" on phones (approved). Never repeats stories shown above.
 *
 * @package techdosedaily
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$tdd_term = get_queried_object();
if ( ! $tdd_term instanceof WP_Term || 'category' !== $tdd_term->taxonomy || is_paged() ) {
	return;
}
if ( function_exists( 'tdd_core_section_module_on' ) && ! tdd_core_section_module_on( (int) $tdd_term->term_id, 'analysis' ) ) {
	return; // Turned off in the section settings.
}
global $wp_query;
$tdd_exclude = array_merge( tdd_shown(), wp_list_pluck( $wp_query->posts, 'ID' ) );
$tdd_posts   = get_posts(
	array(
		'post_type'           => 'post',
		'posts_per_page'      => max( 2, min( 5, (int) $attributes['count'] ) ),
		'cat'                 => $tdd_term->term_id,
		'post__not_in'        => $tdd_exclude,
		'tax_query'           => array( array( 'taxonomy' => 'tdd_format', 'field' => 'slug', 'terms' => array( 'analysis', 'explainer' ) ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
if ( ! $tdd_posts ) {
	return;
}
foreach ( $tdd_posts as $tdd_p ) {
	tdd_mark_shown( $tdd_p->ID );
}
$tdd_lead = array_shift( $tdd_posts );
$tdd_one  = array( 'one' => true );
$tdd_deck = tdd_deck( $tdd_lead );
$tdd_html = '<article class="c6 tdd-story cp-anlead m-ai-lead">' . tdd_media( $tdd_lead, 'tdd-16x9-1200', '(max-width: 767px) 100vw, 620px' ) . tdd_labels( $tdd_lead, $tdd_one )
	. '<h3 class="tdd-story__title"><a href="' . esc_url( get_permalink( $tdd_lead ) ) . '">' . esc_html( get_the_title( $tdd_lead ) ) . '</a></h3>'
	. ( '' !== $tdd_deck ? '<p class="tdd-story__summary tdd-hide-m">' . esc_html( $tdd_deck ) . '</p>' : '' ) . tdd_anby( $tdd_lead ) . '</article>';
$tdd_items = '';
foreach ( $tdd_posts as $tdd_i => $tdd_p ) {
	$tdd_d      = tdd_deck( $tdd_p );
	$tdd_items .= '<article class="tdd-anitem tdd-story' . ( 3 === $tdd_i ? ' tdd-hide-m' : '' ) . '">' . tdd_labels( $tdd_p, $tdd_one )
		. '<h3 class="tdd-story__title"><a href="' . esc_url( get_permalink( $tdd_p ) ) . '">' . esc_html( get_the_title( $tdd_p ) ) . '</a></h3>'
		. ( '' !== $tdd_d ? '<p>' . esc_html( $tdd_d ) . '</p>' : '' ) . tdd_anby( $tdd_p ) . '</article>';
}
$tdd_all = get_term_link( 'analysis', 'tdd_format' );
$tdd_all = is_wp_error( $tdd_all ) ? '' : $tdd_all;
/* translators: %s: section name. */
$tdd_desc = sprintf( __( 'Deeper reads from the TDD %s desk', 'techdosedaily' ), $tdd_term->name );
echo '<section class="cp-sec tdd-sec-analysis" aria-labelledby="tdd-an-title"><div class="tdd-sh tdd-section-rule"><div><h2 class="tdd-sh__title" id="tdd-an-title">' . esc_html__( 'Analysis & Explainers', 'techdosedaily' ) . '</h2><p class="tdd-sh__desc">' . esc_html( $tdd_desc ) . '</p></div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	. ( $tdd_all ? '<a class="tdd-btn tdd-btn--text tdd-sh__more tdd-hide-m" href="' . esc_url( $tdd_all ) . '">' . esc_html__( 'All analysis →', 'techdosedaily' ) . '</a>' : '' ) . '</div>'
	. '<div class="cp-g">' . $tdd_html . ( $tdd_items ? '<div class="c6 cp-an2">' . $tdd_items . '</div>' : '' ) . '</div>'
	. ( $tdd_all ? '<a class="m-more tdd-only-m tdd-sec-analysis__more" href="' . esc_url( $tdd_all ) . '">' . esc_html__( 'All analysis', 'techdosedaily' ) . '</a>' : '' ) . '</section>';
