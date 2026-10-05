<?php
/**
 * SEO integration rules that depend on publication data. Yoast owns titles, descriptions,
 * canonicals, robots output, Open Graph / X cards and XML sitemaps; Core only:
 *
 *   1. supplies the deck (stories) / intro (pages) as a FALLBACK description when no explicit
 *      Yoast description exists — never overwriting one;
 *   2. decides which publication views must not be indexed (one rule set, applied to WordPress
 *      core robots and to Yoast);
 *   3. keeps those views and local test fixtures out of XML sitemaps (core and Yoast).
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

/** Topics with fewer published stories than this are thin: noindex and out of sitemaps. */
function tdd_core_thin_topic_threshold(): int {
	return max( 1, (int) apply_filters( 'tdd_core_thin_topic_threshold', 3 ) );
}

/* ---------- 1. Description fallback ---------- */

/** Fallback description for a post or page: deck (stories) or intro (pages), trimmed for snippets. */
function tdd_core_fallback_description( $post = null ): string {
	$post = get_post( $post );
	if ( ! $post || 'publish' !== $post->post_status ) {
		return '';
	}
	$raw = 'page' === $post->post_type ? (string) get_post_meta( $post->ID, 'tdd_intro', true ) : tdd_core_deck( $post );
	$raw = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $raw ) ) );
	return '' === $raw ? '' : wp_html_excerpt( $raw, 300, '…' );
}

/**
 * Yoast description filters. The fallback is used only when the editor entered nothing explicit:
 *   meta description  → Yoast's value is empty (no explicit description, no template output);
 *   og / twitter      → no explicit social description AND no explicit meta description on the
 *                       indexable. Yoast would otherwise fall back to an auto-excerpt of the body,
 *                       which can differ from the deck; the deck keeps all three consistent.
 */
add_filter(
	'wpseo_metadesc',
	static function ( $desc ) {
		if ( '' !== trim( (string) $desc ) || ! is_singular( array( 'post', 'page' ) ) ) {
			return $desc;
		}
		return tdd_core_fallback_description( get_queried_object_id() );
	},
	20
);
foreach ( array( 'wpseo_opengraph_desc' => 'open_graph_description', 'wpseo_twitter_description' => 'twitter_description' ) as $tdd_core_hook => $tdd_core_field ) {
	add_filter(
		$tdd_core_hook,
		static function ( $desc, $presentation = null ) use ( $tdd_core_field ) {
			if ( ! is_singular( array( 'post', 'page' ) ) ) {
				return $desc;
			}
			$model    = is_object( $presentation ) && isset( $presentation->model ) ? $presentation->model : null;
			$explicit = $model ? trim( (string) ( $model->{$tdd_core_field} ?? '' ) . (string) ( $model->description ?? '' ) ) : trim( (string) $desc );
			if ( '' !== $explicit ) {
				return $desc; // Editor set a social or meta description in Yoast: never overwritten.
			}
			$fallback = tdd_core_fallback_description( get_queried_object_id() );
			return '' !== $fallback ? $fallback : $desc;
		},
		20,
		2
	);
}

/**
 * Block themes add their own <title> during template loading — after Yoast has removed the core
 * title hooks — so pages would carry two identical <title> elements. With Yoast active, Yoast's
 * title is the only one.
 */
add_filter(
	'template_include',
	static function ( $template ) {
		if ( function_exists( 'tdd_core_yoast_active' ) && tdd_core_yoast_active() ) {
			remove_action( 'wp_head', '_block_template_render_title_tag', 1 );
		}
		return $template;
	},
	PHP_INT_MAX
);

/* ---------- 2. Noindex rules ---------- */

/** Fixture content (local testing only) is identified by the _tdd_fixture meta key. */
function tdd_core_is_fixture( int $post_id ): bool {
	return tdd_core_fixtures_hidden() && '' !== (string) get_post_meta( $post_id, '_tdd_fixture', true );
}

/** Fixtures are kept out of indexes and sitemaps. A local audit may switch this off to inspect them as real content. */
function tdd_core_fixtures_hidden(): bool {
	return (bool) apply_filters( 'tdd_core_hide_fixtures', true );
}

/** Published story count for a topic term. */
function tdd_core_topic_count( WP_Term $term ): int {
	return (int) $term->count; // Term counts include only published posts.
}

/**
 * Why the current view must not be indexed, or '' when it may be.
 * search · 404 · empty-author · thin-topic · fixture
 */
function tdd_core_noindex_reason(): string {
	if ( is_search() ) {
		return 'search';
	}
	if ( is_404() ) {
		return '404';
	}
	if ( is_author() && 0 === (int) count_user_posts( (int) get_queried_object_id(), 'post', true ) ) {
		return 'empty-author';
	}
	if ( is_tax( 'tdd_topic' ) ) {
		$t = get_queried_object();
		if ( $t instanceof WP_Term && tdd_core_topic_count( $t ) < tdd_core_thin_topic_threshold() ) {
			return 'thin-topic';
		}
	}
	if ( is_singular( 'mailpoet_page' ) ) {
		return 'newsletter-endpoint'; // MailPoet's subscription-management and captcha pages.
	}
	if ( is_singular() && tdd_core_is_fixture( (int) get_queried_object_id() ) ) {
		return 'fixture';
	}
	return (string) apply_filters( 'tdd_core_noindex_reason', '' );
}

// WordPress core robots API (used when Yoast is not active; harmless when it is).
add_filter(
	'wp_robots',
	static function ( array $robots ): array {
		if ( '' !== tdd_core_noindex_reason() ) {
			unset( $robots['index'], $robots['max-image-preview'], $robots['max-snippet'], $robots['max-video-preview'] );
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	},
	20
);
// Yoast robots (array form, Yoast 19+) and string form.
add_filter(
	'wpseo_robots_array',
	static function ( $robots ) {
		if ( '' !== tdd_core_noindex_reason() && is_array( $robots ) ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'follow';
		}
		return $robots;
	},
	20
);
add_filter(
	'wpseo_robots',
	static function ( $robots ) {
		return '' !== tdd_core_noindex_reason() ? 'noindex, follow' : $robots;
	},
	20
);

/* ---------- 3. Sitemaps ---------- */

/** IDs of fixture posts and pages (empty on a real site). */
function tdd_core_fixture_ids(): array {
	static $ids = null;
	if ( ! tdd_core_fixtures_hidden() ) {
		return array();
	}
	if ( null === $ids ) {
		$ids = array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => 'any',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_key'       => '_tdd_fixture', // phpcs:ignore WordPress.DB.SlowDBQuery
					'no_found_rows'  => true,
				)
			)
		);
	}
	return $ids;
}

/** Topic term IDs below the thin threshold. */
function tdd_core_thin_topic_ids(): array {
	$terms = get_terms( array( 'taxonomy' => 'tdd_topic', 'hide_empty' => false, 'fields' => 'all' ) );
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	return array_values( array_map( static fn( $t ) => (int) $t->term_id, array_filter( $terms, static fn( $t ) => tdd_core_topic_count( $t ) < tdd_core_thin_topic_threshold() ) ) );
}

// WordPress core sitemaps.
add_filter(
	'wp_sitemaps_posts_query_args',
	static function ( array $args ): array {
		$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), tdd_core_fixture_ids() );
		return $args;
	}
);
add_filter(
	'wp_sitemaps_taxonomies_query_args',
	static function ( array $args, string $taxonomy ): array {
		if ( 'tdd_topic' === $taxonomy ) {
			$args['exclude'] = array_merge( (array) ( $args['exclude'] ?? array() ), tdd_core_thin_topic_ids() );
		}
		return $args;
	},
	10,
	2
);
add_filter(
	'wp_sitemaps_taxonomies',
	static function ( array $taxonomies ): array {
		unset( $taxonomies['post_tag'], $taxonomies['post_format'] ); // Not used for publication navigation.
		return $taxonomies;
	}
);
add_filter(
	'wp_sitemaps_post_types',
	static function ( array $types ): array {
		unset( $types['attachment'], $types['mailpoet_page'] );
		return $types;
	}
);

// Yoast sitemaps.
add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', static fn( $ids ) => array_merge( (array) $ids, tdd_core_fixture_ids() ) );
add_filter( 'wpseo_exclude_from_sitemap_by_term_ids', static fn( $ids ) => array_merge( (array) $ids, tdd_core_thin_topic_ids() ) );
add_filter( 'wpseo_sitemap_exclude_taxonomy', static fn( $exclude, $taxonomy ) => in_array( $taxonomy, array( 'post_tag', 'post_format' ), true ) ? true : $exclude, 10, 2 );
add_filter( 'wpseo_sitemap_exclude_post_type', static fn( $exclude, $type ) => in_array( $type, array( 'attachment', 'mailpoet_page' ), true ) ? true : $exclude, 10, 2 );
// Yoast builds indexables only when WP_ENVIRONMENT_TYPE is production; staging must match production output.
add_filter( 'Yoast\WP\SEO\should_index_indexables', static fn( $should ) => $should || 'staging' === wp_get_environment_type() );

/* ---------- 4. Yoast output aligned with publication data ---------- */

/** Core's publication dates for a story: [published, modified] as DATE_ATOM in UTC, or null. */
function tdd_core_story_dates( int $post_id ): ?array {
	$post = get_post( $post_id );
	if ( ! $post || 'post' !== $post->post_type || ! function_exists( 'tdd_core_first_published' ) ) {
		return null;
	}
	$pub = tdd_core_first_published( $post );
	if ( ! $pub ) {
		return null;
	}
	$upd = tdd_core_updated_at( $post );
	$utc = new DateTimeZone( 'UTC' );
	return array(
		DateTimeImmutable::createFromInterface( $pub )->setTimezone( $utc )->format( DATE_ATOM ),
		DateTimeImmutable::createFromInterface( $upd ? $upd : $pub )->setTimezone( $utc )->format( DATE_ATOM ),
		(bool) $upd,
	);
}

/**
 * Yoast derives article dates from WordPress's post date / last save. Stories use the frozen
 * publication time and the last *recorded substantive update* instead (typo fixes and other
 * saves never change "modified"), in Open Graph and in Yoast's schema alike.
 */
add_filter(
	'wpseo_frontend_presentation',
	static function ( $presentation ) {
		// Author archives: the profile photo, never the Gravatar Yoast would derive from the account email.
		if ( is_author() && is_object( $presentation ) ) {
			$photo  = (int) get_user_meta( (int) get_queried_object_id(), 'tdd_photo', true );
			$src    = $photo ? wp_get_attachment_image_src( $photo, 'full' ) : false;
			$images = $src ? array( $src[0] => array( 'url' => $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2], 'type' => (string) get_post_mime_type( $photo ) ) ) : array();
			$presentation->open_graph_images = $images;
			$presentation->twitter_image     = $src ? $src[0] : '';
		}
		if ( is_singular( 'post' ) && is_object( $presentation ) ) {
			$d = tdd_core_story_dates( (int) get_queried_object_id() );
			if ( $d ) {
				$presentation->open_graph_article_published_time = $d[0];
				$presentation->open_graph_article_modified_time  = $d[2] ? $d[1] : ''; // Only after a real update.
			}
		}
		return $presentation;
	},
	20
);
foreach ( array( 'wpseo_schema_article', 'wpseo_schema_webpage' ) as $tdd_core_hook ) {
	add_filter(
		$tdd_core_hook,
		static function ( $piece ) {
			if ( is_array( $piece ) && is_singular( 'post' ) ) {
				$d = tdd_core_story_dates( (int) get_queried_object_id() );
				if ( $d ) {
					$piece['datePublished'] = $d[0];
					$piece['dateModified']  = $d[1];
				}
			}
			return $piece;
		},
		20
	);
}

/** Yoast person pieces: never a Gravatar (derived from the private account email); the profile photo when set. */
add_filter(
	'wpseo_schema_person',
	static function ( $piece ) {
		if ( ! is_array( $piece ) || empty( $piece['image'] ) ) {
			return $piece;
		}
		$url = is_array( $piece['image'] ) ? (string) ( $piece['image']['url'] ?? '' ) : (string) $piece['image'];
		if ( str_contains( $url, 'gravatar.com' ) ) {
			unset( $piece['image'] );
		}
		return $piece;
	},
	20
);

/** "Est. reading time" in link previews = the reading time shown on the story. */
add_filter(
	'wpseo_enhanced_slack_data',
	static function ( $data ) {
		if ( is_array( $data ) && is_singular( 'post' ) ) {
			$label = __( 'Est. reading time', 'wordpress-seo' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Yoast's own key.
			if ( isset( $data[ $label ] ) ) {
				$m              = tdd_core_reading_time( (int) get_queried_object_id() );
				$data[ $label ] = sprintf( /* translators: %d: minutes */ _n( '%d minute', '%d minutes', $m, 'techdosedaily-core' ), $m );
			}
		}
		return $data;
	},
	20
);

/** Yoast image pieces carry the stored credit (and licence URL), as the Core graph does. */
add_filter(
	'wpseo_schema_imageobject',
	static function ( $piece ) {
		if ( ! is_array( $piece ) ) {
			return $piece;
		}
		$url = (string) ( $piece['contentUrl'] ?? ( $piece['url'] ?? '' ) );
		$id  = '' !== $url ? attachment_url_to_postid( $url ) : 0;
		if ( $id ) {
			$credit = trim( wp_strip_all_tags( (string) get_post_meta( $id, 'tdd_credit', true ) ) );
			if ( '' !== $credit ) {
				$piece['creditText'] = $credit;
			}
			$license = esc_url_raw( (string) get_post_meta( $id, 'tdd_license_note', true ), array( 'http', 'https' ) );
			if ( '' !== $license && wp_http_validate_url( $license ) ) {
				$piece['license'] = $license;
			}
		}
		return $piece;
	},
	20
);
