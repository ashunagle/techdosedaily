<?php
/**
 * tdd/newsletter-cta — approved navy panel + sign-up form (states: newsletter.js).
 *
 * @package techdosedaily
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

echo tdd_newsletter_cta( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
