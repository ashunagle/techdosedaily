<?php
/**
 * tdd/contact-cta — ContactCTA (placed at the end of the page by tdd/static-page).
 *
 * @package techdosedaily
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

defined( 'ABSPATH' ) || exit;

echo tdd_contact_cta( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
