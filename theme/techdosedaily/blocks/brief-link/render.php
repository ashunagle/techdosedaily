<?php
/**
 * tdd/brief-link — sidebar Daily Tech Brief link (only with real items and a newsletter page).
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

$tdd_term = is_category() ? get_queried_object() : null;
if ( $tdd_term instanceof WP_Term && function_exists( 'tdd_core_section_module_on' ) && ! tdd_core_section_module_on( (int) $tdd_term->term_id, 'brief' ) ) {
	return; // Turned off in the section settings.
}
echo tdd_side_brief(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
