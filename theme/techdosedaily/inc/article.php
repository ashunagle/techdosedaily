<?php
/**
 * Article (single story) presentation: ArticleHeader, share controls, notices, sources,
 * tags, author card, TOC, sidebar modules and the mapping of core content blocks
 * (heading, image, quote, table) to the approved article markup.
 *
 * Every component is printed only when its data exists — no placeholder people, sources,
 * corrections or takeaways (see PRE-LAUNCH-PLACEHOLDERS.md).
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Core content blocks → approved article markup ---------- */

/** True while a static page body is rendered (inc/static.php). */
function tdd_in_static(): bool {
	return ! empty( $GLOBALS['tdd_in_static'] );
}

/** True while tdd/article-body renders the story content (scopes the block filters below). */
function tdd_in_article(): bool {
	return ! empty( $GLOBALS['tdd_in_article'] );
}

add_filter(
	'render_block',
	static function ( string $html, array $block ): string {
		if ( ! ( tdd_in_article() || tdd_in_static() ) || '' === trim( $html ) ) {
			return $html;
		}
		switch ( $block['blockName'] ) {
			case 'core/heading':
				return tdd_article_heading( $html, $block );
			case 'core/image':
				return tdd_article_image( $html, $block );
			case 'core/quote':
				$p = new WP_HTML_Tag_Processor( $html );
				if ( $p->next_tag( 'blockquote' ) ) {
					$p->add_class( 'tdd-quote' );
				}
				return $p->get_updated_html();
			case 'core/list':
				// Static pages: approved .tdd-list (primary markers). Story lists keep prose styling.
				if ( tdd_in_static() ) {
					$p = new WP_HTML_Tag_Processor( $html );
					if ( $p->next_tag() ) {
						$p->add_class( 'tdd-list' );
					}
					return $p->get_updated_html();
				}
				return $html;
			case 'core/table':
				// Plain tables: approved table type; on phones they scroll inside their own box (never the page).
				$p = new WP_HTML_Tag_Processor( $html );
				if ( $p->next_tag( 'figure' ) ) {
					$p->add_class( 'tdd-block' );
					$p->add_class( 'tdd-table-wrap' );
				}
				if ( $p->next_tag( 'table' ) ) {
					$p->add_class( 'tdd-table' );
				}
				return $p->get_updated_html();
		}
		return $html;
	},
	10,
	2
);

/** H2s get a stable id (for the TOC) unless the editor set an anchor. */
function tdd_article_heading( string $html, array $block ): string {
	$level = (int) ( $block['attrs']['level'] ?? 2 );
	if ( 2 !== $level ) {
		return $html;
	}
	$p = new WP_HTML_Tag_Processor( $html );
	if ( ! $p->next_tag( 'h2' ) ) {
		return $html;
	}
	if ( ! $p->get_attribute( 'id' ) ) {
		$base = sanitize_title( wp_strip_all_tags( $html ) );
		$base = '' !== $base ? $base : 'section';
		$id   = $base;
		$n    = 2;
		while ( isset( $GLOBALS['tdd_heading_ids'][ $id ] ) ) {
			$id = $base . '-' . $n++;
		}
		$GLOBALS['tdd_heading_ids'][ $id ] = true;
		$p->set_attribute( 'id', $id );
	}
	return $p->get_updated_html();
}

/**
 * core/image → <figure class="tdd-figure tdd-block m-bleed"><div class="tdd-media">img</div>
 * <figcaption>caption<span>credit</span></figcaption></figure>. Credit comes from the attachment.
 */
function tdd_article_image( string $html, array $block ): string {
	$id     = (int) ( $block['attrs']['id'] ?? 0 );
	$credit = $id ? trim( (string) get_post_meta( $id, 'tdd_credit', true ) ) : '';
	if ( ! preg_match( '#<img[^>]*>#', $html, $img ) ) {
		return $html;
	}
	$caption = '';
	if ( preg_match( '#<figcaption[^>]*>(.*?)</figcaption>#s', $html, $m ) ) {
		$caption = trim( $m[1] );
	}
	$cap = ( '' !== $caption || '' !== $credit ) ? '<figcaption>' . wp_kses_post( $caption ) . ( '' !== $credit ? '<span>' . esc_html( $credit ) . '</span>' : '' ) . '</figcaption>' : '';
	return '<figure class="tdd-figure tdd-block m-bleed"><div class="tdd-media">' . $img[0] . '</div>' . $cap . '</figure>';
}

/* ---------- Pieces ---------- */

/** Initials for the avatar placeholder (until a real portrait is set). */
function tdd_initials( string $name ): string {
	$parts = preg_split( '/\s+/', trim( $name ) );
	$first = $parts[0] ?? '';
	$last  = count( $parts ) > 1 ? end( $parts ) : '';
	return strtoupper( mb_substr( $first, 0, 1 ) . mb_substr( $last, 0, 1 ) );
}

/** Avatar: real portrait (user meta tdd_photo) or initials. Decorative (name is printed next to it). */
function tdd_avatar( int $user_id, bool $large = false ): string {
	$cls   = 'tdd-avatar' . ( $large ? ' tdd-avatar--lg' : '' );
	$photo = (int) get_user_meta( $user_id, 'tdd_photo', true );
	$inner = $photo ? wp_get_attachment_image( $photo, 'tdd-1x1', false, array( 'alt' => '', 'loading' => 'lazy' ) ) : '';
	if ( '' === $inner ) {
		$inner = esc_html( tdd_initials( (string) get_the_author_meta( 'display_name', $user_id ) ) );
	}
	return '<div class="' . esc_attr( $cls ) . '" aria-hidden="true">' . $inner . '</div>';
}

/** URL of a published page by path, or ''. Used for policy links that exist only once the page does. */
function tdd_page_url( string $path ): string {
	$page = get_page_by_path( $path );
	return ( $page && 'publish' === $page->post_status ) ? (string) get_permalink( $page ) : '';
}

/** "Oct 3, 2026, 8:30 AM IST" */
function tdd_datetime_label( int $ts, bool $date = true ): string {
	return wp_date( $date ? 'M j, Y, g:i A T' : 'g:i A T', $ts );
}

/**
 * Share controls (ShareControls). Copy link and Share need JS (article.js reveals them);
 * Email is a plain mailto link. "Save for later" is not shipped: V1 has no reader accounts.
 *
 * @param string $variant header | rail | mobile | again
 */
function tdd_share( string $variant = 'header' ): string {
	$url   = (string) wp_get_canonical_url() ?: (string) get_permalink();
	$title = wp_strip_all_tags( get_the_title() );
	$copy  = '<button type="button" class="tdd-share__btn%s" data-tdd-copy="' . esc_url( $url ) . '" aria-label="' . esc_attr__( 'Copy link', 'techdosedaily' ) . '" hidden>' . tdd_icon( 'link', '' ) . '%s</button>';
	$share = '<button type="button" class="tdd-share__btn" data-tdd-share="' . esc_url( $url ) . '" data-title="' . esc_attr( $title ) . '" aria-label="' . esc_attr__( 'Share', 'techdosedaily' ) . '" hidden>' . tdd_icon( 'share', '' ) . '</button>';
	$mail  = '<a class="tdd-share__btn" href="' . esc_url( 'mailto:?subject=' . rawurlencode( $title ) . '&body=' . rawurlencode( $url ) ) . '" aria-label="' . esc_attr__( 'Email', 'techdosedaily' ) . '">' . tdd_icon( 'mail', '' ) . '</a>';
	$txt   = esc_html__( 'Copy link', 'techdosedaily' );
	switch ( $variant ) {
		case 'rail':
			return '<div><div class="tdd-toc__title">' . esc_html__( 'Share', 'techdosedaily' ) . '</div><div class="tdd-share">' . sprintf( $copy, '', '' ) . $share . $mail . '</div></div>';
		case 'mobile':
			return '<div class="m-share tdd-only-m">' . sprintf( $copy, ' tdd-share__btn--txt', $txt ) . $share . $mail . '</div>';
		case 'again':
			return '<div class="m-share-again tdd-only-m"><span>' . esc_html__( 'Share this article', 'techdosedaily' ) . '</span><span class="m-share" style="margin:0">' . sprintf( $copy, '', '' ) . $share . '</span></div>';
		default:
			return '<div class="tdd-share tdd-byline__share tdd-hide-m">' . sprintf( $copy, ' tdd-share__btn--txt', $txt ) . $share . $mail . '</div>';
	}
}

/** Byline (ArticleHeader): avatar · By Author, Title / Edited by Editor · How we report. */
function tdd_byline( WP_Post $post ): string {
	$aid    = (int) $post->post_author;
	$name   = (string) get_the_author_meta( 'display_name', $aid );
	$title  = trim( (string) get_user_meta( $aid, 'tdd_title', true ) );
	/* translators: %s: author name (linked). */
	$line1  = sprintf( esc_html__( 'By %s', 'techdosedaily' ), '<a href="' . esc_url( get_author_posts_url( $aid ) ) . '">' . esc_html( $name ) . '</a>' ) . ( '' !== $title ? ', ' . esc_html( $title ) : '' );
	$line2  = array();
	$editor = tdd_has_core() ? tdd_core_editor( $post ) : null;
	if ( $editor && $editor->ID !== $aid ) {
		/* translators: %s: editor name (linked). */
		$line2[] = sprintf( esc_html__( 'Edited by %s', 'techdosedaily' ), '<a href="' . esc_url( get_author_posts_url( $editor->ID ) ) . '">' . esc_html( $editor->display_name ) . '</a>' );
	}
	$how = tdd_page_url( 'editorial-standards' );
	if ( $how ) {
		$line2[] = '<a class="tdd-byline__how" href="' . esc_url( $how ) . '">' . esc_html__( 'How we report', 'techdosedaily' ) . '</a>';
	}
	$who = '<div class="tdd-byline__who">' . $line1 . ( $line2 ? '<br>' . implode( ' · ', $line2 ) : '' ) . '</div>';
	return '<div class="tdd-byline m-byline">' . tdd_avatar( $aid ) . $who . tdd_share( 'header' ) . '</div>';
}

/** Published · Updated · read time (tdd-times / m-meta). */
function tdd_article_times( WP_Post $post ): string {
	$pub     = (int) get_post_timestamp( $post );
	$out     = '<span>' . esc_html__( 'Published', 'techdosedaily' ) . ' ' . tdd_time( $pub, tdd_datetime_label( $pub ) ) . '</span>';
	$updated = tdd_has_core() ? tdd_core_updated_at( $post ) : null;
	if ( $updated && $updated->getTimestamp() > $pub ) {
		$u    = $updated->getTimestamp();
		$same = wp_date( 'Y-m-d', $u ) === wp_date( 'Y-m-d', $pub );
		$out .= '<span class="tdd-times__upd">' . esc_html__( 'Updated', 'techdosedaily' ) . ' ' . tdd_time( $u, tdd_datetime_label( $u, ! $same ) ) . '</span>';
	}
	/* translators: %d: minutes. */
	$out .= '<span><b>' . esc_html( sprintf( __( '%d min read', 'techdosedaily' ), tdd_reading_time( $post ) ) ) . '</b></span>';
	return '<div class="tdd-times m-meta">' . $out . '</div>';
}

/** Hero figure from the featured image (caption + credit from the attachment). */
function tdd_article_hero( WP_Post $post ): string {
	$id = (int) get_post_thumbnail_id( $post );
	if ( ! $id ) {
		return '';
	}
	$img     = wp_get_attachment_image(
		$id,
		'tdd-16x9-1800',
		false,
		array(
			'sizes'         => '(max-width: 767px) 100vw, (max-width: 1199px) 740px, 960px',
			'loading'       => 'eager',
			'fetchpriority' => 'high',
			'decoding'      => 'async',
		)
	);
	$caption = trim( (string) wp_get_attachment_caption( $id ) );
	$credit  = trim( (string) get_post_meta( $id, 'tdd_credit', true ) );
	$cap     = ( '' !== $caption || '' !== $credit ) ? '<figcaption class="m-cap">' . esc_html( $caption ) . ( '' !== $credit ? '<span>' . esc_html( $credit ) . '</span>' : '' ) . '</figcaption>' : '';
	return '<div class="tdd-art__hero"><figure class="tdd-figure m-hero"><div class="tdd-media">' . $img . '</div>' . $cap . '</figure></div>';
}

/** Update notice (Notices): only when a substantive update is recorded. */
function tdd_update_notice( WP_Post $post ): string {
	$updated = tdd_has_core() ? tdd_core_updated_at( $post ) : null;
	$note    = trim( (string) get_post_meta( $post->ID, 'tdd_update_note', true ) );
	if ( ! $updated || '' === $note ) {
		return '';
	}
	/* translators: %s: time. */
	$label = sprintf( __( 'Update · %s', 'techdosedaily' ), tdd_datetime_label( $updated->getTimestamp(), wp_date( 'Y-m-d', $updated->getTimestamp() ) !== wp_date( 'Y-m-d', (int) get_post_timestamp( $post ) ) ) );
	return '<div class="tdd-notice tdd-notice--update" role="note"><b>' . esc_html( $label ) . '</b><span>' . esc_html( $note ) . '</span></div>';
}

/** Correction notices, oldest first. Never hidden once published. */
function tdd_corrections_html( WP_Post $post ): string {
	$rows = tdd_has_core() ? (array) tdd_core_corrections( $post ) : array();
	$out  = '';
	foreach ( $rows as $row ) {
		$ts = ! empty( $row['time'] ) ? strtotime( $row['time'] ) : 0;
		/* translators: %s: time. */
		$label = $ts ? sprintf( __( 'Correction · %s', 'techdosedaily' ), tdd_datetime_label( $ts, wp_date( 'Y-m-d', $ts ) !== wp_date( 'Y-m-d', (int) get_post_timestamp( $post ) ) ) ) : __( 'Correction', 'techdosedaily' );
		$out  .= '<div class="tdd-notice tdd-notice--correction tdd-block" role="note"><b>' . esc_html( $label ) . '</b><span>' . esc_html( $row['text'] ) . '</span></div>';
	}
	return $out;
}

/** Numbered sources (SourceList). Anchors #src-N match superscript refs in the text. */
function tdd_sources_html( WP_Post $post ): string {
	$rows = tdd_has_core() ? (array) tdd_core_sources( $post ) : array();
	if ( ! $rows ) {
		return '';
	}
	$types  = array(
		'primary'       => __( 'Primary', 'techdosedaily' ),
		'interview'     => __( 'Interview', 'techdosedaily' ),
		'supporting'    => __( 'Supporting', 'techdosedaily' ),
		'public-record' => __( 'Public record', 'techdosedaily' ),
		'confidential'  => __( 'Confidential', 'techdosedaily' ),
	);
	$host   = wp_parse_url( home_url(), PHP_URL_HOST );
	$items  = '';
	foreach ( array_values( $rows ) as $i => $s ) {
		$url   = (string) ( $s['url'] ?? '' );
		$ext   = $url && wp_parse_url( $url, PHP_URL_HOST ) !== $host;
		$title = $url ? '<a href="' . esc_url( $url ) . '"' . ( $ext ? ' rel="noopener"' : '' ) . '>' . esc_html( $s['title'] ) . '</a>' : esc_html( $s['title'] );
		$type  = $s['type'] ?? 'supporting';
		$meta  = '<span class="tdd-src__type' . ( 'primary' === $type ? ' tdd-src__type--primary' : '' ) . '">' . esc_html( $types[ $type ] ?? $types['supporting'] ) . '</span>';
		foreach ( array( 'publisher', 'date', 'note' ) as $k ) {
			if ( ! empty( $s[ $k ] ) ) {
				$meta .= '<span>' . esc_html( $s[ $k ] ) . '</span>';
			}
		}
		if ( $ext ) {
			$meta .= '<span class="tdd-src__ext">' . tdd_icon( 'external', '' ) . '<span class="screen-reader-text">' . esc_html__( '(external link)', 'techdosedaily' ) . '</span></span>';
		}
		$items .= '<li class="tdd-src" id="src-' . ( $i + 1 ) . '"><div class="tdd-src__title">' . $title . '</div><div class="tdd-src__meta">' . $meta . '</div></li>';
	}
	$policy = tdd_page_url( 'source-policy' );
	$intro  = (string) apply_filters( 'tdd_sources_intro', __( 'Primary sources are original documents published by the organisations involved or obtained by Tech Dose Daily. Links open the original.', 'techdosedaily' ), $post );
	return '<section class="tdd-sources tdd-block" id="sources" aria-labelledby="sources-title"><div class="tdd-sources__head"><h2 class="tdd-sources__title" id="sources-title">' . esc_html__( 'Sources', 'techdosedaily' ) . '</h2>'
		. ( $policy ? '<a class="tdd-sources__policy" href="' . esc_url( $policy ) . '">' . esc_html__( 'Our source policy →', 'techdosedaily' ) . '</a>' : '' ) . '</div>'
		. ( '' !== $intro ? '<p class="tdd-sources__intro">' . esc_html( $intro ) . '</p>' : '' )
		. '<ol>' . $items . '</ol></section>';
}

/** Topic tags. */
function tdd_tags_html( WP_Post $post ): string {
	$terms = get_the_terms( $post, 'tdd_topic' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}
	$out = '';
	foreach ( $terms as $t ) {
		$out .= '<a href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a>';
	}
	return '<div class="tdd-tags tdd-block" role="group" aria-label="' . esc_attr__( 'Topics', 'techdosedaily' ) . '">' . $out . '</div>';
}

/** Author card (AuthorCard): only true, author-approved fields; missing ones are omitted. */
function tdd_author_card( WP_Post $post ): string {
	$aid   = (int) $post->post_author;
	$name  = (string) get_the_author_meta( 'display_name', $aid );
	$role  = array_filter( array( trim( (string) get_user_meta( $aid, 'tdd_title', true ) ), trim( (string) get_user_meta( $aid, 'tdd_location', true ) ) ) );
	$bio   = trim( (string) get_the_author_meta( 'description', $aid ) );
	$bio   = '' !== $bio ? $bio : trim( (string) get_user_meta( $aid, 'tdd_short_bio', true ) );
	$first = strtok( $name, ' ' );
	/* translators: %s: author first name. */
	$links = '<a href="' . esc_url( get_author_posts_url( $aid ) ) . '">' . esc_html( sprintf( __( 'More from %s →', 'techdosedaily' ), $first ) ) . '</a>';
	foreach ( (array) get_user_meta( $aid, 'tdd_social', true ) as $s ) {
		if ( ! empty( $s['url'] ) && ! empty( $s['label'] ) ) {
			$links .= '<a href="' . esc_url( $s['url'] ) . '" rel="me noopener">' . esc_html( $s['label'] ) . '</a>';
		}
	}
	if ( get_user_meta( $aid, 'tdd_public_email', true ) ) {
		$links .= '<a href="' . esc_url( 'mailto:' . antispambot( (string) get_the_author_meta( 'user_email', $aid ) ) ) . '">' . esc_html__( 'Email', 'techdosedaily' ) . '</a>';
	}
	return '<section class="tdd-author tdd-block" aria-label="' . esc_attr__( 'About the author', 'techdosedaily' ) . '">' . tdd_avatar( $aid, true ) . '<div><div class="tdd-author__name">' . esc_html( $name ) . '</div>'
		. ( $role ? '<div class="tdd-author__role">' . esc_html( implode( ' · ', $role ) ) . '</div>' : '' )
		. ( '' !== $bio ? '<p class="tdd-author__bio">' . esc_html( $bio ) . '</p>' : '' )
		. '<div class="tdd-author__links">' . $links . '</div></div></section>';
}

/**
 * TOC entries from the rendered body: [ [id, text], … ] for H2s with ids, plus Sources.
 *
 * @return array<int,array{0:string,1:string}>
 */
function tdd_toc_items( string $html, bool $has_sources ): array {
	$items = array();
	if ( preg_match_all( '#<h2\b[^>]*\bid="([^"]+)"[^>]*>(.*?)</h2>#s', $html, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $h ) {
			if ( str_contains( $h[0], 'tdd-sources__title' ) ) {
				continue;
			}
			$items[] = array( $h[1], trim( wp_strip_all_tags( $h[2] ) ) );
		}
	}
	if ( $has_sources ) {
		$items[] = array( 'sources', __( 'Sources', 'techdosedaily' ) );
	}
	return $items;
}

/** Desktop rail TOC (TableOfContents). */
function tdd_toc_rail( array $items ): string {
	$li = '';
	foreach ( $items as $i => [ $id, $text ] ) {
		$li .= '<li><a href="#' . esc_attr( $id ) . '"' . ( 0 === $i ? ' aria-current="true"' : '' ) . '>' . esc_html( $text ) . '</a></li>';
	}
	return '<nav class="tdd-toc" aria-labelledby="tdd-toc-title" data-tdd-toc><div class="tdd-toc__title" id="tdd-toc-title">' . esc_html__( 'In this article', 'techdosedaily' ) . '</div><ol>' . $li . '</ol></nav>';
}

/** Collapsible inline TOC (approved m-toc, native <details>, closed by default) — used below 1200px. */
function tdd_toc_mobile( array $items ): string {
	$li = '';
	foreach ( $items as [ $id, $text ] ) {
		$li .= '<li><a href="#' . esc_attr( $id ) . '">' . esc_html( $text ) . '</a></li>';
	}
	/* translators: %d: number of sections. */
	$count = sprintf( _n( '%d section', '%d sections', count( $items ), 'techdosedaily' ), count( $items ) );
	return '<details class="m-toc tdd-toc-inline"><summary>' . esc_html__( 'In this article', 'techdosedaily' ) . ' <span>' . esc_html( $count ) . '</span>' . tdd_icon( 'chevron-down', '' ) . '</summary><nav aria-label="' . esc_attr__( 'In this article', 'techdosedaily' ) . '"><ol>' . $li . '</ol></nav></details>';
}

/* ---------- Story lists around the article ---------- */

/**
 * More in <section>: newest stories in the same primary section, excluding this one.
 *
 * @return WP_Post[]
 */
function tdd_more_in_section( WP_Post $post, int $count = 3 ): array {
	static $cache = array();
	$key = $post->ID . ':' . $count;
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$section = tdd_section( $post );
	if ( ! $section ) {
		return array();
	}
	$cache[ $key ] = get_posts(
		array(
			'post_type'           => 'post',
			'posts_per_page'      => $count,
			'cat'                 => $section->term_id,
			'post__not_in'        => array( $post->ID ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
	return $cache[ $key ];
}

/**
 * Related coverage: stories sharing topics, then the same section, excluding this story and
 * the "More in" list. Empty when nothing qualifies (the section collapses).
 *
 * @return WP_Post[]
 */
function tdd_related_posts( WP_Post $post, int $count = 4 ): array {
	$exclude = array_merge( array( $post->ID ), wp_list_pluck( tdd_more_in_section( $post ), 'ID' ) );
	$topics  = wp_get_post_terms( $post->ID, 'tdd_topic', array( 'fields' => 'ids' ) );
	$found   = array();
	if ( $topics && ! is_wp_error( $topics ) ) {
		$found = get_posts(
			array(
				'post_type'           => 'post',
				'posts_per_page'      => $count,
				'post__not_in'        => $exclude,
				'tax_query'           => array( array( 'taxonomy' => 'tdd_topic', 'terms' => $topics ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);
	}
	$section = tdd_section( $post );
	if ( count( $found ) < $count && $section ) {
		$found = array_merge(
			$found,
			get_posts(
				array(
					'post_type'           => 'post',
					'posts_per_page'      => $count - count( $found ),
					'post__not_in'        => array_merge( $exclude, wp_list_pluck( $found, 'ID' ) ),
					'cat'                 => $section->term_id,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
				)
			)
		);
	}
	return $found;
}

/** "More on X and Y" from the story's topics ('' when it has none). */
function tdd_related_desc( WP_Post $post ): string {
	$terms = get_the_terms( $post, 'tdd_topic' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}
	$names = array_slice( wp_list_pluck( $terms, 'name' ), 0, 2 );
	/* translators: %s: one or two topic names joined with "and". */
	return sprintf( __( 'More on %s', 'techdosedaily' ), implode( ' ' . __( 'and', 'techdosedaily' ) . ' ', $names ) );
}

/** Compact story row with thumb: square 80px in the desktop sidebar, 96px 4:3 in mobile lists. */
function tdd_compact_row( WP_Post $p, string $meta = 'ago', bool $square = false ): string {
	return tdd_story_card(
		$p,
		'compact',
		array(
			'thumb'  => true,
			'meta'   => array( $meta ),
			'size'   => $square ? 'tdd-1x1' : 'tdd-4x3',
			'sizes'  => $square ? '80px' : '96px',
			'labels' => array( 'one' => true ),
		)
	);
}

/** Sidebar "Daily Tech Brief" link — only when today's brief has real items and a page exists. */
function tdd_side_brief(): string {
	if ( ! function_exists( 'tdd_core_fill_placement' ) ) {
		return '';
	}
	$items = tdd_core_fill_placement( 'daily_brief', 10, array( 'fallback' => false ) );
	$url   = tdd_page_url( 'newsletter' );
	if ( count( $items ) < 3 || '' === $url ) {
		return '';
	}
	/* translators: %d: number of brief items. */
	$text = sprintf( __( '%d things worth knowing today, in three minutes →', 'techdosedaily' ), count( $items ) );
	return '<a class="tdd-side-brief" href="' . esc_url( $url ) . '"><img src="' . tdd_asset( 'images/tdd-mark.svg' ) . '" alt="" width="24" height="24"><b>' . esc_html__( 'Daily Tech Brief', 'techdosedaily' ) . '</b><span>' . esc_html( $text ) . '</span></a>';
}
