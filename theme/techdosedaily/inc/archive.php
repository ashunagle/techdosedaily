<?php
/**
 * Section (category) and archive presentation: CategoryMasthead, TopicNav, pinned top stories,
 * the time-led feed with FeedDateGroups, AnalysisItem, guides, topic chips and the section desk.
 * Values come from the approved CategoryDesktop / CategoryMobile markup.
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Main query ---------- */

/** Feed size per page/load (approved: 12 per load). */
const TDD_FEED_PER_PAGE = 12;

/**
 * Pinned top stories for a section: section_lead (1) + section_secondary (3), each slot falling
 * back to the newest story in the section, never repeating. Cached per request.
 *
 * @return int[] [ lead, s1, s2, s3 ]
 */
function tdd_section_top_ids( int $term_id ): array {
	static $cache = array();
	if ( isset( $cache[ $term_id ] ) ) {
		return $cache[ $term_id ];
	}
	if ( ! function_exists( 'tdd_core_placement_resolve' ) ) {
		$cache[ $term_id ] = array_map( 'intval', get_posts( array( 'post_type' => 'post', 'cat' => $term_id, 'posts_per_page' => 4, 'fields' => 'ids', 'no_found_rows' => true ) ) );
		return $cache[ $term_id ];
	}
	$lead              = tdd_core_placement_resolve( 'section_lead', 1, array( 'section' => $term_id ) );
	$rest              = tdd_core_placement_resolve( 'section_secondary', 3, array( 'section' => $term_id, 'exclude' => $lead ) );
	$cache[ $term_id ] = array_merge( $lead, $rest );
	return $cache[ $term_id ];
}

add_action(
	'pre_get_posts',
	static function ( WP_Query $q ) {
		if ( is_admin() || ! $q->is_main_query() || $q->is_feed() ) {
			return;
		}
		if ( $q->is_category() || $q->is_tax( array( 'tdd_topic', 'tdd_format' ) ) || $q->is_tag() || $q->is_author() || $q->is_home() ) {
			$q->set( 'posts_per_page', TDD_FEED_PER_PAGE );
			$q->set( 'ignore_sticky_posts', true );
		}
		if ( $q->is_category() ) {
			$term = $q->get_queried_object();
			if ( $term instanceof WP_Term ) {
				// "A story appears once per page": pinned stories never repeat in the feed (on any page).
				$q->set( 'post__not_in', tdd_section_top_ids( (int) $term->term_id ) );
			}
		}
	}
);

/* ---------- Feed rows ---------- */

/**
 * One LatestUpdateRow. $today: time shown as "10:42 AM" (mobile "10:42"); otherwise "9:20 PM"
 * (in date groups) or "Oct 2" (ungrouped lists).
 */
function tdd_latest_row( WP_Post $post, string $time_mode = 'auto', ?array $labels = null ): string {
	// Default (homepage, approved LatestUpdateRow): the section label only.
	$labels = $labels ?? array( 'format' => false, 'breaking' => false );
	$ts   = (int) get_post_timestamp( $post );
	$now  = ( time() - $ts ) < HOUR_IN_SECONDS ? ' tdd-latest-row__now' : '';
	$deck = tdd_has_core() ? tdd_core_deck( $post ) : '';
	$big  = has_post_thumbnail( $post ) && '' !== $deck && tdd_reading_time( $post ) >= 4;
	$iso  = esc_attr( gmdate( 'c', $ts ) );
	if ( 'auto' === $time_mode ) {
		$time_mode = ( time() - $ts ) < DAY_IN_SECONDS ? 'today' : 'date';
	}
	switch ( $time_mode ) {
		case 'today': // Desktop "10:42 AM", approved mobile "10:42".
			$time = '<time datetime="' . $iso . '">' . esc_html( wp_date( 'g:i', $ts ) ) . '<span class="tdd-hide-m"> ' . esc_html( wp_date( 'A', $ts ) ) . '</span></time>';
			break;
		case 'clock':
			$time = '<time datetime="' . $iso . '">' . esc_html( wp_date( 'g:i A', $ts ) ) . '</time>';
			break;
		default:
			$time = tdd_time( $ts, wp_date( 'M j', $ts ) );
	}
	return '<li><a class="tdd-latest-row' . ( $big ? ' tdd-latest-row--thumb' : ' tdd-latest-row--nothumb' ) . '" href="' . esc_url( get_permalink( $post ) ) . '">'
		. '<span class="tdd-latest-row__time' . $now . '">' . $time . '</span>'
		. '<span class="tdd-latest-row__body">' . tdd_labels( $post, $labels )
		. '<span class="tdd-latest-row__title">' . esc_html( get_the_title( $post ) ) . '</span>'
		. ( $big ? '<span class="tdd-latest-row__dek">' . esc_html( $deck ) . '</span>' : '' ) . '</span>'
		. ( $big ? tdd_media( $post, 'tdd-16x9-400', '128px', false ) : '' )
		. '</a></li>';
}

/** Date group header (FeedDateGroup): Today / Yesterday / weekday. */
function tdd_feed_date( int $ts ): string {
	$day   = wp_date( 'Y-m-d', $ts );
	$today = wp_date( 'Y-m-d' );
	$yest  = wp_date( 'Y-m-d', time() - DAY_IN_SECONDS );
	if ( $day === $today ) {
		$b = __( 'Today', 'techdosedaily' );
		$s = wp_date( 'l, F j', $ts );
	} elseif ( $day === $yest ) {
		$b = __( 'Yesterday', 'techdosedaily' );
		$s = wp_date( 'l, F j', $ts );
	} else {
		$b = wp_date( 'l', $ts );
		$s = wp_date( wp_date( 'Y', $ts ) === wp_date( 'Y' ) ? 'F j' : 'F j, Y', $ts );
	}
	return '<div class="tdd-feed-date" data-day="' . esc_attr( $day ) . '"><b>' . esc_html( $b ) . '</b><span>' . esc_html( $s ) . '</span></div>';
}

/** Time-led feed grouped by day: header + <ol> per day. */
function tdd_feed_groups( array $posts, ?array $labels = null ): string {
	$out   = '';
	$day   = '';
	$today = wp_date( 'Y-m-d' );
	foreach ( $posts as $p ) {
		$ts = (int) get_post_timestamp( $p );
		$d  = wp_date( 'Y-m-d', $ts );
		if ( $d !== $day ) {
			$out .= ( $day ? '</ol>' : '' ) . tdd_feed_date( $ts ) . '<ol>';
			$day  = $d;
		}
		$out .= tdd_latest_row( $p, $d === $today ? 'today' : 'clock', $labels );
	}
	return $out ? $out . '</ol>' : '';
}

/* ---------- Cards ---------- */

/** Meta for section cards: news is time-led ("4h ago"), everything else author-led. */
function tdd_card_meta_parts( WP_Post $post ): array {
	$f = tdd_format( $post );
	return ( ! $f || 'news' === $f->slug ) ? array( 'ago' ) : array( 'author', 'read' );
}

/** AnalysisItem byline: avatar · Name · N min read. */
function tdd_anby( WP_Post $post ): string {
	$aid = (int) $post->post_author;
	/* translators: %d: minutes. */
	$read = sprintf( __( '%d min read', 'techdosedaily' ), tdd_reading_time( $post ) );
	return '<div class="tdd-anby">' . str_replace( '<div class="tdd-avatar', '<span class="tdd-avatar', str_replace( '</div>', '</span>', tdd_avatar( $aid ) ) ) . '<span><b>' . esc_html( get_the_author_meta( 'display_name', $aid ) ) . '</b> · ' . esc_html( $read ) . '</span></div>';
}

/* ---------- Masthead, topics, desk ---------- */

/** Topics for the section topic nav: configured order, else most used in the section. */
function tdd_section_nav_topics( WP_Term $term, int $limit = 7 ): array {
	$ids = function_exists( 'tdd_core_section_settings' ) ? tdd_core_section_settings( $term->term_id )['topic_nav'] : array();
	if ( $ids ) {
		return array_values( array_filter( array_map( static fn( $id ) => get_term( $id, 'tdd_topic' ), $ids ), static fn( $t ) => $t instanceof WP_Term ) );
	}
	return function_exists( 'tdd_core_section_topics' ) ? array_column( tdd_core_section_topics( $term->term_id, $limit ), 0 ) : array();
}

/** The section desk (statement, people, "How we cover"): omitted unless people are set. */
function tdd_section_desk( WP_Term $term, bool $mobile = false ): string {
	$s = function_exists( 'tdd_core_section_settings' ) ? tdd_core_section_settings( $term->term_id ) : array( 'desk_members' => array() );
	if ( empty( $s['desk_members'] ) ) {
		return '';
	}
	$people = '';
	foreach ( $s['desk_members'] as $uid ) {
		$title   = trim( (string) get_user_meta( $uid, 'tdd_title', true ) );
		$people .= '<a class="tdd-anby tdd-desk__person" href="' . esc_url( get_author_posts_url( $uid ) ) . '">' . str_replace( array( '<div class="tdd-avatar', '</div>' ), array( '<span class="tdd-avatar', '</span>' ), tdd_avatar( $uid ) ) . '<span><b>' . esc_html( get_the_author_meta( 'display_name', $uid ) ) . '</b>' . ( '' !== $title ? ' · ' . esc_html( $title ) : '' ) . '</span></a>';
	}
	/* translators: %s: section name. */
	$title = sprintf( __( 'The %s desk', 'techdosedaily' ), $term->name );
	/* translators: %s: section name. */
	$how  = '' !== $s['desk_url'] ? '<a class="tdd-btn tdd-btn--text tdd-desk__how" href="' . esc_url( $s['desk_url'] ) . '">' . esc_html( sprintf( __( 'How we cover %s →', 'techdosedaily' ), $term->name ) ) . '</a>' : '';
	$body = '<div class="tdd-sh tdd-sh--small"><h2 class="tdd-sh__title">' . esc_html( $title ) . '</h2></div>'
		. ( '' !== $s['desk_note'] ? '<p class="tdd-desk__note">' . esc_html( $s['desk_note'] ) . '</p>' : '' )
		. '<div class="tdd-desk__people">' . $people . '</div>' . $how;
	return $mobile
		? '<section class="tdd-desk m-section tdd-only-m is-block" aria-label="' . esc_attr( $title ) . '">' . $body . '</section>'
		: '<div class="tdd-desk">' . $body . '</div>';
}

/** "Follow a topic" chips with real story counts. */
function tdd_topic_chips( WP_Term $term, bool $mobile = false ): string {
	$topics = function_exists( 'tdd_core_section_topics' ) ? tdd_core_section_topics( $term->term_id, 8 ) : array();
	if ( count( $topics ) < 2 ) {
		return '';
	}
	$chips = '';
	foreach ( $topics as [ $t, $n ] ) {
		$chips .= '<a href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . ' <span>' . esc_html( number_format_i18n( $n ) ) . '<span class="screen-reader-text"> ' . esc_html( _n( 'story', 'stories', $n, 'techdosedaily' ) ) . '</span></span></a>';
	}
	$head = '<div class="tdd-sh tdd-sh--small"><h2 class="tdd-sh__title">' . esc_html__( 'Follow a topic', 'techdosedaily' ) . '</h2></div><div class="tdd-topics">' . $chips . '</div>';
	if ( $mobile ) {
		return '<section class="m-section tdd-only-m is-block" aria-label="' . esc_attr__( 'Follow a topic', 'techdosedaily' ) . '">' . $head . '</section>';
	}
	/* translators: %s: section name. */
	return '<div class="tdd-follow">' . $head . '<p class="tdd-topics__note">' . esc_html( sprintf( __( 'Counts are %s stories tagged with each topic.', 'techdosedaily' ), $term->name ) ) . '</p></div>';
}
