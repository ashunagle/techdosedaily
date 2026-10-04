<?php
/**
 * Shared static page system (approved StaticPageDesktop / StaticPageMobile / StaticPageShort,
 * About): page header, numbered sections built from the page's H2s, contents rail (≥1200) or
 * collapsible inline contents (<1200), related documents, related policies, contact CTA.
 *
 * Content is ordinary page content (core blocks + Tech Dose Daily blocks). A block whose data is
 * empty prints <!--tdd:empty-->; the section containing it is dropped with its contents entry,
 * so no heading is ever left without content.
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

/** URL of a page by path ('' unless published). Privacy uses the WordPress privacy page setting. */
function tdd_static_url( string $path ): string {
	if ( 'privacy-policy' === $path ) {
		$url = get_privacy_policy_url();
		if ( $url ) {
			return $url;
		}
	}
	return tdd_page_url( $path );
}

/** Field from page meta. */
function tdd_page_field( WP_Post $p, string $key ): string {
	return trim( (string) get_post_meta( $p->ID, $key, true ) );
}

/** H1: the headline field when set, otherwise the page title. */
function tdd_static_headline( WP_Post $p ): string {
	$h = tdd_page_field( $p, 'tdd_page_headline' );
	return '' !== $h ? $h : get_the_title( $p );
}

/** StaticPageHeader (+ AboutMission statement and focus row when present). */
function tdd_static_header( WP_Post $p, bool $updated = true ): string {
	$kicker    = tdd_page_field( $p, 'tdd_kicker' );
	$intro     = tdd_page_field( $p, 'tdd_intro' );
	$statement = tdd_page_field( $p, 'tdd_statement' );
	$focus     = (array) get_post_meta( $p->ID, 'tdd_focus', true );
	$html      = ( '' !== $kicker ? '<div class="tdd-sp__kicker">' . esc_html( $kicker ) . '</div>' : '' )
		. '<h1 class="tdd-sp__title">' . esc_html( tdd_static_headline( $p ) ) . '</h1>'
		. ( '' !== $statement ? '<p class="tdd-amis__statement">' . esc_html( $statement ) . '</p>' : '' )
		. ( '' !== $intro ? '<p class="tdd-sp__intro' . ( '' !== $statement ? ' tdd-sp__intro--about' : '' ) . '">' . esc_html( $intro ) . '</p>' : '' );
	$items = '';
	foreach ( $focus as $f ) {
		if ( ! empty( $f['title'] ) ) {
			$items .= '<li><b>' . esc_html( $f['title'] ) . '</b>' . esc_html( $f['text'] ?? '' ) . '</li>';
		}
	}
	if ( '' !== $items ) {
		$html .= '<ul class="tdd-amis__focus" aria-label="' . esc_attr__( 'What every story answers', 'techdosedaily' ) . '">' . $items . '</ul>';
	}
	if ( $updated && '' === $statement ) {
		$ts      = function_exists( 'tdd_core_page_updated' ) ? tdd_core_page_updated( $p ) : (int) get_post_timestamp( $p, 'modified' );
		$cadence = tdd_page_field( $p, 'tdd_review_cadence' );
		$vurl    = tdd_page_field( $p, 'tdd_version_url' );
		$vlabel  = tdd_page_field( $p, 'tdd_version_label' );
		$html   .= '<div class="tdd-sp__updated"><span>' . esc_html__( 'Last updated', 'techdosedaily' ) . ' <time datetime="' . esc_attr( wp_date( 'Y-m-d', $ts ) ) . '">' . esc_html( wp_date( 'M j, Y', $ts ) ) . '</time></span>'
			. ( '' !== $cadence ? '<span>' . esc_html( $cadence ) . '</span>' : '' )
			. ( '' !== $vurl ? '<a href="' . esc_url( $vurl ) . '">' . esc_html( '' !== $vlabel ? $vlabel : __( 'Version history →', 'techdosedaily' ) ) . '</a>' : '' ) . '</div>';
	}
	return '<header class="tdd-sp__head">' . $html . '</header>';
}

/**
 * Render page content and build sections.
 *
 * @return array{html:string, toc:array<int,array{0:string,1:string}>, cta:string}
 */
function tdd_static_body( WP_Post $p, bool $sections, bool $numbered ): array {
	$GLOBALS['tdd_in_static']   = true;
	$GLOBALS['tdd_heading_ids'] = array();
	$html                       = apply_filters( 'the_content', str_replace( ']]>', ']]&gt;', get_the_content( null, false, $p ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
	$GLOBALS['tdd_in_static']   = false;

	// The ContactCTA block is placed by the template (end of page), wherever the editor put it.
	$cta = '';
	if ( preg_match( '#<!--tdd:cta-->(.*?)<!--/tdd:cta-->#s', $html, $m ) ) {
		$cta  = $m[1];
		$html = str_replace( $m[0], '', $html );
	}
	$toc = array();
	if ( $sections ) {
		$parts = preg_split( '#(<h2\b(?=[^>]*\bwp-block-heading\b)[^>]*>.*?</h2>)#s', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		$out   = array_shift( $parts ); // Content before the first H2 (e.g. "In short").
		$n     = 0;
		for ( $i = 0; $i < count( $parts ); $i += 2 ) {
			$h    = $parts[ $i ];
			$body = $parts[ $i + 1 ] ?? '';
			if ( str_contains( $body, '<!--tdd:empty-->' ) ) {
				continue; // Data module without data: drop the whole section.
			}
			preg_match( '#\bid="([^"]+)"#', $h, $idm );
			preg_match( '#<h2\b[^>]*>(.*?)</h2>#s', $h, $tm );
			$id    = $idm[1] ?? 'section-' . ( $n + 1 );
			$inner = trim( $tm[1] ?? '' );
			$text  = trim( wp_strip_all_tags( $inner ) );
			++$n;
			$toc[] = array( $id, $text );
			/* translators: %s: section title. */
			$link = '<a class="tdd-spsec__link" href="#' . esc_attr( $id ) . '" aria-label="' . esc_attr( sprintf( __( 'Link to section: %s', 'techdosedaily' ), $text ) ) . '">#</a>';
			$out .= '<section class="tdd-spsec" aria-labelledby="' . esc_attr( $id ) . '"><h2 class="tdd-spsec__h" id="' . esc_attr( $id ) . '">'
				. ( $numbered ? '<span class="tdd-spsec__n" aria-hidden="true">' . $n . '</span>' : '' ) . '<span>' . wp_kses_post( $inner ) . '</span>' . $link . '</h2>' . $body . '</section>';
		}
		$html = $out;
	}
	$html = str_replace( '<!--tdd:empty-->', '', $html );
	return array( 'html' => $html, 'toc' => $toc, 'cta' => $cta );
}

/** Related documents (page meta tdd_related_docs), SourceList styling. */
function tdd_static_related_docs( WP_Post $p ): string {
	$rows = (array) get_post_meta( $p->ID, 'tdd_related_docs', true );
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$li   = '';
	foreach ( $rows as $r ) {
		if ( empty( $r['title'] ) || empty( $r['url'] ) ) {
			continue;
		}
		$ext   = wp_parse_url( $r['url'], PHP_URL_HOST ) !== $host;
		$shown = preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $r['url'] ) );
		$shown = $ext ? (string) wp_parse_url( $r['url'], PHP_URL_HOST ) : $shown . '/';
		$li   .= '<li class="tdd-src"><div class="tdd-src__title"><a href="' . esc_url( $r['url'] ) . '"' . ( $ext ? ' rel="noopener"' : '' ) . '>' . esc_html( $r['title'] ) . '</a></div><div class="tdd-src__meta">'
			. ( 'external' === ( $r['kind'] ?? '' ) ? '<span class="tdd-src__type">' . esc_html__( 'External', 'techdosedaily' ) . '</span>' : '<span class="tdd-src__type tdd-src__type--primary">' . esc_html__( 'Policy', 'techdosedaily' ) . '</span>' )
			. '<span>' . esc_html( $shown ) . '</span>' . ( $ext ? '<span class="tdd-src__ext">' . tdd_icon( 'external', '' ) . '<span class="screen-reader-text">' . esc_html__( '(external link)', 'techdosedaily' ) . '</span></span>' : '' ) . '</div></li>';
	}
	return '' === $li ? '' : '<section class="tdd-sources tdd-block" aria-labelledby="tdd-refs"><div class="tdd-sources__head"><h2 class="tdd-sources__title" id="tdd-refs">' . esc_html__( 'Related documents', 'techdosedaily' ) . '</h2></div><ol>' . $li . '</ol></section>';
}

/**
 * RelatedPolicyLinks: published policy pages in the approved order, omitting the current page.
 *
 * @param string[] $extra Extra leading paths (e.g. "about" on the Contact page).
 */
function tdd_related_policies( int $current = 0, string $title = '', array $extra = array(), int $max = 6 ): string {
	$defaults = function_exists( 'tdd_core_policy_pages' ) ? tdd_core_policy_pages() : array();
	$paths    = array_merge( $extra, array_keys( $defaults ) );
	$links    = '';
	$count    = 0;
	foreach ( array_unique( $paths ) as $path ) {
		$url = tdd_static_url( $path );
		if ( '' === $url ) {
			continue;
		}
		$page = 'privacy-policy' === $path && (int) get_option( 'wp_page_for_privacy_policy' ) ? get_post( (int) get_option( 'wp_page_for_privacy_policy' ) ) : get_page_by_path( $path );
		if ( ! $page || $page->ID === $current ) {
			continue;
		}
		$desc   = trim( (string) get_post_meta( $page->ID, 'tdd_summary', true ) );
		$desc   = '' !== $desc ? $desc : ( $defaults[ $path ] ?? ( 'about' === $path ? __( 'Who we are', 'techdosedaily' ) : '' ) );
		$links .= '<a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $page ) ) . ( '' !== $desc ? '<span>' . esc_html( $desc ) . '</span>' : '' ) . '</a>';
		if ( ++$count >= $max ) {
			break;
		}
	}
	if ( '' === $links ) {
		return '';
	}
	$title = '' !== $title ? $title : __( 'Related policies', 'techdosedaily' );
	return '<nav class="tdd-relpol" aria-labelledby="tdd-relpol-t"><h2 class="tdd-relpol__t" id="tdd-relpol-t">' . esc_html( $title ) . '</h2>' . $links . '</nav>';
}

/** Contents rail (StaticPageTOC desktop) + "Back to top". */
function tdd_static_rail( array $toc ): string {
	$li = '';
	foreach ( $toc as $i => [ $id, $text ] ) {
		$li .= '<li><a href="#' . esc_attr( $id ) . '"' . ( 0 === $i ? ' aria-current="true"' : '' ) . '>' . esc_html( $text ) . '</a></li>';
	}
	return '<aside class="tdd-sp__rail" aria-label="' . esc_attr__( 'Page navigation', 'techdosedaily' ) . '"><div class="tdd-sp__rail-inner"><nav class="tdd-toc" aria-labelledby="tdd-sptoc-t" data-tdd-toc><div class="tdd-toc__title" id="tdd-sptoc-t">' . esc_html__( 'In this page', 'techdosedaily' ) . '</div><ol>' . $li . '</ol></nav><a class="tdd-sp__top" href="#main">' . esc_html__( '↑ Back to top', 'techdosedaily' ) . '</a></div></aside>';
}

/** Collapsible inline contents (<1200px): "In this page · N sections". */
function tdd_static_inline_toc( array $toc ): string {
	$li = '';
	foreach ( $toc as [ $id, $text ] ) {
		$li .= '<li><a href="#' . esc_attr( $id ) . '">' . esc_html( $text ) . '</a></li>';
	}
	/* translators: %d: number of sections. */
	$count = sprintf( _n( '%d section', '%d sections', count( $toc ), 'techdosedaily' ), count( $toc ) );
	return '<details class="m-toc tdd-toc-inline"><summary>' . esc_html__( 'In this page', 'techdosedaily' ) . ' <span>' . esc_html( $count ) . '</span>' . tdd_icon( 'chevron-down', '' ) . '</summary><nav aria-label="' . esc_attr__( 'In this page', 'techdosedaily' ) . '"><ol>' . $li . '</ol></nav></details>';
}

/**
 * The whole static article.
 *
 * @param string $variant auto (rail when 4+ sections) | short | long
 * @param string $end     policies | about
 */
function tdd_static_page( WP_Post $p, string $variant = 'auto', string $end = 'policies' ): string {
	$numbered = (bool) get_post_meta( $p->ID, 'tdd_numbered', true );
	$body     = tdd_static_body( $p, 'short' !== $variant, $numbered );
	$long     = 'long' === $variant || ( 'auto' === $variant && count( $body['toc'] ) >= 4 );
	if ( ! $long && 'short' !== $variant && $body['toc'] ) {
		// Fewer than four sections: approved short variant (plain H2s, no contents).
		$body = tdd_static_body( $p, false, false );
	}
	$prose = '<div class="tdd-prose m-prose">' . $body['html'] . tdd_static_related_docs( $p ) . '</div>';
	$rel   = 'about' === $end
		? tdd_related_policies( $p->ID, '', array( 'editorial-standards', 'corrections-policy', 'source-policy', 'ai-use-policy', 'privacy-policy', 'advertise' ) ) // Approved About order.
		: tdd_related_policies( $p->ID );
	if ( 'about' === $end ) {
		$endhtml = $rel . tdd_newsletter_cta( array( 'title' => __( 'Your Daily Dose of Technology', 'techdosedaily' ), 'text' => __( 'The most important AI and technology stories, explained clearly. One email every weekday morning.', 'techdosedaily' ) ) );
	} else {
		$endhtml = $long ? $rel . $body['cta'] : $body['cta'] . $rel;
	}
	$inner = tdd_static_header( $p ) . ( $long ? tdd_static_rail( $body['toc'] ) : '' )
		. '<div class="tdd-sp__body">' . ( $long ? tdd_static_inline_toc( $body['toc'] ) : '' ) . $prose . ( '' !== $endhtml ? '<div class="tdd-sp__end">' . $endhtml . '</div>' : '' ) . '</div>';
	return tdd_breadcrumbs_html() . '<article class="tdd-sp m-sp' . ( $long ? '' : ' tdd-sp--short' ) . ( 'about' === $end ? ' tdd-sp--about' : '' ) . '">' . $inner . '</article>';
}
