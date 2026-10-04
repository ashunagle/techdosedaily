<?php
/**
 * tdd/most-read — renders only from real view data (TechDoseDaily Core). Never filled with
 * non-popularity fallbacks; hidden when fewer than minItems stories have real counts.
 *
 * Attributes: context home|section|author (window from Core settings), section (slug; on a
 * section/single page defaults to the current section when context = section), hours (override).
 *
 * @package techdosedaily
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tdd_core_most_read' ) ) {
	return;
}
$tdd_ctx     = in_array( $attributes['context'] ?? 'home', array( 'home', 'section', 'author' ), true ) ? $attributes['context'] : 'home';
$tdd_section = ! empty( $attributes['section'] ) ? get_term_by( 'slug', $attributes['section'], 'category' ) : null;
$tdd_author  = 0;
if ( 'section' === $tdd_ctx && ! $tdd_section ) {
	$tdd_section = is_category() ? get_queried_object() : ( is_singular( 'post' ) ? tdd_section( get_queried_object() ) : null );
}
if ( 'author' === $tdd_ctx && is_author() ) {
	$tdd_author = (int) get_queried_object_id();
}
$tdd_hours = (int) ( $attributes['hours'] ?? 0 );
$tdd_hours = $tdd_hours > 0 ? $tdd_hours : ( function_exists( 'tdd_core_most_read_window' ) ? tdd_core_most_read_window( $tdd_ctx ) : 24 );
$tdd_posts = tdd_core_most_read(
	(int) $attributes['count'],
	array(
		'section' => $tdd_section instanceof WP_Term ? $tdd_section->term_id : 0,
		'author'  => $tdd_author,
		'hours'   => $tdd_hours,
	)
);
if ( count( $tdd_posts ) < max( 1, (int) $attributes['minItems'] ) ) {
	return;
}
// A ranking, not a placement: it doesn't take part in page-level de-duplication.
$tdd_short  = 'short' === ( $attributes['labelStyle'] ?? 'last' );
$tdd_window = $tdd_short && $tdd_hours >= 24 && 0 === $tdd_hours % 24
	/* translators: %d: days */
	? sprintf( _n( '%d day', '%d days', (int) ( $tdd_hours / 24 ), 'techdosedaily' ), (int) ( $tdd_hours / 24 ) )
	: ( $tdd_hours <= 24
	/* translators: %d: hours */
	? ( 24 === $tdd_hours ? __( 'Last 24 hours', 'techdosedaily' ) : sprintf( __( 'Last %d hours', 'techdosedaily' ), $tdd_hours ) )
	/* translators: %d: days */
	: sprintf( __( 'Last %d days', 'techdosedaily' ), (int) round( $tdd_hours / 24 ) ) );
/* translators: %s: section name. */
$tdd_title = ! empty( $attributes['titleAuthor'] ) && is_author() ? sprintf( /* translators: %s: author first name */ __( 'Most read by %s', 'techdosedaily' ), strtok( get_queried_object()->display_name, ' ' ) ) : ( ! empty( $attributes['titleSection'] ) && $tdd_section instanceof WP_Term ? sprintf( __( 'Most Read in %s', 'techdosedaily' ), $tdd_section->name ) : $attributes['title'] );
$tdd_rows   = '';
foreach ( $tdd_posts as $tdd_i => $tdd_post ) {
	$tdd_rows .= '<li><a class="tdd-mr-row" href="' . esc_url( get_permalink( $tdd_post ) ) . '"><span class="tdd-mr-row__num">' . esc_html( sprintf( '%02d', $tdd_i + 1 ) ) . '</span><span class="tdd-mr-row__body"><span class="tdd-mr-row__title">' . esc_html( get_the_title( $tdd_post ) ) . '</span>' . tdd_labels( $tdd_post, array( 'one' => true ) ) . '</span></a></li>';
}
$tdd_cls = trim( 'tdd-mostread ' . ( $attributes['className'] ?? '' ) );
echo '<div class="' . esc_attr( $tdd_cls ) . '"><div class="tdd-sh tdd-sh--small"><h2 class="tdd-sh__title">' . esc_html( $tdd_title ) . '</h2>' . ( ( $attributes['showWindow'] ?? true ) ? '<span class="tdd-meta tdd-sh__more">' . esc_html( $tdd_window ) . '</span>' : '' ) . '</div><ol>' . $tdd_rows . '</ol></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
