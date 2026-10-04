<?php
/**
 * Front-end loading behaviour (Phase 7). Presentation only; no data.
 *
 *  - Only the likely above-the-fold hero is eager + fetchpriority=high. Theme templates set that
 *    explicitly (homepage lead, section lead, article hero); everything else is lazy.
 *  - Images and iframes inside story/page content are always lazy: they sit below the headline,
 *    deck and byline (and below the hero when there is one).
 *  - Content images get `sizes` that match the 740px reading column instead of WP's generic
 *    "(max-width: 1024px) 100vw, 1024px", so phones and desktops download the right srcset entry.
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

/** Reading-column `sizes` (container 740, 20px mobile padding); wide/full-aligned media span the viewport. */
function tdd_content_sizes( string $tag ): string {
	if ( preg_match( '/\balign(?:wide|full)\b/', $tag ) ) {
		return '100vw';
	}
	return '(max-width: 767px) calc(100vw - 40px), 740px';
}

add_filter(
	'wp_get_loading_optimization_attributes',
	static function ( $attrs, $tag_name, $attr, $context ) {
		if ( ! in_array( $context, array( 'the_content', 'do_shortcode', 'widget_block_content' ), true ) ) {
			return $attrs; // Theme-rendered images pass explicit attributes.
		}
		$attrs = is_array( $attrs ) ? $attrs : array();
		unset( $attrs['fetchpriority'] );
		$attrs['loading'] = 'lazy';
		if ( 'img' === $tag_name ) {
			$attrs['decoding'] = 'async';
		}
		return $attrs;
	},
	10,
	4
);

add_filter(
	'wp_content_img_tag',
	static function ( $html, $context ) {
		if ( 'the_content' !== $context || ! str_contains( $html, ' srcset=' ) ) {
			return $html;
		}
		$sizes = tdd_content_sizes( $html );
		return preg_match( '/ sizes="[^"]*"/', $html )
			? preg_replace( '/ sizes="(?:auto, )?[^"]*"/', ' sizes="auto, ' . esc_attr( $sizes ) . '"', $html, 1 )
			: str_replace( ' srcset=', ' sizes="auto, ' . esc_attr( $sizes ) . '" srcset=', $html );
	},
	20,
	2
);

/**
 * Every theme script/stylesheet URL (including block.json viewScript/style, which otherwise carry
 * WordPress's version):
 *  - points at the minified sibling (x.min.css / x.min.js, built by tools/minify.mjs) when one
 *    exists, unless SCRIPT_DEBUG is on;
 *  - gets a file-modification version, so long-lived static caching can never serve an outdated
 *    file after a deploy.
 */
function tdd_versioned_src( $src ) {
	if ( ! is_string( $src ) || ! str_starts_with( $src, TDD_URI . '/' ) ) {
		return $src;
	}
	$rel = (string) strtok( substr( $src, strlen( TDD_URI ) + 1 ), '?' );
	// The .min file is used only when it is at least as new as its source, so an edited source whose
	// .min was not rebuilt yet is served as-is instead of a stale minified copy.
	if ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) && preg_match( '/^(assets\/(?:css|js)\/[\w-]+)\.(css|js)$/', $rel, $m ) ) {
		$min = TDD_DIR . "/{$m[1]}.min.{$m[2]}";
		if ( is_readable( $min ) && (int) @filemtime( $min ) >= (int) @filemtime( TDD_DIR . '/' . $rel ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$rel = "{$m[1]}.min.{$m[2]}";
		}
	}
	return add_query_arg( 'ver', tdd_asset_ver( $rel ), TDD_URI . '/' . $rel );
}
add_filter( 'script_loader_src', 'tdd_versioned_src', 20 );
add_filter( 'style_loader_src', 'tdd_versioned_src', 20 );

/**
 * Search: the mobile filter panel becomes a bottom sheet once search.js runs. Setting the class
 * before first paint (instead of after the deferred script) stops the filters from rendering
 * in-flow and then disappearing (CLS 0.57 on mobile before Phase 7). Without JS nothing changes.
 */
add_action(
	'wp_head',
	static function () {
		if ( is_search() ) {
			wp_print_inline_script_tag( "document.documentElement.classList.add('tdd-js-search');" );
		}
	},
	2
);

/**
 * Phase 8: blocks that print the current post's content (story body, static page, contact page)
 * must never run again inside that content — a story whose body contains one of them would render
 * itself forever and exhaust memory (a denial of service any author could trigger). A nested copy
 * renders nothing.
 */
function tdd_content_renderer_blocks(): array {
	return array( 'tdd/article-body', 'tdd/static-page', 'tdd/contact-page' );
}
add_filter(
	'pre_render_block',
	static function ( $pre, array $block ) {
		global $tdd_rendering_content;
		$name = $block['blockName'] ?? '';
		if ( null !== $pre || ! in_array( $name, tdd_content_renderer_blocks(), true ) ) {
			return $pre;
		}
		$tdd_rendering_content = (array) $tdd_rendering_content;
		if ( ! empty( $tdd_rendering_content ) ) {
			return ''; // Already inside a post body: never nest.
		}
		$tdd_rendering_content[] = $name;
		return $pre;
	},
	10,
	2
);
add_filter(
	'render_block',
	static function ( $html, array $block ) {
		global $tdd_rendering_content;
		if ( in_array( $block['blockName'] ?? '', tdd_content_renderer_blocks(), true ) && ! empty( $tdd_rendering_content ) && end( $tdd_rendering_content ) === $block['blockName'] ) {
			array_pop( $tdd_rendering_content );
		}
		return $html;
	},
	10,
	2
);
