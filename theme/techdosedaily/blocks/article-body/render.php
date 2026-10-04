<?php
/**
 * tdd/article-body — the approved article body row:
 *   rail (TOC for ~7+ minute pieces, share) · reading column · sidebar.
 * Reading column order: update notice → story content (Key takeaways → mobile TOC → body …)
 * → corrections → sources → mobile share row → tags → author card.
 * Every part is omitted when its data is missing.
 *
 * @package techdosedaily
 * @var array    $attributes
 * @var WP_Block $block
 */

defined( 'ABSPATH' ) || exit;

static $tdd_rendering = false;
$tdd_post = get_post( $block->context['postId'] ?? get_the_ID() );
if ( $tdd_rendering || ! $tdd_post || 'post' !== $tdd_post->post_type ) {
	return;
}
$tdd_rendering = true;

// Story content with the article mappings for core blocks (inc/article.php).
$GLOBALS['tdd_in_article']  = true;
$GLOBALS['tdd_heading_ids'] = array();
$tdd_content                = apply_filters( 'the_content', str_replace( ']]>', ']]&gt;', get_the_content( null, false, $tdd_post ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
$GLOBALS['tdd_in_article']  = false;

$tdd_sources = tdd_sources_html( $tdd_post );
$tdd_toc     = tdd_toc_items( $tdd_content, '' !== $tdd_sources );
$tdd_has_toc = count( $tdd_toc ) >= 2 && tdd_reading_time( $tdd_post ) >= (int) $attributes['tocMinutes'];

// Mobile collapsible TOC goes right after Key takeaways (or at the top of the body).
if ( $tdd_has_toc ) {
	$tdd_mtoc    = tdd_toc_mobile( $tdd_toc );
	$tdd_marker  = '<!--tdd:after-takeaways-->';
	$tdd_content = str_contains( $tdd_content, $tdd_marker ) ? preg_replace( '/' . preg_quote( $tdd_marker, '/' ) . '/', $tdd_mtoc, $tdd_content, 1 ) : $tdd_mtoc . $tdd_content;
}

$tdd_prose = tdd_update_notice( $tdd_post ) . $tdd_content . tdd_corrections_html( $tdd_post ) . $tdd_sources . tdd_share( 'again' ) . tdd_tags_html( $tdd_post ) . tdd_author_card( $tdd_post );

$tdd_rail = '<aside class="tdd-art__rail" aria-label="' . esc_attr__( 'Article tools', 'techdosedaily' ) . '"><div class="tdd-art__rail-inner">' . ( $tdd_has_toc ? tdd_toc_rail( $tdd_toc ) : '' ) . tdd_share( 'rail' ) . '</div></aside>';

$tdd_side = '';
if ( $attributes['sidebar'] ) {
	$tdd_parts = array();
	$tdd_mr    = render_block(
		array(
			'blockName'    => 'tdd/most-read',
			'attrs'        => array( 'count' => 5, 'context' => 'home', 'showWindow' => false ),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		)
	);
	if ( '' !== trim( $tdd_mr ) ) {
		$tdd_parts[] = $tdd_mr;
	}
	$tdd_more    = tdd_more_in_section( $tdd_post );
	$tdd_section = tdd_section( $tdd_post );
	if ( $tdd_more && $tdd_section ) {
		$tdd_rows = '';
		foreach ( $tdd_more as $tdd_p ) {
			$tdd_rows .= tdd_compact_row( $tdd_p, 'ago', true );
		}
		/* translators: %s: section name. */
		$tdd_parts[] = '<div><div class="tdd-sh tdd-sh--small"><h2 class="tdd-sh__title">' . esc_html( sprintf( __( 'More in %s', 'techdosedaily' ), $tdd_section->name ) ) . '</h2></div><div class="tdd-stack">' . $tdd_rows . '</div></div>';
	}
	$tdd_brief = tdd_side_brief();
	if ( '' !== $tdd_brief ) {
		$tdd_parts[] = $tdd_brief;
	}
	if ( $tdd_parts ) {
		$tdd_side = '<aside class="tdd-art__side tdd-hide-m" aria-label="' . esc_attr__( 'More from Tech Dose Daily', 'techdosedaily' ) . '">' . implode( '', $tdd_parts ) . '</aside>';
	}
}

$tdd_rendering = false;
echo '<div class="tdd-art tdd-art--body">' . $tdd_rail . '<div class="tdd-prose m-prose">' . $tdd_prose . '</div>' . $tdd_side . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- story content (filtered) and escaped helpers.
