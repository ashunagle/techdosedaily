<?php
/**
 * tdd/search-results — the approved search page (inc/search.php).
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

global $wp_query;
$tdd_term   = get_search_query( false );
$tdd_st     = tdd_search_state();
$tdd_total  = (int) $wp_query->found_posts;
$tdd_words  = tdd_search_words( $tdd_term );
$tdd_facets = '' !== $tdd_term ? tdd_search_facets( $tdd_term ) : array( 'section' => array(), 'format' => array(), 'author' => array(), 'date' => array( 'day' => 0, 'week' => 0, 'month' => 0, 'year' => 0 ), 'total' => 0 );

$tdd_field = '<form class="tdd-search" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '">' . tdd_icon( 'search', '' )
	. '<label for="tdd-q" class="screen-reader-text">' . esc_html__( 'Search', 'techdosedaily' ) . '</label><input id="tdd-q" type="search" name="s" value="' . esc_attr( $tdd_term ) . '" autocomplete="off" enterkeyhint="search">'
	. '<button class="tdd-search__clear" type="button" aria-label="' . esc_attr__( 'Clear search', 'techdosedaily' ) . '"' . ( '' === $tdd_term ? ' hidden' : '' ) . '>' . tdd_icon( 'x', '' ) . '</button>'
	. '<button class="tdd-btn tdd-btn--primary tdd-hide-m" type="submit">' . esc_html__( 'Search', 'techdosedaily' ) . '</button></form>';

$tdd_sum = '';
if ( '' !== $tdd_term ) {
	$tdd_count = number_format_i18n( $tdd_total );
	/* translators: 1: number of results, 2: search term. */
	$tdd_desk = sprintf( esc_html( _n( '%1$s result', '%1$s results', $tdd_total, 'techdosedaily' ) ), $tdd_count );
	$tdd_sort = '<div class="tdd-sort"><span class="tdd-hide-m">' . esc_html__( 'Sort', 'techdosedaily' ) . '</span><a href="' . esc_url( tdd_search_url( array( 'sort' => 'relevance' ) ) ) . '"' . ( 'newest' !== $tdd_st['sort'] ? ' aria-current="true"' : '' ) . '>' . esc_html__( 'Relevance', 'techdosedaily' ) . '</a><a href="' . esc_url( tdd_search_url( array( 'sort' => 'newest' ) ) ) . '"' . ( 'newest' === $tdd_st['sort'] ? ' aria-current="true"' : '' ) . '>' . esc_html__( 'Newest', 'techdosedaily' ) . '</a></div>';
	// Desktop "<b>214 results</b> for “q”"; approved mobile "<b>214</b> results".
	$tdd_sum = '<div class="tdd-search-sum"><p role="status"><b class="tdd-hide-m">' . $tdd_desk . '</b><span class="tdd-hide-m"> ' . esc_html__( 'for', 'techdosedaily' ) . ' “' . esc_html( $tdd_term ) . '”</span><span class="tdd-only-m"><b>' . esc_html( $tdd_count ) . '</b>&nbsp;' . esc_html( _n( 'result', 'results', $tdd_total, 'techdosedaily' ) ) . '</span></p>' . ( $tdd_total ? $tdd_sort : '' ) . '</div>';
}

$tdd_body = '';
if ( $wp_query->posts ) {
	$tdd_rows = '';
	foreach ( $wp_query->posts as $tdd_p ) {
		$tdd_rows .= tdd_search_row( $tdd_p, $tdd_words );
	}
	$tdd_cur  = max( 1, (int) get_query_var( 'paged' ) );
	$tdd_from = ( $tdd_cur - 1 ) * TDD_SEARCH_PER_PAGE + 1;
	$tdd_to   = min( $tdd_total, $tdd_cur * TDD_SEARCH_PER_PAGE );
	/* translators: 1: first, 2: last, 3: total. */
	$tdd_note = esc_html( sprintf( __( 'Showing %1$s–%2$s of %3$s', 'techdosedaily' ), number_format_i18n( $tdd_from ), number_format_i18n( $tdd_to ), number_format_i18n( $tdd_total ) ) );
	$tdd_body = tdd_search_match( $tdd_term ) . '<ol class="tdd-results" data-tdd-feed>' . $tdd_rows . '</ol>'
		. tdd_pagination( array( 'moreLabel' => __( 'Load more results', 'techdosedaily' ), 'label' => __( 'Search result pages', 'techdosedaily' ), 'prev' => __( 'Previous', 'techdosedaily' ), 'next' => __( 'Next', 'techdosedaily' ), 'note' => $tdd_note, 'moreMobileOnly' => true ) );
} else {
	$tdd_body = tdd_search_empty( $tdd_term );
}
$tdd_has_filters = '' !== $tdd_term && $tdd_facets['total'] > 0;

echo '<section class="sp-head m-search-head"><h1 class="tdd-search-head__title"><span class="tdd-hide-m">' . esc_html__( 'Search Tech Dose Daily', 'techdosedaily' ) . '</span><span class="tdd-only-m">' . esc_html__( 'Search', 'techdosedaily' ) . '</span></h1>' . $tdd_field . $tdd_sum . ( $tdd_has_filters ? tdd_search_filterbar( $tdd_facets ) : '' ) . '</section>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	. '<div class="sp-g' . ( $tdd_has_filters ? '' : ' sp-g--nofilters' ) . '">' . ( $tdd_has_filters ? '<aside class="sp-filters" aria-label="' . esc_attr__( 'Filter results', 'techdosedaily' ) . '">' . tdd_search_filters( $tdd_facets ) . '</aside>' : '' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	. '<div class="sp-results">' . $tdd_body . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
