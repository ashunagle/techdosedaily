<?php
/**
 * tdd/home-sections — CategoryBlock row (inc/home.php).
 *
 * @package techdosedaily
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$tdd_blocks = array();
foreach ( (array) $attributes['blocks'] as $tdd_b ) {
	if ( is_array( $tdd_b ) && 2 === count( $tdd_b ) && in_array( $tdd_b[1], array( 'alert', 'feature', 'list' ), true ) ) {
		$tdd_blocks[] = array( sanitize_title( $tdd_b[0] ), $tdd_b[1] );
	}
}
echo tdd_home_sections( $tdd_blocks, array_map( 'sanitize_title', (array) $attributes['mobileOrder'] ), array_map( 'sanitize_title', (array) $attributes['chips'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
