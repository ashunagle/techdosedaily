<?php
/**
 * tdd/author-archive — "Latest from <author>": main query rows + pagination, or the empty state.
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

global $wp_query;
$tdd_user = tdd_author_user();
if ( ! $tdd_user ) {
	return;
}
$tdd_first = tdd_first_name( $tdd_user );
$tdd_rest  = trim( substr( $tdd_user->display_name, strlen( $tdd_first ) ) );
$tdd_count = tdd_author_story_count( $tdd_user->ID );
/* translators: %s: author first name (full name on desktop). */
$tdd_title = sprintf( esc_html__( 'Latest from %s', 'techdosedaily' ), esc_html( $tdd_first ) . ( '' !== $tdd_rest ? '<span class="tdd-hide-m"> ' . esc_html( $tdd_rest ) . '</span>' : '' ) );
/* translators: %s: number of stories. */
$tdd_desc = $tdd_count ? sprintf( _n( '%s story · newest first', '%s stories · newest first', $tdd_count, 'techdosedaily' ), number_format_i18n( $tdd_count ) ) : __( 'New to Tech Dose Daily', 'techdosedaily' );
$tdd_rss  = $tdd_count ? '<a class="tdd-btn tdd-btn--text tdd-sh__more tdd-hide-m" href="' . esc_url( get_author_feed_link( $tdd_user->ID ) ) . '">' . esc_html__( 'RSS', 'techdosedaily' ) . '</a>' : '';
$tdd_body = '';
if ( $wp_query->posts ) {
	foreach ( $wp_query->posts as $tdd_p ) {
		$tdd_body .= tdd_author_row( $tdd_p );
	}
	/* translators: %s: author name. */
	$tdd_body = '<ol class="tdd-aarchive" data-tdd-feed>' . $tdd_body . '</ol>' . str_replace( 'aria-label="Pages"', 'aria-label="' . esc_attr__( 'Author article pages', 'techdosedaily' ) . '"', tdd_pagination( array( 'moreLabel' => __( 'Load more articles', 'techdosedaily' ), 'label' => __( 'Author article pages', 'techdosedaily' ), 'noteCount' => false ) ) );
} else {
	$tdd_body = tdd_author_empty( $tdd_user );
}
echo '<div class="tdd-sh"><div><h2 class="tdd-sh__title">' . $tdd_title . '</h2><p class="tdd-sh__desc">' . esc_html( $tdd_desc ) . '</p></div>' . $tdd_rss . '</div>' . $tdd_body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above / in helpers.
