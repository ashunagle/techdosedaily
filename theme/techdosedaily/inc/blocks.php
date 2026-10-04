<?php
/**
 * Registers the theme's server-rendered blocks (blocks/<name>/block.json + render.php).
 * Each block outputs the exact markup of its approved design-system component.
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		// Editor UI for the content blocks editors place inside stories (takeaways, why, data table).
		wp_register_script( 'tdd-editor-blocks', TDD_URI . '/assets/js/editor-blocks.js', array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ), TDD_VERSION, true );
		foreach ( glob( TDD_DIR . '/blocks/*/block.json' ) as $block_json ) {
			register_block_type( dirname( $block_json ) );
		}
	}
);

add_filter(
	'block_categories_all',
	static function ( array $categories ): array {
		array_unshift( $categories, array( 'slug' => 'techdosedaily', 'title' => __( 'Tech Dose Daily', 'techdosedaily' ) ) );
		return $categories;
	}
);
