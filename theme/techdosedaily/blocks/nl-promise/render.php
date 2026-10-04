<?php
/**
 * tdd/nl-promise — NewsletterPromise.
 *
 * @package techdosedaily
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

defined( 'ABSPATH' ) || exit;

echo tdd_nl_promise( (string) $attributes['title'], (array) $attributes['rows'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
