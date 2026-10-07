<?php
/**
 * Phase 6: Yoast integration hooks, exercised without Yoast (the documented Yoast filters are
 * called exactly as Yoast calls them). Run: wp eval-file tests/seo/seo_hooks.php
 */
$R  = array();
$owner_before = get_option( 'tdd_core_schema_owner', 'yoast' ); // restored at the end
$ok = static function ( $n, $c, $e = '' ) use ( &$R ) { $R[] = ( $c ? 'PASS ' : 'FAIL ' ) . $n . ( ! $c && '' !== $e ? ' — ' . wp_json_encode( $e ) : '' ); };
$go = static function ( array $q ) { global $wp_query, $wp_the_query; $wp_the_query = $wp_query = new WP_Query( $q ); }; // phpcs:ignore

$story = get_page_by_path( 'run-a-local-ai-coding-assistant-without-sending-code-off-your-machine', OBJECT, 'post' );
$go( array( 'p' => $story->ID ) );
$deck = get_post_meta( $story->ID, 'tdd_deck', true );
$ok( 'meta description: empty Yoast value → deck', apply_filters( 'wpseo_metadesc', '' ) === wp_html_excerpt( $deck, 300, '…' ), apply_filters( 'wpseo_metadesc', '' ) );
$ok( 'meta description: explicit Yoast value is never overwritten', 'Explicit editor description' === apply_filters( 'wpseo_metadesc', 'Explicit editor description' ) );
$ok( 'og:description fallback → deck', '' !== apply_filters( 'wpseo_opengraph_desc', '' ) );
$ok( 'twitter:description fallback → deck', '' !== apply_filters( 'wpseo_twitter_description', '' ) );

$bare = get_page_by_path( 'sample-a-short-news-story-with-no-image-editor-or-topics', OBJECT, 'post' );
$go( array( 'p' => $bare->ID ) );
$ok( 'no deck → no invented description', '' === apply_filters( 'wpseo_metadesc', '' ) );

$page = get_page_by_path( 'editorial-standards' );
$go( array( 'page_id' => $page->ID ) );
$ok( 'page: description fallback → intro', str_starts_with( apply_filters( 'wpseo_metadesc', '' ), 'How Tech Dose Daily reports' ) );

$go( array( 'tdd_topic' => 'ai-policy' ) );
$r = apply_filters( 'wpseo_robots_array', array( 'index' => 'index', 'follow' => 'follow' ) );
$ok( 'Yoast robots: thin topic → noindex, follow', 'noindex' === $r['index'] && 'follow' === $r['follow'], $r );
$go( array( 'tdd_topic' => 'ai-agents' ) );
$r = apply_filters( 'wpseo_robots_array', array( 'index' => 'index', 'follow' => 'follow' ) );
if ( '0' === (string) get_option( 'blog_public' ) ) {
	// Staging is noindex: Yoast itself marks every view noindex. What matters is that Core adds no reason.
	$ok( 'Core adds no noindex reason to a topic with enough stories (site-wide noindex comes from Yoast on staging)', '' === tdd_core_noindex_reason(), tdd_core_noindex_reason() );
} else {
	$ok( 'Yoast robots: topic with enough stories untouched', 'index' === $r['index'], $r );
}

$ids = apply_filters( 'wpseo_exclude_from_sitemap_by_post_ids', array() );
$ok( 'Yoast sitemap: fixture stories excluded', in_array( $story->ID, $ids, true ) );
$terms = apply_filters( 'wpseo_exclude_from_sitemap_by_term_ids', array() );
$ok( 'Yoast sitemap: thin topics excluded', in_array( (int) get_term_by( 'slug', 'ai-policy', 'tdd_topic' )->term_id, $terms, true ) );
$ok( 'Yoast sitemap: attachments excluded', true === apply_filters( 'wpseo_sitemap_exclude_post_type', false, 'attachment' ) );

update_option( 'tdd_core_schema_owner', 'core' );
$ok( 'owner core → wpseo_json_ld_output returns false', false === apply_filters( 'wpseo_json_ld_output', array( 'x' ), 'test' ) );
update_option( 'tdd_core_schema_owner', 'yoast' );
$ok( 'owner yoast → Yoast prints when Yoast is active, Core when it is not (never neither)', ( tdd_core_yoast_active() ? 'yoast' : 'core' ) === tdd_core_schema_owner() );
$ok( 'owner core → Core prints', ( static function () { update_option( 'tdd_core_schema_owner', 'core' ); $v = tdd_core_schema_owner(); update_option( 'tdd_core_schema_owner', 'yoast' ); return $v; } )() === 'core' );
$ok( 'invalid saved value falls back to yoast', 'yoast' === ( static function () { update_option( 'tdd_core_schema_owner', 'bogus' ); $v = tdd_core_schema_setting(); update_option( 'tdd_core_schema_owner', 'yoast' ); return $v; } )() );

update_option( 'tdd_core_schema_owner', $owner_before );
echo 'Yoast active: ' . ( tdd_core_yoast_active() ? WPSEO_VERSION : 'no' ) . "\n" . implode( "\n", $R ) . "\n";
