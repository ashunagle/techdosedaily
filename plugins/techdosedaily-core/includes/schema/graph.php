<?php
/**
 * The Core JSON-LD graph. Builds one @graph per public view from stored data only.
 *
 * Stable @id values (entities are referenced, never repeated):
 *   {home}/#organization          NewsMediaOrganization (publisher)
 *   {home}/#website               WebSite
 *   {home}/#logo                  ImageObject (site icon), only when set
 *   {author archive}#person       Person (authors and editors)
 *   {canonical}#webpage           WebPage / AboutPage / ContactPage / CollectionPage / ProfilePage
 *   {canonical}#breadcrumb        BreadcrumbList
 *   {canonical}#article           NewsArticle (+ subtype) for stories
 *   {image file URL}#image        ImageObject for a real attachment
 *
 * Omission rules: an empty value is never emitted; nothing is invented (no default image, logo,
 * author, social profile or rating). AI disclosure, vendor-reported, severity and sources are not
 * mapped (no valid property / private editorial data).
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Helpers ---------- */

/** Plain text for JSON-LD: tags stripped, entities decoded, whitespace collapsed. */
function tdd_core_schema_text( $v ): string {
	$v = html_entity_decode( wp_strip_all_tags( (string) $v ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	return trim( (string) preg_replace( '/\s+/u', ' ', $v ) );
}

/** Drop empty values (null, '', []) recursively; keep 0/false only where meaningful (booleans). */
function tdd_core_schema_clean( array $node ): array {
	foreach ( $node as $k => $v ) {
		if ( is_array( $v ) ) {
			$v = tdd_core_schema_clean( $v );
		}
		if ( null === $v || '' === $v || array() === $v ) {
			unset( $node[ $k ] );
			continue;
		}
		$node[ $k ] = $v;
	}
	return array_is_list( $node ) ? array_values( $node ) : $node;
}

function tdd_core_schema_ref( string $id ): array {
	return array( '@id' => $id );
}

function tdd_core_schema_date( $ts ): string {
	if ( $ts instanceof DateTimeInterface ) {
		return DateTimeImmutable::createFromInterface( $ts )->setTimezone( wp_timezone() )->format( DATE_ATOM ); // One offset everywhere.
	}
	return $ts ? wp_date( DATE_ATOM, (int) $ts ) : '';
}

function tdd_core_schema_lang(): string {
	return str_replace( '_', '-', get_locale() );
}

/** Valid absolute http(s) URL or ''. */
function tdd_core_schema_url( $url ): string {
	$url = esc_url_raw( (string) $url, array( 'http', 'https' ) );
	return ( '' !== $url && wp_http_validate_url( $url ) ) ? $url : '';
}

/** Current view's canonical URL (matches WordPress/Yoast canonicals, including /page/N/). */
function tdd_core_schema_canonical(): string {
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	$base  = '';
	if ( is_singular() ) {
		$base = (string) wp_get_canonical_url( get_queried_object_id() );
		return $base; // Includes comment/page pagination itself.
	}
	if ( is_front_page() ) {
		$base = home_url( '/' );
	} elseif ( is_home() ) {
		$base = (string) get_permalink( (int) get_option( 'page_for_posts' ) );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$link = get_term_link( get_queried_object() );
		$base = is_wp_error( $link ) ? '' : $link;
	} elseif ( is_author() ) {
		$base = get_author_posts_url( (int) get_queried_object_id() );
	}
	if ( '' === $base ) {
		return '';
	}
	return $paged > 1 ? user_trailingslashit( trailingslashit( $base ) . 'page/' . $paged ) : $base;
}

/* ---------- Site entities ---------- */

function tdd_core_schema_org_id(): string {
	return home_url( '/#organization' );
}

function tdd_core_schema_website_id(): string {
	return home_url( '/#website' );
}

/** Published page URL by path ('' if missing or unpublished). Privacy uses the WordPress setting. */
function tdd_core_schema_page_url( string $path ): string {
	$page = 'privacy-policy' === $path && (int) get_option( 'wp_page_for_privacy_policy' ) ? get_post( (int) get_option( 'wp_page_for_privacy_policy' ) ) : get_page_by_path( $path );
	return ( $page && 'publish' === $page->post_status && '' === $page->post_password ) ? (string) get_permalink( $page ) : '';
}

function tdd_core_schema_organization(): array {
	$logo = null;
	$icon = (int) get_option( 'site_icon' );
	if ( $icon && wp_attachment_is_image( $icon ) ) {
		$src = wp_get_attachment_image_src( $icon, 'full' );
		if ( $src ) {
			$logo = array(
				'@type'      => 'ImageObject',
				'@id'        => home_url( '/#logo' ),
				'url'        => $src[0],
				'contentUrl' => $src[0],
				'width'      => (int) $src[1],
				'height'     => (int) $src[2],
				'caption'    => tdd_core_schema_text( get_bloginfo( 'name' ) ),
			);
		}
	}
	$org = array(
		'@type'                 => 'NewsMediaOrganization',
		'@id'                   => tdd_core_schema_org_id(),
		'name'                  => tdd_core_schema_text( get_bloginfo( 'name' ) ),
		'url'                   => home_url( '/' ),
		'logo'                  => $logo,
		'image'                 => $logo ? tdd_core_schema_ref( home_url( '/#logo' ) ) : null,
		// Only policies that exist as published pages (NewsMediaOrganization properties).
		'publishingPrinciples'  => tdd_core_schema_page_url( 'editorial-standards' ),
		'correctionsPolicy'     => tdd_core_schema_page_url( 'corrections-policy' ),
		'actionableFeedbackPolicy' => tdd_core_schema_page_url( 'contact' ),
	);
	return apply_filters( 'tdd_core_schema_organization', $org );
}

function tdd_core_schema_website(): array {
	return array(
		'@type'           => 'WebSite',
		'@id'             => tdd_core_schema_website_id(),
		'url'             => home_url( '/' ),
		'name'            => tdd_core_schema_text( get_bloginfo( 'name' ) ),
		'description'     => tdd_core_schema_text( get_bloginfo( 'description' ) ),
		'publisher'       => tdd_core_schema_ref( tdd_core_schema_org_id() ),
		'inLanguage'      => tdd_core_schema_lang(),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => add_query_arg( 's', '{search_term_string}', home_url( '/' ) ),
			),
			'query-input' => 'required name=search_term_string',
		),
	);
}

/* ---------- People and images ---------- */

/** Stable Person @id for a user (author archive URL + #person). */
function tdd_core_schema_person_id( int $user_id ): string {
	return get_author_posts_url( $user_id ) . '#person';
}

/** Person from public profile fields only (no email, login, Gravatar). */
function tdd_core_schema_person( int $user_id ): ?array {
	$u = get_userdata( $user_id );
	if ( ! $u ) {
		return null;
	}
	$m       = static fn( string $k ) => tdd_core_schema_text( get_user_meta( $user_id, $k, true ) );
	$has     = count_user_posts( $user_id, 'post', true ) > 0;
	$photo   = (int) get_user_meta( $user_id, 'tdd_photo', true );
	$same_as = array();
	foreach ( (array) get_user_meta( $user_id, 'tdd_social', true ) as $s ) {
		$url = is_array( $s ) ? tdd_core_schema_url( $s['url'] ?? '' ) : '';
		if ( '' !== $url ) {
			$same_as[] = $url;
		}
	}
	return array(
		'@type'       => 'Person',
		'@id'         => tdd_core_schema_person_id( $user_id ),
		'name'        => tdd_core_schema_text( $u->display_name ),
		'url'         => $has ? get_author_posts_url( $user_id ) : '', // Author page only when it is a real, indexable page.
		'jobTitle'    => $m( 'tdd_title' ),
		'description' => $m( 'tdd_short_bio' ),
		'image'       => $photo ? tdd_core_schema_image( $photo ) : null,
		'sameAs'      => array_values( array_unique( $same_as ) ),
		'worksFor'    => tdd_core_schema_ref( tdd_core_schema_org_id() ),
	);
}

/** ImageObject for a real image attachment, with credit/licence only when stored and valid. */
function tdd_core_schema_image( int $attachment_id ): ?array {
	if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
		return null;
	}
	$src = wp_get_attachment_image_src( $attachment_id, 'full' );
	if ( ! $src ) {
		return null;
	}
	$alt     = tdd_core_schema_text( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
	$caption = tdd_core_schema_text( wp_get_attachment_caption( $attachment_id ) );
	$credit  = tdd_core_schema_text( get_post_meta( $attachment_id, 'tdd_credit', true ) );
	$license = tdd_core_schema_url( get_post_meta( $attachment_id, 'tdd_license_note', true ) ); // Only a licence URL is a valid `license`.
	return array(
		'@type'      => 'ImageObject',
		'@id'        => $src[0] . '#image',
		'url'        => $src[0],
		'contentUrl' => $src[0],
		'width'      => (int) $src[1],
		'height'     => (int) $src[2],
		'caption'    => '' !== $caption ? $caption : $alt,
		'creditText' => $credit,
		'license'    => $license,
		'inLanguage' => tdd_core_schema_lang(),
	);
}

/* ---------- Breadcrumbs ---------- */

/** [ [name, url], … ] for the current view. The last item is the current page (no URL needed). */
function tdd_core_schema_breadcrumb_items(): array {
	$items = array( array( __( 'Home', 'techdosedaily-core' ), home_url( '/' ) ) );
	if ( is_singular( 'post' ) ) {
		$section = tdd_core_primary_section( get_queried_object_id() );
		if ( $section ) {
			$items[] = array( $section->name, get_term_link( $section ) );
		}
		$items[] = array( get_the_title( get_queried_object_id() ), '' );
	} elseif ( is_page() && ! is_front_page() ) {
		$id        = get_queried_object_id();
		$ancestors = array_reverse( get_post_ancestors( $id ) );
		$slug      = (string) get_post_field( 'post_name', $id );
		$is_priv   = (int) get_option( 'wp_page_for_privacy_policy' ) === $id;
		if ( ! $ancestors && ( isset( tdd_core_policy_pages()[ $slug ] ) || $is_priv ) ) {
			$about = get_page_by_path( 'about' );
			if ( $about && 'publish' === $about->post_status ) {
				$items[] = array( get_the_title( $about ), get_permalink( $about ) );
			}
		}
		foreach ( $ancestors as $a ) {
			$items[] = array( get_the_title( $a ), get_permalink( $a ) );
		}
		$items[] = array( get_the_title( $id ), '' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$items[] = array( single_term_title( '', false ), '' );
	} elseif ( is_author() ) {
		$items[] = array( get_the_author_meta( 'display_name', (int) get_queried_object_id() ), '' );
	} elseif ( is_home() && ! is_front_page() ) {
		$items[] = array( get_the_title( (int) get_option( 'page_for_posts' ) ), '' );
	} else {
		return array();
	}
	return $items;
}

function tdd_core_schema_breadcrumb( string $canonical ): ?array {
	$items = tdd_core_schema_breadcrumb_items();
	if ( count( $items ) < 2 ) {
		return null;
	}
	$list = array();
	foreach ( $items as $i => [ $name, $url ] ) {
		$last   = $i === count( $items ) - 1;
		$list[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => tdd_core_schema_text( $name ),
			'item'     => $last ? $canonical : ( is_string( $url ) ? $url : '' ),
		);
	}
	return array(
		'@type'           => 'BreadcrumbList',
		'@id'             => $canonical . '#breadcrumb',
		'itemListElement' => $list,
	);
}

/* ---------- Stories ---------- */

/**
 * Story type → schema type. Only subtypes that are semantically exact are used; the story type is
 * always also given as `genre`. Sponsored content is not news: AdvertiserContentArticle (an Article).
 */
function tdd_core_schema_article_type( ?WP_Term $format ): array {
	$slug = $format ? $format->slug : 'news';
	$map  = apply_filters(
		'tdd_core_schema_article_types',
		array(
			'analysis'  => array( 'NewsArticle', 'AnalysisNewsArticle' ),
			'explainer' => array( 'NewsArticle', 'BackgroundNewsArticle' ),
			'sponsored' => array( 'Article', 'AdvertiserContentArticle' ),
		)
	);
	return $map[ $slug ] ?? array( 'NewsArticle' );
}

/** Original publication time, frozen at first publication. */
function tdd_core_first_published( WP_Post $post ): ?DateTimeImmutable {
	$raw = (string) get_post_meta( $post->ID, '_tdd_first_published', true );
	if ( '' !== $raw ) {
		try {
			return new DateTimeImmutable( $raw );
		} catch ( Exception $e ) {
			unset( $e );
		}
	}
	$d = get_post_datetime( $post, 'date', 'gmt' );
	return $d ? $d : null;
}

/** Only stories readers can see: published, not password-protected, not a preview. */
function tdd_core_schema_public_post( WP_Post $post ): bool {
	return 'publish' === $post->post_status && '' === $post->post_password && ! is_preview();
}

function tdd_core_schema_article( WP_Post $post, string $canonical ): array {
	$format    = tdd_core_story_format( $post );
	$type      = tdd_core_schema_article_type( $format );
	$published = tdd_core_first_published( $post );
	$updated   = tdd_core_updated_at( $post );
	$section   = tdd_core_primary_section( $post );
	$editor    = tdd_core_editor( $post );
	$thumb     = (int) get_post_thumbnail_id( $post );
	$topics    = get_the_terms( $post->ID, 'tdd_topic' );
	$words     = str_word_count( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
	$minutes   = tdd_core_reading_time( $post );
	$sponsor   = tdd_core_schema_text( get_post_meta( $post->ID, 'tdd_sponsor', true ) );
	$short     = tdd_core_schema_text( get_post_meta( $post->ID, 'tdd_short_title', true ) );
	$title     = tdd_core_schema_text( $post->post_title ); // Raw title: no display filters (curly quotes, markup).

	$corrections = array();
	foreach ( tdd_core_corrections( $post ) as $c ) {
		$text = is_array( $c ) ? tdd_core_schema_text( $c['text'] ?? '' ) : '';
		if ( '' === $text ) {
			continue;
		}
		$time = '';
		try {
			$time = ( new DateTimeImmutable( (string) ( $c['time'] ?? '' ) ) )->format( DATE_ATOM );
		} catch ( Exception $e ) {
			unset( $e );
		}
		$corrections[] = array(
			'@type'         => 'CorrectionComment',
			'text'          => $text,
			'datePublished' => $time,
		);
	}

	return array(
		'@type'               => 1 === count( $type ) ? $type[0] : $type,
		'@id'                 => $canonical . '#article',
		'headline'            => $title,
		'alternativeHeadline' => $short !== $title ? $short : '',
		'description'         => tdd_core_schema_text( tdd_core_deck( $post ) ),
		'url'                 => $canonical,
		'mainEntityOfPage'    => tdd_core_schema_ref( $canonical . '#webpage' ),
		'isPartOf'            => tdd_core_schema_ref( $canonical . '#webpage' ),
		'datePublished'       => tdd_core_schema_date( $published ),
		'dateModified'        => tdd_core_schema_date( $updated ? $updated : $published ), // Substantive updates only.
		'author'              => tdd_core_schema_ref( tdd_core_schema_person_id( (int) $post->post_author ) ),
		'editor'              => $editor ? tdd_core_schema_ref( tdd_core_schema_person_id( $editor->ID ) ) : null,
		'publisher'           => tdd_core_schema_ref( tdd_core_schema_org_id() ),
		'image'               => $thumb && wp_attachment_is_image( $thumb ) ? tdd_core_schema_ref( wp_get_attachment_image_src( $thumb, 'full' )[0] . '#image' ) : null,
		'articleSection'      => $section ? tdd_core_schema_text( $section->name ) : '',
		'genre'               => $format ? tdd_core_schema_text( $format->name ) : '',
		'keywords'            => ( $topics && ! is_wp_error( $topics ) ) ? array_values( array_map( static fn( $t ) => tdd_core_schema_text( $t->name ), $topics ) ) : array(),
		'wordCount'           => $words > 0 ? $words : null,
		'timeRequired'        => $minutes > 0 ? 'PT' . $minutes . 'M' : '',
		'isAccessibleForFree' => true,
		'sponsor'             => ( $format && 'sponsored' === $format->slug && '' !== $sponsor ) ? array( '@type' => 'Organization', 'name' => $sponsor ) : null,
		'correction'          => $corrections,
		'inLanguage'          => tdd_core_schema_lang(),
	);
}

/* ---------- Pages ---------- */

/** WebPage node for the current view. */
function tdd_core_schema_webpage( string $type, string $canonical, array $extra = array() ): array {
	$title = function_exists( 'wp_get_document_title' ) ? wp_get_document_title() : '';
	return array_merge(
		array(
			'@type'      => $type,
			'@id'        => $canonical . '#webpage',
			'url'        => $canonical,
			'name'       => tdd_core_schema_text( $title ),
			'isPartOf'   => tdd_core_schema_ref( tdd_core_schema_website_id() ),
			'breadcrumb' => null,
			'inLanguage' => tdd_core_schema_lang(),
		),
		$extra
	);
}

/**
 * The whole graph for the current request, or [] when this view must carry no schema
 * (404, search, previews, drafts, password-protected, admin, feeds, attachments).
 */
function tdd_core_schema_graph(): array {
	if ( is_admin() || is_feed() || is_404() || is_search() || is_preview() || is_attachment() || is_robots() ) {
		return array();
	}
	$canonical = tdd_core_schema_canonical();
	if ( '' === $canonical ) {
		return array();
	}
	$graph  = array( tdd_core_schema_organization(), tdd_core_schema_website() );
	$people = array();
	$images = array();
	$crumb  = tdd_core_schema_breadcrumb( $canonical );

	if ( is_singular( 'post' ) ) {
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || ! tdd_core_schema_public_post( $post ) ) {
			return array();
		}
		$article = tdd_core_schema_article( $post, $canonical );
		$people[ (int) $post->post_author ] = true;
		$editor = tdd_core_editor( $post );
		if ( $editor ) {
			$people[ $editor->ID ] = true;
		}
		$thumb = (int) get_post_thumbnail_id( $post );
		$img   = $thumb ? tdd_core_schema_image( $thumb ) : null;
		if ( $img ) {
			$images[ $img['@id'] ] = $img;
		}
		$graph[] = tdd_core_schema_webpage(
			'WebPage',
			$canonical,
			array(
				'breadcrumb'         => $crumb ? tdd_core_schema_ref( $crumb['@id'] ) : null,
				'primaryImageOfPage' => $img ? tdd_core_schema_ref( $img['@id'] ) : null,
				'datePublished'      => $article['datePublished'],
				'dateModified'       => $article['dateModified'],
			)
		);
		$graph[] = $article;
	} elseif ( is_page() || is_front_page() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post && ! tdd_core_schema_public_post( $post ) ) {
			return array();
		}
		$type = 'WebPage';
		if ( $post instanceof WP_Post ) {
			$tpl  = (string) get_page_template_slug( $post );
			$type = 'page-about' === $tpl ? 'AboutPage' : ( 'page-contact' === $tpl ? 'ContactPage' : 'WebPage' );
		}
		$graph[] = tdd_core_schema_webpage(
			$type,
			$canonical,
			$post instanceof WP_Post && ! is_front_page() ? array(
				'breadcrumb'    => $crumb ? tdd_core_schema_ref( $crumb['@id'] ) : null,
				'description'   => tdd_core_schema_text( get_post_meta( $post->ID, 'tdd_intro', true ) ),
				'datePublished' => tdd_core_schema_date( get_post_timestamp( $post ) ),
				'dateModified'  => tdd_core_schema_date( tdd_core_page_updated( $post ) ),
				'about'         => in_array( $type, array( 'AboutPage', 'ContactPage' ), true ) ? tdd_core_schema_ref( tdd_core_schema_org_id() ) : null,
			) : array( 'about' => tdd_core_schema_ref( tdd_core_schema_org_id() ) )
		);
	} elseif ( is_author() ) {
		$uid = (int) get_queried_object_id();
		if ( 0 === (int) count_user_posts( $uid, 'post', true ) ) {
			return array(); // noindex empty author page: no profile schema.
		}
		$people[ $uid ] = true;
		$graph[]        = tdd_core_schema_webpage(
			'ProfilePage',
			$canonical,
			array(
				'breadcrumb' => $crumb ? tdd_core_schema_ref( $crumb['@id'] ) : null,
				'mainEntity' => tdd_core_schema_ref( tdd_core_schema_person_id( $uid ) ),
			)
		);
	} elseif ( is_category() || is_tag() || is_tax() || is_home() ) {
		$desc = '';
		if ( ! is_home() ) {
			$term = get_queried_object();
			$desc = $term instanceof WP_Term ? tdd_core_schema_text( term_description( $term ) ) : '';
		}
		$graph[] = tdd_core_schema_webpage(
			'CollectionPage',
			$canonical,
			array(
				'breadcrumb'  => $crumb ? tdd_core_schema_ref( $crumb['@id'] ) : null,
				'description' => $desc,
			)
		);
	} else {
		return array(); // Date archives and anything else: no schema.
	}

	if ( $crumb ) {
		$graph[] = $crumb;
	}
	foreach ( array_keys( $people ) as $uid ) {
		$p = tdd_core_schema_person( $uid );
		if ( $p ) {
			if ( ! empty( $p['image'] ) ) {
				$images[ $p['image']['@id'] ] = $p['image'];
				$p['image']                   = tdd_core_schema_ref( $p['image']['@id'] );
			}
			$graph[] = $p;
		}
	}
	foreach ( $images as $img ) {
		$graph[] = $img;
	}
	$graph = array_map( 'tdd_core_schema_clean', $graph );
	return apply_filters( 'tdd_core_schema_graph', $graph );
}

/** Safe JSON for a <script type="application/ld+json"> element (no "</script>" breakout). */
function tdd_core_schema_json( array $graph ): string {
	return (string) wp_json_encode(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
	);
}
