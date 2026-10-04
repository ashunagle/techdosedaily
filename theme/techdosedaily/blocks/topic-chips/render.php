<?php
/**
 * tdd/topic-chips — "Follow a topic" with real counts (current section).
 *
 * @package techdosedaily
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$tdd_term = is_category() ? get_queried_object() : null;
if ( $tdd_term instanceof WP_Term && ( ! function_exists( 'tdd_core_section_module_on' ) || tdd_core_section_module_on( (int) $tdd_term->term_id, 'topics' ) ) ) {
	echo tdd_topic_chips( $tdd_term, ! empty( $attributes['mobile'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
}
