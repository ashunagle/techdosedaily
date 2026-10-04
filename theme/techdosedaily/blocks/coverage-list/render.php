<?php
/**
 * tdd/coverage-list — CoverageList from the live sections.
 *
 * @package techdosedaily
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

defined( 'ABSPATH' ) || exit;

echo tdd_coverage_list( array_map( 'sanitize_title', (array) $attributes['exclude'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
