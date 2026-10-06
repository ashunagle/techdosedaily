<?php
/**
 * Tech Dose Daily theme bootstrap.
 *
 * Presentation lives in this theme. Modules in inc/ are kept independent so the
 * editorial data model (meta, taxonomies, schema, forms) can move to a plugin later
 * without touching templates.
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

define( 'TDD_VERSION', '0.9.3' );
define( 'TDD_DIR', get_template_directory() );
define( 'TDD_URI', get_template_directory_uri() );

foreach ( array( 'helpers', 'setup', 'assets', 'menus', 'template-tags', 'components', 'article', 'archive', 'home', 'author', 'search', 'static', 'pages', 'blocks', 'performance' ) as $tdd_module ) {
	require_once TDD_DIR . "/inc/{$tdd_module}.php";
}
