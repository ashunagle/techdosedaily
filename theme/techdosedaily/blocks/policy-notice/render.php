<?php
/**
 * tdd/policy-notice — PolicyNotice around one inner paragraph.
 *
 * @package techdosedaily
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

defined( 'ABSPATH' ) || exit;

echo tdd_policy_notice( (string) $attributes['title'], (string) $content, (bool) $attributes['change'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses in the builder.
