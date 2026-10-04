<?php
/**
 * Sections = top-level categories served at top-level URLs: /ai/, /big-tech/ …
 *
 * - Category links drop the /category/ base for top-level sections.
 * - Rewrite rules resolve /ai/, /ai/page/2/ and /ai/feed/ directly.
 * - Old /category/ai/… URLs 301 to the new URL (no duplicate indexable archive).
 * - Section slugs are reserved: top-level Pages cannot take them.
 * - Every post has exactly one primary section (meta tdd_primary_section).
 * - URL stability: the section in a story's URL is stored at first publication
 *   (protected meta _tdd_permalink_section, a slug) and never changes afterwards. If the
 *   primary section later changes, /ai/new-reasoning-model/ stays the URL; the story appears
 *   in its new section's archive and schema uses the current section. Any other
 *   /<section>/<slug>/ spelling 301s to the stored URL (redirect_canonical).
 *
 * Sections have no child categories by design; secondary discovery uses tdd_topic.
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

/** The nine approved sections (slug => name). Used for seeding, reservation and docs. */
function tdd_core_default_sections(): array {
	return array(
		'ai'            => 'AI',
		'tech'          => 'Tech',
		'software'      => 'Software',
		'cybersecurity' => 'Cybersecurity',
		'startups'      => 'Startups',
		'big-tech'      => 'Big Tech',
		'cloud'         => 'Cloud',
		'developer'     => 'Developer',
		'guides'        => 'Guides',
	);
}

/** Slugs of all live top-level sections (excluding the default "uncategorized"). */
function tdd_core_section_slugs(): array {
	$terms = get_terms(
		array(
			'taxonomy'   => 'category',
			'parent'     => 0,
			'hide_empty' => false,
			'fields'     => 'slugs',
		)
	);
	$slugs = is_wp_error( $terms ) ? array() : array_diff( $terms, array( 'uncategorized' ) );
	return array_values( array_unique( array_merge( array_keys( tdd_core_default_sections() ), $slugs ) ) );
}

/** Slugs that top-level Pages may never use. */
function tdd_core_reserved_slugs(): array {
	return apply_filters( 'tdd_core_reserved_slugs', array_merge( tdd_core_section_slugs(), array( 'category', 'topic', 'story-type', 'author', 'search', 'page', 'feed' ) ) );
}

/* ---------- URLs ---------- */

/** Top-level section link: /ai/ instead of /category/ai/. */
add_filter(
	'term_link',
	static function ( string $url, $term, string $taxonomy ): string {
		if ( 'category' === $taxonomy && 0 === (int) $term->parent && 'uncategorized' !== $term->slug ) {
			return home_url( user_trailingslashit( $term->slug, 'category' ) );
		}
		return $url;
	},
	10,
	3
);

/** Rewrite rules for section archives, pagination and feeds. */
function tdd_core_sections_rewrite_rules(): void {
	foreach ( tdd_core_section_slugs() as $slug ) {
		$s = preg_quote( $slug, '#' );
		add_rewrite_rule( "^{$s}/?$", 'index.php?category_name=' . $slug, 'top' );
		add_rewrite_rule( "^{$s}/page/?([0-9]{1,})/?$", 'index.php?category_name=' . $slug . '&paged=$matches[1]', 'top' );
		add_rewrite_rule( "^{$s}/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$", 'index.php?category_name=' . $slug . '&feed=$matches[1]', 'top' );
	}
}
add_action( 'init', 'tdd_core_sections_rewrite_rules' );

/** Re-flush rules when sections change (deferred to the next request). */
foreach ( array( 'created_category', 'edited_category', 'delete_category' ) as $tdd_core_hook ) {
	add_action( $tdd_core_hook, static fn() => update_option( 'tdd_core_flush_rules', 1, false ) );
}
add_action(
	'init',
	static function () {
		if ( get_option( 'tdd_core_flush_rules' ) ) {
			delete_option( 'tdd_core_flush_rules' );
			flush_rewrite_rules( false );
		}
	},
	99
);

/** 301 any /category/<section>/… URL to its top-level equivalent. */
add_action(
	'template_redirect',
	static function () {
		$path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', PHP_URL_PATH );
		$base = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		$path = trim( (string) $path, '/' );
		if ( $base && str_starts_with( $path, $base . '/' ) ) {
			$path = substr( $path, strlen( $base ) + 1 );
		}
		if ( ! preg_match( '#^category/([^/]+)(/.*)?$#', $path, $m ) ) {
			return;
		}
		if ( ! in_array( $m[1], tdd_core_section_slugs(), true ) ) {
			return;
		}
		$rest  = isset( $m[2] ) ? ltrim( $m[2], '/' ) : '';
		$query = isset( $_SERVER['QUERY_STRING'] ) && '' !== $_SERVER['QUERY_STRING'] ? '?' . sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';
		wp_safe_redirect( home_url( user_trailingslashit( $m[1] . ( $rest ? '/' . untrailingslashit( $rest ) : '' ) ) ) . $query, 301, 'TechDoseDaily Core' );
		exit;
	},
	1
);

/** Reserve section slugs: a top-level Page called "ai" becomes "ai-page". */
add_filter(
	'wp_unique_post_slug',
	static function ( string $slug, int $post_id, string $status, string $post_type, int $parent ) {
		if ( 0 === $parent && is_post_type_hierarchical( $post_type ) && in_array( $slug, tdd_core_reserved_slugs(), true ) ) {
			return $slug . '-page';
		}
		return $slug;
	},
	10,
	5
);

/* ---------- Primary section ---------- */

/**
 * Primary section of a post, or null.
 *
 * @param int|WP_Post|null $post Post.
 */
function tdd_core_primary_section( $post = null ): ?WP_Term {
	$post = get_post( $post );
	if ( ! $post ) {
		return null;
	}
	$id = (int) get_post_meta( $post->ID, 'tdd_primary_section', true );
	if ( $id ) {
		$term = get_term( $id, 'category' );
		if ( $term instanceof WP_Term ) {
			return $term;
		}
	}
	$cats = get_the_category( $post->ID );
	$cats = array_values( array_filter( $cats, static fn( $c ) => 0 === (int) $c->parent && 'uncategorized' !== $c->slug ) );
	return $cats[0] ?? null;
}

/**
 * Section slug used in a post's URL: stored at first publication, else (drafts, scheduled,
 * stories published before this existed) the current primary section.
 */
function tdd_core_permalink_section_slug( $post ): string {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$stored = (string) get_post_meta( $post->ID, '_tdd_permalink_section', true );
	if ( '' !== $stored ) {
		return $stored;
	}
	$primary = tdd_core_primary_section( $post );
	return $primary ? $primary->slug : '';
}

/** Freeze the URL section the first time a story is published. */
add_action(
	'wp_after_insert_post',
	static function ( int $post_id, WP_Post $post ) {
		if ( 'post' !== $post->post_type || 'publish' !== $post->post_status || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( '' !== (string) get_post_meta( $post_id, '_tdd_permalink_section', true ) ) {
			return;
		}
		$primary = tdd_core_primary_section( $post );
		if ( $primary ) {
			update_post_meta( $post_id, '_tdd_permalink_section', $primary->slug );
		}
	},
	20,
	2
);

/** %category% in post permalinks = the stored URL section (works even if that section was renamed away). */
add_filter(
	'post_link_category',
	static function ( $cat, $cats, $post ) {
		$slug = tdd_core_permalink_section_slug( $post );
		if ( '' === $slug ) {
			return $cat;
		}
		$term = get_term_by( 'slug', $slug, 'category' );
		if ( $term instanceof WP_Term && 0 === (int) $term->parent ) {
			return $term;
		}
		// Section no longer exists as a term: keep the frozen slug anyway.
		return new WP_Term( (object) array( 'term_id' => 0, 'slug' => $slug, 'parent' => 0, 'taxonomy' => 'category', 'name' => $slug ) );
	},
	10,
	3
);

/**
 * /<section>/<slug>/ resolves the story by slug alone, so a story whose section changed is still
 * found; redirect_canonical then 301s any non-stored section spelling to the real URL.
 */
add_filter(
	'request',
	static function ( array $vars ): array {
		if ( ! empty( $vars['name'] ) && ! empty( $vars['category_name'] ) && empty( $vars['post_type'] ) && empty( $vars['feed'] ) ) {
			unset( $vars['category_name'] );
		}
		return $vars;
	}
);

/** Keep the primary section valid: it must be one of the post's categories. */
add_action(
	'save_post_post',
	static function ( int $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$cats    = wp_get_post_categories( $post_id );
		$primary = (int) get_post_meta( $post_id, 'tdd_primary_section', true );
		if ( $primary && in_array( $primary, $cats, true ) ) {
			return;
		}
		$top = array_values(
			array_filter(
				$cats,
				static function ( $id ) {
					$t = get_term( $id, 'category' );
					return $t && ! is_wp_error( $t ) && 0 === (int) $t->parent && 'uncategorized' !== $t->slug;
				}
			)
		);
		if ( 1 === count( $top ) ) {
			update_post_meta( $post_id, 'tdd_primary_section', $top[0] );
		} elseif ( $primary && ! in_array( $primary, $cats, true ) ) {
			delete_post_meta( $post_id, 'tdd_primary_section' ); // Editor must choose (enforced in the editor panel, Phase 5).
		}
	},
	20
);

/** Seed the nine sections if they are missing (activation + WP-CLI). */
function tdd_core_seed_sections(): void {
	foreach ( tdd_core_default_sections() as $slug => $name ) {
		if ( ! term_exists( $slug, 'category' ) ) {
			wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
		}
	}
}

/** 301 /<other-section>/<slug>/ to the story's stored URL (keeps any trailing /2/ or query). */
add_action(
	'template_redirect',
	static function () {
		if ( ! is_singular( 'post' ) || is_preview() || is_feed() || is_embed() ) {
			return;
		}
		$post = get_queried_object();
		$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', PHP_URL_PATH ), '/' );
		$base = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( $base && str_starts_with( $path, $base . '/' ) ) {
			$path = substr( $path, strlen( $base ) + 1 );
		}
		$parts = explode( '/', $path );
		if ( count( $parts ) < 2 || $parts[1] !== $post->post_name ) {
			return; // ?p= links etc. are handled by core.
		}
		$expected = tdd_core_permalink_section_slug( $post );
		if ( '' === $expected || $parts[0] === $expected ) {
			return;
		}
		$rest  = implode( '/', array_slice( $parts, 2 ) );
		$query = isset( $_SERVER['QUERY_STRING'] ) && '' !== $_SERVER['QUERY_STRING'] ? '?' . sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';
		wp_safe_redirect( get_permalink( $post ) . ( $rest ? user_trailingslashit( $rest ) : '' ) . $query, 301, 'TechDoseDaily Core' );
		exit;
	},
	2
);

/** One-time backfill: freeze the URL section of already-published stories (activation + upgrade). */
function tdd_core_backfill_permalink_sections(): void {
	$ids = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => '_tdd_permalink_section', 'compare' => 'NOT EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	foreach ( $ids as $id ) {
		$primary = tdd_core_primary_section( $id );
		if ( $primary ) {
			update_post_meta( $id, '_tdd_permalink_section', $primary->slug );
		}
	}
	update_option( 'tdd_core_permalink_backfill', 1, false );
}
add_action(
	'init',
	static function () {
		if ( ! get_option( 'tdd_core_permalink_backfill' ) ) {
			tdd_core_backfill_permalink_sections();
		}
	},
	20
);
