<?php
/**
 * tdd/breadcrumbs — the visible trail. BreadcrumbList schema is emitted by TechDoseDaily Core (Phase 6) from the same data.
 *
 * @package techdosedaily
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

echo tdd_breadcrumbs_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
