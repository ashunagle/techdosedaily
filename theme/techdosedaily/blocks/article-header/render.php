<?php
/**
 * tdd/article-header — ArticleHeader + hero for the current story (approved desktop .tdd-art
 * head with the approved mobile m-art-* values on the same elements).
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
$tdd_deck = tdd_has_core() ? tdd_core_deck( $tdd_post ) : '';

$tdd_head  = tdd_breadcrumbs_html();
$tdd_head .= tdd_labels( $tdd_post, array( 'wrap' => true ) );
$tdd_head .= '<h1 class="tdd-art__h1 m-art-h1">' . esc_html( get_the_title( $tdd_post ) ) . '</h1>';
$tdd_head .= '' !== $tdd_deck ? '<p class="tdd-art__deck m-art-deck">' . esc_html( $tdd_deck ) . '</p>' : '';
$tdd_head .= tdd_byline( $tdd_post );
$tdd_head .= tdd_article_times( $tdd_post );
$tdd_head .= tdd_share( 'mobile' );

echo '<div class="tdd-art tdd-art--top"><header class="tdd-art__head m-art-head">' . $tdd_head . '</header>' . ( $attributes['hero'] ? tdd_article_hero( $tdd_post ) : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped helpers.
