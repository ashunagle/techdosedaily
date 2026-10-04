<?php
/**
 * Small shared helpers.
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns an inline SVG icon from assets/icons (Lucide outline, currentColor).
 *
 * @param string $name  Icon file name without extension.
 * @param string $class Extra class names.
 */
function tdd_icon( string $name, string $class = 'tdd-ic' ): string {
	static $cache = array();
	if ( ! isset( $cache[ $name ] ) ) {
		$file           = TDD_DIR . '/assets/icons/' . sanitize_file_name( $name ) . '.svg';
		$cache[ $name ] = is_readable( $file ) ? trim( (string) file_get_contents( $file ) ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
	return str_replace( '<svg ', '<svg class="' . esc_attr( $class ) . '" aria-hidden="true" focusable="false" ', $cache[ $name ] );
}

/** URL of a theme asset. */
function tdd_asset( string $path ): string {
	return esc_url( TDD_URI . '/assets/' . ltrim( $path, '/' ) );
}

/** Logo markup as in the approved header/footer. */
function tdd_logo( bool $link = true, string $variant = 'auto' ): string {
	$name  = esc_attr( get_bloginfo( 'name' ) );
	// V1 ships light mode only, so only the light logo is printed (no hidden dark image download).
	$light = '<img class="on-light" src="' . tdd_asset( 'images/tdd-logo-a-pulse.svg' ) . '" alt="' . $name . '" width="226" height="32">';
	$dark  = '';
	if ( 'white' === $variant ) {
		$imgs = '<img src="' . tdd_asset( 'images/tdd-logo-a-pulse-white.svg' ) . '" alt="' . $name . '" width="226" height="32" class="tdd-footer__logo">';
	} else {
		$imgs = $light . $dark;
	}
	if ( ! $link ) {
		return $imgs;
	}
	/* translators: %s: site name. */
	$label = sprintf( __( '%s home', 'techdosedaily' ), $name );
	return '<a class="tdd-logo" href="' . esc_url( home_url( '/' ) ) . '" aria-label="' . esc_attr( $label ) . '">' . $imgs . '</a>';
}
