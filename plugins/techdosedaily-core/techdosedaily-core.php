<?php
/**
 * Plugin Name:       TechDoseDaily Core
 * Plugin URI:        https://techdosedaily.com/
 * Description:       Publication data model for Tech Dose Daily — sections, story formats, topics, editorial metadata, placements, newsletter adapter, forms and structured data. Theme-independent: switching themes never loses this data.
 * Version:           0.9.4
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Tech Dose Daily
 * License:           GPL-2.0-or-later
 * Text Domain:       techdosedaily-core
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

define( 'TDD_CORE_VERSION', '0.9.4' );
define( 'TDD_CORE_FILE', __FILE__ );
define( 'TDD_CORE_DIR', __DIR__ );

$tdd_core_modules = array(
	'sections',     // Top-level section URLs, primary section, reserved slugs.
	'taxonomies',   // tdd_format, tdd_topic.
	'meta',         // Registered post + user meta (single source of truth for fields).
	'editorial',    // Breaking state, updates, corrections, sources, reading time.
	'placements',   // Editorial placement table + API + REST.
	'popularity',   // Cookieless view counts for Most Read.
	'api',          // Public template functions the theme calls (tdd_core_*).
	'newsletter/interface-provider',
	'newsletter/class-result',
	'newsletter/class-null-provider',
	'newsletter/class-mailpoet-provider',
	'newsletter/newsletter',
	'security',     // Shared nonce / honeypot / timing / throttle helpers for public forms.
	'hardening',    // XML-RPC, app passwords, REST users, login errors, headers, uploads, image metadata.
	'pages',        // Static page fields (policies, About, Newsletter, Contact).
	'contact',      // Contact routes, settings and form processing (REST + no-JS PRG).
	'seo',          // Description fallback, noindex rules, sitemap exclusions (Yoast + core).
	'schema/schema', // Single JSON-LD owner + the Core graph (schema/graph.php).
	'performance',  // Page-cache headers (editorial-aware TTL, LiteSpeed), purges, WebP sub-sizes.
	'admin/admin',            // Newsroom admin: menu, story panels, media fields.
	'admin/forms',            // Shared admin form widgets.
	'admin/placements-admin', // Placement editor.
	'admin/sections-admin',   // Section settings on the Sections screen.
	'admin/users-admin',      // Author profile fields.
	'admin/reporters-admin',  // Editors: About listing + Featured Reporting for reporters (no edit_users).
	'admin/settings',         // Site settings + launch readiness.
);
foreach ( $tdd_core_modules as $tdd_core_module ) {
	require_once TDD_CORE_DIR . "/includes/{$tdd_core_module}.php";
}

register_activation_hook( __FILE__, 'tdd_core_activate' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/** Create tables, seed formats, flush rules. Idempotent. */
function tdd_core_activate(): void {
	tdd_core_placements_install();
	tdd_core_views_install();
	tdd_core_register_taxonomies();
	tdd_core_seed_formats();
	tdd_core_seed_sections();
	tdd_core_sections_rewrite_rules();
	flush_rewrite_rules();
}
