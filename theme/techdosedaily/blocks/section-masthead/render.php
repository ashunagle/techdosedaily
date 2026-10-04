<?php
/**
 * tdd/section-masthead — CategoryMasthead (+ TopicNav on sections). One DOM: desktop .tdd-cathead
 * with the approved mobile .m-cathead values on the same elements.
 * Counts and times are real; the description is the term description (omitted when empty).
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

$tdd_term = get_queried_object();
if ( is_home() && ! is_front_page() ) {
	// The chronological "Latest" listing (Posts page): masthead with the page title and real freshness.
	$tdd_newest = get_posts( array( 'post_type' => 'post', 'posts_per_page' => 1, 'no_found_rows' => true ) );
	$tdd_fresh  = '';
	if ( $tdd_newest ) {
		$tdd_ts    = (int) get_post_timestamp( $tdd_newest[0] );
		$tdd_fresh = '<div class="tdd-cathead__aside m-cathead__meta"><p class="tdd-fresh"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>' . esc_html__( 'Updated', 'techdosedaily' ) . ' <b>' . tdd_time( $tdd_ts, wp_date( wp_date( 'Y-m-d', $tdd_ts ) === wp_date( 'Y-m-d' ) ? 'g:i A T' : 'M j, g:i A T', $tdd_ts ) ) . '</b></p></div>';
	}
	echo '<section class="tdd-cathead m-cathead" aria-labelledby="tdd-cathead-title"><div>' . tdd_breadcrumbs_html() . '<h1 class="tdd-cathead__title m-cathead__title" id="tdd-cathead-title">' . esc_html( get_the_title( (int) get_option( 'page_for_posts' ) ) ) . '</h1></div>' . $tdd_fresh . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}
if ( ! $tdd_term instanceof WP_Term ) {
	return;
}
$tdd_is_section = 'category' === $tdd_term->taxonomy;
$tdd_desc       = trim( wp_strip_all_tags( term_description( $tdd_term ) ) );
$tdd_mark       = '<svg viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="6" fill="var(--primary)"/><rect x="6" y="12" width="4" height="8" rx="2" fill="var(--on-primary)"/><rect x="12" y="7" width="4" height="18" rx="2" fill="var(--on-primary)"/><rect x="18" y="10" width="4" height="12" rx="2" fill="var(--on-primary)"/><rect x="24" y="14" width="4" height="4" rx="2" fill="var(--on-primary)"/></svg>';

// Freshness = newest story in this archive.
$tdd_newest = get_posts( array( 'post_type' => 'post', 'posts_per_page' => 1, 'no_found_rows' => true, 'tax_query' => array( array( 'taxonomy' => $tdd_term->taxonomy, 'terms' => $tdd_term->term_id ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
$tdd_aside  = '';
if ( $tdd_newest ) {
	$tdd_ts     = (int) get_post_timestamp( $tdd_newest[0] );
	$tdd_label  = wp_date( 'Y-m-d', $tdd_ts ) === wp_date( 'Y-m-d' ) ? wp_date( 'g:i A T', $tdd_ts ) : wp_date( 'M j, g:i A T', $tdd_ts );
	$tdd_aside .= '<p class="tdd-fresh"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>' . esc_html__( 'Updated', 'techdosedaily' ) . ' <b>' . tdd_time( $tdd_ts, $tdd_label ) . '</b></p>';
}
if ( $tdd_is_section && function_exists( 'tdd_core_section_count_since' ) ) {
	$tdd_month = tdd_core_section_count_since( $tdd_term->term_id, (int) ( new DateTimeImmutable( 'first day of this month midnight', wp_timezone() ) )->getTimestamp() );
	$tdd_today = tdd_core_section_count_since( $tdd_term->term_id, (int) ( new DateTimeImmutable( 'today', wp_timezone() ) )->getTimestamp() );
	if ( $tdd_month > 0 ) {
		/* translators: %s: number of stories. */
		$tdd_aside .= '<span class="tdd-cathead__stat"><b>' . esc_html( sprintf( _n( '%s story', '%s stories', $tdd_month, 'techdosedaily' ), number_format_i18n( $tdd_month ) ) ) . '</b> ' . esc_html__( 'this month', 'techdosedaily' )
			/* translators: %s: number of stories. */
			. ( $tdd_today > 0 ? '<span class="tdd-hide-m"> · ' . esc_html( sprintf( __( '%s today', 'techdosedaily' ), number_format_i18n( $tdd_today ) ) ) . '</span>' : '' ) . '</span>';
	}
}
$tdd_nl = tdd_page_url( 'newsletter' );
if ( $tdd_nl ) {
	/* translators: %s: section name. */
	$tdd_aside .= '<a class="tdd-btn tdd-btn--text" href="' . esc_url( $tdd_nl ) . '">' . esc_html( sprintf( __( 'Get %s news by email →', 'techdosedaily' ), $tdd_term->name ) ) . '</a>';
}

$tdd_html = '<section class="tdd-cathead m-cathead" aria-labelledby="tdd-cathead-title"><div>' . tdd_breadcrumbs_html()
	. '<h1 class="tdd-cathead__title m-cathead__title" id="tdd-cathead-title">' . ( $tdd_is_section ? $tdd_mark : '' ) . esc_html( $tdd_term->name ) . '</h1>'
	. ( '' !== $tdd_desc ? '<p class="tdd-cathead__desc m-cathead__desc">' . esc_html( $tdd_desc ) . '</p>' : '' ) . '</div>'
	. ( '' !== $tdd_aside ? '<div class="tdd-cathead__aside m-cathead__meta">' . $tdd_aside . '</div>' : '' ) . '</section>';

if ( $tdd_is_section ) {
	$tdd_topics = tdd_section_nav_topics( $tdd_term );
	if ( $tdd_topics ) {
		/* translators: %s: section name. */
		$tdd_links = '<a href="' . esc_url( get_term_link( $tdd_term ) ) . '" aria-current="page">' . esc_html( sprintf( __( 'All %s', 'techdosedaily' ), $tdd_term->name ) ) . '</a>';
		foreach ( $tdd_topics as $tdd_t ) {
			$tdd_links .= '<a href="' . esc_url( get_term_link( $tdd_t ) ) . '">' . esc_html( $tdd_t->name ) . '</a>';
		}
		/* translators: %s: section name. */
		$tdd_html .= '<nav class="tdd-topicnav m-topicnav" aria-label="' . esc_attr( sprintf( __( '%s topics', 'techdosedaily' ), $tdd_term->name ) ) . '">' . $tdd_links . '</nav>';
	}
}
echo $tdd_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
