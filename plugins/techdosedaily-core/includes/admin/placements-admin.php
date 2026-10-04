<?php
/**
 * Placement editor (Tech Dose Daily → Placements): every homepage, section and Daily Tech Brief
 * slot with its live story, what takes over when it ends, scheduled entries, and — when nothing is
 * placed — which story the automatic fallback is showing. Talks to /tdd/v1/placements*.
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

function tdd_core_placements_page(): void {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage placements.', 'techdosedaily-core' ) );
	}
	echo '<div class="wrap tdd-board-wrap"><h1>' . esc_html__( 'Placements', 'techdosedaily-core' ) . '</h1>'
		. '<p class="tdd-intro">' . esc_html__( 'Choose the stories for the homepage, each section page and the Daily Tech Brief. A slot with no story placed fills itself with the newest eligible story, so the site is never empty. When a placed story ends, the one it replaced comes back.', 'techdosedaily-core' ) . '</p>'
		. '<div id="tdd-board"><p>' . esc_html__( 'Loading…', 'techdosedaily-core' ) . '</p></div>'
		. '<noscript><p>' . esc_html__( 'The placement editor needs JavaScript.', 'techdosedaily-core' ) . '</p></noscript></div>';
}

add_action(
	'admin_enqueue_scripts',
	static function ( string $hook ) {
		if ( 'toplevel_page_tdd-placements' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'tdd-placements', tdd_core_asset( 'admin/placements.js' ), array( 'wp-element', 'wp-api-fetch', 'wp-date', 'wp-i18n', 'wp-url' ), tdd_core_asset_ver( 'admin/placements.js' ), true );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab preselect.
		$section = isset( $_GET['section'] ) ? absint( $_GET['section'] ) : 0;
		wp_localize_script(
			'tdd-placements',
			'tddBoard',
			array(
				'sections'    => tdd_core_section_options(),
				'section'     => $section,
				'tab'         => $section ? 'section' : 'home',
				'timezone'    => wp_timezone_string(),
				'homeUrl'     => home_url( '/' ),
			)
		);
	}
);
