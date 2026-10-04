<?php
/**
 * Homepage modules (approved HomepageDesktop v1.1 + HomepageMobile v1).
 * Each module resolves its stories once per request (placements → automatic fallback, never a
 * hole, never a story twice on the page) and prints the desktop markup plus, where the approved
 * mobile structure differs, a mobile variant from the same stories.
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve a placement once per request, excluding stories already on the page, and mark them shown.
 *
 * @return WP_Post[]
 */
function tdd_home_resolve( string $placement, int $limit, array $args = array() ): array {
	static $cache = array();
	$key = $placement . ':' . $limit . ':' . wp_json_encode( $args );
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	if ( function_exists( 'tdd_core_placement_resolve' ) ) {
		$ids = tdd_core_placement_resolve( $placement, $limit, $args + array( 'exclude' => tdd_shown() ) );
	} else {
		$ids = get_posts( array( 'post_type' => 'post', 'posts_per_page' => $limit, 'post__not_in' => tdd_shown(), 'fields' => 'ids', 'no_found_rows' => true ) );
	}
	$posts = array_values( array_filter( array_map( 'get_post', $ids ) ) );
	foreach ( $posts as $p ) {
		tdd_mark_shown( $p->ID );
	}
	$cache[ $key ] = $posts;
	return $posts;
}

/** Newest stories matching a query, once per request, excluding stories shown above. */
function tdd_home_query( string $key, array $q ): array {
	static $cache = array();
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$posts = get_posts(
		$q + array(
			'post_type'           => 'post',
			'post__not_in'        => tdd_shown(),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
	foreach ( $posts as $p ) {
		tdd_mark_shown( $p->ID );
	}
	$cache[ $key ] = $posts;
	return $posts;
}

/** Card meta: news is time-led; other story types add the read time. */
function tdd_home_meta( WP_Post $p ): array {
	$f = tdd_format( $p );
	return ( ! $f || 'news' === $f->slug ) ? array( 'ago' ) : array( 'ago', 'read' );
}

/* ---------- Hero ---------- */

function tdd_home_hero(): string {
	$lead = tdd_home_resolve( 'homepage_lead', 1 );
	$rail = tdd_home_resolve( 'homepage_secondary', 4 );
	if ( ! $lead ) {
		return '';
	}
	$feature = tdd_story_card( $lead[0], 'feature', array( 'heading' => 'h1', 'eager' => true, 'size' => 'tdd-16x9-1200', 'sizes' => '(max-width: 767px) 100vw, 840px' ) );
	$feature = str_replace( 'class="tdd-story tdd-story--feature m-lead"', 'class="hp-hero__main tdd-story tdd-story--feature m-lead"', $feature );
	$desk    = '';
	$mob     = '';
	// Rail labels (approved): "Breaking · Section" when breaking, otherwise one label (story type, else section).
	$lbl = static fn( WP_Post $p ) => tdd_is_breaking( $p ) ? array( 'format' => false ) : array( 'one' => true );
	foreach ( $rail as $i => $p ) {
		$desk .= 0 === $i
			? tdd_story_card( $p, 'medium', array( 'heading' => 'h2', 'meta' => tdd_home_meta( $p ), 'sizes' => '(max-width: 1199px) 33vw, 400px', 'labels' => $lbl( $p ) + array( 'wrap' => true ) ) )
			: tdd_story_card( $p, 'compact', array( 'thumb' => true, 'meta' => tdd_home_meta( $p ), 'size' => 'tdd-4x3', 'sizes' => '96px', 'labels' => $lbl( $p ) ) );
		if ( $i < 3 ) {
			$mob .= tdd_story_card( $p, 'compact', array( 'heading' => 0 === $i ? 'h2' : 'h3', 'thumb' => true, 'meta' => tdd_home_meta( $p ), 'size' => 'tdd-4x3', 'sizes' => '96px', 'labels' => $lbl( $p ) ) );
		}
	}
	return '<section class="tdd-grid hp-airy hp-hero" aria-label="' . esc_attr__( 'Top stories', 'techdosedaily' ) . '">' . $feature
		. ( $desk ? '<aside class="hp-rail tdd-hide-m" aria-label="' . esc_attr__( 'More top stories', 'techdosedaily' ) . '">' . $desk . '</aside>' : '' ) . '</section>'
		. ( $mob ? '<section class="m-list hp-toplist tdd-only-m is-block" aria-label="' . esc_attr__( 'More top stories', 'techdosedaily' ) . '">' . $mob . '</section>' : '' );
}

/* ---------- AI News Today ---------- */

function tdd_home_ai_band(): string {
	$ai = get_term_by( 'slug', 'ai', 'category' );
	if ( ! $ai ) {
		return '';
	}
	$lead = tdd_home_resolve( 'ai_band_lead', 1, array( 'fallback_section' => $ai->term_id ) );
	$rest = tdd_home_resolve( 'ai_band_secondary', 5, array( 'fallback_section' => $ai->term_id ) );
	if ( ! $lead ) {
		return '';
	}
	$l     = $lead[0];
	$deck  = tdd_deck( $l );
	$large = '<article class="hp-col7 tdd-story tdd-story--large m-ai-lead">' . tdd_media( $l, 'tdd-16x9-1200', '(max-width: 767px) 100vw, 720px' ) . tdd_labels( $l, array( 'wrap' => true ) )
		. '<h3 class="tdd-story__title"><a href="' . esc_url( get_permalink( $l ) ) . '">' . esc_html( get_the_title( $l ) ) . '</a></h3>'
		. ( '' !== $deck ? '<p class="tdd-story__summary tdd-hide-m">' . esc_html( $deck ) . '</p>' : '' )
		. tdd_meta( $l, array( 'author', 'date', 'read' ) ) . '</article>';
	$list  = '';
	foreach ( $rest as $i => $p ) {
		// Approved mobile shows three supporting stories.
		$card  = tdd_story_card( $p, 'compact', array( 'thumb' => true, 'meta' => array( 'ago' ), 'size' => 'tdd-4x3', 'sizes' => '96px', 'labels' => array( 'one' => true, 'topic' => true ) ) );
		$list .= $i >= 3 ? str_replace( 'class="tdd-story ', 'class="tdd-hide-m tdd-story ', $card ) : $card;
	}
	$all = get_term_link( $ai );
	return '<section class="tdd-ai-band tdd-section hp-ai m-section" aria-labelledby="hp-ai-title"><div class="tdd-container">'
		. '<div class="tdd-sh"><div><div class="tdd-ai-badge">' . tdd_icon( 'pulse', '' ) . esc_html__( 'Our core beat', 'techdosedaily' ) . '</div><h2 class="tdd-sh__title" id="hp-ai-title">' . esc_html__( 'AI News Today', 'techdosedaily' ) . '</h2><p class="tdd-sh__desc tdd-hide-m">' . esc_html__( 'Models, tools, research and policy — updated through the day', 'techdosedaily' ) . '</p></div>'
		. '<a class="tdd-btn tdd-btn--text tdd-sh__more" href="' . esc_url( $all ) . '"><span class="tdd-hide-m">' . esc_html__( 'View all AI news →', 'techdosedaily' ) . '</span><span class="tdd-only-m">' . esc_html__( 'All AI →', 'techdosedaily' ) . '</span></a></div>'
		. '<div class="tdd-grid hp-airy">' . $large . ( $list ? '<div class="hp-col5 tdd-stack m-list hp-ai__list">' . $list . '</div>' : '' ) . '</div></div></section>';
}

/* ---------- Editor's Picks ---------- */

function tdd_home_picks(): string {
	$picks = tdd_home_resolve( 'editors_pick', 4 );
	if ( ! $picks ) {
		return '';
	}
	$p1   = $picks[0];
	$deck = tdd_deck( $p1 );
	$one  = array( 'one' => true );
	/* translators: %d: minutes. */
	$long = static fn( WP_Post $p ) => sprintf( __( '%d min', 'techdosedaily' ), tdd_reading_time( $p ) );
	$by   = static fn( WP_Post $p ) => '<span>' . sprintf( esc_html__( 'By %s', 'techdosedaily' ), '<b>' . esc_html( get_the_author_meta( 'display_name', $p->post_author ) ) . '</b>' ) . '</span>';
	$prim = '<article class="hp-col7 tdd-story tdd-story--large hp-picks__primary m-pick-lead">' . tdd_media( $p1, 'tdd-16x9-1200', '(max-width: 767px) 100vw, 720px' ) . tdd_labels( $p1, array( 'wrap' => true ) )
		. '<h3 class="tdd-story__title"><a href="' . esc_url( get_permalink( $p1 ) ) . '">' . esc_html( get_the_title( $p1 ) ) . '</a></h3>'
		. ( '' !== $deck ? '<p class="tdd-story__summary tdd-hide-m">' . esc_html( $deck ) . '</p>' : '' )
		. '<div class="tdd-meta">' . $by( $p1 ) . '<span>' . esc_html( tdd_reading_time( $p1 ) >= 12 ? __( 'Long read', 'techdosedaily' ) . ' · ' . $long( $p1 ) : sprintf( /* translators: %d: minutes */ __( '%d min read', 'techdosedaily' ), tdd_reading_time( $p1 ) ) ) . '</span></div></article>';
	$side = '';
	$mob  = '';
	if ( isset( $picks[1] ) ) {
		$p2    = $picks[1];
		$d2    = tdd_deck( $p2 );
		$read2 = '<span>' . esc_html( sprintf( /* translators: %d: minutes */ __( '%d min read', 'techdosedaily' ), tdd_reading_time( $p2 ) ) ) . '</span>';
		$side .= '<article class="tdd-story tdd-story--medium hp-picks__secondary">' . tdd_media( $p2, 'tdd-16x9-800', '(max-width: 1199px) 40vw, 520px' ) . tdd_labels( $p2, $one )
			. '<h3 class="tdd-story__title"><a href="' . esc_url( get_permalink( $p2 ) ) . '">' . esc_html( get_the_title( $p2 ) ) . '</a></h3>'
			. ( '' !== $d2 ? '<p class="tdd-story__summary">' . esc_html( $d2 ) . '</p>' : '' ) . '<div class="tdd-meta">' . $by( $p2 ) . $read2 . '</div></article>';
		$mob  .= '<article class="tdd-story tdd-story--compact">' . tdd_labels( $p2, $one ) . '<h3 class="tdd-story__title"><a href="' . esc_url( get_permalink( $p2 ) ) . '">' . esc_html( get_the_title( $p2 ) ) . '</a></h3><div class="tdd-meta">' . $by( $p2 ) . $read2 . '</div></article>';
	}
	$tert = '';
	foreach ( array_slice( $picks, 2 ) as $p ) {
		$card  = tdd_story_card( $p, 'compact', array( 'thumb' => false, 'meta' => array( 'read' ), 'labels' => $one ) );
		$tert .= $card;
		$mob  .= $card;
	}
	if ( '' !== $tert ) {
		$side .= '<div class="hp-picks__tertiary">' . $tert . '</div>';
	}
	return '<section class="tdd-section m-section" aria-labelledby="hp-picks-title"><div class="tdd-sh"><div><h2 class="tdd-sh__title" id="hp-picks-title">' . esc_html__( 'Editor’s Picks', 'techdosedaily' ) . '</h2><p class="tdd-sh__desc"><span class="tdd-hide-m">' . esc_html__( 'Deeper reporting worth your time this week', 'techdosedaily' ) . '</span><span class="tdd-only-m">' . esc_html__( 'Deeper reporting worth your time', 'techdosedaily' ) . '</span></p></div></div>'
		. '<div class="tdd-grid hp-airy">' . $prim . ( '' !== $side ? '<div class="hp-col5 tdd-hide-m">' . $side . '</div>' : '' ) . '</div>'
		. ( '' !== $mob ? '<div class="m-list hp-picks__mlist tdd-only-m is-block">' . $mob . '</div>' : '' ) . '</section>';
}

/* ---------- Section blocks (CategoryBlock) ---------- */

/** Section heading row used by every CategoryBlock. */
function tdd_cat_head( WP_Term $t ): string {
	return '<div class="tdd-sh tdd-sh--small tdd-cat__head"><h2 class="tdd-sh__title">' . esc_html( $t->name ) . '</h2><a class="tdd-btn tdd-btn--text tdd-sh__more" href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html__( 'View all →', 'techdosedaily' ) . '</a></div>';
}

/** Severity line from post meta (alert treatment); '' when not set — never invented. */
function tdd_severity( WP_Post $p ): string {
	$s = trim( (string) get_post_meta( $p->ID, 'tdd_severity', true ) );
	return '' !== $s ? '<span class="tdd-cat__severity">' . esc_html( $s ) . '</span>' : '';
}

/**
 * One CategoryBlock in its desktop and mobile forms.
 *
 * @return array{0:string,1:string} [ desktop, mobile ]
 */
function tdd_category_block( string $slug, string $variant ): array {
	$t = get_term_by( 'slug', $slug, 'category' );
	if ( ! $t ) {
		return array( '', '' );
	}
	$count = 'alert' === $variant ? 4 : ( 'feature' === $variant ? 3 : 1 );
	$posts = tdd_home_query( 'cat:' . $slug, array( 'cat' => $t->term_id, 'posts_per_page' => $count ) );
	if ( ! $posts ) {
		return array( '', '' );
	}
	$lead = array_shift( $posts );
	$url  = esc_url( get_permalink( $lead ) );
	$ttl  = esc_html( get_the_title( $lead ) );
	$deck = tdd_deck( $lead );
	$head = tdd_cat_head( $t );
	$d    = '';
	$m    = '';
	switch ( $variant ) {
		case 'alert':
			$d = '<div class="hp-col4 tdd-cat tdd-cat--alert">' . $head . '<article class="tdd-story tdd-story--medium tdd-cat__lead">' . tdd_media( $lead, 'tdd-16x9-800', '(max-width: 1199px) 33vw, 400px' ) . tdd_severity( $lead )
				. '<h3 class="tdd-story__title"><a href="' . $url . '">' . $ttl . '</a></h3>' . ( '' !== $deck ? '<p class="tdd-story__summary">' . esc_html( $deck ) . '</p>' : '' ) . '</article>';
			$m = '<div class="m-cat tdd-cat--alert">' . $head . '<article class="tdd-story tdd-cat__mlead">' . tdd_severity( $lead ) . '<h3 class="tdd-story__title"><a href="' . $url . '">' . $ttl . '</a></h3>' . ( '' !== $deck ? '<p class="tdd-story__summary">' . esc_html( $deck ) . '</p>' : '' ) . '</article>';
			$rows = '';
			foreach ( $posts as $i => $p ) {
				$card  = tdd_story_card( $p, 'compact', array( 'thumb' => false, 'meta' => array( 'ago' ), 'labels' => array( 'section' => false, 'format' => false, 'breaking' => false ) ) );
				$rows .= $card;
				$m    .= 0 === $i ? '<div class="m-list">' : '';
				$m    .= $i < 2 ? $card : '';
			}
			$d .= $rows ? '<div class="tdd-stack">' . $rows . '</div>' : '';
			$m .= $posts ? '</div>' : '';
			break;
		case 'feature':
			$d = '<div class="hp-col5 tdd-cat tdd-cat--feature">' . $head . '<article class="tdd-story tdd-story--medium tdd-cat__lead">' . tdd_media( $lead, 'tdd-16x9-800', '(max-width: 1199px) 40vw, 520px' ) . tdd_labels( $lead, array( 'wrap' => true ) )
				. '<h3 class="tdd-story__title"><a href="' . $url . '">' . $ttl . '</a></h3>' . ( '' !== $deck ? '<p class="tdd-story__summary">' . esc_html( $deck ) . '</p>' : '' ) . tdd_meta( $lead, array( 'author', 'read' ) ) . '</article>';
			$m = '<div class="m-cat">' . $head . '<article class="tdd-story tdd-story--medium">' . tdd_media( $lead, 'tdd-16x9-800', '100vw' ) . tdd_labels( $lead, array( 'wrap' => true ) ) . '<h3 class="tdd-story__title"><a href="' . $url . '">' . $ttl . '</a></h3></article>';
			$pair  = '';
			$mrows = '';
			foreach ( $posts as $p ) {
				$pair  .= '<article class="tdd-story tdd-story--compact">' . tdd_media( $p, 'tdd-16x9-400', '(max-width: 1199px) 20vw, 250px' ) . tdd_labels( $p, array( 'one' => true ) ) . '<h3 class="tdd-story__title"><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></h3></article>';
				$f      = tdd_format( $p );
				$meta   = ( $f && 'news' !== $f->slug ) ? '<div class="tdd-meta"><span>' . esc_html( $f->name . ' · ' . sprintf( /* translators: %d: minutes */ __( '%d min', 'techdosedaily' ), tdd_reading_time( $p ) ) ) . '</span></div>' : tdd_meta( $p, array( 'ago' ) );
				$mrows .= '<article class="tdd-story tdd-story--compact"><h3 class="tdd-story__title"><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></h3>' . $meta . '</article>';
			}
			$d .= $pair ? '<div class="tdd-cat__pair">' . $pair . '</div>' : '';
			$m .= $mrows ? '<div class="m-list">' . $mrows . '</div>' : '';
			break;
		default: // list
			$d = '<div class="hp-col3 tdd-cat tdd-cat--list">' . $head . '<article class="tdd-story tdd-story--compact tdd-cat__lead">' . tdd_media( $lead, 'tdd-16x9-400', '(max-width: 1199px) 25vw, 300px' )
				. '<h3 class="tdd-story__title"><a href="' . $url . '">' . $ttl . '</a></h3>' . tdd_meta( $lead, array( 'ago' ) ) . '</article>';
			$m = '<div class="m-cat">' . $head . '<div class="m-list m-list--after-sh">' . tdd_story_card( $lead, 'compact', array( 'thumb' => true, 'meta' => array( 'ago' ), 'size' => 'tdd-4x3', 'sizes' => '96px', 'labels' => array( 'topic' => true, 'one' => false, 'format' => false, 'breaking' => false ) ) ) . '</div>';
	}
	return array( $d . '</div>', $m . '</div>' );
}

/**
 * Category row. Desktop order as given; mobile uses $mobile_order and ends with chips for
 * the sections not featured elsewhere on the page.
 */
function tdd_home_sections( array $blocks, array $mobile_order, array $chips ): string {
	$desk = array();
	$mob  = array();
	foreach ( $blocks as [ $slug, $variant ] ) {
		[ $desk[ $slug ], $mob[ $slug ] ] = tdd_category_block( $slug, $variant );
	}
	$desk = array_filter( $desk );
	if ( ! $desk ) {
		return '';
	}
	// A section with no stories is dropped; the others share the row so it never shows a hole.
	if ( count( $desk ) < count( $blocks ) ) {
		$span = 1 === count( $desk ) ? 'hp-col12' : 'hp-col6';
		$desk = array_map( static fn( $h ) => preg_replace( '/^<div class="hp-col\d+ /', '<div class="' . $span . ' ', $h ), $desk );
	}
	$d = implode( '', $desk );
	$m = '';
	foreach ( $mobile_order as $slug ) {
		$m .= $mob[ $slug ] ?? '';
	}
	$links = '';
	foreach ( $chips as $slug ) {
		$t = get_term_by( 'slug', $slug, 'category' );
		if ( $t && $t->count > 0 ) {
			$links .= '<a href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a>';
		}
	}
	return '<section class="tdd-grid hp-airy tdd-section tdd-hide-m" aria-label="' . esc_attr__( 'Sections', 'techdosedaily' ) . '">' . $d . '</section>'
		. '<section class="m-section tdd-only-m is-block" aria-label="' . esc_attr__( 'Sections', 'techdosedaily' ) . '">' . $m . ( $links ? '<nav class="m-sections" aria-label="' . esc_attr__( 'More sections', 'techdosedaily' ) . '">' . $links . '</nav>' : '' ) . '</section>';
}

/* ---------- Practical Guides ---------- */

/** Newest guides (any section), once per request. */
function tdd_home_guides_posts( int $count = 3 ): array {
	return tdd_home_query( 'guides', array( 'posts_per_page' => $count, 'tax_query' => array( array( 'taxonomy' => 'tdd_format', 'field' => 'slug', 'terms' => array( 'guide' ) ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
}

/** Guides link target: the Guides section, else the Guide story-type archive. */
function tdd_guides_url(): string {
	$t = get_term_by( 'slug', 'guides', 'category' );
	if ( $t ) {
		return (string) get_term_link( $t );
	}
	$l = get_term_link( 'guide', 'tdd_format' );
	return is_wp_error( $l ) ? '' : (string) $l;
}
