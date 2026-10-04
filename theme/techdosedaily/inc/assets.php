<?php
/**
 * Stylesheet and script loading.
 *
 * Every page loads tokens + core + layout. Each template adds only the generated
 * stylesheets it needs (see tools/build-css.mjs). Nothing else is enqueued on the front end.
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cache-busting version for a theme file: changes whenever the file changes, so static assets can be
 * served with a one-year immutable Cache-Control without ever going stale after a deploy.
 */
function tdd_asset_ver( string $rel ): string {
	$t = @filemtime( TDD_DIR . '/' . $rel ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- missing file → theme version.
	return $t ? TDD_VERSION . '.' . $t : TDD_VERSION;
}

/** Template-specific stylesheet sets (handles from assets/css/*.css). */
function tdd_style_set(): array {
	if ( is_404() ) {
		return array( 'search', 'about-contact', 'newsletter-404' );
	}
	if ( is_search() ) {
		return array( 'search', 'archive' );
	}
	if ( is_author() ) {
		return array( 'author', 'archive' );
	}
	if ( is_front_page() ) {
		return array( 'archive' ); // Homepage (static front page or posts): homepage rules live in core.css.
	}
	if ( is_singular( 'post' ) ) {
		return array( 'article' );
	}
	if ( is_page() ) {
		$slug = get_page_template_slug();
		switch ( $slug ) {
			case 'page-about':
			case 'page-contact':
				return array( 'article', 'static', 'about-contact' );
			case 'page-newsletter':
				return array( 'article', 'static', 'about-contact', 'newsletter-404' );
			default:
				return array( 'article', 'static' );
		}
	}
	return array( 'archive' ); // category, tag, topic, date fallbacks.
}

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'tdd-tokens', TDD_URI . '/assets/css/tokens.css', array(), tdd_asset_ver( 'assets/css/tokens.css' ) );
		wp_enqueue_style( 'tdd-core', TDD_URI . '/assets/css/core.css', array( 'tdd-tokens' ), tdd_asset_ver( 'assets/css/core.css' ) );
		$deps = array( 'tdd-core' );
		foreach ( tdd_style_set() as $handle ) {
			wp_enqueue_style( "tdd-{$handle}", TDD_URI . "/assets/css/{$handle}.css", $deps, tdd_asset_ver( "assets/css/{$handle}.css" ) );
			$deps[] = "tdd-{$handle}";
		}
		// Hand-written layout layer: containers, responsive header/footer, drawer. Loaded last.
		wp_enqueue_style( 'tdd-layout', TDD_URI . '/assets/css/layout.css', $deps, tdd_asset_ver( 'assets/css/layout.css' ) );

		wp_enqueue_script( 'tdd-header', TDD_URI . '/assets/js/header.js', array(), tdd_asset_ver( 'assets/js/header.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );

		// Most Read: cookieless view ping on published article pages (counts stored by TechDoseDaily Core).
		// Never on previews or for editorial users (Core re-checks both server-side).
		if ( is_singular( 'post' ) && ! is_preview() && 'publish' === get_post_status() && ! current_user_can( 'edit_posts' ) && function_exists( 'tdd_core_record_view' ) ) {
			wp_enqueue_script( 'tdd-view', TDD_URI . '/assets/js/view-beacon.js', array(), tdd_asset_ver( 'assets/js/view-beacon.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
			add_action(
				'wp_footer',
				static function () {
					printf( '<span hidden data-tdd-view="%d" data-endpoint="%s"></span>', (int) get_queried_object_id(), esc_url( rest_url( 'tdd/v1/view' ) ) );
				}
			);
		}

		// Core block CSS we don't use on the front end.
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'classic-theme-styles' );
	},
	20
);

/** Load only the core block styles actually used on the page. */
add_filter( 'should_load_separate_core_block_assets', '__return_true' );

/** Preload the two fonts used above the fold on every template. */
add_action(
	'wp_head',
	static function () {
		foreach ( array( 'Manrope-800', 'Inter-400' ) as $font ) {
			printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( TDD_URI . "/assets/fonts/{$font}.woff2" ) );
		}
	},
	1
);
