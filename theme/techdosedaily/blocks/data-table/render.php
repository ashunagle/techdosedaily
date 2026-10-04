<?php
/**
 * tdd/data-table — DataTable. Desktop: the approved table. Phones (≤767): stacked comparison
 * rows (m-cmp) when there are at most three value columns; wider tables scroll in their own box.
 * Title, unit line and source note are kept on both. Omitted when there are no rows.
 *
 * @package techdosedaily
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$tdd_head = array_map( 'strval', (array) $attributes['head'] );
$tdd_rows = array_values( array_filter( (array) $attributes['rows'], static fn( $r ) => is_array( $r ) && $r ) );
if ( ! $tdd_rows ) {
	return;
}
$tdd_inline = array(
	'a'      => array( 'href' => true, 'aria-label' => true ),
	'sup'    => array(),
	'b'      => array(),
	'strong' => array(),
	'em'     => array(),
);
$tdd_cell   = static fn( $v ) => wp_kses( (string) $v, $tdd_inline );
$tdd_cols   = max( count( $tdd_head ), max( array_map( 'count', $tdd_rows ) ) );
$tdd_focus  = (int) $attributes['focus']; // 1-based row; 0 = none.

$tdd_table = '<table class="tdd-table">';
if ( $tdd_head ) {
	$tdd_table .= '<thead><tr>';
	foreach ( range( 0, $tdd_cols - 1 ) as $tdd_c ) {
		$tdd_table .= '<th scope="col"' . ( $tdd_c ? ' class="num"' : '' ) . '>' . $tdd_cell( $tdd_head[ $tdd_c ] ?? '' ) . '</th>';
	}
	$tdd_table .= '</tr></thead>';
}
$tdd_table .= '<tbody>';
foreach ( $tdd_rows as $tdd_i => $tdd_r ) {
	$tdd_table .= '<tr' . ( $tdd_i + 1 === $tdd_focus ? ' class="is-focus"' : '' ) . '>';
	foreach ( range( 0, $tdd_cols - 1 ) as $tdd_c ) {
		$tdd_table .= $tdd_c ? '<td class="num">' . $tdd_cell( $tdd_r[ $tdd_c ] ?? '' ) . '</td>' : '<td>' . $tdd_cell( $tdd_r[0] ?? '' ) . '</td>';
	}
	$tdd_table .= '</tr>';
}
$tdd_table .= '</tbody></table>';

if ( 'policy' === ( $attributes['variant'] ?? 'data' ) ) {
	// Approved StaticPage table: text cells, bold first column; phones get labelled stacked rows.
	static $tdd_n = 0;
	$tdd_id    = 'tdd-ptable-' . ( ++$tdd_n );
	$tdd_title = (string) $attributes['title'];
	$tdd_pt    = '<table class="tdd-table"' . ( '' !== $tdd_title ? ' aria-labelledby="' . $tdd_id . '"' : '' ) . '>';
	if ( $tdd_head ) {
		$tdd_pt .= '<thead><tr>';
		foreach ( range( 0, $tdd_cols - 1 ) as $tdd_c ) {
			$tdd_pt .= '<th scope="col">' . $tdd_cell( $tdd_head[ $tdd_c ] ?? '' ) . '</th>';
		}
		$tdd_pt .= '</tr></thead>';
	}
	$tdd_pt  .= '<tbody>';
	$tdd_cmp  = '';
	foreach ( $tdd_rows as $tdd_r ) {
		$tdd_pt  .= '<tr>';
		$tdd_vals = '';
		foreach ( range( 0, $tdd_cols - 1 ) as $tdd_c ) {
			$tdd_v   = $tdd_cell( $tdd_r[ $tdd_c ] ?? '' );
			$tdd_pt .= '<td>' . ( 0 === $tdd_c ? '<b>' . $tdd_v . '</b>' : $tdd_v ) . '</td>';
			if ( $tdd_c ) {
				$tdd_vals .= '<p><small>' . $tdd_cell( $tdd_head[ $tdd_c ] ?? '' ) . '</small>' . $tdd_v . '</p>';
			}
		}
		$tdd_pt  .= '</tr>';
		$tdd_cmp .= '<div class="m-cmp__row" role="row"><div class="m-cmp__name" role="rowheader">' . $tdd_cell( $tdd_r[0] ?? '' ) . '</div><div class="sp-mrow" role="cell">' . $tdd_vals . '</div></div>';
	}
	$tdd_pt .= '</tbody></table>';
	echo '<div class="tdd-block tdd-data tdd-data--policy">'
		. ( '' !== $tdd_title ? '<div class="tdd-table__title" id="' . $tdd_id . '">' . esc_html( $tdd_title ) . '</div>' : '' )
		. ( '' !== $attributes['subtitle'] ? '<div class="tdd-table__sub tdd-hide-m">' . esc_html( $attributes['subtitle'] ) . '</div>' : '' )
		. '<div class="tdd-hide-m">' . $tdd_pt . '</div>'
		. '<div class="m-cmp tdd-only-m is-block" role="table" aria-label="' . esc_attr( '' !== $tdd_title ? $tdd_title : __( 'Data table', 'techdosedaily' ) ) . '">' . $tdd_cmp . '</div>'
		. ( '' !== $attributes['note'] ? '<div class="tdd-table__note">' . $tdd_cell( $attributes['note'] ) . '</div>' : '' )
		. '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped/kses above.
	return;
}

$tdd_stack = $tdd_cols - 1 <= 3;
$tdd_body  = '';
if ( $tdd_stack ) {
	$tdd_short = array_map( 'strval', (array) $attributes['short'] );
	$tdd_cmp   = '';
	foreach ( $tdd_rows as $tdd_i => $tdd_r ) {
		$tdd_vals = '';
		foreach ( range( 1, $tdd_cols - 1 ) as $tdd_c ) {
			$tdd_label = '' !== ( $tdd_short[ $tdd_c ] ?? '' ) ? $tdd_short[ $tdd_c ] : ( $tdd_head[ $tdd_c ] ?? '' );
			$tdd_vals .= '<div><small>' . $tdd_cell( $tdd_label ) . '</small>' . $tdd_cell( $tdd_r[ $tdd_c ] ?? '' ) . '</div>';
		}
		$tdd_cmp .= '<div class="m-cmp__row' . ( $tdd_i + 1 === $tdd_focus ? ' is-focus' : '' ) . '"><div class="m-cmp__name">' . $tdd_cell( $tdd_r[0] ?? '' ) . '</div><div class="m-cmp__vals">' . $tdd_vals . '</div></div>';
	}
	$tdd_body = '<div class="tdd-hide-m">' . $tdd_table . '</div><div class="m-cmp tdd-only-m is-block">' . $tdd_cmp . '</div>';
} else {
	$tdd_body = '<div class="tdd-table-wrap" tabindex="0" role="region" aria-label="' . esc_attr( '' !== $attributes['title'] ? $attributes['title'] : __( 'Data table', 'techdosedaily' ) ) . '">' . $tdd_table . '</div>';
}

echo '<div class="tdd-block tdd-data">'
	. ( '' !== $attributes['title'] ? '<div class="tdd-table__title">' . esc_html( $attributes['title'] ) . '</div>' : '' )
	. ( '' !== $attributes['subtitle'] ? '<div class="tdd-table__sub">' . esc_html( $attributes['subtitle'] ) . '</div>' : '' )
	. $tdd_body
	. ( '' !== $attributes['note'] ? '<div class="tdd-table__note">' . $tdd_cell( $attributes['note'] ) . '</div>' : '' )
	. '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped/kses above.
