<?php
/**
 * tdd/more-in-section — mobile “More in <section>” list shown after the article (≤767 only;
 * on larger screens the same stories sit in the article sidebar). Omitted when empty.
 *
 * @package techdosedaily
 * @var array    $attributes
 * @var WP_Block $block
 */

defined( 'ABSPATH' ) || exit;

$tdd_post    = get_post( $block->context['postId'] ?? get_the_ID() );
$tdd_section = $tdd_post ? tdd_section( $tdd_post ) : null;
if ( ! $tdd_post || ! $tdd_section ) {
	return;
}
$tdd_posts = tdd_more_in_section( $tdd_post, max( 1, min( 6, (int) $attributes['count'] ) ) );
if ( ! $tdd_posts ) {
	return;
}
$tdd_rows = '';
foreach ( $tdd_posts as $tdd_p ) {
	$tdd_rows .= tdd_compact_row( $tdd_p );
}
/* translators: %s: section name. */
$tdd_title = sprintf( __( 'More in %s', 'techdosedaily' ), $tdd_section->name );
/* translators: %s: section name. */
$tdd_all = sprintf( __( 'All %s →', 'techdosedaily' ), $tdd_section->name );
echo '<section class="m-section tdd-only-m is-block" aria-label="' . esc_attr( $tdd_title ) . '"><div class="tdd-sh tdd-sh--small"><h2 class="tdd-sh__title">' . esc_html( $tdd_title ) . '</h2><a class="tdd-btn tdd-btn--text tdd-sh__more" href="' . esc_url( get_term_link( $tdd_section ) ) . '">' . esc_html( $tdd_all ) . '</a></div><div class="m-list m-list--after-sh">' . $tdd_rows . '</div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped helpers.
