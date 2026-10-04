<?php
/**
 * "Tech Dose Daily profile" on Users → Profile / Edit user. Reporters edit their own public facts;
 * About-page listing, Featured Reporting and the reporter's editor are for editors only.
 * Only true, author-approved details — every field is optional and hidden on the site when empty.
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

function tdd_core_profile_fields( WP_User $user ): void {
	if ( ! current_user_can( 'edit_user', $user->ID ) || ! user_can( $user, 'edit_posts' ) ) {
		return; // Only contributors and up have a public author profile.
	}
	$m       = static fn( string $k ) => get_user_meta( $user->ID, $k, true );
	$editor  = current_user_can( 'edit_others_posts' );
	$photo   = (int) $m( 'tdd_photo' );
	$img     = $photo ? wp_get_attachment_image_url( $photo, 'thumbnail' ) : '';
	$social  = array_values( (array) $m( 'tdd_social' ) );
	$topics  = array();
	foreach ( get_terms( array( 'taxonomy' => 'tdd_topic', 'hide_empty' => false, 'orderby' => 'name', 'number' => 400 ) ) as $t ) {
		$topics[ $t->term_id ] = $t->name;
	}
	$text = static fn( string $k, string $label, string $help = '', int $max = 120 ) => '<tr><th scope="row"><label for="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><input type="text" class="regular-text" id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" maxlength="' . $max . '" value="' . esc_attr( (string) get_user_meta( $user->ID, $k, true ) ) . '">' . ( '' !== $help ? '<p class="description">' . esc_html( $help ) . '</p>' : '' ) . '</td></tr>';

	wp_nonce_field( 'tdd_profile_' . $user->ID, 'tdd_profile_nonce' );
	echo '<div class="tdd-form-section"><h2>' . esc_html__( 'Tech Dose Daily profile', 'techdosedaily-core' ) . '</h2>';
	echo '<p class="description">' . esc_html__( 'Shown on your author page and story bylines. Only add what is true and what you are happy to make public; empty fields are simply not shown.', 'techdosedaily-core' ) . '</p>';
	echo '<table class="form-table" role="presentation">';
	echo $text( 'tdd_title', __( 'Role / title', 'techdosedaily-core' ), __( 'e.g. “Senior AI Correspondent”.', 'techdosedaily-core' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $text( 'tdd_short_bio', __( 'One-line bio', 'techdosedaily-core' ), __( 'For bylines and cards: what you cover, in one sentence.', 'techdosedaily-core' ), 180 ); // phpcs:ignore
	echo '<tr><th scope="row"><label for="tdd_note">' . esc_html__( 'How I report', 'techdosedaily-core' ) . '</label></th><td><textarea id="tdd_note" name="tdd_note" rows="3" class="large-text" maxlength="600">' . esc_textarea( (string) $m( 'tdd_note' ) ) . '</textarea><p class="description">' . esc_html__( 'Optional note on your author page, e.g. how you test products or handle company claims.', 'techdosedaily-core' ) . '</p></td></tr>';
	echo $text( 'tdd_location', __( 'Location', 'techdosedaily-core' ), __( 'Only if you want it shown, e.g. “London”.', 'techdosedaily-core' ) ); // phpcs:ignore
	echo $text( 'tdd_covering_since', __( 'Experience line', 'techdosedaily-core' ), __( 'Only if true, e.g. “Covering software and AI since 2016”.', 'techdosedaily-core' ) ); // phpcs:ignore

	// Photo.
	echo '<tr><th scope="row">' . esc_html__( 'Profile photo', 'techdosedaily-core' ) . '</th><td>';
	if ( current_user_can( 'upload_files' ) ) {
		echo '<div class="tdd-photo" data-tdd-photo><img' . ( $img ? ' src="' . esc_url( $img ) . '"' : ' hidden' ) . ' alt=""><span class="tdd-photo__ph"' . ( $img ? ' hidden' : '' ) . '>' . esc_html( mb_strtoupper( mb_substr( $user->display_name, 0, 1 ) ) ) . '</span>'
			. '<input type="hidden" name="tdd_photo" value="' . esc_attr( (string) $photo ) . '"><button type="button" class="button" data-photo-choose>' . esc_html__( 'Choose photo', 'techdosedaily-core' ) . '</button> <button type="button" class="button-link button-link-delete" data-photo-remove' . ( $img ? '' : ' hidden' ) . '>' . esc_html__( 'Remove', 'techdosedaily-core' ) . '</button></div>';
		echo '<p class="description">' . esc_html__( 'A real, recent portrait (square works best). Until one is set, your initials are shown.', 'techdosedaily-core' ) . '</p>';
	} else {
		echo '<p class="description">' . esc_html__( 'Ask an editor to add your portrait.', 'techdosedaily-core' ) . '</p>';
	}
	echo '</td></tr>';

	// Beats.
	echo '<tr><th scope="row"><label for="tdd-beats">' . esc_html__( 'Beats', 'techdosedaily-core' ) . '</label></th><td>' . tdd_core_ordered_field( 'tdd-beats', 'tdd_beats', array_map( 'intval', (array) $m( 'tdd_beats' ) ), $topics, __( 'Add a topic…', 'techdosedaily-core' ), 8 ) . '<p class="description">' . esc_html__( '4–8 topics you cover most, in order. Shown on your author page.', 'techdosedaily-core' ) . '</p></td></tr>'; // phpcs:ignore

	// Social profiles.
	$row = static fn( $i, $label, $url ) => '<div class="tdd-repeat__row"><label class="screen-reader-text" for="tdd-sl-' . $i . '">' . esc_html__( 'Label', 'techdosedaily-core' ) . '</label><input type="text" id="tdd-sl-' . $i . '" name="tdd_social[' . $i . '][label]" value="' . esc_attr( $label ) . '" placeholder="' . esc_attr__( 'LinkedIn', 'techdosedaily-core' ) . '"><label class="screen-reader-text" for="tdd-su-' . $i . '">' . esc_html__( 'Profile URL', 'techdosedaily-core' ) . '</label><input type="url" id="tdd-su-' . $i . '" name="tdd_social[' . $i . '][url]" value="' . esc_attr( $url ) . '" placeholder="https://"><button type="button" class="button-link button-link-delete" data-remove>' . esc_html__( 'Remove', 'techdosedaily-core' ) . '</button></div>';
	$rows = '';
	foreach ( $social as $i => $s ) {
		$rows .= $row( $i, (string) ( $s['label'] ?? '' ), (string) ( $s['url'] ?? '' ) );
	}
	echo '<tr><th scope="row">' . esc_html__( 'Social profiles', 'techdosedaily-core' ) . '</th><td><div class="tdd-repeat" data-tdd-repeat><div data-rows>' . $rows . '</div><template>' . $row( '__i__', '', '' ) . '</template><p><button type="button" class="button" data-add>' . esc_html__( 'Add a profile', 'techdosedaily-core' ) . '</button></p><input type="hidden" name="tdd_social_present" value="1"></div><p class="description">' . esc_html__( 'Accounts you actually use for your work.', 'techdosedaily-core' ) . '</p></td></tr>'; // phpcs:ignore

	echo '<tr><th scope="row">' . esc_html__( 'Contact link', 'techdosedaily-core' ) . '</th><td><label><input type="checkbox" name="tdd_public_email" value="1"' . checked( (bool) $m( 'tdd_public_email' ), true, false ) . '> ' . esc_html__( 'Show an email link on my author page', 'techdosedaily-core' ) . '</label><p class="description">' . esc_html__( 'Uses the account email above.', 'techdosedaily-core' ) . '</p></td></tr>';
	echo '</table>';

	if ( $editor ) {
		echo '<h3>' . esc_html__( 'Editors only', 'techdosedaily-core' ) . '</h3><table class="form-table" role="presentation">';
		echo '<tr><th scope="row">' . esc_html__( 'About page', 'techdosedaily-core' ) . '</th><td><label><input type="checkbox" id="tdd-about" name="tdd_show_on_about" value="1"' . checked( (bool) $m( 'tdd_show_on_about' ), true, false ) . '> ' . esc_html__( 'List under “Our editors” on the About page', 'techdosedaily-core' ) . '</label>'
			. '<p class="tdd-depends" data-tdd-depends="tdd-about"><label for="tdd-about-order">' . esc_html__( 'Order', 'techdosedaily-core' ) . '</label> <input type="number" id="tdd-about-order" name="tdd_about_order" min="0" max="99" class="small-text" value="' . esc_attr( (string) (int) $m( 'tdd_about_order' ) ) . '"> <span class="description">' . esc_html__( 'Lower numbers first. Uses the name, title, one-line bio and photo above.', 'techdosedaily-core' ) . '</span></p></td></tr>';
		$own = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'author' => $user->ID, 'posts_per_page' => 100, 'no_found_rows' => true ) );
		$fp  = array_values( array_map( 'intval', (array) $m( 'tdd_featured_posts' ) ) );
		echo '<tr><th scope="row">' . esc_html__( 'Featured Reporting', 'techdosedaily-core' ) . '</th><td>';
		if ( $own ) {
			$opts = array();
			foreach ( $own as $p ) {
				$opts[ $p->ID ] = get_the_title( $p ) . ' — ' . get_the_date( 'M j, Y', $p );
			}
			echo tdd_core_ordered_field( 'tdd-featured', 'tdd_featured_posts', $fp, $opts, __( 'Add a story…', 'techdosedaily-core' ), 3, __( 'Not set: the newest analysis and explainers are shown.', 'techdosedaily-core' ) ); // phpcs:ignore
			echo '<p class="description">' . esc_html__( 'Up to 3 stories, in order, at the top of the author page.', 'techdosedaily-core' ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'Available once this person has published stories.', 'techdosedaily-core' ) . '</p>';
		}
		echo '</td></tr>';
		if ( current_user_can( 'edit_users' ) ) {
			$sel = '<option value="0">' . esc_html__( '— Not set —', 'techdosedaily-core' ) . '</option>';
			foreach ( tdd_core_editor_users() as $u ) {
				if ( $u['id'] !== $user->ID ) {
					$sel .= '<option value="' . esc_attr( (string) $u['id'] ) . '"' . selected( (int) $m( 'tdd_editor_user' ), $u['id'], false ) . '>' . esc_html( $u['name'] ) . '</option>';
				}
			}
			echo '<tr><th scope="row"><label for="tdd-editoruser">' . esc_html__( 'Editor', 'techdosedaily-core' ) . '</label></th><td><select id="tdd-editoruser" name="tdd_editor_user">' . $sel . '</select><p class="description">' . esc_html__( 'Shown in “About this reporter”.', 'techdosedaily-core' ) . '</p></td></tr>'; // phpcs:ignore
		}
		echo '</table>';
	}
	echo '</div>';
}
add_action( 'show_user_profile', 'tdd_core_profile_fields' );
add_action( 'edit_user_profile', 'tdd_core_profile_fields' );

function tdd_core_save_profile_fields( int $user_id ): void {
	if ( ! isset( $_POST['tdd_profile_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['tdd_profile_nonce'] ) ), 'tdd_profile_' . $user_id ) || ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- registered meta sanitizers run in update_user_meta().
	foreach ( array( 'tdd_title', 'tdd_short_bio', 'tdd_location', 'tdd_covering_since' ) as $k ) {
		if ( isset( $_POST[ $k ] ) ) {
			update_user_meta( $user_id, $k, wp_unslash( $_POST[ $k ] ) );
		}
	}
	if ( isset( $_POST['tdd_note'] ) ) {
		update_user_meta( $user_id, 'tdd_note', sanitize_textarea_field( wp_unslash( $_POST['tdd_note'] ) ) );
	}
	update_user_meta( $user_id, 'tdd_public_email', ! empty( $_POST['tdd_public_email'] ) );
	if ( isset( $_POST['tdd_photo'] ) && current_user_can( 'upload_files' ) ) {
		$pid = absint( $_POST['tdd_photo'] );
		update_user_meta( $user_id, 'tdd_photo', $pid && wp_attachment_is_image( $pid ) ? $pid : 0 );
	}
	$beats = tdd_core_posted_ids( 'tdd_beats' );
	if ( null !== $beats ) {
		update_user_meta( $user_id, 'tdd_beats', array_slice( $beats, 0, 8 ) );
	}
	if ( ! empty( $_POST['tdd_social_present'] ) ) {
		$rows = array();
		foreach ( (array) wp_unslash( $_POST['tdd_social'] ?? array() ) as $r ) {
			$url = esc_url_raw( trim( (string) ( $r['url'] ?? '' ) ), array( 'http', 'https' ) );
			if ( '' !== $url ) {
				$rows[] = array( 'label' => sanitize_text_field( $r['label'] ?? '' ), 'url' => $url );
			}
		}
		update_user_meta( $user_id, 'tdd_social', $rows );
	}
	if ( current_user_can( 'edit_others_posts' ) ) {
		update_user_meta( $user_id, 'tdd_show_on_about', ! empty( $_POST['tdd_show_on_about'] ) ? '1' : '' );
		if ( isset( $_POST['tdd_about_order'] ) ) {
			update_user_meta( $user_id, 'tdd_about_order', absint( $_POST['tdd_about_order'] ) );
		}
		$fp = tdd_core_posted_ids( 'tdd_featured_posts' );
		if ( null !== $fp ) {
			update_user_meta( $user_id, 'tdd_featured_posts', array_slice( array_values( array_filter( $fp, static fn( $id ) => (int) get_post_field( 'post_author', $id ) === $user_id ) ), 0, 3 ) );
		}
	}
	if ( current_user_can( 'edit_users' ) && isset( $_POST['tdd_editor_user'] ) ) {
		$eid = absint( $_POST['tdd_editor_user'] );
		update_user_meta( $user_id, 'tdd_editor_user', $eid !== $user_id && $eid && user_can( $eid, 'edit_others_posts' ) ? $eid : 0 );
	}
	// phpcs:enable
}
add_action( 'personal_options_update', 'tdd_core_save_profile_fields' );
add_action( 'edit_user_profile_update', 'tdd_core_save_profile_fields' );
