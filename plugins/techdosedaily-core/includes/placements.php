<?php
/**
 * Editorial placements — one small table instead of per-post feature booleans.
 *
 *   placement   homepage_lead | homepage_secondary | editors_pick | ai_band_lead | ai_band_secondary
 *               | section_lead | section_secondary | daily_brief | most_read_pick
 *   post_id     the story
 *   position    1-based slot within the placement
 *   section_id  optional category term (section_* placements)
 *   start_at    when the placement becomes active (UTC)
 *   expires_at  optional end (UTC); NULL = until replaced
 *
 * Resolution (per slot, see tdd_core_placement_resolve()):
 *   1. the active explicit entry — the most recently started valid entry for that position;
 *   2. otherwise the next valid explicit entry for that position (an older one still in its window,
 *      e.g. when the newer story expired, was unpublished, or is already shown elsewhere on the page);
 *   3. otherwise the newest eligible story (automatic fallback).
 * So placing a story in an occupied slot does NOT end the previous entry: it is superseded and
 * becomes the fallback if the newer one expires. Expiry never leaves a hole in the UI.
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

const TDD_CORE_PLACEMENTS_DB = '2'; // 2: start/expiry indexes (Phase 7 cache boundaries).

function tdd_core_placements_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'tdd_placements';
}

/** Registry: key => [label, max positions, needs section]. Filterable. */
function tdd_core_placement_types(): array {
	return apply_filters(
		'tdd_core_placement_types',
		array(
			'homepage_lead'      => array( __( 'Homepage lead story', 'techdosedaily-core' ), 1, false ),
			'homepage_secondary' => array( __( 'Homepage secondary stories', 'techdosedaily-core' ), 4, false ),
			'editors_pick'       => array( __( "Editor's Picks", 'techdosedaily-core' ), 4, false ),
			'ai_band_lead'       => array( __( 'AI News Today — lead', 'techdosedaily-core' ), 1, false ),
			'ai_band_secondary'  => array( __( 'AI News Today — supporting', 'techdosedaily-core' ), 5, false ),
			'section_lead'       => array( __( 'Section page — pinned feature', 'techdosedaily-core' ), 1, true ),
			'section_secondary'  => array( __( 'Section page — supporting features', 'techdosedaily-core' ), 3, true ),
			'daily_brief'        => array( __( 'Daily Tech Brief — items', 'techdosedaily-core' ), 10, false ),
		)
	);
}

function tdd_core_placements_install(): void {
	global $wpdb;
	$table   = tdd_core_placements_table();
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta(
		"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			placement varchar(64) NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			position smallint(5) unsigned NOT NULL DEFAULT 1,
			section_id bigint(20) unsigned NOT NULL DEFAULT 0,
			start_at datetime NOT NULL,
			expires_at datetime NULL DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY placement_lookup (placement,section_id,position),
			KEY post_id (post_id),
			KEY start_at (start_at),
			KEY expires_at (expires_at)
		) {$charset};"
	);
	update_option( 'tdd_core_placements_db', TDD_CORE_PLACEMENTS_DB, false );
}
add_action(
	'plugins_loaded',
	static function () {
		if ( TDD_CORE_PLACEMENTS_DB !== get_option( 'tdd_core_placements_db' ) ) {
			tdd_core_placements_install();
		}
	}
);

/** Cache-busting version for placement reads. */
function tdd_core_placements_bump(): void {
	wp_cache_set( 'version', microtime( true ), 'tdd_placements' );
	do_action( 'tdd_core_placements_changed' ); // Page caches purge (performance.php).
}
function tdd_core_placements_version(): string {
	$v = wp_cache_get( 'version', 'tdd_placements' );
	return $v ? (string) $v : '0';
}

/**
 * Valid explicit candidates per position: [ position => int[] post IDs, newest start first ].
 * Valid = started, not expired, published.
 *
 * @return array<int,int[]>
 */
function tdd_core_placement_candidates( string $placement, int $section_id = 0 ): array {
	global $wpdb;
	// Phase 7: the cached rows are NOT pre-filtered by the clock. Start/expiry are compared with the
	// current time on every call, so a cached copy can never show an entry before it starts or after
	// it ends (page caches rely on this when they regenerate exactly at a placement boundary).
	// Placement edits and status changes of placed stories bump the version (fresh rows at once).
	$key  = "cand2:{$placement}:{$section_id}:" . tdd_core_placements_version();
	$rows = wp_cache_get( $key, 'tdd_placements' );
	if ( false === $rows ) {
		$table = tdd_core_placements_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.position, p.post_id, p.start_at, p.expires_at FROM {$table} p
				 INNER JOIN {$wpdb->posts} wp ON wp.ID = p.post_id AND wp.post_status = 'publish'
				 WHERE p.placement = %s AND p.section_id = %d AND ( p.expires_at IS NULL OR p.expires_at > %s )
				 ORDER BY p.position ASC, p.start_at DESC, p.id DESC",
				$placement,
				$section_id,
				gmdate( 'Y-m-d H:i:s' )
			),
			ARRAY_A
		);
		wp_cache_set( $key, $rows, 'tdd_placements', 10 * MINUTE_IN_SECONDS );
	}
	$now = gmdate( 'Y-m-d H:i:s' );
	$out = array();
	foreach ( (array) $rows as $r ) {
		if ( $r['start_at'] <= $now && ( null === $r['expires_at'] || '' === (string) $r['expires_at'] || $r['expires_at'] > $now ) ) {
			$out[ (int) $r['position'] ][] = (int) $r['post_id'];
		}
	}
	return $out;
}

/**
 * Resolve a placement into a gap-free list of post IDs.
 *
 * @param string $placement Placement key.
 * @param int    $limit     Slots to fill (usually the placement's max positions).
 * @param array  $args      { section: int, exclude: int[] (already on the page), fallback: bool,
 *                            fallback_section: int (section for automatic fill; default = section) }
 * @return int[]
 */
function tdd_core_placement_resolve( string $placement, int $limit, array $args = array() ): array {
	$args    = wp_parse_args( $args, array( 'section' => 0, 'exclude' => array(), 'fallback' => true, 'fallback_section' => null ) );
	$fcat    = null === $args['fallback_section'] ? (int) $args['section'] : (int) $args['fallback_section'];
	$used    = array_fill_keys( array_map( 'intval', (array) $args['exclude'] ), true );
	$cands   = tdd_core_placement_candidates( $placement, (int) $args['section'] );
	$slots   = array();
	for ( $pos = 1; $pos <= $limit; $pos++ ) {
		$slots[ $pos ] = 0;
		foreach ( $cands[ $pos ] ?? array() as $id ) {
			if ( empty( $used[ $id ] ) ) {
				$slots[ $pos ] = $id;
				$used[ $id ]   = true;
				break;
			}
		}
	}
	$missing = count( array_filter( $slots, static fn( $id ) => 0 === $id ) );
	if ( $missing && $args['fallback'] ) {
		$q = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $missing,
			'post__not_in'        => array_keys( $used ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'fields'              => 'ids',
		);
		if ( $fcat ) {
			$q['cat'] = $fcat;
		}
		$auto = array_map( 'intval', get_posts( $q ) );
		foreach ( $slots as $pos => $id ) {
			if ( 0 === $id && $auto ) {
				$slots[ $pos ] = array_shift( $auto );
			}
		}
	}
	return tdd_core_prime_posts( array_values( array_filter( $slots ) ) ); // Compact: never a hole.
}

/**
 * Active explicit post IDs for a placement (slot holders only), ordered by position.
 *
 * @return int[]
 */
function tdd_core_placement_ids( string $placement, int $section_id = 0 ): array {
	$types = tdd_core_placement_types();
	return tdd_core_placement_resolve( $placement, (int) ( $types[ $placement ][1] ?? 10 ), array( 'section' => $section_id, 'fallback' => false ) );
}

/**
 * Place a story. The previous holder of the slot is superseded, not ended, so it takes over
 * again if this entry expires. Any other active entry for the same story in this placement
 * (a different position) is ended, so a story never occupies two slots of one placement.
 *
 * @return int|WP_Error Placement row ID.
 */
function tdd_core_place( int $post_id, string $placement, int $position = 1, int $section_id = 0, ?string $start = null, ?string $expires = null ) {
	global $wpdb;
	$types = tdd_core_placement_types();
	if ( ! isset( $types[ $placement ] ) ) {
		return new WP_Error( 'tdd_bad_placement', __( 'Unknown placement.', 'techdosedaily-core' ), array( 'status' => 400 ) );
	}
	[ , $max, $needs_section ] = $types[ $placement ];
	if ( $position < 1 || $position > $max ) {
		return new WP_Error( 'tdd_bad_position', sprintf( /* translators: %d: max */ __( 'Position must be between 1 and %d.', 'techdosedaily-core' ), $max ), array( 'status' => 400 ) );
	}
	if ( $needs_section && ! ( $section_id && get_term( $section_id, 'category' ) instanceof WP_Term ) ) {
		return new WP_Error( 'tdd_bad_section', __( 'This placement needs a section.', 'techdosedaily-core' ), array( 'status' => 400 ) );
	}
	if ( 'post' !== get_post_type( $post_id ) ) {
		return new WP_Error( 'tdd_bad_post', __( 'Only stories can be placed.', 'techdosedaily-core' ), array( 'status' => 400 ) );
	}
	$now   = gmdate( 'Y-m-d H:i:s' );
	$start = $start ? gmdate( 'Y-m-d H:i:s', strtotime( $start ) ) : $now;
	$exp   = $expires ? gmdate( 'Y-m-d H:i:s', strtotime( $expires ) ) : null;
	$table = tdd_core_placements_table();
	$section_id = $needs_section ? $section_id : 0;

	// End other active entries for the same story in this placement (it moves, it doesn't duplicate).
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET expires_at = %s WHERE placement = %s AND section_id = %d AND post_id = %d AND ( expires_at IS NULL OR expires_at > %s )", $start, $placement, $section_id, $post_id, $start ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert(
		$table,
		array(
			'placement'  => $placement,
			'post_id'    => $post_id,
			'position'   => $position,
			'section_id' => $section_id,
			'start_at'   => $start,
			'expires_at' => $exp,
			'created_by' => get_current_user_id(),
			'created_at' => $now,
		),
		array( '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%s' ) // wpdb writes NULL for null values.
	);
	tdd_core_placements_bump();
	return (int) $wpdb->insert_id;
}

/** End a placement now (history kept). */
function tdd_core_unplace( int $id ): bool {
	global $wpdb;
	$table = tdd_core_placements_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$done = (bool) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET expires_at = %s WHERE id = %d", gmdate( 'Y-m-d H:i:s' ), $id ) );
	tdd_core_placements_bump();
	return $done;
}

/** Active rows for admin/REST listing. */
function tdd_core_placement_rows( string $placement = '', int $section_id = -1 ): array {
	global $wpdb;
	$table = tdd_core_placements_table();
	$now   = gmdate( 'Y-m-d H:i:s' );
	$where = $wpdb->prepare( 'start_at <= %s AND ( expires_at IS NULL OR expires_at > %s )', $now, $now );
	if ( $placement ) {
		$where .= $wpdb->prepare( ' AND placement = %s', $placement );
	}
	if ( $section_id >= 0 ) {
		$where .= $wpdb->prepare( ' AND section_id = %d', $section_id );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	return $wpdb->get_results( "SELECT id, placement, post_id, position, section_id, start_at, expires_at FROM {$table} WHERE {$where} ORDER BY placement, section_id, position", ARRAY_A );
}

/**
 * What fills an empty slot, for the placement editor ("Automatic fallback: newest eligible AI story").
 * Mirrors how the theme resolves each placement.
 *
 * @return array{mode:string, section:int, label:string}
 */
function tdd_core_placement_fallback( string $placement, int $section_id = 0 ): array {
	$ai = get_term_by( 'slug', 'ai', 'category' );
	switch ( $placement ) {
		case 'ai_band_lead':
		case 'ai_band_secondary':
			return array( 'mode' => 'section', 'section' => $ai ? (int) $ai->term_id : 0, 'label' => __( 'newest eligible AI story', 'techdosedaily-core' ) );
		case 'section_lead':
		case 'section_secondary':
			$t = get_term( $section_id, 'category' );
			/* translators: %s: section name. */
			return array( 'mode' => 'section', 'section' => $section_id, 'label' => sprintf( __( 'newest eligible %s story', 'techdosedaily-core' ), $t instanceof WP_Term ? $t->name : __( 'section', 'techdosedaily-core' ) ) );
		case 'daily_brief':
			return array( 'mode' => 'none', 'section' => 0, 'label' => __( 'not filled automatically — the brief only lists stories editors choose', 'techdosedaily-core' ) );
		default:
			return array( 'mode' => 'latest', 'section' => 0, 'label' => __( 'newest eligible story', 'techdosedaily-core' ) );
	}
}

/** Placements shown higher up the same page (the live page never repeats a story). */
function tdd_core_placement_page_order(): array {
	return array(
		'home'    => array( 'homepage_lead', 'homepage_secondary', 'ai_band_lead', 'ai_band_secondary', 'editors_pick' ),
		'section' => array( 'section_lead', 'section_secondary' ),
		'brief'   => array( 'daily_brief' ),
	);
}

/** Local date input (site timezone, or any ISO string with an offset) → UTC 'Y-m-d H:i:s', or null. */
function tdd_core_to_utc( $value ): ?string {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return null;
	}
	try {
		return ( new DateTimeImmutable( $value, wp_timezone() ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	} catch ( Exception $e ) {
		return null;
	}
}

/** A story as the admin tools show it. */
function tdd_core_placement_post_summary( int $post_id ): ?array {
	$p = get_post( $post_id );
	if ( ! $p ) {
		return null;
	}
	$sec = function_exists( 'tdd_core_primary_section' ) ? tdd_core_primary_section( $p ) : null;
	return array(
		'id'       => $p->ID,
		'title'    => html_entity_decode( get_the_title( $p ), ENT_QUOTES ),
		'status'   => $p->post_status,
		'section'  => $sec ? $sec->name : '',
		'date'     => get_post_time( DATE_ATOM, true, $p ),
		'link'     => get_permalink( $p ),
		'edit'     => current_user_can( 'edit_post', $p->ID ) ? get_edit_post_link( $p->ID, 'raw' ) : '',
		'breaking' => function_exists( 'tdd_core_is_breaking' ) && tdd_core_is_breaking( $p ),
	);
}

/** One placement row for REST (times as ISO 8601 UTC). */
function tdd_core_placement_entry( array $r ): array {
	$by = get_userdata( (int) ( $r['created_by'] ?? 0 ) );
	return array(
		'id'         => (int) $r['id'],
		'position'   => (int) $r['position'],
		'start_at'   => gmdate( 'c', strtotime( $r['start_at'] . ' UTC' ) ),
		'expires_at' => $r['expires_at'] ? gmdate( 'c', strtotime( $r['expires_at'] . ' UTC' ) ) : null,
		'by'         => $by ? $by->display_name : '',
		'post'       => tdd_core_placement_post_summary( (int) $r['post_id'] ),
	);
}

/**
 * Editor view of one placement: per slot the live entry, the entries waiting behind it (they take
 * over if it expires), scheduled entries, and — for empty slots — the story the automatic fallback
 * picks right now.
 *
 * @param int[] $page_exclude Stories already used higher up the page (preview of the live dedupe).
 */
function tdd_core_placement_board( string $placement, int $section_id = 0, array $page_exclude = array() ): array {
	global $wpdb;
	$types = tdd_core_placement_types();
	[ $label, $max, $needs_section ] = $types[ $placement ];
	$section_id = $needs_section ? $section_id : 0;
	$table      = tdd_core_placements_table();
	$now        = gmdate( 'Y-m-d H:i:s' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.* FROM {$table} p INNER JOIN {$wpdb->posts} wp ON wp.ID = p.post_id AND wp.post_type = 'post' WHERE p.placement = %s AND p.section_id = %d AND ( p.expires_at IS NULL OR p.expires_at > %s ) ORDER BY p.position ASC, p.start_at DESC, p.id DESC", $placement, $section_id, $now ), ARRAY_A );
	$slots = array();
	for ( $pos = 1; $pos <= $max; $pos++ ) {
		$slots[ $pos ] = array( 'position' => $pos, 'current' => null, 'queue' => array(), 'scheduled' => array(), 'auto' => null );
	}
	$used = array_fill_keys( array_map( 'intval', $page_exclude ), true );
	foreach ( (array) $rows as $r ) {
		$pos = (int) $r['position'];
		if ( ! isset( $slots[ $pos ] ) ) {
			continue;
		}
		$entry = tdd_core_placement_entry( $r );
		if ( $r['start_at'] > $now ) {
			$slots[ $pos ]['scheduled'][] = $entry;
		} elseif ( 'publish' !== get_post_status( (int) $r['post_id'] ) ) {
			$entry['skipped'] = 'unpublished';
			$slots[ $pos ]['queue'][] = $entry;
		} elseif ( null === $slots[ $pos ]['current'] && empty( $used[ (int) $r['post_id'] ] ) ) {
			$slots[ $pos ]['current'] = $entry;
			$used[ (int) $r['post_id'] ] = true;
		} else {
			if ( ! empty( $used[ (int) $r['post_id'] ] ) && null === $slots[ $pos ]['current'] ) {
				$entry['skipped'] = 'shown-above';
			}
			$slots[ $pos ]['queue'][] = $entry;
		}
	}
	$fb = tdd_core_placement_fallback( $placement, $section_id );
	if ( 'none' !== $fb['mode'] ) {
		foreach ( $slots as $pos => $slot ) {
			if ( $slot['current'] ) {
				continue;
			}
			$q = array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1, 'post__not_in' => array_keys( $used ), 'ignore_sticky_posts' => true, 'no_found_rows' => true, 'fields' => 'ids' );
			if ( $fb['section'] ) {
				$q['cat'] = $fb['section'];
			}
			$id = (int) ( get_posts( $q )[0] ?? 0 );
			if ( $id ) {
				$slots[ $pos ]['auto'] = tdd_core_placement_post_summary( $id );
				$used[ $id ]           = true;
			}
		}
	}
	return array(
		'placement'     => $placement,
		'label'         => $label,
		'max'           => $max,
		'needs_section' => $needs_section,
		'section'       => $section_id,
		'fallback'      => $fb,
		'slots'         => array_values( $slots ),
		'used'          => array_keys( $used ),
	);
}

/** Change an entry's window (start / expiry). */
function tdd_core_placement_update( int $id, ?string $start, ?string $expires, bool $clear_expiry = false ) {
	global $wpdb;
	$table = tdd_core_placements_table();
	$data  = array();
	if ( null !== $start ) {
		$data['start_at'] = $start;
	}
	if ( null !== $expires ) {
		$data['expires_at'] = $expires;
	} elseif ( $clear_expiry ) {
		$data['expires_at'] = null;
	}
	if ( ! $data ) {
		return new WP_Error( 'tdd_nothing', __( 'Nothing to change.', 'techdosedaily-core' ), array( 'status' => 400 ) );
	}
	if ( isset( $data['start_at'], $data['expires_at'] ) && $data['expires_at'] && $data['expires_at'] <= $data['start_at'] ) {
		return new WP_Error( 'tdd_bad_window', __( 'The end must be after the start.', 'techdosedaily-core' ), array( 'status' => 400 ) );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$ok = $wpdb->update( $table, $data, array( 'id' => $id ) );
	tdd_core_placements_bump();
	return false !== $ok;
}

/** Active and scheduled placements of one story. */
function tdd_core_placements_for_post( int $post_id ): array {
	global $wpdb;
	$table = tdd_core_placements_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d AND ( expires_at IS NULL OR expires_at > %s ) ORDER BY start_at DESC", $post_id, gmdate( 'Y-m-d H:i:s' ) ), ARRAY_A );
	$types = tdd_core_placement_types();
	return array_map(
		static function ( $r ) use ( $types ) {
			$e              = tdd_core_placement_entry( $r );
			$e['placement'] = $r['placement'];
			$e['label']     = $types[ $r['placement'] ][0] ?? $r['placement'];
			$t              = (int) $r['section_id'] ? get_term( (int) $r['section_id'], 'category' ) : null;
			$e['section']   = $t instanceof WP_Term ? $t->name : '';
			unset( $e['post'] );
			return $e;
		},
		(array) $rows
	);
}

/** A placed story that is published, unpublished, scheduled or trashed changes what placements resolve to. */
add_action(
	'transition_post_status',
	static function ( string $new, string $old, WP_Post $post ) {
		if ( 'post' === $post->post_type && $new !== $old && ( 'publish' === $new || 'publish' === $old ) ) {
			tdd_core_placements_bump();
		}
	},
	10,
	3
);

/** A deleted story leaves no placement behind (trashed stories simply stop qualifying). */
add_action(
	'deleted_post',
	static function ( int $post_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $wpdb->delete( tdd_core_placements_table(), array( 'post_id' => $post_id ), array( '%d' ) ) ) {
			tdd_core_placements_bump();
		}
	}
);

/* ---------- REST: /tdd/v1/placements (editors only) ---------- */
add_action(
	'rest_api_init',
	static function () {
		$perm = static fn() => current_user_can( 'edit_others_posts' );
		register_rest_route(
			'tdd/v1',
			'/placements',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $perm,
					'callback'            => static fn( WP_REST_Request $r ) => rest_ensure_response( tdd_core_placement_rows( (string) $r['placement'], null === $r['section'] ? -1 : (int) $r['section'] ) ),
					'args'                => array(
						'placement' => array( 'type' => 'string', 'enum' => array_merge( array( '' ), array_keys( tdd_core_placement_types() ) ) ),
						'section'   => array( 'type' => 'integer' ),
					),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => $perm,
					'callback'            => static function ( WP_REST_Request $r ) {
						if ( ! in_array( get_post_status( (int) $r['post_id'] ), array( 'publish', 'future' ), true ) ) {
							return new WP_Error( 'tdd_bad_post', __( 'Only published or scheduled stories can be placed.', 'techdosedaily-core' ), array( 'status' => 400 ) );
						}
						$start = tdd_core_to_utc( $r['start_at'] ?? '' );
						$exp   = tdd_core_to_utc( $r['expires_at'] ?? '' );
						if ( $start && $exp && $exp <= $start ) {
							return new WP_Error( 'tdd_bad_window', __( 'The end must be after the start.', 'techdosedaily-core' ), array( 'status' => 400 ) );
						}
						$id = tdd_core_place( (int) $r['post_id'], (string) $r['placement'], (int) ( $r['position'] ?? 1 ), (int) ( $r['section'] ?? 0 ), $start ? $start . ' UTC' : null, $exp ? $exp . ' UTC' : null );
						return is_wp_error( $id ) ? $id : rest_ensure_response( array( 'id' => $id ) );
					},
					'args'                => array(
						'post_id'    => array( 'type' => 'integer', 'required' => true ),
						'placement'  => array( 'type' => 'string', 'required' => true, 'enum' => array_keys( tdd_core_placement_types() ) ),
						'position'   => array( 'type' => 'integer', 'default' => 1 ),
						'section'    => array( 'type' => 'integer', 'default' => 0 ),
						'start_at'   => array( 'type' => 'string' ),
						'expires_at' => array( 'type' => 'string' ),
					),
				),
			)
		);
		register_rest_route(
			'tdd/v1',
			'/placements/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'DELETE',
					'permission_callback' => $perm,
					'callback'            => static fn( WP_REST_Request $r ) => rest_ensure_response( array( 'ended' => tdd_core_unplace( (int) $r['id'] ) ) ),
				),
				array(
					'methods'             => 'PATCH',
					'permission_callback' => $perm,
					'callback'            => static function ( WP_REST_Request $r ) {
						$start = $r->has_param( 'start_at' ) ? tdd_core_to_utc( $r['start_at'] ) : null;
						$exp   = $r->has_param( 'expires_at' ) ? tdd_core_to_utc( $r['expires_at'] ) : null;
						$out   = tdd_core_placement_update( (int) $r['id'], $start, $exp, $r->has_param( 'expires_at' ) && null === $exp );
						return is_wp_error( $out ) ? $out : rest_ensure_response( array( 'updated' => $out ) );
					},
					'args'                => array(
						'start_at'   => array( 'type' => array( 'string', 'null' ) ),
						'expires_at' => array( 'type' => array( 'string', 'null' ) ),
					),
				),
			)
		);
		// Placement editor: one placement (optionally for a section), as editors see it.
		register_rest_route(
			'tdd/v1',
			'/placements/board',
			array(
				'methods'             => 'GET',
				'permission_callback' => $perm,
				'callback'            => static function ( WP_REST_Request $r ) {
					$section = (int) $r['section'];
					$group   = (string) $r['group'];
					$order   = tdd_core_placement_page_order()[ $group ] ?? array( (string) $r['placement'] );
					$out     = array();
					$used    = array();
					foreach ( $order as $placement ) {
						$board  = tdd_core_placement_board( $placement, $section, $used );
						$used   = $board['used'];
						unset( $board['used'] );
						$out[] = $board;
					}
					return rest_ensure_response( $out );
				},
				'args'                => array(
					'group'     => array( 'type' => 'string', 'enum' => array( '', 'home', 'section', 'brief' ), 'default' => '' ),
					'placement' => array( 'type' => 'string', 'enum' => array_merge( array( '' ), array_keys( tdd_core_placement_types() ) ), 'default' => '' ),
					'section'   => array( 'type' => 'integer', 'default' => 0 ),
				),
			)
		);
		register_rest_route(
			'tdd/v1',
			'/placements/post/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => $perm,
				'callback'            => static fn( WP_REST_Request $r ) => rest_ensure_response( tdd_core_placements_for_post( (int) $r['id'] ) ),
			)
		);
	}
);

/* ---------- WP-CLI: wp tdd placement add|list|end ---------- */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'tdd placement',
		new class() {
			/**
			 * Place a story.
			 *
			 * ## OPTIONS
			 * <post_id>
			 * <placement>
			 * [--position=<n>]
			 * [--section=<slug>]
			 * [--expires=<datetime>]
			 */
			public function add( $args, $assoc ) {
				$section = isset( $assoc['section'] ) ? get_term_by( 'slug', $assoc['section'], 'category' ) : null;
				$id      = tdd_core_place( (int) $args[0], $args[1], (int) ( $assoc['position'] ?? 1 ), $section ? $section->term_id : 0, null, $assoc['expires'] ?? null );
				is_wp_error( $id ) ? WP_CLI::error( $id->get_error_message() ) : WP_CLI::success( "Placement {$id}" );
			}
			/** List active placements. */
			public function list() {
				WP_CLI\Utils\format_items( 'table', tdd_core_placement_rows(), array( 'id', 'placement', 'post_id', 'position', 'section_id', 'start_at', 'expires_at' ) );
			}
			/**
			 * End a placement.
			 *
			 * <id>
			 */
			public function end( $args ) {
				tdd_core_unplace( (int) $args[0] ) ? WP_CLI::success( 'Ended.' ) : WP_CLI::error( 'Not found.' );
			}
		}
	);
}
