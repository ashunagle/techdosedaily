<?php
/**
 * tdd/policy-callout — PolicyCallout around inner blocks. Omitted when empty.
 *
 * @package techdosedaily
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

defined( 'ABSPATH' ) || exit;

echo tdd_policy_callout( (string) $attributes['title'], (string) $content, (bool) $attributes['neutral'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inner blocks are filtered post content.
