<?php
/**
 * tdd/section-feed — the archive's main query as the approved time-led feed:
 * heading + freshness + RSS, FeedDateGroups (Today / Yesterday / weekday), rows, pagination.
 * Pinned stories are excluded from the main query (inc/archive.php).
 *
 * @package techdosedaily
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

global $wp_query;
$tdd_term  = get_queried_object();
$tdd_name  = $tdd_term instanceof WP_Term ? $tdd_term->name : '';
$tdd_posts = $wp_query->posts;
if ( ! $tdd_posts ) {
	echo '<p class="tdd-feed-empty">' . esc_html__( 'No stories here yet.', 'techdosedaily' ) . '</p>';
	return;
}
/* translators: %s: section name. */
$tdd_title = '' !== $attributes['title'] ? $attributes['title'] : ( is_home() ? __( 'All stories', 'techdosedaily' ) : ( $tdd_name ? sprintf( __( 'Latest %s News', 'techdosedaily' ), $tdd_name ) : __( 'Latest', 'techdosedaily' ) ) );
$tdd_ts    = (int) get_post_timestamp( $tdd_posts[0] );
$tdd_icon  = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
/* translators: %s: section name. */
$tdd_every = $tdd_name ? sprintf( __( 'Every %s story, newest first', 'techdosedaily' ), $tdd_name ) : __( 'Newest first', 'techdosedaily' );
// Desktop "Every AI story, newest first · Last update 10:42 AM IST"; approved mobile "Newest first · 10:42 AM IST".
$tdd_fresh = '<p class="tdd-fresh tdd-feed-fresh">' . $tdd_icon . '<span class="tdd-hide-m">' . esc_html( $tdd_every ) . '</span><span class="tdd-only-m">' . esc_html__( 'Newest first', 'techdosedaily' ) . '</span> · <b><span class="tdd-hide-m">' . esc_html__( 'Last update', 'techdosedaily' ) . ' </span>' . esc_html( wp_date( wp_date( 'Y-m-d', $tdd_ts ) === wp_date( 'Y-m-d' ) ? 'g:i A T' : 'M j, g:i A T', $tdd_ts ) ) . '</b></p>';
$tdd_rss   = $tdd_term instanceof WP_Term ? '<a class="tdd-btn tdd-btn--text tdd-sh__more tdd-hide-m" href="' . esc_url( get_term_feed_link( $tdd_term->term_id, $tdd_term->taxonomy ) ) . '">' . esc_html__( 'RSS', 'techdosedaily' ) . '</a>' : '';
$tdd_lbl   = is_category() ? array( 'topic' => true, 'breaking' => false, 'wrap' => true, 'tag' => 'span' ) : array( 'breaking' => false, 'wrap' => true, 'tag' => 'span' );

echo '<div class="tdd-sh"><div><h2 class="tdd-sh__title">' . esc_html( $tdd_title ) . '</h2>' . $tdd_fresh . '</div>' . $tdd_rss . '</div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	. '<div class="tdd-feed" data-tdd-feed>' . tdd_feed_groups( $tdd_posts, $tdd_lbl ) . '</div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	. tdd_pagination( array( 'moreLabel' => '', 'feed' => '[data-tdd-feed]' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
