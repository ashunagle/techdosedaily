<?php
/**
 * tdd/section-guides — "Practical <Section>": guides in this section, most recently updated first.
 * Desktop 4 tiles; phones 3 compact rows (approved). Omitted when there are no guides.
 *
 * @package techdosedaily
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$tdd_term = get_queried_object();
if ( ! $tdd_term instanceof WP_Term || 'category' !== $tdd_term->taxonomy || is_paged() ) {
	return;
}
if ( function_exists( 'tdd_core_section_module_on' ) && ! tdd_core_section_module_on( (int) $tdd_term->term_id, 'guides' ) ) {
	return; // Turned off in the section settings.
}
$tdd_posts = get_posts(
	array(
		'post_type'           => 'post',
		'posts_per_page'      => 12,
		'cat'                 => $tdd_term->term_id,
		'post__not_in'        => tdd_shown(),
		'tax_query'           => array( array( 'taxonomy' => 'tdd_format', 'field' => 'slug', 'terms' => array( 'guide' ) ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
if ( ! $tdd_posts ) {
	return;
}
// Sorted by last substantive update (falls back to publication).
$tdd_upd = static function ( WP_Post $p ): int {
	$u = tdd_has_core() ? tdd_core_updated_at( $p ) : null;
	return $u ? $u->getTimestamp() : (int) get_post_timestamp( $p );
};
usort( $tdd_posts, static fn( $a, $b ) => $tdd_upd( $b ) <=> $tdd_upd( $a ) );
$tdd_posts = array_slice( $tdd_posts, 0, max( 1, min( 8, (int) $attributes['count'] ) ) );

$tdd_tiles = '';
$tdd_rows  = '';
foreach ( $tdd_posts as $tdd_i => $tdd_p ) {
	tdd_mark_shown( $tdd_p->ID );
	$tdd_ts = $tdd_upd( $tdd_p );
	/* translators: %d: minutes. */
	$tdd_meta = '<div class="tdd-meta"><span>' . esc_html( sprintf( __( '%d min', 'techdosedaily' ), tdd_reading_time( $tdd_p ) ) ) . '</span><span>' . esc_html__( 'Updated', 'techdosedaily' ) . ' ' . tdd_time( $tdd_ts, wp_date( 'M j', $tdd_ts ) ) . '</span></div>';
	$tdd_lbl  = '<span class="tdd-label tdd-label--guide">' . esc_html__( 'Guide', 'techdosedaily' ) . '</span>';
	$tdd_t    = '<h3 class="tdd-story__title"><a href="' . esc_url( get_permalink( $tdd_p ) ) . '">' . esc_html( get_the_title( $tdd_p ) ) . '</a></h3>';
	$tdd_tiles .= '<article class="tdd-story">' . tdd_media( $tdd_p, 'tdd-16x9-800', '(max-width: 1199px) 50vw, 300px' ) . $tdd_lbl . $tdd_t . $tdd_meta . '</article>';
	if ( $tdd_i < 3 ) {
		$tdd_m     = tdd_media( $tdd_p, 'tdd-4x3', '96px' );
		$tdd_rows .= '<article class="tdd-story tdd-story--compact' . ( $tdd_m ? ' has-thumb' : '' ) . '">' . $tdd_m . '<div class="tdd-labels">' . $tdd_lbl . '</div>' . $tdd_t . $tdd_meta . '</article>';
	}
}
$tdd_all = get_term_link( 'guide', 'tdd_format' );
$tdd_all = is_wp_error( $tdd_all ) ? '' : $tdd_all;
/* translators: %s: section name. */
$tdd_title = sprintf( __( 'Practical %s', 'techdosedaily' ), $tdd_term->name );
/* translators: %s: section name. */
$tdd_alltxt = sprintf( __( 'All %s guides →', 'techdosedaily' ), $tdd_term->name );
echo '<section class="cp-sec tdd-sec-guides" aria-labelledby="tdd-guides-title"><div class="tdd-sh tdd-sec-guides__head"><div><h2 class="tdd-sh__title" id="tdd-guides-title">' . esc_html( $tdd_title ) . '</h2><p class="tdd-sh__desc tdd-hide-m">' . esc_html__( 'Step-by-step guides, kept up to date', 'techdosedaily' ) . '</p></div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	. ( $tdd_all ? '<a class="tdd-btn tdd-btn--text tdd-sh__more" href="' . esc_url( $tdd_all ) . '"><span class="tdd-hide-m">' . esc_html( $tdd_alltxt ) . '</span><span class="tdd-only-m">' . esc_html__( 'All guides →', 'techdosedaily' ) . '</span></a>' : '' ) . '</div>'
	. '<div class="tdd-guides tdd-hide-m">' . $tdd_tiles . '</div><div class="m-list m-list--after-sh tdd-only-m is-block">' . $tdd_rows . '</div></section>';
