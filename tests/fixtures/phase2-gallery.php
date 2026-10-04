<?php
/**
 * LOCAL TEST FIXTURE ONLY. Creates a "Phase 2 components" page that renders every Phase 2 block
 * with fixture data, for side-by-side comparison with the design-system component previews.
 * Run after phase2-content.php: wp eval-file tests/fixtures/phase2-gallery.php
 */
$content = <<<'HTML'
<!-- wp:html --><style>@media (max-width: 767px){.tdd-gallery .tdd-grid{display:block}.tdd-gallery .tdd-grid>*{margin-bottom:40px}}</style><div class="tdd-gallery"><!-- /wp:html -->
<!-- wp:tdd/trending /-->
<!-- wp:html --><div style="height:32px"></div><!-- /wp:html -->
<!-- wp:tdd/breadcrumbs /-->
<!-- wp:html --><div class="tdd-grid" style="margin-top:24px;align-items:start"><div style="grid-column:span 8"><!-- /wp:html -->
<!-- wp:tdd/story-card {"variant":"feature","source":"placement","placement":"homepage_lead","position":1} /-->
<!-- wp:html --></div><div style="grid-column:span 4"><!-- /wp:html -->
<!-- wp:tdd/story-list {"variant":"compact","source":"placement","placement":"homepage_secondary","count":4} /-->
<!-- wp:html --></div></div><div class="tdd-grid" style="margin-top:80px;align-items:start"><div style="grid-column:span 8"><!-- /wp:html -->
<!-- wp:tdd/latest-feed {"count":7} /-->
<!-- wp:tdd/pagination /-->
<!-- wp:html --></div><div style="grid-column:span 4"><!-- /wp:html -->
<!-- wp:tdd/most-read /-->
<!-- wp:html --></div></div><div style="margin-top:80px"><!-- /wp:html -->
<!-- wp:tdd/section-heading {"title":"Editor’s Picks","description":"Deeper reporting worth your time this week"} /-->
<!-- wp:html --><div class="tdd-grid" style="row-gap:32px"><div style="grid-column:span 4"><!-- /wp:html -->
<!-- wp:tdd/story-card {"variant":"large","source":"placement","placement":"editors_pick","position":1} /-->
<!-- wp:html --></div><div style="grid-column:span 4"><!-- /wp:html -->
<!-- wp:tdd/story-card {"variant":"medium","source":"placement","placement":"editors_pick","position":2} /-->
<!-- wp:html --></div><div style="grid-column:span 4"><!-- /wp:html -->
<!-- wp:tdd/story-card {"variant":"medium","source":"placement","placement":"editors_pick","position":3} /-->
<!-- wp:html --></div></div></div><div class="tdd-grid" style="margin-top:80px;align-items:start"><div style="grid-column:span 7"><!-- /wp:html -->
<!-- wp:tdd/daily-brief {"linkUrl":"#"} /-->
<!-- wp:html --></div><div style="grid-column:span 5;display:flex"><!-- /wp:html -->
<!-- wp:tdd/newsletter-cta /-->
<!-- wp:html --></div></div></div><!-- /wp:html -->
HTML;
$page = get_page_by_path( 'phase-2-components' );
$args = array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Phase 2 components (local fixture)', 'post_name' => 'phase-2-components', 'post_content' => $content );
if ( $page ) { $args['ID'] = $page->ID; }
$id = wp_insert_post( $args );
update_post_meta( $id, '_tdd_fixture', 1 );
// Posts page for "Latest".
$latest = get_page_by_path( 'latest' );
$lid = $latest ? $latest->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Latest', 'post_name' => 'latest' ) );
$home = get_page_by_path( 'home' );
$hid  = $home ? $home->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Home', 'post_name' => 'home' ) );
update_option( 'page_on_front', $hid ); update_option( 'page_for_posts', $lid ); update_option( 'show_on_front', 'page' );
// Trending menu (fixture).
$menu = wp_get_nav_menu_object( 'Trending' ); if ( $menu ) { wp_delete_nav_menu( $menu->term_id ); }
$mid = wp_create_nav_menu( 'Trending' );
foreach ( array( 'OpenAI', 'Claude', 'Gemini', 'NVIDIA', 'Microsoft', 'Zero-day patches', 'AI agents' ) as $t ) {
	wp_update_nav_menu_item( $mid, 0, array( 'menu-item-title' => $t, 'menu-item-url' => '#topic-' . sanitize_title( $t ), 'menu-item-status' => 'publish' ) );
}
$locs = get_theme_mod( 'nav_menu_locations', array() ); $locs['trending'] = $mid; set_theme_mod( 'nav_menu_locations', $locs );
update_option( 'timezone_string', 'Asia/Kolkata' );
echo get_permalink( $id ) . "\n";
