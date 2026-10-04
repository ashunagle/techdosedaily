<?php
/**
 * Theme supports, image sizes, body class, head hygiene.
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function () {
		load_theme_textdomain( 'techdosedaily', TDD_DIR . '/languages' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
		add_theme_support( 'menus' ); // Appearance → Menus drives header/footer navigation.
		// Editor canvas looks like the article: tokens + generated component CSS + an editor-only layer.
		add_theme_support( 'editor-styles' );
		add_editor_style( array( 'assets/css/tokens.css', 'assets/css/core.css', 'assets/css/article.css', 'assets/css/static.css', 'assets/css/about-contact.css', 'assets/css/newsletter-404.css', 'assets/css/editor.css' ) );
		remove_theme_support( 'core-block-patterns' ); // Only TDD patterns are offered to editors.

		// Image sizes from the design system: 16:9 feature/cards/hero, 4:3 thumbs, 1:1 author, social.
		add_image_size( 'tdd-16x9-1800', 1800, 1013, true );
		add_image_size( 'tdd-16x9-1200', 1200, 675, true );
		add_image_size( 'tdd-16x9-800', 800, 450, true );
		add_image_size( 'tdd-16x9-400', 400, 225, true );
		add_image_size( 'tdd-4x3', 480, 360, true );
		add_image_size( 'tdd-1x1', 400, 400, true );
		add_image_size( 'tdd-social', 1200, 630, true );
	}
);

/** Every page is wrapped by the design-system root class. */
add_filter(
	'body_class',
	static function ( array $classes ): array {
		$classes[] = 'tdd';
		return $classes;
	}
);

/** Comments are off site-wide in V1. */
add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );

/** Remove head noise that costs bytes and adds nothing for readers. */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'rsd_link' );

/** Favicon from the brand set (until a Site Icon is uploaded in Settings). */
add_action(
	'wp_head',
	static function () {
		if ( ! has_site_icon() ) {
			echo '<link rel="icon" href="' . tdd_asset( 'images/tdd-favicon.svg' ) . '" type="image/svg+xml">' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in tdd_asset().
		}
	},
	2
);
