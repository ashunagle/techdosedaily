<?php
/**
 * Public, theme-facing API. Themes call these (guarded with function_exists) and never
 * read tdd_* meta or the placement/view tables directly.
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

function tdd_core_deck( $post = null ): string {
	$post = get_post( $post );
	return $post ? (string) get_post_meta( $post->ID, 'tdd_deck', true ) : '';
}

/** The story type term (defaults to News). */
function tdd_core_story_format( $post = null ): ?WP_Term {
	$post  = get_post( $post );
	$terms = $post ? get_the_terms( $post->ID, 'tdd_format' ) : false;
	if ( $terms && ! is_wp_error( $terms ) ) {
		return $terms[0];
	}
	$news = get_term_by( 'slug', 'news', 'tdd_format' );
	return $news ?: null;
}

function tdd_core_reading_time( $post = null ): int {
	$post = get_post( $post );
	if ( ! $post ) {
		return 0;
	}
	$m = (int) get_post_meta( $post->ID, 'tdd_reading_time', true );
	return $m ?: tdd_core_compute_reading_time( $post->post_content );
}

function tdd_core_editor( $post = null ): ?WP_User {
	$post = get_post( $post );
	$id   = $post ? (int) get_post_meta( $post->ID, 'tdd_editor', true ) : 0;
	$user = $id ? get_userdata( $id ) : false;
	return $user ?: null;
}

/**
 * Stories for a placement, resolved slot by slot (see tdd_core_placement_resolve()):
 * active explicit entry → next valid explicit entry → newest eligible story. Never leaves a hole.
 *
 * @param array $args { section: int, exclude: int[] (stories already on the page), fallback: bool }
 * @return WP_Post[]
 */
function tdd_core_fill_placement( string $placement, int $limit, array $args = array() ): array {
	return array_values( array_filter( array_map( 'get_post', tdd_core_placement_resolve( $placement, $limit, $args ) ) ) );
}

/**
 * Most-read stories from real view data only. Empty array = not enough data;
 * the theme then hides the Most Read module rather than faking it.
 *
 * @param array $args { context: home|section|author (picks the configured window), section: int,
 *                      author: int, hours: int (overrides the context window) }
 * @return WP_Post[]
 */
function tdd_core_most_read( int $limit = 5, array $args = array() ): array {
	$args  = wp_parse_args( $args, array( 'context' => 'home', 'section' => 0, 'author' => 0, 'hours' => 0 ) );
	$hours = (int) $args['hours'] ?: tdd_core_most_read_window( (string) $args['context'] );
	return array_values( array_filter( array_map( 'get_post', tdd_core_most_read_ids( $limit, (int) $args['section'], $hours, (int) $args['author'] ) ) ) );
}

/** Short headline for compact lists, falling back to the full title. */
function tdd_core_short_title( $post = null ): string {
	$post  = get_post( $post );
	$short = $post ? trim( (string) get_post_meta( $post->ID, 'tdd_short_title', true ) ) : '';
	return '' !== $short ? $short : ( $post ? get_the_title( $post ) : '' );
}

/** Most recent time a placement changed (for "Updated …" lines), or null. */
function tdd_core_placement_updated( string $placement, int $section_id = 0 ): ?int {
	global $wpdb;
	$table = tdd_core_placements_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$t = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(start_at) FROM {$table} WHERE placement = %s AND section_id = %d AND start_at <= %s", $placement, $section_id, gmdate( 'Y-m-d H:i:s' ) ) );
	return $t ? strtotime( $t . ' UTC' ) : null;
}

/**
 * Section page settings (term meta) with safe defaults.
 *
 * @return array{desk_note:string,desk_url:string,desk_members:int[],topic_nav:int[]}
 */
function tdd_core_section_settings( int $term_id ): array {
	return array(
		'desk_note'    => (string) get_term_meta( $term_id, 'tdd_desk_note', true ),
		'desk_url'     => (string) get_term_meta( $term_id, 'tdd_desk_url', true ),
		'desk_members' => array_values( array_filter( array_map( 'intval', (array) get_term_meta( $term_id, 'tdd_desk_members', true ) ) ) ),
		'topic_nav'    => array_values( array_filter( array_map( 'intval', (array) get_term_meta( $term_id, 'tdd_topic_nav', true ) ) ) ),
	);
}

/** Optional section-page modules editors can switch off (all on by default). */
function tdd_core_section_modules(): array {
	return array(
		'analysis' => __( 'Analysis & explainers', 'techdosedaily-core' ),
		'guides'   => __( 'Practical guides', 'techdosedaily-core' ),
		'topics'   => __( '“Follow a topic” chips', 'techdosedaily-core' ),
		'desk'     => __( 'Section desk (needs desk people)', 'techdosedaily-core' ),
		'brief'    => __( 'Daily Tech Brief link', 'techdosedaily-core' ),
	);
}

/** Whether a section-page module is on (term meta tdd_section_hidden lists the ones turned off). */
function tdd_core_section_module_on( int $term_id, string $module ): bool {
	return ! in_array( $module, (array) get_term_meta( $term_id, 'tdd_section_hidden', true ), true );
}

/**
 * Topics used by stories in a section, most used first: [ [WP_Term, count], … ].
 * Counts are real story counts within the section.
 */
function tdd_core_section_topics( int $section_id, int $limit = 8 ): array {
	global $wpdb;
	$key = 'tdd_sectopics_' . $section_id . '_' . $limit . '_' . wp_cache_get_last_changed( 'posts' ) . wp_cache_get_last_changed( 'terms' );
	$rows = wp_cache_get( $key, 'tdd' );
	if ( false === $rows ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tt.term_id, COUNT(DISTINCT p.ID) AS n
				 FROM {$wpdb->term_relationships} tr
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'tdd_topic'
				 INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_status = 'publish' AND p.post_type = 'post'
				 INNER JOIN {$wpdb->term_relationships} cr ON cr.object_id = p.ID
				 INNER JOIN {$wpdb->term_taxonomy} ct ON ct.term_taxonomy_id = cr.term_taxonomy_id AND ct.taxonomy = 'category' AND ct.term_id = %d
				 GROUP BY tt.term_id ORDER BY n DESC, tt.term_id ASC LIMIT %d",
				$section_id,
				$limit
			),
			ARRAY_A
		);
		wp_cache_set( $key, $rows, 'tdd', HOUR_IN_SECONDS );
	}
	$out = array();
	foreach ( (array) $rows as $r ) {
		$t = get_term( (int) $r['term_id'], 'tdd_topic' );
		if ( $t instanceof WP_Term ) {
			$out[] = array( $t, (int) $r['n'] );
		}
	}
	return $out;
}

/**
 * Phase 7: load posts, their meta, terms and featured-image attachments for a list of IDs in a few
 * batched queries, instead of one-by-one when each card is rendered (was ~110 queries on the homepage).
 *
 * @param int[] $ids Post IDs.
 * @return int[] The same IDs.
 */
function tdd_core_prime_posts( array $ids ): array {
	$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
	if ( ! $ids || ! function_exists( '_prime_post_caches' ) ) {
		return $ids;
	}
	_prime_post_caches( $ids, true, true );
	$thumbs = array();
	foreach ( $ids as $id ) {
		$t = (int) get_post_meta( $id, '_thumbnail_id', true );
		if ( $t ) {
			$thumbs[] = $t;
		}
	}
	if ( $thumbs ) {
		_prime_post_caches( $thumbs, false, true );
	}
	return $ids;
}

/** Published stories in a section since a timestamp (masthead counts). */
function tdd_core_section_count_since( int $section_id, int $since ): int {
	$q = new WP_Query(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'cat'            => $section_id,
			'date_query'     => array( array( 'after' => gmdate( 'Y-m-d H:i:s', $since ), 'column' => 'post_date_gmt', 'inclusive' => true ) ),
			'fields'         => 'ids',
			'posts_per_page' => 1,
		)
	);
	return (int) $q->found_posts;
}
