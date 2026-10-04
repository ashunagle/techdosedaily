<?php
/**
 * tdd/pagination — real /page/N/ URLs always present; "Load more" appears only with JS.
 *
 * @package techdosedaily
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

echo tdd_pagination( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
