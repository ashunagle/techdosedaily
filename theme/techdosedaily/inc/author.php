<?php
/**
 * Author page (approved AuthorDesktop / AuthorMobile): masthead, beats, featured reporting,
 * archive rows, "About this reporter" and the empty state for new writers.
 * Only true, author-approved profile fields are printed; every missing field is omitted.
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

/** The author being viewed (author archive), or null. */
function tdd_author_user(): ?WP_User {
	$u = is_author() ? get_queried_object() : null;
	return $u instanceof WP_User ? $u : null;
}

function tdd_first_name( WP_User $u ): string {
	return (string) strtok( $u->display_name, ' ' );
}

/** Published story count for an author. */
function tdd_author_story_count( int $user_id ): int {
	return (int) count_user_posts( $user_id, 'post', true );
}

/** Large author photo (AuthorMasthead): real portrait or initials (decorative; the name is the H1). */
function tdd_author_photo( WP_User $u ): string {
	$photo = (int) get_user_meta( $u->ID, 'tdd_photo', true );
	$img   = $photo ? wp_get_attachment_image( $photo, 'tdd-1x1', false, array( 'alt' => '', 'loading' => 'eager' ) ) : '';
	return '<div class="tdd-aphoto" aria-hidden="true">' . ( '' !== $img ? $img : esc_html( tdd_initials( $u->display_name ) ) ) . '</div>';
}

/** The section desk this person belongs to (section settings), or null. */
function tdd_author_desk( WP_User $u ): ?WP_Term {
	foreach ( get_terms( array( 'taxonomy' => 'category', 'parent' => 0, 'hide_empty' => false ) ) as $t ) {
		$members = function_exists( 'tdd_core_section_settings' ) ? tdd_core_section_settings( $t->term_id )['desk_members'] : array();
		if ( in_array( $u->ID, $members, true ) ) {
			return $t;
		}
	}
	return null;
}

/** Contact & follow links: [ label, url, kind(link|email|rss) ]. Real profiles only. */
function tdd_author_links( WP_User $u ): array {
	$out = array();
	foreach ( (array) get_user_meta( $u->ID, 'tdd_social', true ) as $s ) {
		if ( ! empty( $s['url'] ) && ! empty( $s['label'] ) ) {
			$out[] = array( $s['label'], $s['url'], 'link' );
		}
	}
	if ( get_user_meta( $u->ID, 'tdd_public_email', true ) ) {
		$out[] = array( __( 'Email', 'techdosedaily' ), 'mailto:' . antispambot( $u->user_email ), 'email' );
	}
	if ( tdd_author_story_count( $u->ID ) > 0 ) {
		$out[] = array( __( 'RSS', 'techdosedaily' ), get_author_feed_link( $u->ID ), 'rss' );
	}
	return $out;
}

function tdd_author_masthead( WP_User $u ): string {
	$name   = $u->display_name;
	$title  = trim( (string) get_user_meta( $u->ID, 'tdd_title', true ) );
	$loc    = trim( (string) get_user_meta( $u->ID, 'tdd_location', true ) );
	$since  = trim( (string) get_user_meta( $u->ID, 'tdd_covering_since', true ) );
	$note   = trim( (string) get_user_meta( $u->ID, 'tdd_note', true ) );
	$bio    = trim( (string) $u->description );
	$bio    = '' !== $bio ? $bio : trim( (string) get_user_meta( $u->ID, 'tdd_short_bio', true ) );
	$how    = tdd_page_url( 'editorial-standards' );
	$links  = tdd_author_links( $u );
	$ameta  = ( '' !== $title ? '<b>' . esc_html( $title ) . '</b>' : '' ) . '<span class="tdd-hide-m">' . esc_html( get_bloginfo( 'name' ) ) . '</span>' . ( '' !== $loc ? '<span class="tdd-hide-m">' . esc_html( $loc ) . '</span>' : '' );
	$m2     = array_filter( array( $loc, $since ) );
	$desk_l = '';
	$mob_l  = '';
	foreach ( $links as [ $label, $url, $kind ] ) {
		if ( 'link' === $kind ) {
			$desk_l .= '<a href="' . esc_url( $url ) . '" rel="me noopener">' . esc_html( $label ) . tdd_icon( 'external', '' ) . '</a>';
			/* translators: 1: author name, 2: network. */
			$mob_l .= '<a href="' . esc_url( $url ) . '" rel="me noopener" aria-label="' . esc_attr( sprintf( __( '%1$s on %2$s', 'techdosedaily' ), $name, $label ) ) . '">' . esc_html( $label ) . '</a>';
		} else {
			$icon    = tdd_icon( 'email' === $kind ? 'mail' : 'rss', '' );
			/* translators: %s: author name. */
			$aria    = 'email' === $kind ? sprintf( __( 'Email %s', 'techdosedaily' ), $name ) : sprintf( __( 'RSS feed of %s’s stories', 'techdosedaily' ), $name );
			$desk_l .= '<a href="' . esc_url( $url ) . '">' . $icon . esc_html( $label ) . '</a>';
			$mob_l  .= '<a href="' . esc_url( $url ) . '" aria-label="' . esc_attr( $aria ) . '">' . $icon . '</a>';
		}
	}
	$tips = tdd_page_url( 'tips' );
	$tip  = '';
	if ( $tips || get_user_meta( $u->ID, 'tdd_public_email', true ) ) {
		/* translators: %s: author first name. */
		$tip = '<p class="tdd-asocial__tip">' . esc_html__( 'Have a tip?', 'techdosedaily' ) . ' ' . ( get_user_meta( $u->ID, 'tdd_public_email', true ) ? esc_html( sprintf( __( 'Email %s', 'techdosedaily' ), tdd_first_name( $u ) ) ) . ( $tips ? ', ' . esc_html__( 'or use our', 'techdosedaily' ) . ' <a href="' . esc_url( $tips ) . '">' . esc_html__( 'secure tip line', 'techdosedaily' ) . '</a>' : '' ) : esc_html__( 'Use our', 'techdosedaily' ) . ' <a href="' . esc_url( $tips ) . '">' . esc_html__( 'secure tip line', 'techdosedaily' ) . '</a>' ) . '.</p>';
	}
	$beats = '';
	foreach ( (array) get_user_meta( $u->ID, 'tdd_beats', true ) as $tid ) {
		$t = get_term( (int) $tid, 'tdd_topic' );
		if ( $t instanceof WP_Term ) {
			$beats .= '<a class="tdd-abeat" href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a>';
		}
	}
	$identity = '<div class="tdd-amast__id"><h1 class="tdd-amast__name">' . esc_html( $name ) . '</h1>'
		. ( '' !== $ameta ? '<div class="tdd-ameta">' . $ameta . '</div>' : '' )
		. ( '' !== $bio ? '<p class="tdd-amast__bio">' . esc_html( $bio ) . '</p>' : '' )
		. ( '' !== $since ? '<p class="tdd-amast__since tdd-hide-m">' . esc_html( $since ) . '</p>' : '' )
		. ( $m2 ? '<div class="m-ameta2 tdd-only-m is-block"><span>' . implode( '</span><span>', array_map( 'esc_html', $m2 ) ) . '</span></div>' : '' )
		. ( '' !== $mob_l ? '<div class="m-asocial tdd-only-m" role="group" aria-label="' . esc_attr__( 'Contact and follow', 'techdosedaily' ) . '">' . $mob_l . '</div>' : '' )
		. ( '' !== $note ? '<p class="tdd-amast__note">' . esc_html( $note ) . ( $how ? ' <a href="' . esc_url( $how ) . '">' . esc_html__( 'How we report →', 'techdosedaily' ) . '</a>' : '' ) . '</p>' : '' )
		. '</div>';
	$social = '' !== $desk_l ? '<div class="tdd-asocial tdd-hide-m" role="group" aria-labelledby="tdd-asocial-title"><div class="tdd-asocial__title" id="tdd-asocial-title">' . esc_html__( 'Contact & follow', 'techdosedaily' ) . '</div>' . $desk_l . $tip . '</div>' : '';
	/* translators: %s: author name. */
	return '<section class="tdd-amast m-amast" aria-label="' . esc_attr( sprintf( __( 'About %s', 'techdosedaily' ), $name ) ) . '">' . tdd_author_photo( $u ) . $identity . $social . '</section>'
		. ( '' !== $beats ? '<div class="tdd-abeats" role="group" aria-label="' . esc_attr__( 'Beats', 'techdosedaily' ) . '"><span class="tdd-abeats__title">' . esc_html__( 'Covers', 'techdosedaily' ) . '</span>' . $beats . '</div>' : '' );
}

/** Featured Reporting: editor-selected (user meta) else the author's newest analysis/explainers. */
function tdd_author_featured( WP_User $u ): string {
	$ids    = array_map( 'intval', (array) get_user_meta( $u->ID, 'tdd_featured_posts', true ) );
	$posts  = array_values( array_filter( array_map( 'get_post', array_filter( $ids ) ), static fn( $p ) => $p && 'publish' === $p->post_status ) );
	$chosen = (bool) $posts;
	if ( ! $posts ) {
		$posts = get_posts( array( 'post_type' => 'post', 'author' => $u->ID, 'posts_per_page' => 3, 'no_found_rows' => true, 'tax_query' => array( array( 'taxonomy' => 'tdd_format', 'field' => 'slug', 'terms' => array( 'analysis', 'explainer' ) ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	if ( ! $posts ) {
		return '';
	}
	foreach ( $posts as $p ) {
		tdd_mark_shown( $p->ID );
	}
	$lead = array_shift( $posts );
	$deck = tdd_deck( $lead );
	/* translators: %d: minutes. */
	$read = static fn( WP_Post $p ) => '<span>' . esc_html( sprintf( __( '%d min read', 'techdosedaily' ), tdd_reading_time( $p ) ) ) . '</span>';
	$date = static fn( WP_Post $p ) => '<span>' . tdd_time( (int) get_post_timestamp( $p ), wp_date( 'M j, Y', (int) get_post_timestamp( $p ) ) ) . '</span>';
	$l    = '<article class="tdd-afeat__lead tdd-story m-ai-lead">' . tdd_media( $lead, 'tdd-16x9-800', '(max-width: 767px) 100vw, 420px' ) . '<div class="tdd-afeat__text">' . tdd_labels( $lead, array( 'topic' => true, 'wrap' => true ) )
		. '<h3 class="tdd-story__title"><a href="' . esc_url( get_permalink( $lead ) ) . '">' . esc_html( get_the_title( $lead ) ) . '</a></h3>'
		. ( '' !== $deck ? '<p class="tdd-story__summary tdd-hide-m">' . esc_html( $deck ) . '</p>' : '' ) . '<div class="tdd-meta">' . $date( $lead ) . $read( $lead ) . '</div></div></article>';
	$side = '';
	$mob  = '';
	foreach ( $posts as $p ) {
		$t     = '<h3 class="tdd-story__title"><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></h3>';
		$side .= '<article class="tdd-story">' . tdd_labels( $p, array( 'one' => true ) ) . $t . '<div class="tdd-meta">' . $date( $p ) . $read( $p ) . '</div></article>';
		$mob  .= '<article class="tdd-story tdd-story--compact">' . tdd_labels( $p, array( 'one' => true, 'wrap' => true ) ) . $t . '<div class="tdd-meta">' . $date( $p ) . '</div></article>';
	}
	$desc = $chosen ? __( 'Selected analysis and explainers, chosen by editors', 'techdosedaily' ) : __( 'Recent analysis and explainers', 'techdosedaily' );
	return '<section class="ap-sec tdd-afeat-sec" aria-labelledby="tdd-afeat-title"><div class="tdd-sh tdd-section-rule"><div><h2 class="tdd-sh__title" id="tdd-afeat-title">' . esc_html__( 'Featured Reporting', 'techdosedaily' ) . '</h2><p class="tdd-sh__desc tdd-hide-m">' . esc_html( $desc ) . '</p></div></div>'
		. '<div class="tdd-afeat">' . $l . ( '' !== $side ? '<div class="tdd-afeat__side tdd-hide-m">' . $side . '</div><div class="m-list tdd-afeat__mlist tdd-only-m is-block">' . $mob . '</div>' : '' ) . '</div></section>';
}

/** One AuthorArticleRow. */
function tdd_author_row( WP_Post $p ): string {
	$deck  = tdd_deck( $p );
	$thumb = has_post_thumbnail( $p ) && '' !== $deck && tdd_reading_time( $p ) >= 4;
	$ts    = (int) get_post_timestamp( $p );
	/* translators: %d: minutes. */
	$meta = '<span class="tdd-meta"><span>' . tdd_time( $ts, wp_date( 'M j, Y', $ts ) ) . '</span><span>' . esc_html( sprintf( __( '%d min read', 'techdosedaily' ), tdd_reading_time( $p ) ) ) . '</span></span>';
	return '<li><a class="tdd-arow' . ( $thumb ? '' : ' tdd-arow--nothumb' ) . '" href="' . esc_url( get_permalink( $p ) ) . '"><span class="tdd-arow__body">' . tdd_labels( $p, array( 'topic' => true, 'breaking' => false, 'wrap' => true, 'tag' => 'span' ) )
		. '<span class="tdd-arow__title">' . esc_html( get_the_title( $p ) ) . '</span>' . ( '' !== $deck ? '<span class="tdd-arow__ex">' . esc_html( $deck ) . '</span>' : '' ) . $meta . '</span>'
		. ( $thumb ? tdd_media( $p, 'tdd-16x9-400', '(max-width: 767px) 88px, 128px', false ) : '' ) . '</a></li>';
}

/** AuthorEmptyState: no placeholder stories; points to the section the writer covers. */
function tdd_author_empty( WP_User $u ): string {
	$first = tdd_first_name( $u );
	$desk  = tdd_author_desk( $u );
	$links = '';
	$line  = __( 'Until their first story publishes, the latest coverage is one click away.', 'techdosedaily' );
	if ( $desk ) {
		/* translators: %s: section name. */
		$line   = sprintf( __( 'Until their first story publishes, here is the latest from the %s section they cover.', 'techdosedaily' ), $desk->name );
		/* translators: %s: section name. */
		$links .= '<a class="tdd-btn tdd-btn--secondary" href="' . esc_url( get_term_link( $desk ) ) . '">' . esc_html( sprintf( __( 'Latest in %s', 'techdosedaily' ), $desk->name ) ) . '</a>';
	} else {
		$links .= '<a class="tdd-btn tdd-btn--secondary" href="' . esc_url( tdd_latest_url() ) . '">' . esc_html__( 'Latest stories', 'techdosedaily' ) . '</a>';
	}
	/* translators: %s: first name. */
	return '<div class="tdd-aempty"><span class="tdd-label tdd-label--plain">' . esc_html__( 'No stories yet', 'techdosedaily' ) . '</span><h3>' . esc_html( sprintf( __( '%s’s first stories are on the way', 'techdosedaily' ), $first ) ) . '</h3><p>' . esc_html( $line ) . '</p><div class="tdd-empty__links">' . $links . '</div></div>';
}

/** About this reporter (dl): role, based in, focus (beats), editor. Missing rows omitted. */
function tdd_author_about( WP_User $u ): string {
	$rows  = array();
	$title = trim( (string) get_user_meta( $u->ID, 'tdd_title', true ) );
	if ( '' !== $title ) {
		$rows[] = array( __( 'Role', 'techdosedaily' ), esc_html( $title . ', ' . get_bloginfo( 'name' ) ) );
	}
	$loc = trim( (string) get_user_meta( $u->ID, 'tdd_location', true ) );
	if ( '' !== $loc ) {
		$rows[] = array( __( 'Based in', 'techdosedaily' ), esc_html( $loc ) );
	}
	$beats = array_filter( array_map( static fn( $id ) => get_term( (int) $id, 'tdd_topic' ), (array) get_user_meta( $u->ID, 'tdd_beats', true ) ), static fn( $t ) => $t instanceof WP_Term );
	if ( $beats ) {
		$rows[] = array( __( 'Focus', 'techdosedaily' ), esc_html( implode( ', ', array_slice( wp_list_pluck( $beats, 'name' ), 0, 3 ) ) ) );
	}
	$ed = (int) get_user_meta( $u->ID, 'tdd_editor_user', true );
	if ( $ed && get_userdata( $ed ) ) {
		$rows[] = array( __( 'Editor', 'techdosedaily' ), '<a class="tdd-aabout__editor" href="' . esc_url( get_author_posts_url( $ed ) ) . '">' . esc_html( get_userdata( $ed )->display_name ) . '</a>' );
	}
	if ( ! $rows ) {
		return '';
	}
	$dl = '';
	foreach ( $rows as [ $dt, $dd ] ) {
		$dl .= '<div><dt>' . esc_html( $dt ) . '</dt><dd>' . $dd . '</dd></div>';
	}
	return '<div class="tdd-sh tdd-sh--small"><h2 class="tdd-sh__title">' . esc_html__( 'About this reporter', 'techdosedaily' ) . '</h2></div><div class="tdd-aabout"><dl>' . $dl . '</dl></div>';
}

/* Robots for empty author pages: TechDoseDaily Core (seo.php, tdd_core_noindex_reason). */
