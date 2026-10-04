<?php
/**
 * Menu locations. Editors manage these in Appearance → Menus.
 * Each location falls back to sensible defaults built from real site data.
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function () {
		register_nav_menus(
			array(
				'primary'           => __( 'Header sections', 'techdosedaily' ),
				'footer-categories' => __( 'Footer: Categories', 'techdosedaily' ),
				'footer-company'    => __( 'Footer: Company', 'techdosedaily' ),
				'footer-policies'   => __( 'Footer: Policies', 'techdosedaily' ),
				'footer-newsletter' => __( 'Footer: Newsletter', 'techdosedaily' ),
				'footer-follow'     => __( 'Footer: Follow (real accounts only)', 'techdosedaily' ),
				'footer-legal'      => __( 'Footer: Bottom row', 'techdosedaily' ),
				'drawer-more'       => __( 'Menu drawer: Latest / Daily Tech Brief / Newsletter', 'techdosedaily' ),
				'drawer-about'      => __( 'Menu drawer: About', 'techdosedaily' ),
				'trending'          => __( 'Trending bar (homepage)', 'techdosedaily' ),
			)
		);
	}
);

/**
 * Flat list of [label, url, current] for a menu location.
 *
 * @param string $location Menu location.
 * @return array<int, array{0:string,1:string,2:bool}>
 */
function tdd_menu_items( string $location ): array {
	$locations = get_nav_menu_locations();
	$items     = array();
	if ( ! empty( $locations[ $location ] ) ) {
		$menu_items = wp_get_nav_menu_items( $locations[ $location ] );
		foreach ( (array) $menu_items as $item ) {
			if ( (int) $item->menu_item_parent ) {
				continue; // Navigation is single-level by design.
			}
			$items[] = array( $item->title, $item->url, tdd_is_current_url( $item->url, $item ) );
		}
		return $items;
	}
	return tdd_menu_fallback( $location );
}

/** True when a menu link points at the current section or page. */
function tdd_is_current_url( string $url, $item = null ): bool {
	if ( $item && 'taxonomy' === $item->type && 'category' === $item->object ) {
		if ( is_category( (int) $item->object_id ) ) {
			return true;
		}
		if ( is_singular( 'post' ) ) {
			$primary = tdd_section();
			return $primary ? (int) $primary->term_id === (int) $item->object_id : has_category( (int) $item->object_id );
		}
		return false;
	}
	return untrailingslashit( $url ) === untrailingslashit( home_url( add_query_arg( array() ) ) );
}

/** Defaults used until menus are assigned. Uses real categories/pages only. */
function tdd_menu_fallback( string $location ): array {
	$sections = array( 'AI', 'Tech', 'Software', 'Cybersecurity', 'Startups', 'Big Tech', 'Cloud', 'Developer', 'Guides' );
	$by_name  = static function ( array $names ): array {
		$out = array();
		foreach ( $names as $name ) {
			$term = get_term_by( 'name', $name, 'category' );
			if ( $term ) {
				$out[] = array( $term->name, get_term_link( $term ), is_category( $term->term_id ) );
			}
		}
		return $out;
	};
	$pages    = static function ( array $map ): array {
		$out = array();
		foreach ( $map as $slug => $label ) {
			$page = get_page_by_path( $slug );
			if ( $page ) {
				$out[] = array( $label, get_permalink( $page ), is_page( $page->ID ) );
			}
		}
		return $out;
	};
	switch ( $location ) {
		case 'primary':
		case 'footer-categories':
			return $by_name( $sections );
		case 'footer-company':
			return $pages( array( 'about' => 'About', 'editorial-standards' => 'Editorial standards', 'contact' => 'Contact', 'careers' => 'Careers' ) );
		case 'footer-policies':
			return $pages( array( 'privacy-policy' => 'Privacy', 'terms' => 'Terms', 'source-policy' => 'Source Policy', 'corrections-policy' => 'Corrections', 'ai-use-policy' => 'AI use policy' ) );
		case 'footer-newsletter':
			$news = $pages( array( 'newsletter' => 'Daily Tech Brief' ) );
			if ( $news ) {
				$news[] = array( 'Subscribe →', $news[0][1], false );
			}
			return $news;
		case 'drawer-more':
			$more = array();
			if ( get_option( 'page_for_posts' ) ) {
				$more[] = array( __( 'Latest', 'techdosedaily' ), tdd_latest_url(), is_home() );
			}
			return array_merge( $more, $pages( array( 'daily-tech-brief' => 'Daily Tech Brief', 'newsletter' => 'Newsletter' ) ) );
		case 'drawer-about':
			return $pages( array( 'about' => 'About' ) );
		case 'footer-legal':
			return $pages( array( 'privacy-policy' => 'Privacy', 'terms' => 'Terms', 'source-policy' => 'Source Policy' ) );
		default:
			return array(); // Follow: real accounts only — nothing until a menu is set.
	}
}

/** Renders a flat list of links. */
function tdd_links( array $items, string $wrap = 'li' ): string {
	$html = '';
	foreach ( $items as [ $label, $url, $current ] ) {
		$link  = '<a href="' . esc_url( $url ) . '"' . ( $current ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		$html .= $wrap ? "<{$wrap}>{$link}</{$wrap}>" : $link;
	}
	return $html;
}
