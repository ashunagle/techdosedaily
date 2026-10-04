<?php
/**
 * tdd/story-list — a list of StoryCards with page-level de-duplication.
 *
 * @package techdosedaily
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$tdd_posts = tdd_block_posts( $attributes, (int) $attributes['count'] );
if ( ! $tdd_posts ) {
	return;
}
$tdd_o = array();
if ( $attributes['heading'] ) {
	$tdd_o['heading'] = $attributes['heading'];
}
if ( 'compact' === $attributes['variant'] ) {
	$tdd_o['thumb'] = (bool) $attributes['thumbs'];
}
$tdd_html = '';
foreach ( $tdd_posts as $tdd_post ) {
	tdd_mark_shown( $tdd_post->ID );
	$tdd_html .= tdd_story_card( $tdd_post, $attributes['variant'], $tdd_o );
}
echo $attributes['stack'] ? '<div class="tdd-stack m-list">' . $tdd_html . '</div>' : $tdd_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
