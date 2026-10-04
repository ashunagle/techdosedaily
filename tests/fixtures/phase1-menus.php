<?php
/**
 * LOCAL TEST FIXTURE ONLY — never run on staging/production.
 * Recreates the footer/header link sets shown in the approved designs so screenshots
 * can be compared with 03-Approved. Social URLs are example.com placeholders.
 * Run: wp eval-file tests/fixtures/phase1-menus.php
 */
$make = function ( string $name, string $location, array $links ) {
	$menu = wp_get_nav_menu_object( $name );
	if ( $menu ) { wp_delete_nav_menu( $menu->term_id ); }
	$id = wp_create_nav_menu( $name );
	foreach ( $links as $label => $target ) {
		$args = array( 'menu-item-title' => $label, 'menu-item-status' => 'publish' );
		if ( is_array( $target ) ) {
			[ $type, $ref ] = $target;
			if ( 'cat' === $type ) {
				$t = get_term_by( 'name', $ref, 'category' );
				$args += array( 'menu-item-type' => 'taxonomy', 'menu-item-object' => 'category', 'menu-item-object-id' => $t->term_id );
			} else {
				$p = get_page_by_path( $ref );
				$args += array( 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $p->ID );
			}
		} else {
			$args += array( 'menu-item-type' => 'custom', 'menu-item-url' => $target );
		}
		wp_update_nav_menu_item( $id, 0, $args );
	}
	$locs = get_theme_mod( 'nav_menu_locations', array() );
	$locs[ $location ] = $id;
	set_theme_mod( 'nav_menu_locations', $locs );
};
$cats = fn( $names ) => array_combine( $names, array_map( fn( $n ) => array( 'cat', $n ), $names ) );
$make( 'Header sections', 'primary', $cats( array( 'AI', 'Tech', 'Software', 'Cybersecurity', 'Startups', 'Big Tech', 'Cloud', 'Developer', 'Guides' ) ) );
$make( 'Footer categories', 'footer-categories', $cats( array( 'AI', 'Tech', 'Software', 'Cybersecurity', 'Startups', 'Cloud', 'Developer', 'Guides' ) ) );
$make( 'Footer company', 'footer-company', array( 'About' => array( 'page', 'about' ), 'Editorial standards' => array( 'page', 'editorial-standards' ), 'Contact' => array( 'page', 'contact' ), 'Careers' => '#careers-placeholder' ) );
$make( 'Footer policies', 'footer-policies', array( 'Privacy' => array( 'page', 'privacy-policy' ), 'Terms' => array( 'page', 'terms' ), 'Source Policy' => array( 'page', 'source-policy' ), 'Corrections' => array( 'page', 'corrections-policy' ), 'AI use policy' => array( 'page', 'ai-use-policy' ) ) );
$make( 'Footer newsletter', 'footer-newsletter', array( 'Daily Tech Brief' => array( 'page', 'newsletter' ), 'AI News Today' => '#ai-news-today-placeholder', 'Subscribe →' => array( 'page', 'newsletter' ) ) );
$make( 'Footer follow', 'footer-follow', array( 'LinkedIn' => 'https://example.com/linkedin', 'X' => 'https://example.com/x', 'WhatsApp' => 'https://example.com/whatsapp', 'YouTube' => 'https://example.com/youtube' ) );
$make( 'Footer legal', 'footer-legal', array( 'Privacy' => array( 'page', 'privacy-policy' ), 'Terms' => array( 'page', 'terms' ), 'Source Policy' => array( 'page', 'source-policy' ), 'Cookie settings' => '#cookie-settings-placeholder' ) );
echo "menus seeded\n";
