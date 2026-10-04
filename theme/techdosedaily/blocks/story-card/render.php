<?php
/**
 * tdd/story-card — one StoryCard.
 *
 * @package techdosedaily
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$tdd_post = tdd_block_posts( $attributes, 1 )[0] ?? null;
if ( ! $tdd_post ) {
	return;
}
tdd_mark_shown( $tdd_post->ID );
$tdd_o = $attributes['heading'] ? array( 'heading' => $attributes['heading'] ) : array();
echo tdd_story_card( $tdd_post, $attributes['variant'], $tdd_o ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
