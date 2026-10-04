<?php
/**
 * tdd/faq — NewsletterFAQ.
 *
 * @package techdosedaily
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

defined( 'ABSPATH' ) || exit;

echo tdd_faq( (string) $attributes['title'], (array) $attributes['rows'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
