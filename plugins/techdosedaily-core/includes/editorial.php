<?php
/**
 * Editorial state: Breaking (time-limited), substantive updates, corrections, sources,
 * reading time. Read through the public API in api.php.
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

/** Default Breaking window. Editors can shorten or extend per story. */
const TDD_CORE_BREAKING_HOURS = 6;

/** Breaking is a state that expires — never a story type. */
function tdd_core_is_breaking( $post = null ): bool {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}
	$until = (string) get_post_meta( $post->ID, 'tdd_breaking_until', true );
	if ( '' === $until ) {
		return false;
	}
	$from = (string) get_post_meta( $post->ID, 'tdd_breaking_from', true );
	if ( '' !== $from && strtotime( $from ) > time() ) {
		return false; // Scheduled, not yet breaking.
	}
	return strtotime( $until ) > time();
}

/** Default Breaking window in hours (Settings → Tech Dose Daily). */
function tdd_core_breaking_hours(): int {
	$h = (int) get_option( 'tdd_breaking_hours', TDD_CORE_BREAKING_HOURS );
	return $h >= 1 && $h <= 72 ? $h : TDD_CORE_BREAKING_HOURS;
}

/**
 * Corrections are a public record. Once a story has ever been published (`_tdd_first_published`,
 * frozen at first publication — so unpublishing first does not unlock it), existing entries can't be
 * removed, reordered, re-dated or rewritten by anyone but an administrator (legal fixes). New entries
 * are appended. Enforced at the storage layer (every path: editor, REST, WP-CLI as non-admin, code)
 * and reported clearly to the editor when a REST save would break it.
 */
function tdd_core_corrections_locked( int $post_id ): bool {
	if ( current_user_can( 'manage_options' ) || 'post' !== get_post_type( $post_id ) ) {
		return false;
	}
	return 'publish' === get_post_status( $post_id ) || '' !== (string) get_post_meta( $post_id, '_tdd_first_published', true );
}

/** True when $new keeps every stored entry unchanged and in place (appending is allowed). */
function tdd_core_corrections_preserved( array $old, $new ): bool {
	$new = array_values( (array) $new );
	foreach ( array_values( $old ) as $i => $row ) {
		$n = $new[ $i ] ?? null;
		if ( ! is_array( $n ) || (string) ( $n['text'] ?? '' ) !== (string) ( $row['text'] ?? '' ) ) {
			return false;
		}
		$t_old = strtotime( (string) ( $row['time'] ?? '' ) );
		$t_new = strtotime( (string) ( $n['time'] ?? '' ) );
		if ( $t_old !== $t_new ) {
			return false;
		}
	}
	return true;
}

/** Clear editor message (instead of a generic save error) when a published correction would be lost. */
add_filter(
	'rest_pre_insert_post',
	static function ( $prepared, WP_REST_Request $request ) {
		$meta = $request->get_param( 'meta' );
		if ( ! is_array( $meta ) || ! array_key_exists( 'tdd_corrections', $meta ) || empty( $prepared->ID ) || ! tdd_core_corrections_locked( (int) $prepared->ID ) ) {
			return $prepared;
		}
		if ( ! tdd_core_corrections_preserved( (array) get_post_meta( $prepared->ID, 'tdd_corrections', true ), $meta['tdd_corrections'] ) ) {
			return new WP_Error( 'tdd_corrections_locked', __( 'Published corrections are a public record and can’t be edited or removed. Add a new correction instead.', 'techdosedaily-core' ), array( 'status' => 400 ) );
		}
		return $prepared;
	},
	10,
	2
);

add_filter(
	'update_post_metadata',
	static function ( $check, $object_id, $meta_key, $meta_value ) {
		if ( 'tdd_corrections' !== $meta_key || ! tdd_core_corrections_locked( (int) $object_id ) ) {
			return $check;
		}
		$old = (array) get_post_meta( $object_id, 'tdd_corrections', true );
		$new = tdd_core_sanitize_corrections( $meta_value );
		return tdd_core_corrections_preserved( $old, $new ) ? $check : false; // Refuse; the stored record stays.
	},
	10,
	4
);
add_filter(
	'delete_post_metadata',
	static function ( $check, $object_id, $meta_key ) {
		if ( 'tdd_corrections' === $meta_key && $object_id && tdd_core_corrections_locked( (int) $object_id ) && array() !== (array) get_post_meta( $object_id, 'tdd_corrections', true ) ) {
			return false; // Never deleted once published (REST null, delete_post_meta, …).
		}
		return $check;
	},
	10,
	3
);

/** Reading time (minutes) at ~230 words per minute; computed on save. */
function tdd_core_compute_reading_time( string $content ): int {
	$words = str_word_count( wp_strip_all_tags( strip_shortcodes( $content ) ) );
	return max( 1, (int) ceil( $words / 230 ) );
}
add_action(
	'save_post_post',
	static function ( int $post_id, WP_Post $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, 'tdd_reading_time', tdd_core_compute_reading_time( $post->post_content ) );
	},
	10,
	2
);

/** Last substantive update, or null (cosmetic edits never count). */
function tdd_core_updated_at( $post = null ): ?DateTimeImmutable {
	$post = get_post( $post );
	$raw  = $post ? (string) get_post_meta( $post->ID, 'tdd_updated_at', true ) : '';
	if ( '' === $raw ) {
		return null;
	}
	try {
		$updated = new DateTimeImmutable( $raw );
	} catch ( Exception $e ) {
		return null;
	}
	$published = get_post_datetime( $post, 'date', 'gmt' );
	return ( $published && $updated <= $published ) ? null : $updated;
}

function tdd_core_corrections( $post = null ): array {
	$post = get_post( $post );
	return $post ? (array) get_post_meta( $post->ID, 'tdd_corrections', true ) : array();
}

/**
 * Sources as readers may see them. For a confidential source only the description (title), type
 * and date are public; its link, publisher and note are internal newsroom details and are never
 * printed, put in schema or returned by the public REST API (Phase 8).
 */
function tdd_core_sources( $post = null ): array {
	return array_map( 'tdd_core_public_source', tdd_core_sources_raw( $post ) );
}

/** Everything stored (editor UI / users who can edit the story only). */
function tdd_core_sources_raw( $post = null ): array {
	$post = get_post( $post );
	return $post ? array_values( array_filter( (array) get_post_meta( $post->ID, 'tdd_sources', true ), 'is_array' ) ) : array();
}

function tdd_core_public_source( array $row ): array {
	if ( 'confidential' === ( $row['type'] ?? '' ) ) {
		$row['url']       = '';
		$row['publisher'] = '';
		$row['note']      = '';
	}
	return $row;
}

/** Public REST reads (context=view/embed) get the same redacted list; the editor uses context=edit. */
add_filter(
	'rest_prepare_post',
	static function ( WP_REST_Response $response, WP_Post $post, WP_REST_Request $request ) {
		$data = $response->get_data();
		if ( isset( $data['meta']['tdd_sources'] ) && ( 'edit' !== $request['context'] || ! current_user_can( 'edit_post', $post->ID ) ) ) {
			$data['meta']['tdd_sources'] = array_map( 'tdd_core_public_source', array_filter( (array) $data['meta']['tdd_sources'], 'is_array' ) );
			$response->set_data( $data );
		}
		return $response;
	},
	10,
	3
);
