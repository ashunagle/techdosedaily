<?php
/**
 * Page caching and media output (Phase 7). See PERFORMANCE.md.
 *
 * Cache-Control is decided here, once, for every public HTML response:
 *
 *   never cached   logged-in users, non-GET/HEAD, previews, password-protected posts, the Contact page
 *                  (DONOTCACHEPAGE), no-JS form result URLs (?tdd_nl= / ?tdd_cf=)
 *   cached         everything else, for the view's TTL — but never past the next scheduled editorial
 *                  change (a Breaking label starting/ending, a placement starting/expiring, a scheduled
 *                  story going live), so time-based state is correct without a purge
 *   purged         on editorial changes (publish/unpublish/update of stories, placements, sections,
 *                  site settings, menus, templates, author profiles)
 *
 * Works with any standards-following shared cache (Cache-Control s-maxage), nginx fastcgi_cache
 * (X-Accel-Expires) and the free LiteSpeed Cache plugin (its control/purge API) — correctness never
 * depends on a particular (or paid) cache: without one, these are only response headers.
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Media: WebP sub-sizes ---------- */

/**
 * JPEG uploads keep their original file; every generated sub-size (all srcset candidates) is WebP,
 * when the server's image editor can write WebP. Existing images are unaffected until regenerated.
 */
add_filter(
	'image_editor_output_format',
	static function ( $formats ) {
		if ( ! (bool) apply_filters( 'tdd_core_webp_subsizes', true ) || ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			return $formats;
		}
		$formats['image/jpeg'] = 'image/webp';
		return $formats;
	}
);

/* ---------- Page-cache lifetime ---------- */

/** Base lifetime (seconds) for a cacheable view, before the next-editorial-change cap. */
function tdd_core_cache_base_ttl(): int {
	if ( is_404() ) {
		$ttl = MINUTE_IN_SECONDS; // A story published at that URL a minute later must appear.
	} elseif ( is_front_page() || is_home() || is_category() || is_tax() || is_tag() || is_search() ) {
		$ttl = 5 * MINUTE_IN_SECONDS; // Rivers, placements, Most Read, "x min ago" labels.
	} elseif ( is_singular( 'post' ) || is_author() ) {
		$ttl = 15 * MINUTE_IN_SECONDS; // Story rails carry relative times and Most Read.
	} elseif ( is_singular() ) {
		$ttl = HOUR_IN_SECONDS; // Static pages.
	} else {
		$ttl = 5 * MINUTE_IN_SECONDS;
	}
	return (int) apply_filters( 'tdd_core_cache_ttl', $ttl );
}

/**
 * Unix time of the next scheduled editorial change that alters rendered pages, or 0 if none is
 * known: Breaking start/end, placement start/expiry, scheduled story publication.
 * Stored briefly in a transient; cleared by every purge.
 */
function tdd_core_cache_next_change(): int {
	$now    = time();
	$cached = get_transient( 'tdd_core_next_change' );
	if ( false !== $cached && ( 0 === (int) $cached || (int) $cached > $now ) ) {
		return (int) $cached;
	}
	global $wpdb;
	$next  = PHP_INT_MAX;
	$floor = gmdate( 'Y-m-d', $now - 2 * DAY_IN_SECONDS ); // ISO 8601 values sort lexically (offset ≤ 14h).

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$times = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_status = 'publish' AND p.post_type = 'post'
			 WHERE pm.meta_key IN ( 'tdd_breaking_from', 'tdd_breaking_until' ) AND pm.meta_value >= %s",
			$floor
		)
	);
	foreach ( $times as $t ) {
		$ts = strtotime( (string) $t );
		if ( $ts && $ts > $now ) {
			$next = min( $next, $ts );
		}
	}

	if ( function_exists( 'tdd_core_placements_table' ) ) {
		$table = tdd_core_placements_table();
		$gnow  = gmdate( 'Y-m-d H:i:s', $now );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT ( SELECT MIN( start_at ) FROM {$table} WHERE start_at > %s ) AS s, ( SELECT MIN( expires_at ) FROM {$table} WHERE expires_at > %s ) AS e", $gnow, $gnow ), ARRAY_A );
		foreach ( array( 's', 'e' ) as $k ) {
			if ( ! empty( $row[ $k ] ) ) {
				$next = min( $next, (int) strtotime( $row[ $k ] . ' UTC' ) );
			}
		}
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$future = $wpdb->get_var( "SELECT MIN( post_date_gmt ) FROM {$wpdb->posts} WHERE post_status = 'future' AND post_type IN ( 'post', 'page' )" );
	if ( $future ) {
		$next = min( $next, (int) strtotime( $future . ' UTC' ) );
	}

	$next = PHP_INT_MAX === $next ? 0 : $next;
	set_transient( 'tdd_core_next_change', $next, $next ? max( 1, min( $next - $now, 10 * MINUTE_IN_SECONDS ) ) : 10 * MINUTE_IN_SECONDS );
	return $next;
}

/** Seconds this response may be cached (≥ 1), or 0 when it must not be cached. */
function tdd_core_cache_ttl(): int {
	if ( '' !== tdd_core_cache_nocache_reason() ) {
		return 0;
	}
	$ttl  = tdd_core_cache_base_ttl();
	$next = tdd_core_cache_next_change();
	if ( $next ) {
		$ttl = min( $ttl, max( 1, $next - time() ) );
	}
	return max( 1, $ttl );
}

/** Why this request must not be cached ('' = cacheable). */
function tdd_core_cache_nocache_reason(): string {
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- presence checks only.
	$reason = match ( true ) {
		! in_array( $method, array( 'GET', 'HEAD' ), true ) => 'method',
		is_user_logged_in()                               => 'logged-in',
		is_preview() || is_customize_preview()             => 'preview',
		is_singular() && post_password_required()          => 'password',
		defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE       => 'donotcachepage',
		isset( $_GET['tdd_nl'] ) || isset( $_GET['tdd_cf'] ) => 'form-result',
		default                                            => '',
	};
	// phpcs:enable
	return (string) apply_filters( 'tdd_core_cache_nocache_reason', $reason );
}

/** Send the headers (after page-level template_redirect handlers such as the Contact page's). */
add_action(
	'template_redirect',
	static function () {
		if ( is_admin() || is_feed() || is_robots() || is_trackback() || headers_sent() ) {
			return;
		}
		$ttl = tdd_core_cache_ttl();
		if ( 0 === $ttl ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			nocache_headers();
			header( 'X-TDD-Cache: no-cache; ' . tdd_core_cache_nocache_reason() );
			if ( defined( 'LSCWP_V' ) ) {
				do_action( 'litespeed_control_set_nocache', 'tdd: ' . tdd_core_cache_nocache_reason() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache API.
			}
			return;
		}
		header( sprintf( 'Cache-Control: public, max-age=0, s-maxage=%d', $ttl ) );
		header( 'X-TDD-Cache: ttl=' . $ttl );
		if ( isset( $_SERVER['SERVER_SOFTWARE'] ) && false !== stripos( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ), 'nginx' ) ) {
			header( 'X-Accel-Expires: ' . $ttl ); // nginx fastcgi/proxy cache; never forwarded to browsers.
		}
		if ( defined( 'LSCWP_V' ) ) {
			do_action( 'litespeed_control_set_ttl', $ttl ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache API.
		}
	},
	99
);

/* ---------- Purge ---------- */

/**
 * Ask every page cache to drop its copies. Collected per request and sent once at shutdown.
 * Listeners: the LiteSpeed Cache plugin (built in), anything hooked to `tdd_core_cache_purge`.
 */
function tdd_core_cache_purge( string $reason ): void {
	static $queued = null;
	delete_transient( 'tdd_core_next_change' );
	if ( null !== $queued ) {
		$queued[] = $reason;
		return;
	}
	$queued = array( $reason );
	add_action(
		'shutdown',
		static function () use ( &$queued ) {
			$why = implode( ', ', array_unique( $queued ) );
			if ( defined( 'LSCWP_V' ) ) {
				do_action( 'litespeed_purge_all', 'tdd: ' . $why ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache API.
			}
			do_action( 'tdd_core_cache_purge', $why );
		},
		1
	);
}

// Stories and pages: anything that changes a public post (publish, unpublish, edit of a live post,
// scheduling, trash). Breaking/correction/source meta are saved with the post, so they are covered.
add_action(
	'transition_post_status',
	static function ( string $new, string $old, WP_Post $post ) {
		if ( in_array( $post->post_type, array( 'post', 'page', 'attachment' ), true ) && ( 'publish' === $new || 'publish' === $old || 'future' === $new ) ) {
			tdd_core_cache_purge( "{$post->post_type} {$post->ID} {$old}→{$new}" );
		}
		if ( in_array( $post->post_type, array( 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation' ), true ) ) {
			tdd_core_cache_purge( $post->post_type );
		}
	},
	10,
	3
);
// Meta changed outside a post save (REST meta-only updates, WP-CLI, Yoast's own fields, image credits).
// Allow-list only: editing locks and other bookkeeping meta never purge.
$tdd_core_meta_purge = static function ( $meta_id, $object_id, $meta_key ) {
	if ( ! preg_match( '/^(_?tdd_|_yoast_wpseo_|_thumbnail_id$|_wp_page_template$|_wp_attachment_image_alt$)/', (string) $meta_key ) ) {
		return;
	}
	$p = get_post( (int) $object_id );
	if ( $p && ( 'publish' === $p->post_status || 'attachment' === $p->post_type ) ) {
		tdd_core_cache_purge( "meta {$meta_key}" );
	}
};
add_action( 'added_post_meta', $tdd_core_meta_purge, 10, 3 );
add_action( 'updated_post_meta', $tdd_core_meta_purge, 10, 3 );
add_action( 'deleted_post_meta', $tdd_core_meta_purge, 10, 3 );
unset( $tdd_core_meta_purge );

// Placements (editor, REST, WP-CLI, status changes of placed stories).
add_action( 'tdd_core_placements_changed', static fn() => tdd_core_cache_purge( 'placements' ) );
// Sections, topics, story types.
add_action( 'edited_term', static fn( $t, $tt, $tax ) => in_array( $tax, array( 'category', 'tdd_topic', 'tdd_format', 'post_tag' ), true ) && tdd_core_cache_purge( "term {$tax}" ), 10, 3 );
add_action( 'created_term', static fn( $t, $tt, $tax ) => 'category' === $tax && tdd_core_cache_purge( 'section created' ), 10, 3 );
add_action( 'delete_term', static fn( $t, $tt, $tax ) => in_array( $tax, array( 'category', 'tdd_topic', 'tdd_format', 'post_tag' ), true ) && tdd_core_cache_purge( "term {$tax}" ), 10, 3 );
add_action( 'updated_term_meta', static fn() => tdd_core_cache_purge( 'section settings' ) );
add_action( 'added_term_meta', static fn() => tdd_core_cache_purge( 'section settings' ) );
// Author profiles (bylines, author pages, About).
add_action( 'profile_update', static fn() => tdd_core_cache_purge( 'profile' ) );
add_action( 'updated_user_meta', static fn( $m, $u, $key ) => str_starts_with( (string) $key, 'tdd_' ) && tdd_core_cache_purge( 'profile' ), 10, 3 );
// Plugins/themes switched or updated (e.g. Yoast deactivated → the Core graph takes over at once).
add_action( 'activated_plugin', static fn() => tdd_core_cache_purge( 'plugin activated' ) );
add_action( 'deactivated_plugin', static fn() => tdd_core_cache_purge( 'plugin deactivated' ) );
add_action( 'switch_theme', static fn() => tdd_core_cache_purge( 'theme switched' ) );
add_action( 'upgrader_process_complete', static fn() => tdd_core_cache_purge( 'update' ) );
// Menus and site-wide settings (Tech Dose Daily settings, site title/tagline, Yoast, reading settings).
add_action( 'wp_update_nav_menu', static fn() => tdd_core_cache_purge( 'menu' ) );
add_action(
	'updated_option',
	static function ( string $option ) {
		if ( preg_match( '/^(tdd_|wpseo|active_plugins$|blogname$|blogdescription$|site_icon$|show_on_front$|page_on_front$|page_for_posts$|posts_per_page$|permalink_structure$|blog_public$)/', $option ) ) {
			tdd_core_cache_purge( "option {$option}" );
		}
	}
);
