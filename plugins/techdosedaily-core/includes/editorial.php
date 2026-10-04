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

/** Clear editor message (instead of a generic save error) when a published correction would be lost. */
add_filter(
	'rest_pre_insert_post',
	static function ( $prepared, WP_REST_Request $request ) {
		$meta = $request->get_param( 'meta' );
		if ( ! is_array( $meta ) || ! array_key_exists( 'tdd_corrections', $meta ) || empty( $prepared->ID ) || 'publish' !== get_post_status( $prepared->ID ) || current_user_can( 'manage_options' ) ) {
			return $prepared;
		}
		$old = (array) get_post_meta( $prepared->ID, 'tdd_corrections', true );
		$new = array_values( (array) $meta['tdd_corrections'] );
		foreach ( $old as $i => $row ) {
			if ( ( $new[ $i ]['text'] ?? null ) !== ( $row['text'] ?? '' ) ) {
				return new WP_Error( 'tdd_corrections_locked', __( 'Published corrections are a public record and can’t be edited or removed. Add a new correction instead.', 'techdosedaily-core' ), array( 'status' => 400 ) );
			}
		}
		return $prepared;
	},
	10,
	2
);

/**
 * Corrections are a public record: once a story is published, existing entries can't be removed
 * or rewritten through the editor or REST (administrators excepted, for legal fixes). New entries
 * are appended.
 */
add_filter(
	'update_post_metadata',
	static function ( $check, $object_id, $meta_key, $meta_value ) {
		if ( 'tdd_corrections' !== $meta_key || 'publish' !== get_post_status( $object_id ) || current_user_can( 'manage_options' ) ) {
			return $check;
		}
		$old = (array) get_post_meta( $object_id, 'tdd_corrections', true );
		$new = (array) $meta_value;
		foreach ( $old as $i => $row ) {
			if ( ! isset( $new[ $i ] ) || ( $new[ $i ]['text'] ?? '' ) !== ( $row['text'] ?? '' ) ) {
				return false; // Refuse the write; the stored record stays as it was.
			}
		}
		return $check;
	},
	10,
	4
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

function tdd_core_sources( $post = null ): array {
	$post = get_post( $post );
	return $post ? (array) get_post_meta( $post->ID, 'tdd_sources', true ) : array();
}
