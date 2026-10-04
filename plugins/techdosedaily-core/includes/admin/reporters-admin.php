<?php
/**
 * Tech Dose Daily → Reporter profiles (Phase 8). The approved Phase 5 workflow: editors decide who is
 * listed on the About page (and in which order) and choose each reporter's Featured Reporting —
 * without WordPress's general `edit_users` capability.
 *
 * This screen can change exactly three public editorial fields and nothing else:
 *   tdd_show_on_about · tdd_about_order · tdd_featured_posts (that reporter's own published stories, max 3)
 * Roles, emails, passwords, names and every other account field stay with the person and administrators.
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

/** Who an editor may manage here: people with a public byline; administrators only by administrators. */
function tdd_core_reporter_manageable( int $user_id ): bool {
	$user = get_userdata( $user_id );
	if ( ! $user || ! current_user_can( 'edit_others_posts' ) || ! user_can( $user, 'edit_posts' ) ) {
		return false;
	}
	return ! user_can( $user, 'manage_options' ) || current_user_can( 'manage_options' );
}

/** The three fields, validated. Returns the values that were stored. */
function tdd_core_save_reporter_fields( int $user_id, bool $on_about, int $order, array $featured ): array {
	$order = min( 99, max( 0, $order ) );
	$keep  = array();
	foreach ( $featured as $pid ) {
		$pid = absint( $pid );
		if ( $pid && ! in_array( $pid, $keep, true ) && 'post' === get_post_type( $pid ) && 'publish' === get_post_status( $pid ) && (int) get_post_field( 'post_author', $pid ) === $user_id ) {
			$keep[] = $pid;
		}
	}
	$keep = array_slice( $keep, 0, 3 );
	update_user_meta( $user_id, 'tdd_show_on_about', $on_about ? '1' : '' );
	update_user_meta( $user_id, 'tdd_about_order', $order );
	update_user_meta( $user_id, 'tdd_featured_posts', $keep );
	return array( 'on_about' => $on_about, 'order' => $order, 'featured' => $keep );
}

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'tdd-placements', __( 'Reporter profiles', 'techdosedaily-core' ), __( 'Reporter profiles', 'techdosedaily-core' ), 'edit_others_posts', 'tdd-reporters', 'tdd_core_reporters_page' );
	},
	11
);

function tdd_core_reporters_page(): void {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage reporter profiles.', 'techdosedaily-core' ) );
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view selection and notices.
	$uid  = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
	$done = isset( $_GET['updated'] );
	// phpcs:enable
	echo '<div class="wrap tdd-form-section"><h1>' . esc_html__( 'Reporter profiles', 'techdosedaily-core' ) . '</h1>';
	if ( $done ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Reporter profile saved.', 'techdosedaily-core' ) . '</p></div>';
	}

	if ( $uid ) {
		if ( ! tdd_core_reporter_manageable( $uid ) ) {
			wp_die( esc_html__( 'You can’t manage this person’s profile.', 'techdosedaily-core' ) );
		}
		$u   = get_userdata( $uid );
		$own = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'author' => $uid, 'posts_per_page' => 100, 'no_found_rows' => true ) );
		$opt = array();
		foreach ( $own as $p ) {
			$opt[ $p->ID ] = get_the_title( $p ) . ' — ' . get_the_date( 'M j, Y', $p );
		}
		$fp    = array_values( array_map( 'intval', (array) get_user_meta( $uid, 'tdd_featured_posts', true ) ) );
		$title = (string) get_user_meta( $uid, 'tdd_title', true );
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=tdd-reporters' ) ) . '">← ' . esc_html__( 'All reporters', 'techdosedaily-core' ) . '</a></p>';
		echo '<h2>' . esc_html( $u->display_name ) . ( '' !== $title ? ' <span class="description">· ' . esc_html( $title ) . '</span>' : '' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Editors choose who appears on the About page and which stories lead this reporter’s author page. Everything else in the profile is edited by the reporter (or an administrator).', 'techdosedaily-core' ) . ' <a href="' . esc_url( get_author_posts_url( $uid ) ) . '">' . esc_html__( 'View author page', 'techdosedaily-core' ) . '</a></p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'tdd_reporter_' . $uid, 'tdd_reporter_nonce' );
		echo '<input type="hidden" name="action" value="tdd_reporter_profile"><input type="hidden" name="user_id" value="' . esc_attr( (string) $uid ) . '">';
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row">' . esc_html__( 'About page', 'techdosedaily-core' ) . '</th><td><label><input type="checkbox" id="tdd-about" name="tdd_show_on_about" value="1"' . checked( (bool) get_user_meta( $uid, 'tdd_show_on_about', true ), true, false ) . '> ' . esc_html__( 'List under “Our editors” on the About page', 'techdosedaily-core' ) . '</label>'
			. '<p class="tdd-depends" data-tdd-depends="tdd-about"><label for="tdd-about-order">' . esc_html__( 'Order', 'techdosedaily-core' ) . '</label> <input type="number" id="tdd-about-order" name="tdd_about_order" min="0" max="99" class="small-text" value="' . esc_attr( (string) (int) get_user_meta( $uid, 'tdd_about_order', true ) ) . '"> <span class="description">' . esc_html__( 'Lower numbers first.', 'techdosedaily-core' ) . '</span></p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Featured Reporting', 'techdosedaily-core' ) . '</th><td>';
		if ( $opt ) {
			echo tdd_core_ordered_field( 'tdd-featured', 'tdd_featured_posts', $fp, $opt, __( 'Add a story…', 'techdosedaily-core' ), 3, __( 'Not set: the newest analysis and explainers are shown.', 'techdosedaily-core' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
			echo '<p class="description">' . esc_html__( 'Up to 3 of this reporter’s published stories, in order, at the top of the author page.', 'techdosedaily-core' ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'Available once this person has published stories.', 'techdosedaily-core' ) . '</p>';
		}
		echo '</td></tr></table>';
		submit_button( __( 'Save reporter profile', 'techdosedaily-core' ) );
		echo '</form></div>';
		return;
	}

	$people = get_users( array( 'capability' => 'edit_posts', 'orderby' => 'display_name' ) );
	echo '<p class="description">' . esc_html__( 'About-page listing and Featured Reporting for each reporter.', 'techdosedaily-core' ) . '</p>';
	echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Reporter', 'techdosedaily-core' ) . '</th><th scope="col">' . esc_html__( 'About page', 'techdosedaily-core' ) . '</th><th scope="col">' . esc_html__( 'Featured Reporting', 'techdosedaily-core' ) . '</th><th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'techdosedaily-core' ) . '</span></th></tr></thead><tbody>';
	foreach ( $people as $u ) {
		if ( ! tdd_core_reporter_manageable( $u->ID ) ) {
			continue;
		}
		$about = get_user_meta( $u->ID, 'tdd_show_on_about', true )
			/* translators: %d: order. */
			? sprintf( __( 'Listed (order %d)', 'techdosedaily-core' ), (int) get_user_meta( $u->ID, 'tdd_about_order', true ) )
			: __( 'Not listed', 'techdosedaily-core' );
		$n     = count( (array) get_user_meta( $u->ID, 'tdd_featured_posts', true ) );
		/* translators: %d: number of stories. */
		$feat = $n ? sprintf( _n( '%d story', '%d stories', $n, 'techdosedaily-core' ), $n ) : __( 'Automatic', 'techdosedaily-core' );
		echo '<tr><td><strong>' . esc_html( $u->display_name ) . '</strong></td><td>' . esc_html( $about ) . '</td><td>' . esc_html( $feat ) . '</td><td><a class="button" href="' . esc_url( admin_url( 'admin.php?page=tdd-reporters&user=' . $u->ID ) ) . '">' . esc_html__( 'Edit', 'techdosedaily-core' ) . '<span class="screen-reader-text"> ' . esc_html( $u->display_name ) . '</span></a></td></tr>';
	}
	echo '</tbody></table></div>';
}

/** Save handler: nonce + capability + manageable target; writes only the three fields. */
add_action(
	'admin_post_tdd_reporter_profile',
	static function () {
		$uid = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified next.
		if ( ! $uid || ! isset( $_POST['tdd_reporter_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['tdd_reporter_nonce'] ) ), 'tdd_reporter_' . $uid ) ) {
			wp_die( esc_html__( 'This form has expired. Please reload the page and try again.', 'techdosedaily-core' ), '', array( 'response' => 403 ) );
		}
		if ( ! tdd_core_reporter_manageable( $uid ) ) {
			wp_die( esc_html__( 'You can’t manage this person’s profile.', 'techdosedaily-core' ), '', array( 'response' => 403 ) );
		}
		$featured = tdd_core_posted_ids( 'tdd_featured_posts' );
		if ( null === $featured ) {
			$featured = array_map( 'intval', (array) get_user_meta( $uid, 'tdd_featured_posts', true ) );
		}
		tdd_core_save_reporter_fields( $uid, ! empty( $_POST['tdd_show_on_about'] ), isset( $_POST['tdd_about_order'] ) ? absint( $_POST['tdd_about_order'] ) : 0, $featured );
		wp_safe_redirect( admin_url( 'admin.php?page=tdd-reporters&user=' . $uid . '&updated=1' ) );
		exit;
	}
);
