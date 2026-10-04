<?php
/**
 * tdd/funding — FundingDisclosure.
 *
 * @package techdosedaily
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

defined( 'ABSPATH' ) || exit;

echo tdd_funding( (array) $attributes['rows'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
