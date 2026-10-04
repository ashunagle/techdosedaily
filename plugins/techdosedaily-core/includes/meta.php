<?php
/**
 * Registered post, attachment and user meta — the single source of truth for every
 * editorial field. All keys are prefixed tdd_ and exposed in REST for the editor.
 * Documented in ../README.md (Data model).
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

/** Allowed source types (SourceList component). */
function tdd_core_source_types(): array {
	return array( 'primary', 'interview', 'supporting', 'public-record', 'confidential' );
}

/** Allowed image source types (ImageStrategy). */
function tdd_core_image_source_types(): array {
	return array( 'official', 'photo', 'licensed', 'screenshot', 'graphic', 'ai-generated' );
}

add_action(
	'init',
	static function () {
		$can_edit = static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id );
		$str      = static fn( string $desc ) => array( 'type' => 'string', 'single' => true, 'default' => '', 'description' => $desc, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => $can_edit );
		$int      = static fn( string $desc ) => array( 'type' => 'integer', 'single' => true, 'default' => 0, 'description' => $desc, 'show_in_rest' => true, 'sanitize_callback' => 'absint', 'auth_callback' => $can_edit );
		$bool     = static fn( string $desc ) => array( 'type' => 'boolean', 'single' => true, 'default' => false, 'description' => $desc, 'show_in_rest' => true, 'auth_callback' => $can_edit );

		$post_meta = array(
			'tdd_deck'             => $str( 'Deck / subheadline. Also the meta description.' ),
			'tdd_short_title'      => $str( 'Short headline for compact lists (Daily Tech Brief, rails). Optional, ≤ 60 characters.' ),
			'tdd_primary_section'  => $int( 'Primary section (category term ID). Exactly one per story.' ),
			'tdd_editor'           => $int( 'Editing editor (user ID). Shown as "Edited by".' ),
			'tdd_updated_at'       => $str( 'Last substantive update, ISO 8601. Drives "Updated" and dateModified. Never set for cosmetic edits.' ),
			'tdd_update_note'      => $str( 'One-line description of the substantive update.' ),
			'tdd_breaking_until'   => $str( 'Breaking state ends at this ISO 8601 time. Empty = not breaking.' ),
			'tdd_breaking_from'    => $str( 'Breaking state starts at this ISO 8601 time. Empty = from publication.' ),
			'tdd_vendor_reported'  => $bool( 'Figures in this story are vendor-reported and not independently verified.' ),
			'tdd_ai_disclosure'    => $str( 'Disclosure text when AI-generated material appears in the story.' ),
			'tdd_sponsor'          => $str( 'Sponsor name. Forces the Sponsored story type and rel="sponsored".' ),
			'tdd_reading_time'     => $int( 'Minutes to read. Computed on save.' ),
			'tdd_severity'         => $str( 'Security severity line for alert treatments, e.g. "Patch now · Critical". Only from the vendor/CVE rating.' ),
		);
		$post_meta['tdd_editor']['sanitize_callback']         = static fn( $v ) => get_userdata( (int) $v ) ? (int) $v : 0;
		$post_meta['tdd_updated_at']['sanitize_callback']     = 'tdd_core_sanitize_datetime';
		$post_meta['tdd_breaking_until']['sanitize_callback'] = 'tdd_core_sanitize_datetime';
		$post_meta['tdd_breaking_from']['sanitize_callback']  = 'tdd_core_sanitize_datetime';
		$post_meta['tdd_short_title']['sanitize_callback']    = static fn( $v ) => mb_substr( sanitize_text_field( $v ), 0, 80 );
		$post_meta['tdd_deck']['sanitize_callback']           = 'sanitize_textarea_field';
		foreach ( $post_meta as $key => $args ) {
			register_post_meta( 'post', $key, $args );
		}

		register_post_meta(
			'post',
			'tdd_corrections',
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'description'       => 'Dated corrections, newest last. Never deleted once published.',
				'auth_callback'     => $can_edit,
				'sanitize_callback' => 'tdd_core_sanitize_corrections',
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'time' => array( 'type' => 'string' ),
								'text' => array( 'type' => 'string' ),
							),
						),
					),
				),
			)
		);

		register_post_meta(
			'post',
			'tdd_sources',
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'description'       => 'Numbered sources list (SourceList).',
				'auth_callback'     => $can_edit,
				'sanitize_callback' => 'tdd_core_sanitize_sources',
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'title'     => array( 'type' => 'string' ),
								'url'       => array( 'type' => 'string' ),
								'type'      => array( 'type' => 'string', 'enum' => tdd_core_source_types() ),
								'publisher' => array( 'type' => 'string' ),
								'date'      => array( 'type' => 'string' ),
								'note'      => array( 'type' => 'string' ),
							),
						),
					),
				),
			)
		);

		// Image credit lives on the attachment so it travels with the image wherever it is reused.
		$att_can = static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id );
		foreach (
			array(
				'tdd_credit'       => 'Credit line, e.g. "Company handout" or photographer/agency.',
				'tdd_source_url'   => 'Where the image came from (page or licence record).',
				'tdd_license_note' => 'Licence / usage terms note.',
			) as $key => $desc
		) {
			register_post_meta( 'attachment', $key, array( 'type' => 'string', 'single' => true, 'default' => '', 'description' => $desc, 'show_in_rest' => true, 'sanitize_callback' => 'tdd_source_url' === $key ? 'esc_url_raw' : 'sanitize_text_field', 'auth_callback' => $att_can ) );
		}
		register_post_meta(
			'attachment',
			'tdd_source_type',
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'description'       => 'official | photo | licensed | screenshot | graphic | ai-generated (AI images are labelled where shown).',
				'show_in_rest'      => array( 'schema' => array( 'type' => 'string', 'enum' => array_merge( array( '' ), tdd_core_image_source_types() ) ) ),
				'sanitize_callback' => static fn( $v ) => in_array( $v, tdd_core_image_source_types(), true ) ? $v : '',
				'auth_callback'     => $att_can,
			)
		);

		// Author profile fields. Only true, author-approved facts.
		$user_can = static fn( $allowed, $meta_key, $user_id ) => current_user_can( 'edit_user', $user_id );
		$user     = array(
			'tdd_title'          => array( 'string', 'Job title, e.g. "Senior AI Correspondent".' ),
			'tdd_location'       => array( 'string', 'City/country, only if the author wants it shown.' ),
			'tdd_covering_since' => array( 'string', 'Free text, e.g. "Covering software and AI since 2016" — only if true.' ),
			'tdd_short_bio'      => array( 'string', 'One sentence for cards.' ),
			'tdd_note'           => array( 'string', 'How this author reports (shown on the author page).' ),
			'tdd_photo'          => array( 'integer', 'Attachment ID of a real portrait. Initials placeholder until set.' ),
			'tdd_public_email'   => array( 'boolean', 'Show a contact link on the author page.' ),
			'tdd_show_on_about'  => array( 'boolean', 'List on the About page desk.' ),
			'tdd_about_order'    => array( 'integer', 'Order on the About page.' ),
		);
		foreach ( $user as $key => [ $type, $desc ] ) {
			register_meta( 'user', $key, array( 'type' => $type, 'single' => true, 'description' => $desc, 'show_in_rest' => true, 'auth_callback' => $user_can, 'sanitize_callback' => 'string' === $type ? 'sanitize_text_field' : ( 'integer' === $type ? 'absint' : null ) ) );
		}
		register_meta( 'user', 'tdd_featured_posts', array( 'type' => 'array', 'single' => true, 'default' => array(), 'description' => 'Featured Reporting on the author page: up to 3 story IDs chosen by editors.', 'auth_callback' => static fn() => current_user_can( 'edit_others_posts' ), 'sanitize_callback' => static fn( $v ) => array_slice( array_values( array_filter( array_map( 'absint', (array) $v ), static fn( $id ) => 'post' === get_post_type( $id ) ) ), 0, 3 ), 'show_in_rest' => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ) ) ) );
		register_meta( 'user', 'tdd_editor_user', array( 'type' => 'integer', 'single' => true, 'default' => 0, 'description' => 'The reporter’s editor (user ID), shown in "About this reporter".', 'auth_callback' => static fn() => current_user_can( 'edit_users' ), 'sanitize_callback' => static fn( $v ) => get_userdata( (int) $v ) ? (int) $v : 0, 'show_in_rest' => true ) );
		register_meta( 'user', 'tdd_beats', array( 'type' => 'array', 'single' => true, 'default' => array(), 'description' => 'Topic term IDs the author covers (4–8).', 'auth_callback' => $user_can, 'sanitize_callback' => static fn( $v ) => array_values( array_filter( array_map( 'absint', (array) $v ) ) ), 'show_in_rest' => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ) ) ) );
		register_meta(
			'user',
			'tdd_social',
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'description'       => 'Real profiles only: [{label, url}].',
				'auth_callback'     => $user_can,
				'sanitize_callback' => static fn( $v ) => array_values( array_filter( array_map( static fn( $i ) => ( ! empty( $i['url'] ) ? array( 'label' => sanitize_text_field( $i['label'] ?? '' ), 'url' => esc_url_raw( $i['url'] ) ) : null ), (array) $v ) ) ),
				'show_in_rest'      => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'object', 'properties' => array( 'label' => array( 'type' => 'string' ), 'url' => array( 'type' => 'string' ) ) ) ) ),
			)
		);

		// Section (category) settings. Edited in Phase 5 tooling; read via tdd_core_section_settings().
		$term_can = static fn() => current_user_can( 'manage_categories' );
		$ids      = static fn( $v ) => array_values( array_filter( array_map( 'absint', (array) $v ) ) );
		register_term_meta( 'category', 'tdd_short_description', array( 'type' => 'string', 'single' => true, 'default' => '', 'description' => 'One line for the About page coverage list.', 'show_in_rest' => true, 'auth_callback' => $term_can, 'sanitize_callback' => 'sanitize_text_field' ) );
		register_term_meta( 'category', 'tdd_desk_note', array( 'type' => 'string', 'single' => true, 'default' => '', 'description' => 'Short statement for the section desk module.', 'show_in_rest' => true, 'auth_callback' => $term_can, 'sanitize_callback' => 'sanitize_text_field' ) );
		register_term_meta( 'category', 'tdd_desk_url', array( 'type' => 'string', 'single' => true, 'default' => '', 'description' => '"How we cover" link.', 'show_in_rest' => true, 'auth_callback' => $term_can, 'sanitize_callback' => 'esc_url_raw' ) );
		register_term_meta( 'category', 'tdd_section_hidden', array( 'type' => 'array', 'single' => true, 'default' => array(), 'description' => 'Section-page modules turned off (analysis, guides, topics, desk, brief).', 'auth_callback' => $term_can, 'sanitize_callback' => static fn( $v ) => array_values( array_intersect( array_map( 'strval', (array) $v ), array( 'analysis', 'guides', 'topics', 'desk', 'brief' ) ) ), 'show_in_rest' => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ) ) ) );
		foreach ( array( 'tdd_desk_members' => 'User IDs on the section desk (real people only).', 'tdd_topic_nav' => 'Topic term IDs for the section topic navigation, in order.' ) as $key => $desc ) {
			register_term_meta( 'category', $key, array( 'type' => 'array', 'single' => true, 'default' => array(), 'description' => $desc, 'auth_callback' => $term_can, 'sanitize_callback' => $ids, 'show_in_rest' => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ) ) ) );
		}
	},
	6
);

/** ISO 8601 or empty. */
function tdd_core_sanitize_datetime( $value ): string {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	try {
		return ( new DateTimeImmutable( $value, wp_timezone() ) )->format( DATE_ATOM );
	} catch ( Exception $e ) {
		return '';
	}
}

function tdd_core_sanitize_corrections( $value ): array {
	$out = array();
	foreach ( (array) $value as $row ) {
		$text = sanitize_textarea_field( $row['text'] ?? '' );
		if ( '' === $text ) {
			continue;
		}
		$out[] = array( 'time' => tdd_core_sanitize_datetime( $row['time'] ?? 'now' ), 'text' => $text );
	}
	return $out;
}

function tdd_core_sanitize_sources( $value ): array {
	$out = array();
	foreach ( (array) $value as $row ) {
		$title = sanitize_text_field( $row['title'] ?? '' );
		if ( '' === $title ) {
			continue;
		}
		$type  = in_array( $row['type'] ?? '', tdd_core_source_types(), true ) ? $row['type'] : 'supporting';
		$out[] = array(
			'title'     => $title,
			'url'       => esc_url_raw( $row['url'] ?? '' ),
			'type'      => $type,
			'publisher' => sanitize_text_field( $row['publisher'] ?? '' ),
			'date'      => sanitize_text_field( $row['date'] ?? '' ),
			'note'      => sanitize_text_field( $row['note'] ?? '' ),
		);
	}
	return $out;
}
