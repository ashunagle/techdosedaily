<?php
/**
 * Search (approved SearchDesktop / SearchMobile / SearchEmpty): query handling, filters with
 * real counts, TopicMatch, result rows with highlighted terms, empty state.
 *
 * URL parameters (all optional, all real links): s, section[] (slugs), format[] (slugs),
 * date (day|week|month|year), author[] (user nicenames), sort (newest). Search pages are noindex
 * (WordPress core / Yoast); pages are real /page/N/ links.
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

const TDD_SEARCH_PER_PAGE = 10;

/** Sanitised filter state from the request. */
function tdd_search_state(): array {
	static $state = null;
	if ( null !== $state ) {
		return $state;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only GET filters.
	$list  = static fn( string $k ) => isset( $_GET[ $k ] ) ? array_values( array_unique( array_filter( array_map( 'sanitize_title', (array) wp_unslash( $_GET[ $k ] ) ) ) ) ) : array();
	$date  = isset( $_GET['date'] ) ? sanitize_key( wp_unslash( $_GET['date'] ) ) : '';
	$sort  = isset( $_GET['sort'] ) && 'newest' === sanitize_key( wp_unslash( $_GET['sort'] ) ) ? 'newest' : 'relevance';
	// phpcs:enable
	$state = array(
		'section' => array_slice( $list( 'section' ), 0, 12 ),
		'format'  => array_slice( $list( 'format' ), 0, 6 ),
		'author'  => array_slice( $list( 'author' ), 0, 12 ),
		'date'    => in_array( $date, array( 'day', 'week', 'month', 'year' ), true ) ? $date : '',
		'sort'    => $sort,
	);
	return $state;
}

function tdd_search_date_after( string $range ): string {
	$map = array( 'day' => DAY_IN_SECONDS, 'week' => WEEK_IN_SECONDS, 'month' => MONTH_IN_SECONDS, 'year' => YEAR_IN_SECONDS );
	return isset( $map[ $range ] ) ? gmdate( 'Y-m-d H:i:s', time() - $map[ $range ] ) : '';
}

add_action(
	'pre_get_posts',
	static function ( WP_Query $q ) {
		if ( is_admin() || ! $q->is_main_query() || ! $q->is_search() ) {
			return;
		}
		$st = tdd_search_state();
		$q->set( 'post_type', 'post' );
		$q->set( 'posts_per_page', TDD_SEARCH_PER_PAGE );
		$q->set( 'ignore_sticky_posts', true );
		$tax = array();
		if ( $st['section'] ) {
			$tax[] = array( 'taxonomy' => 'category', 'field' => 'slug', 'terms' => $st['section'] );
		}
		if ( $st['format'] ) {
			$tax[] = array( 'taxonomy' => 'tdd_format', 'field' => 'slug', 'terms' => $st['format'] );
		}
		if ( $tax ) {
			$q->set( 'tax_query', $tax ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		if ( $st['author'] ) {
			$ids = array();
			foreach ( $st['author'] as $nicename ) {
				$u = get_user_by( 'slug', $nicename );
				if ( $u ) {
					$ids[] = $u->ID;
				}
			}
			$q->set( 'author__in', $ids ? $ids : array( 0 ) );
		}
		if ( $st['date'] ) {
			$q->set( 'date_query', array( array( 'after' => tdd_search_date_after( $st['date'] ), 'column' => 'post_date_gmt' ) ) );
		}
		if ( 'newest' === $st['sort'] ) {
			$q->set( 'orderby', 'date' );
			$q->set( 'order', 'DESC' );
		}
	}
);

/**
 * Facet counts for the search term alone (so every option shows how many stories it would
 * return). Capped at 2,000 matches; cached for 10 minutes per term.
 *
 * @return array{section:array,format:array,author:array,date:array,total:int}
 */
function tdd_search_facets( string $term ): array {
	$key    = 'tdd_sfacets_' . md5( strtolower( $term ) . '|' . wp_cache_get_last_changed( 'posts' ) );
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		return $cached;
	}
	$ids = get_posts(
		array(
			'post_type'        => 'post',
			's'                => $term,
			'posts_per_page'   => 2000,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);
	$out = array( 'section' => array(), 'format' => array(), 'author' => array(), 'date' => array( 'day' => 0, 'week' => 0, 'month' => 0, 'year' => 0 ), 'total' => count( $ids ) );
	if ( $ids ) {
		foreach ( wp_get_object_terms( $ids, array( 'category', 'tdd_format' ), array( 'fields' => 'all_with_object_id' ) ) as $t ) {
			$bucket = 'category' === $t->taxonomy ? 'section' : 'format';
			if ( 'section' === $bucket && ( 0 !== (int) $t->parent || 'uncategorized' === $t->slug ) ) {
				continue;
			}
			$out[ $bucket ][ $t->slug ]['name']  = $t->name;
			$out[ $bucket ][ $t->slug ]['count'] = ( $out[ $bucket ][ $t->slug ]['count'] ?? 0 ) + 1;
		}
		$now = time();
		foreach ( $ids as $id ) {
			$p    = get_post( $id );
			$nice = get_the_author_meta( 'user_nicename', $p->post_author );
			$out['author'][ $nice ]['name']  = get_the_author_meta( 'display_name', $p->post_author );
			$out['author'][ $nice ]['count'] = ( $out['author'][ $nice ]['count'] ?? 0 ) + 1;
			$age = $now - (int) get_post_timestamp( $p );
			foreach ( array( 'day' => DAY_IN_SECONDS, 'week' => WEEK_IN_SECONDS, 'month' => MONTH_IN_SECONDS, 'year' => YEAR_IN_SECONDS ) as $k => $span ) {
				if ( $age <= $span ) {
					++$out['date'][ $k ];
				}
			}
		}
		foreach ( array( 'section', 'format', 'author' ) as $k ) {
			uasort( $out[ $k ], static fn( $a, $b ) => $b['count'] <=> $a['count'] );
		}
	}
	set_transient( $key, $out, 10 * MINUTE_IN_SECONDS );
	return $out;
}

/** Search URL with a modified filter state (keeps the term and other filters; resets paging). */
function tdd_search_url( array $changes = array() ): string {
	$st   = array_merge( tdd_search_state(), $changes );
	$args = array( 's' => get_search_query( false ) );
	foreach ( array( 'section', 'format', 'author' ) as $k ) {
		if ( $st[ $k ] ) {
			$args[ $k ] = $st[ $k ];
		}
	}
	if ( $st['date'] ) {
		$args['date'] = $st['date'];
	}
	if ( 'newest' === $st['sort'] ) {
		$args['sort'] = 'newest';
	}
	return add_query_arg( urlencode_deep( $args ), home_url( '/' ) );
}

/** Words to highlight (3+ characters). */
function tdd_search_words( string $term ): array {
	return array_values( array_filter( preg_split( '/\s+/u', trim( $term ) ), static fn( $w ) => mb_strlen( $w ) >= 3 ) );
}

/** Escape text, then wrap matched words in <mark> (approved ResultRow). */
function tdd_highlight( string $text, array $words ): string {
	$html = esc_html( $text );
	if ( ! $words ) {
		return $html;
	}
	$alts = implode( '|', array_map( static fn( $w ) => preg_quote( esc_html( $w ), '/' ), $words ) );
	return (string) preg_replace( '/(' . $alts . ')/iu', '<mark>$1</mark>', $html );
}

/** Excerpt for a result: deck / hand-written excerpt, else a body snippet around the first match. */
function tdd_search_excerpt( WP_Post $p, array $words ): string {
	$deck = tdd_deck( $p );
	if ( '' !== $deck ) {
		return $deck;
	}
	$body = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( $p->post_content ) ) ) );
	$pos  = 0;
	foreach ( $words as $w ) {
		$i = mb_stripos( $body, $w );
		if ( false !== $i ) {
			$pos = max( 0, $i - 60 );
			break;
		}
	}
	$snip = mb_substr( $body, $pos, 200 );
	return ( $pos > 0 ? '… ' : '' ) . $snip . ( mb_strlen( $body ) > $pos + 200 ? ' …' : '' );
}

function tdd_search_row( WP_Post $p, array $words ): string {
	$thumb = has_post_thumbnail( $p );
	$ts    = (int) get_post_timestamp( $p );
	$upd   = tdd_has_core() ? tdd_core_updated_at( $p ) : null;
	$date  = $upd && $upd->getTimestamp() > $ts + DAY_IN_SECONDS
		/* translators: %s: date. */
		? sprintf( esc_html__( 'Updated %s', 'techdosedaily' ), tdd_time( $upd->getTimestamp(), wp_date( 'M j, Y', $upd->getTimestamp() ) ) )
		: tdd_time( $ts, wp_date( 'M j, Y', $ts ) );
	/* translators: %s: author name. */
	$meta = '<span class="tdd-meta"><span>' . sprintf( esc_html__( 'By %s', 'techdosedaily' ), '<b>' . esc_html( get_the_author_meta( 'display_name', $p->post_author ) ) . '</b>' ) . '</span><span>' . $date . '</span><span>' . esc_html( sprintf( /* translators: %d: minutes */ __( '%d min read', 'techdosedaily' ), tdd_reading_time( $p ) ) ) . '</span></span>';
	$ex   = tdd_search_excerpt( $p, $words );
	return '<li><a class="tdd-result' . ( $thumb ? '' : ' tdd-result--nothumb' ) . '" href="' . esc_url( get_permalink( $p ) ) . '"><span class="tdd-result__body">' . tdd_labels( $p, array( 'topic' => true, 'breaking' => false, 'wrap' => true, 'tag' => 'span' ) )
		. '<span class="tdd-result__title">' . tdd_highlight( get_the_title( $p ), $words ) . '</span>'
		. ( '' !== $ex ? '<span class="tdd-result__excerpt">' . tdd_highlight( $ex, $words ) . '</span>' : '' ) . $meta . '</span>'
		. ( $thumb ? tdd_media( $p, 'tdd-16x9-400', '(max-width: 767px) 88px, 176px', false ) : '' ) . '</a></li>';
}

/** TopicMatch: the query names a topic or an author (exact, case-insensitive; singular/plural). */
function tdd_search_match( string $term ): string {
	$t = trim( $term );
	if ( '' === $t ) {
		return '';
	}
	$cands = array_unique( array( $t, rtrim( $t, 's' ), $t . 's' ) );
	foreach ( $cands as $c ) {
		$topic = get_term_by( 'name', $c, 'tdd_topic' );
		if ( $topic && $topic->count > 0 ) {
			/* translators: %s: number of stories. */
			$n = sprintf( _n( '%s story', '%s stories', $topic->count, 'techdosedaily' ), number_format_i18n( $topic->count ) );
			return '<div class="tdd-topicmatch">' . tdd_icon( 'pulse', '' ) . '<span>' . esc_html__( 'Topic', 'techdosedaily' ) . ' · <b>' . esc_html( $topic->name ) . '</b> · ' . esc_html( $n ) . '</span><a class="tdd-btn tdd-btn--text" href="' . esc_url( get_term_link( $topic ) ) . '"><span class="tdd-hide-m">' . esc_html__( 'Go to topic →', 'techdosedaily' ) . '</span><span class="tdd-only-m">' . esc_html__( 'Go →', 'techdosedaily' ) . '</span></a></div>';
		}
	}
	$users = get_users( array( 'search' => $t, 'search_columns' => array( 'display_name' ), 'number' => 1, 'has_published_posts' => array( 'post' ) ) );
	if ( $users && 0 === strcasecmp( $users[0]->display_name, $t ) ) {
		$u = $users[0];
		$n = (int) count_user_posts( $u->ID, 'post', true );
		/* translators: %s: number of stories. */
		$n = sprintf( _n( '%s story', '%s stories', $n, 'techdosedaily' ), number_format_i18n( $n ) );
		return '<div class="tdd-topicmatch">' . tdd_icon( 'pulse', '' ) . '<span>' . esc_html__( 'Author', 'techdosedaily' ) . ' · <b>' . esc_html( $u->display_name ) . '</b> · ' . esc_html( $n ) . '</span><a class="tdd-btn tdd-btn--text" href="' . esc_url( get_author_posts_url( $u->ID ) ) . '"><span class="tdd-hide-m">' . esc_html__( 'Go to author →', 'techdosedaily' ) . '</span><span class="tdd-only-m">' . esc_html__( 'Go →', 'techdosedaily' ) . '</span></a></div>';
	}
	return '';
}

/** Filters (SearchFilters): GET form; auto-applies with JS, "Apply filters" button without. */
function tdd_search_filters( array $facets ): string {
	$st     = tdd_search_state();
	$active = count( $st['section'] ) + count( $st['format'] ) + count( $st['author'] ) + ( $st['date'] ? 1 : 0 );
	$box    = static function ( string $name, string $value, string $label, int $count, bool $checked, string $type = 'checkbox' ): string {
		return '<label><input type="' . $type . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . checked( $checked, true, false ) . '>' . esc_html( $label ) . '<span>' . esc_html( number_format_i18n( $count ) ) . '</span></label>';
	};
	$sets = '';
	$defs = array(
		'section' => array( __( 'Section', 'techdosedaily' ), 6 ),
		'format'  => array( __( 'Format', 'techdosedaily' ), 6 ),
	);
	foreach ( $defs as $k => [ $legend, $max ] ) {
		if ( ! $facets[ $k ] ) {
			continue;
		}
		$rows = '';
		foreach ( array_slice( $facets[ $k ], 0, $max, true ) as $slug => $f ) {
			$rows .= $box( $k . '[]', $slug, $f['name'], $f['count'], in_array( $slug, $st[ $k ], true ) );
		}
		$sets .= '<fieldset><legend>' . esc_html( $legend ) . '</legend>' . $rows . '</fieldset>';
	}
	$dates = array( '' => __( 'Any time', 'techdosedaily' ), 'day' => __( 'Past 24 hours', 'techdosedaily' ), 'week' => __( 'Past week', 'techdosedaily' ), 'month' => __( 'Past month', 'techdosedaily' ), 'year' => __( 'Past year', 'techdosedaily' ) );
	$rows  = '';
	foreach ( $dates as $k => $label ) {
		$rows .= $box( 'date', $k, $label, '' === $k ? $facets['total'] : $facets['date'][ $k ], $st['date'] === $k, 'radio' );
	}
	$sets .= '<fieldset><legend>' . esc_html__( 'Date', 'techdosedaily' ) . '</legend>' . $rows . '</fieldset>';
	if ( count( $facets['author'] ) > 1 ) {
		$rows = '';
		foreach ( array_slice( $facets['author'], 0, 4, true ) as $nice => $f ) {
			$rows .= $box( 'author[]', $nice, $f['name'], $f['count'], in_array( $nice, $st['author'], true ) );
		}
		$sets .= '<fieldset><legend>' . esc_html__( 'Author', 'techdosedaily' ) . '</legend>' . $rows . '</fieldset>';
	}
	$clear = $active ? '<a class="tdd-btn tdd-btn--text" href="' . esc_url( tdd_search_url( array( 'section' => array(), 'format' => array(), 'author' => array(), 'date' => '' ) ) ) . '">' . esc_html__( 'Clear all', 'techdosedaily' ) . '</a>' : '';
	$keep  = '<input type="hidden" name="s" value="' . esc_attr( get_search_query( false ) ) . '">' . ( 'newest' === $st['sort'] ? '<input type="hidden" name="sort" value="newest">' : '' );
	return '<form class="tdd-filters" id="tdd-filters" method="get" action="' . esc_url( home_url( '/' ) ) . '" aria-labelledby="tdd-filters-title" data-tdd-filters>'
		. '<div class="tdd-filters__head"><h2 id="tdd-filters-title">' . esc_html__( 'Filters', 'techdosedaily' ) . '</h2>' . $clear
		. '<button type="button" class="tdd-btn tdd-btn--icon tdd-filters__close" aria-label="' . esc_attr__( 'Close filters', 'techdosedaily' ) . '" hidden>' . tdd_icon( 'x', 'tdd-ic' ) . '</button></div>'
		. $keep . $sets . '<button type="submit" class="tdd-btn tdd-btn--primary tdd-filters__apply">' . esc_html__( 'Show results', 'techdosedaily' ) . '</button></form>';
}

/** Mobile filter bar: "Filters (n)" + removable chips for active filters. */
function tdd_search_filterbar( array $facets ): string {
	$st    = tdd_search_state();
	$chips = '';
	$x     = tdd_icon( 'x', '' );
	foreach ( array( 'section', 'format', 'author' ) as $k ) {
		foreach ( $st[ $k ] as $v ) {
			$name   = $facets[ $k ][ $v ]['name'] ?? $v;
			/* translators: %s: filter value. */
			$chips .= '<span class="m-chip">' . esc_html( $name ) . '<a class="m-chip__x" href="' . esc_url( tdd_search_url( array( $k => array_values( array_diff( $st[ $k ], array( $v ) ) ) ) ) ) . '" aria-label="' . esc_attr( sprintf( __( 'Remove %s filter', 'techdosedaily' ), $name ) ) . '">' . $x . '</a></span>';
		}
	}
	if ( $st['date'] ) {
		$labels = array( 'day' => __( 'Past 24 hours', 'techdosedaily' ), 'week' => __( 'Past week', 'techdosedaily' ), 'month' => __( 'Past month', 'techdosedaily' ), 'year' => __( 'Past year', 'techdosedaily' ) );
		$chips .= '<span class="m-chip">' . esc_html( $labels[ $st['date'] ] ) . '<a class="m-chip__x" href="' . esc_url( tdd_search_url( array( 'date' => '' ) ) ) . '" aria-label="' . esc_attr__( 'Remove date filter', 'techdosedaily' ) . '">' . $x . '</a></span>';
	}
	$n = count( $st['section'] ) + count( $st['format'] ) + count( $st['author'] ) + ( $st['date'] ? 1 : 0 );
	/* translators: %d: number of active filters. */
	$label = $n ? sprintf( __( 'Filters (%d)', 'techdosedaily' ), $n ) : __( 'Filters', 'techdosedaily' );
	return '<div class="m-filterbar tdd-only-m"><a class="tdd-btn tdd-btn--secondary" href="#tdd-filters" data-tdd-filters-open aria-controls="tdd-filters" aria-expanded="false">' . tdd_icon( 'filter', 'tdd-ic tdd-ic--16' ) . esc_html( $label ) . '</a>' . $chips . '</div>';
}

/** SearchEmpty. */
function tdd_search_empty( string $term ): string {
	$st      = tdd_search_state();
	$tips    = '<li>' . esc_html__( 'Check the spelling or try fewer words', 'techdosedaily' ) . '</li>';
	$filters = array();
	foreach ( $st['section'] as $s ) {
		$t = get_term_by( 'slug', $s, 'category' );
		$filters[] = '<b>' . esc_html( $t ? $t->name : $s ) . '</b>';
	}
	$dates = array( 'day' => __( 'the past 24 hours', 'techdosedaily' ), 'week' => __( 'the past week', 'techdosedaily' ), 'month' => __( 'the past month', 'techdosedaily' ), 'year' => __( 'the past year', 'techdosedaily' ) );
	if ( $filters || $st['date'] || $st['format'] || $st['author'] ) {
		$what  = $filters ? implode( ', ', $filters ) : esc_html__( 'some stories', 'techdosedaily' );
		$when  = $st['date'] ? ' ' . esc_html__( 'in', 'techdosedaily' ) . ' <b>' . esc_html( $dates[ $st['date'] ] ) . '</b>' : '';
		$tips .= '<li>' . esc_html__( 'Remove filters — you’re searching only', 'techdosedaily' ) . ' ' . $what . $when . '</li>';
	}
	$tips   .= '<li>' . esc_html__( 'Search for a company or product name', 'techdosedaily' ) . '</li>';
	$popular = '';
	foreach ( array_slice( tdd_menu_items( 'trending' ), 0, 4 ) as [ $label, $url ] ) {
		$popular .= '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}
	$links = '<a class="tdd-btn tdd-btn--secondary" href="' . esc_url( tdd_latest_url() ) . '">' . esc_html__( 'See latest news', 'techdosedaily' ) . '</a>';
	$sec   = $st['section'] ? get_term_by( 'slug', $st['section'][0], 'category' ) : get_term_by( 'slug', 'ai', 'category' );
	if ( $sec ) {
		/* translators: %s: section name. */
		$links .= '<a class="tdd-btn tdd-btn--secondary" href="' . esc_url( get_term_link( $sec ) ) . '">' . esc_html( sprintf( __( 'Browse %s', 'techdosedaily' ), $sec->name ) ) . '</a>';
	}
	/* translators: %s: search term. */
	$title = '' !== $term ? sprintf( __( 'No stories match “%s”', 'techdosedaily' ), $term ) : __( 'What are you looking for?', 'techdosedaily' );
	return '<section class="tdd-empty" aria-live="polite">' . ( '' !== $term ? '<span class="tdd-label tdd-label--plain">' . esc_html__( '0 results', 'techdosedaily' ) . '</span>' : '' ) . '<h2>' . esc_html( $title ) . '</h2>'
		. ( '' !== $term ? '<ul>' . $tips . '</ul>' : '' )
		. ( '' !== $popular ? '<p class="tdd-empty__pop">' . esc_html__( 'Popular right now', 'techdosedaily' ) . '</p><div class="tdd-topics">' . $popular . '</div>' : '' )
		. '<div class="tdd-empty__links">' . $links . '</div></section>';
}
