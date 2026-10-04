<?php
/**
 * Small, accessible form widgets shared by the section, profile and settings screens.
 * Behaviour lives in assets/admin/forms.js.
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_enqueue_scripts',
	static function ( string $hook ) {
		$screen = get_current_screen();
		$forms  = in_array( $hook, array( 'profile.php', 'user-edit.php', 'tech-dose-daily_page_tdd-settings' ), true )
			|| ( $screen && 'category' === $screen->taxonomy );
		if ( ! $forms ) {
			return;
		}
		if ( in_array( $hook, array( 'profile.php', 'user-edit.php' ), true ) && current_user_can( 'upload_files' ) ) {
			wp_enqueue_media();
		}
		wp_enqueue_script( 'tdd-forms', tdd_core_asset( 'admin/forms.js' ), array(), tdd_core_asset_ver( 'admin/forms.js' ), true );
		wp_localize_script(
			'tdd-forms',
			'tddForms',
			array(
				'moveUp'      => __( 'Move up', 'techdosedaily-core' ),
				'moveDown'    => __( 'Move down', 'techdosedaily-core' ),
				'remove'      => __( 'Remove', 'techdosedaily-core' ),
				'photoTitle'  => __( 'Choose a portrait', 'techdosedaily-core' ),
				'photoButton' => __( 'Use this photo', 'techdosedaily-core' ),
			)
		);
	}
);

/**
 * Ordered picker: chosen items in order (move / remove) plus an "Add…" select.
 *
 * @param string            $name     Input name (without []).
 * @param int[]             $selected IDs in order.
 * @param array<int,string> $options  id => label.
 */
function tdd_core_ordered_field( string $id, string $name, array $selected, array $options, string $add_label, int $max = 0, string $empty = '' ): string {
	$li = '';
	foreach ( $selected as $sid ) {
		if ( isset( $options[ $sid ] ) ) {
			$li .= '<li data-id="' . esc_attr( (string) $sid ) . '"><span>' . esc_html( $options[ $sid ] ) . '</span><input type="hidden" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( (string) $sid ) . '"></li>';
		}
	}
	$opts = '<option value="">' . esc_html( $add_label ) . '</option>';
	foreach ( $options as $oid => $label ) {
		$opts .= '<option value="' . esc_attr( (string) $oid ) . '">' . esc_html( $label ) . '</option>';
	}
	return '<div class="tdd-ordered" data-tdd-ordered data-name="' . esc_attr( $name ) . '[]"' . ( $max ? ' data-max="' . (int) $max . '"' : '' ) . '>'
		. ( '' !== $empty ? '<p class="description tdd-ordered__empty">' . esc_html( $empty ) . '</p>' : '' )
		. '<ol>' . $li . '</ol>'
		. '<label class="screen-reader-text" for="' . esc_attr( $id ) . '">' . esc_html( $add_label ) . '</label><select id="' . esc_attr( $id ) . '">' . $opts . '</select>'
		. '<input type="hidden" name="' . esc_attr( $name ) . '_present" value="1"></div>';
}

/** Posted list of IDs from an ordered picker (null = field not on the form). */
function tdd_core_posted_ids( string $name ): ?array {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- callers verify the nonce first.
	if ( empty( $_POST[ $name . '_present' ] ) ) {
		return null;
	}
	return array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST[ $name ] ?? array() ) ) ) ) );
	// phpcs:enable
}
