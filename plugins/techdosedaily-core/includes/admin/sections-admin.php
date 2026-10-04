<?php
/**
 * Section settings on the Sections (category) edit screen: one-line description, desk, "How we
 * cover" link, topic navigation, optional page modules and a link to the section's pinned stories.
 * Saved as registered term meta (meta.php) after a nonce and capability check.
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

/** Fields on "Add section" (just the one-liner; the rest after creation). */
add_action(
	'category_add_form_fields',
	static function () {
		wp_nonce_field( 'tdd_section_settings', 'tdd_section_nonce' );
		echo '<div class="form-field"><label for="tdd-short">' . esc_html__( 'One-line description', 'techdosedaily-core' ) . '</label><input type="text" id="tdd-short" name="tdd_short_description" maxlength="120"><p>' . esc_html__( 'Shown on the About page coverage list, e.g. “Models, agents, research and the companies building them.”', 'techdosedaily-core' ) . '</p></div>';
	}
);

add_action(
	'category_edit_form_fields',
	static function ( WP_Term $term ) {
		if ( 0 !== (int) $term->parent ) {
			return;
		}
		$s       = tdd_core_section_settings( $term->term_id );
		$short   = (string) get_term_meta( $term->term_id, 'tdd_short_description', true );
		$hidden  = (array) get_term_meta( $term->term_id, 'tdd_section_hidden', true );
		$people  = array();
		foreach ( get_users( array( 'capability' => 'edit_posts', 'orderby' => 'display_name' ) ) as $u ) {
			$title          = trim( (string) get_user_meta( $u->ID, 'tdd_title', true ) );
			$people[ $u->ID ] = $u->display_name . ( '' !== $title ? ' — ' . $title : '' );
		}
		$topics = array();
		$used   = function_exists( 'tdd_core_section_topics' ) ? tdd_core_section_topics( $term->term_id, 40 ) : array();
		foreach ( $used as [ $t, $n ] ) {
			/* translators: 1: topic, 2: number of stories. */
			$topics[ $t->term_id ] = sprintf( __( '%1$s (%2$d in this section)', 'techdosedaily-core' ), $t->name, $n );
		}
		foreach ( get_terms( array( 'taxonomy' => 'tdd_topic', 'hide_empty' => false, 'orderby' => 'name', 'number' => 400 ) ) as $t ) {
			$topics[ $t->term_id ] ??= $t->name;
		}
		$row = static fn( string $label, string $for, string $field, string $help = '' ) => '<tr class="form-field"><th scope="row"><label for="' . esc_attr( $for ) . '">' . esc_html( $label ) . '</label></th><td>' . $field . ( '' !== $help ? '<p class="description">' . esc_html( $help ) . '</p>' : '' ) . '</td></tr>';

		wp_nonce_field( 'tdd_section_settings', 'tdd_section_nonce' );
		echo '<tr><th colspan="2" style="padding-left:0"><h2>' . esc_html__( 'Section page', 'techdosedaily-core' ) . '</h2></th></tr>'; // phpcs:ignore
		echo $row( __( 'One-line description', 'techdosedaily-core' ), 'tdd-short', '<input type="text" id="tdd-short" name="tdd_short_description" maxlength="120" value="' . esc_attr( $short ) . '">', __( 'Shown on the About page coverage list. The Description above is the longer intro shown on the section page.', 'techdosedaily-core' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		echo $row( __( 'Topic navigation', 'techdosedaily-core' ), 'tdd-topicnav', tdd_core_ordered_field( 'tdd-topicnav', 'tdd_topic_nav', $s['topic_nav'], $topics, __( 'Add a topic…', 'techdosedaily-core' ), 7, __( 'Not set: the topics most used in this section are shown.', 'techdosedaily-core' ) ), __( 'Up to 7, in this order, under the section title.', 'techdosedaily-core' ) ); // phpcs:ignore
		$mods = '';
		foreach ( tdd_core_section_modules() as $key => $label ) {
			$mods .= '<label style="display:block;margin:0 0 6px"><input type="checkbox" name="tdd_modules[]" value="' . esc_attr( $key ) . '"' . checked( ! in_array( $key, $hidden, true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo $row( __( 'Modules', 'techdosedaily-core' ), 'tdd-mods', '<fieldset id="tdd-mods"><legend class="screen-reader-text">' . esc_html__( 'Modules', 'techdosedaily-core' ) . '</legend>' . $mods . '<input type="hidden" name="tdd_modules_present" value="1"></fieldset>', __( 'Modules also hide themselves while they have nothing real to show.', 'techdosedaily-core' ) ); // phpcs:ignore
		echo $row( __( 'Pinned stories', 'techdosedaily-core' ), 'tdd-pins', '<a id="tdd-pins" class="button" href="' . esc_url( admin_url( 'admin.php?page=tdd-placements&section=' . $term->term_id ) ) . '">' . esc_html__( 'Choose this section’s lead and supporting stories →', 'techdosedaily-core' ) . '</a>' ); // phpcs:ignore

		echo '<tr><th colspan="2" style="padding-left:0"><h2>' . esc_html__( 'Section desk', 'techdosedaily-core' ) . '</h2><p class="description" style="font-weight:400">' . esc_html__( 'The desk module appears only once real people are listed.', 'techdosedaily-core' ) . '</p></th></tr>';
		echo $row( __( 'Desk people', 'techdosedaily-core' ), 'tdd-desk', tdd_core_ordered_field( 'tdd-desk', 'tdd_desk_members', $s['desk_members'], $people, __( 'Add a person…', 'techdosedaily-core' ), 6 ) ); // phpcs:ignore
		echo $row( __( 'Desk statement', 'techdosedaily-core' ), 'tdd-desknote', '<textarea id="tdd-desknote" name="tdd_desk_note" rows="2" maxlength="240">' . esc_textarea( $s['desk_note'] ) . '</textarea>', __( 'One or two sentences on what this desk covers and how.', 'techdosedaily-core' ) ); // phpcs:ignore
		echo $row( __( '“How we cover” link', 'techdosedaily-core' ), 'tdd-deskurl', '<input type="url" id="tdd-deskurl" name="tdd_desk_url" value="' . esc_attr( $s['desk_url'] ) . '" placeholder="https://">' ); // phpcs:ignore
	}
);

/** Save on create and edit. */
function tdd_core_save_section_settings( int $term_id ): void {
	if ( ! isset( $_POST['tdd_section_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['tdd_section_nonce'] ) ), 'tdd_section_settings' ) || ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	foreach ( array( 'tdd_short_description', 'tdd_desk_note', 'tdd_desk_url' ) as $k ) {
		if ( isset( $_POST[ $k ] ) ) {
			update_term_meta( $term_id, $k, wp_unslash( $_POST[ $k ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- registered sanitizer runs.
		}
	}
	foreach ( array( 'tdd_topic_nav', 'tdd_desk_members' ) as $k ) {
		$ids = tdd_core_posted_ids( $k );
		if ( null !== $ids ) {
			update_term_meta( $term_id, $k, $ids );
		}
	}
	if ( ! empty( $_POST['tdd_modules_present'] ) ) {
		$on = array_map( 'sanitize_key', (array) wp_unslash( $_POST['tdd_modules'] ?? array() ) );
		update_term_meta( $term_id, 'tdd_section_hidden', array_values( array_diff( array_keys( tdd_core_section_modules() ), $on ) ) );
	}
}
add_action( 'created_category', 'tdd_core_save_section_settings' );
add_action( 'edited_category', 'tdd_core_save_section_settings' );

/** Sections list: the one-liner is more useful than the slug column. */
add_filter(
	'manage_edit-category_columns',
	static function ( array $cols ) {
		$out = array();
		foreach ( $cols as $k => $v ) {
			if ( 'description' === $k ) {
				$out['tdd_short'] = __( 'One-line description', 'techdosedaily-core' );
				continue;
			}
			$out[ $k ] = $v;
		}
		return $out;
	}
);
add_filter(
	'manage_category_custom_column',
	static function ( $out, $col, $term_id ) {
		if ( 'tdd_short' !== $col ) {
			return $out;
		}
		$v = (string) get_term_meta( (int) $term_id, 'tdd_short_description', true );
		return '' !== $v ? esc_html( $v ) : '<span class="tdd-missing">' . esc_html__( 'Not set', 'techdosedaily-core' ) . '</span>';
	},
	10,
	3
);
