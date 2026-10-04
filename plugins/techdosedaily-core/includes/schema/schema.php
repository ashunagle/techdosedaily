<?php
/**
 * Structured data ownership (see SEO-SCHEMA.md).
 *
 *   Yoast SEO           titles, meta descriptions, canonical, robots, Open Graph / X cards, XML sitemaps
 *   TechDoseDaily Core  the JSON-LD graph (graph.php) — when it is the schema owner
 *   Theme               nothing SEO-related (presentation only)
 *
 * Exactly one graph is ever printed:
 *   setting "core"                      → Core graph; Yoast JSON-LD suppressed
 *   setting "yoast" + Yoast active      → Yoast graph; Core prints nothing
 *   setting "yoast" + Yoast NOT active  → Core graph (fallback, so the site is never without schema)
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/graph.php';

/** Saved choice: 'yoast' (default) or 'core'. */
function tdd_core_schema_setting(): string {
	$v = (string) get_option( 'tdd_core_schema_owner', '' );
	if ( '' === $v ) {
		$v = get_option( 'tdd_core_schema_enabled', false ) ? 'core' : 'yoast'; // Pre-Phase 6 option.
	}
	return 'core' === $v ? 'core' : 'yoast';
}

/** True when Yoast SEO is loaded and able to print its own schema. */
function tdd_core_yoast_active(): bool {
	return defined( 'WPSEO_VERSION' );
}

/** Who prints JSON-LD on this request: 'core' or 'yoast'. Never both, never neither. */
function tdd_core_schema_owner(): string {
	$owner = ( 'core' === tdd_core_schema_setting() || ! tdd_core_yoast_active() ) ? 'core' : 'yoast';
	return (string) apply_filters( 'tdd_core_schema_owner', $owner );
}

/** Back-compatible check used elsewhere: Core prints the graph. */
function tdd_core_schema_enabled(): bool {
	return 'core' === tdd_core_schema_owner();
}

/* ---------- Single owner ---------- */

/**
 * Views that carry no schema from either owner: 404, search, previews, attachments, empty author
 * pages (all noindex or non-public).
 */
function tdd_core_schema_blocked_view(): bool {
	return is_404() || is_search() || is_preview() || is_attachment()
		|| ( is_author() && 0 === (int) count_user_posts( (int) get_queried_object_id(), 'post', true ) );
}

// Yoast's documented switch for its JSON-LD output. Evaluated per request, so switching is instant.
add_filter( 'wpseo_json_ld_output', static fn( $out ) => ( tdd_core_schema_enabled() || tdd_core_schema_blocked_view() ) ? false : $out, 99 );

add_action(
	'wp_head',
	static function () {
		if ( ! tdd_core_schema_enabled() ) {
			return;
		}
		$graph = tdd_core_schema_graph();
		if ( ! $graph ) {
			return;
		}
		echo "\n<script type=\"application/ld+json\" class=\"tdd-schema-graph\">" . tdd_core_schema_json( $graph ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with HEX_TAG/HEX_AMP; no markup can break out.
	},
	30
);

/* ---------- Frozen publication time ---------- */

/** datePublished never moves after first publication (even if the post date is edited later). */
add_action(
	'wp_after_insert_post',
	static function ( int $post_id, WP_Post $post ) {
		if ( 'post' !== $post->post_type || 'publish' !== $post->post_status || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( '' !== (string) get_post_meta( $post_id, '_tdd_first_published', true ) ) {
			return;
		}
		$d = get_post_datetime( $post, 'date', 'gmt' );
		if ( $d ) {
			update_post_meta( $post_id, '_tdd_first_published', $d->format( DATE_ATOM ) );
		}
	},
	20,
	2
);
