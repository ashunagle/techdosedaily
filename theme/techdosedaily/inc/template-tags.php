<?php
/**
 * Presentation helpers that print approved design-system markup from real post data.
 * Data comes from TechDoseDaily Core (tdd_core_*); every call is guarded so the theme
 * degrades gracefully (plain WordPress data) if the plugin is inactive.
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

/** Is the TechDoseDaily Core plugin active? */
function tdd_has_core(): bool {
	return function_exists( 'tdd_core_primary_section' );
}

function tdd_section( $post = null ): ?WP_Term {
	if ( tdd_has_core() ) {
		return tdd_core_primary_section( $post );
	}
	$cats = get_the_category( get_post( $post )->ID ?? 0 );
	return $cats[0] ?? null;
}

function tdd_format( $post = null ): ?WP_Term {
	return tdd_has_core() ? tdd_core_story_format( $post ) : null;
}

function tdd_deck( $post = null ): string {
	$deck = tdd_has_core() ? tdd_core_deck( $post ) : '';
	// Only a written deck or a hand-written excerpt; never an auto-trimmed slice of the body.
	return '' !== $deck ? $deck : ( has_excerpt( $post ) ? (string) get_the_excerpt( $post ) : '' );
}

function tdd_reading_time( $post = null ): int {
	return tdd_has_core() ? tdd_core_reading_time( $post ) : max( 1, (int) ceil( str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $post ) ) ) / 230 ) );
}

function tdd_is_breaking( $post = null ): bool {
	return tdd_has_core() && tdd_core_is_breaking( $post );
}

/** Format slug → label modifier class (design-system Label). News has no format label. */
function tdd_format_class( string $slug ): string {
	$map = array(
		'analysis'  => 'tdd-label--analysis',
		'explainer' => 'tdd-label--explainer',
		'guide'     => 'tdd-label--guide',
		'review'    => 'tdd-label--review',
		'sponsored' => 'tdd-label--plain',
	);
	return $map[ $slug ] ?? '';
}

/**
 * Labels for a story: [Breaking] · Section · [Format].
 * One label prints a bare span (as in the approved cards); several print .tdd-labels.
 *
 * @param array $opts { section: bool, format: bool, breaking: bool, one: bool }
 *                     one = a single label: the story type if it isn't News, else the section
 *                     (Most Read rows, as approved).
 */
function tdd_labels( $post = null, array $opts = array() ): string {
	$opts    = wp_parse_args( $opts, array( 'section' => true, 'format' => true, 'breaking' => true, 'one' => false, 'wrap' => false, 'topic' => false, 'tag' => 'div' ) );
	$format  = $opts['format'] ? tdd_format( $post ) : null;
	$fslug   = $format ? $format->slug : 'news';
	$section = $opts['section'] ? tdd_section( $post ) : null;
	$fmt     = ( $format && 'news' !== $fslug ) ? '<span class="tdd-label ' . esc_attr( tdd_format_class( $fslug ) ) . '">' . esc_html( $format->name ) . '</span>' : '';
	$sec     = $section ? '<span class="tdd-label">' . esc_html( $section->name ) . '</span>' : '';
	if ( $opts['topic'] ) {
		// Inside a section the section label is redundant: the story's first topic is shown instead (approved section page).
		$topics = get_the_terms( get_post( $post ), 'tdd_topic' );
		if ( $topics && ! is_wp_error( $topics ) ) {
			$sec = '<span class="tdd-label">' . esc_html( $topics[0]->name ) . '</span>';
		}
	}
	if ( $opts['one'] ) {
		return $fmt ? $fmt : $sec;
	}
	$labels = array();
	if ( $opts['breaking'] && tdd_is_breaking( $post ) ) {
		$labels[] = '<span class="tdd-label tdd-label--breaking">' . esc_html__( 'Breaking', 'techdosedaily' ) . '</span>';
	}
	if ( $sec ) {
		$labels[] = $sec;
	}
	if ( $fmt ) {
		$labels[] = $fmt;
	}
	if ( count( $labels ) > 1 || ( $opts['wrap'] && $labels ) ) {
		$tag = 'span' === $opts['tag'] ? 'span' : 'div';
		return '<' . $tag . ' class="tdd-labels">' . implode( '', $labels ) . '</' . $tag . '>';
	}
	return $labels[0] ?? '';
}

/** "34 min ago" · "3h ago" · then a date (approved compact meta). */
function tdd_time_ago( $post = null ): string {
	$ts   = (int) get_post_timestamp( $post );
	$diff = time() - $ts;
	if ( $diff < HOUR_IN_SECONDS ) {
		/* translators: %d: minutes. */
		return sprintf( __( '%d min ago', 'techdosedaily' ), max( 1, (int) floor( $diff / MINUTE_IN_SECONDS ) ) );
	}
	if ( $diff < DAY_IN_SECONDS ) {
		/* translators: %d: hours. */
		return sprintf( __( '%dh ago', 'techdosedaily' ), (int) floor( $diff / HOUR_IN_SECONDS ) );
	}
	return wp_date( 'M j, Y', $ts );
}

/** <time> element with machine-readable datetime. */
function tdd_time( int $ts, string $text ): string {
	return '<time datetime="' . esc_attr( gmdate( 'c', $ts ) ) . '">' . esc_html( $text ) . '</time>';
}

/**
 * Approved meta line (.tdd-meta). Parts: author, date, updated, ago, read.
 *
 * @param string[] $parts Which pieces, in order.
 */
function tdd_meta( $post = null, array $parts = array( 'author', 'date', 'read' ) ): string {
	$post = get_post( $post );
	$out  = array();
	foreach ( $parts as $part ) {
		switch ( $part ) {
			case 'author':
				/* translators: %s: author name. */
				$out[] = sprintf( esc_html__( 'By %s', 'techdosedaily' ), '<b>' . esc_html( get_the_author_meta( 'display_name', $post->post_author ) ) . '</b>' );
				break;
			case 'date':
				$ts    = (int) get_post_timestamp( $post );
				$out[] = tdd_time( $ts, wp_date( 'M j, Y', $ts ) );
				break;
			case 'updated':
				$updated = tdd_has_core() ? tdd_core_updated_at( $post ) : null;
				if ( $updated ) {
					/* translators: %s: time. */
					$out[] = sprintf( esc_html__( 'Updated %s', 'techdosedaily' ), tdd_time( $updated->getTimestamp(), wp_date( 'g:i A', $updated->getTimestamp() ) ) );
				} else {
					$ts    = (int) get_post_timestamp( $post );
					$out[] = tdd_time( $ts, wp_date( 'M j, Y', $ts ) );
				}
				break;
			case 'ago':
				$out[] = tdd_time( (int) get_post_timestamp( $post ), tdd_time_ago( $post ) );
				break;
			case 'read':
				/* translators: %d: minutes. */
				$out[] = esc_html( sprintf( __( '%d min read', 'techdosedaily' ), tdd_reading_time( $post ) ) );
				break;
		}
	}
	return '<div class="tdd-meta"><span>' . implode( '</span><span>', $out ) . '</span></div>';
}

/** Featured image inside the approved .tdd-media wrapper (link or span). */
function tdd_media( $post, string $size = 'tdd-16x9-800', string $sizes = '(max-width: 767px) 100vw, 400px', bool $link = true, bool $eager = false ): string {
	$post = get_post( $post );
	if ( ! $post || ! has_post_thumbnail( $post ) ) {
		return '';
	}
	// Only the likely LCP image (homepage lead, section lead) is eager + high priority; everything else is lazy.
	$img = get_the_post_thumbnail(
		$post,
		$size,
		$eager
			? array( 'sizes' => $sizes, 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async' )
			: array( 'sizes' => $sizes, 'loading' => 'lazy', 'decoding' => 'async' )
	);
	return $link
		? '<a href="' . esc_url( get_permalink( $post ) ) . '" class="tdd-media" tabindex="-1" aria-hidden="true">' . $img . '</a>'
		: '<span class="tdd-media">' . $img . '</span>';
}

/**
 * Story card (design-system StoryCard): feature | large | medium | compact.
 *
 * @param array $o { heading: h1–h4, summary: bool, meta: string[], thumb: bool, eager: bool,
 *                  labels: array (tdd_labels options, e.g. [ 'one' => true ]) }
 */
function tdd_story_card( $post, string $variant = 'medium', array $o = array() ): string {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$defaults = array(
		'feature' => array( 'heading' => 'h1', 'summary' => true, 'meta' => array( 'author', 'updated', 'read' ), 'thumb' => true, 'size' => 'tdd-16x9-1200', 'sizes' => '(max-width: 767px) 100vw, 800px' ),
		'large'   => array( 'heading' => 'h3', 'summary' => true, 'meta' => array( 'author', 'date', 'read' ), 'thumb' => true, 'size' => 'tdd-16x9-800', 'sizes' => '(max-width: 767px) 100vw, 600px' ),
		'medium'  => array( 'heading' => 'h3', 'summary' => false, 'meta' => array( 'ago' ), 'thumb' => true, 'size' => 'tdd-16x9-800', 'sizes' => '(max-width: 767px) 100vw, 400px' ),
		'compact' => array( 'heading' => 'h3', 'summary' => false, 'meta' => array( 'ago' ), 'thumb' => false, 'size' => 'tdd-4x3', 'sizes' => '120px' ),
	);
	$o       = wp_parse_args( $o, $defaults[ $variant ] ?? $defaults['medium'] );
	$o['eager']  = $o['eager'] ?? false;
	$o['labels'] = $o['labels'] ?? array();
	$h       = in_array( $o['heading'], array( 'h1', 'h2', 'h3', 'h4' ), true ) ? $o['heading'] : 'h3';
	$url     = esc_url( get_permalink( $post ) );
	$title   = '<' . $h . ' class="tdd-story__title"><a href="' . $url . '">' . esc_html( get_the_title( $post ) ) . '</a></' . $h . '>';
	$summary = $o['summary'] && tdd_deck( $post ) ? '<p class="tdd-story__summary">' . esc_html( tdd_deck( $post ) ) . '</p>' : '';
	$meta    = $o['meta'] ? tdd_meta( $post, $o['meta'] ) : '';
	$media   = $o['thumb'] ? tdd_media( $post, $o['size'], $o['sizes'], true, $o['eager'] ) : '';
	$classes = 'tdd-story tdd-story--' . sanitize_html_class( $variant ) . ( 'compact' === $variant && $media ? ' has-thumb' : '' ) . ( 'feature' === $variant ? ' m-lead' : '' );

	if ( 'feature' === $variant ) {
		// Approved feature markup: text stack first, then the image (homepage lead).
		return '<article class="' . $classes . '"><div class="tdd-story__stack">' . tdd_labels( $post, $o['labels'] ) . $title . $summary . $meta . '</div>' . $media . '</article>';
	}
	return '<article class="' . $classes . '">' . $media . tdd_labels( $post, $o['labels'] ) . $title . $summary . $meta . '</article>';
}

/** Freshness line (static text, never animated). */
function tdd_freshness( ?int $ts ): string {
	if ( ! $ts ) {
		return '';
	}
	$icon = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
	/* translators: %s: time with zone, e.g. 10:42 AM IST. */
	// Desktop: "… · Last update 10:42 AM IST"; mobile (approved): "… · 10:42 AM".
	return '<p class="tdd-fresh" style="margin-top:6px">' . $icon . esc_html__( 'Updated throughout the day', 'techdosedaily' ) . ' · <b><span class="tdd-hide-m">' . esc_html__( 'Last update', 'techdosedaily' ) . ' </span>' . esc_html( wp_date( 'g:i A', $ts ) ) . '<span class="tdd-hide-m"> ' . esc_html( wp_date( 'T', $ts ) ) . '</span></b></p>';
}

/** URL of the chronological "Latest" listing (Posts page), falling back to home. */
function tdd_latest_url(): string {
	$id = (int) get_option( 'page_for_posts' );
	return $id ? (string) get_permalink( $id ) : home_url( '/' );
}
