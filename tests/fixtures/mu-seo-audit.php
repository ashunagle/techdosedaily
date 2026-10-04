<?php
/**
 * LOCAL TEST FIXTURE ONLY (copy into wp-content/mu-plugins for the Phase 6 SEO tests).
 * Request switches (local audit convenience; never on a real site):
 *   ?tdd_audit=1        treat [Sample] fixture content as real content (indexable, in sitemaps)
 *   ?tdd_yoast_stub=1   load a minimal Yoast stand-in: defines WPSEO_VERSION and prints a
 *                       "yoast-schema-graph" script unless `wpseo_json_ld_output` returns false —
 *                       the documented Yoast switch. Used only until real Yoast is installed.
 */
// phpcs:disable WordPress.Security.NonceVerification
if ( ! empty( $_GET['tdd_audit'] ) ) {
	add_filter( 'tdd_core_hide_fixtures', '__return_false' );
}
// Audit mode and normal mode share sitemap URLs: never serve a cached sitemap from the other mode.
add_filter( 'wpseo_enable_xml_sitemap_transient_caching', '__return_false' );
if ( ! empty( $_GET['tdd_yoast_stub'] ) && ! defined( 'WPSEO_VERSION' ) ) {
	define( 'WPSEO_VERSION', 'stub' );
	add_action(
		'wp_head',
		static function () {
			$data = apply_filters( 'wpseo_json_ld_output', array( '@context' => 'https://schema.org', '@graph' => array( array( '@type' => 'WebPage', '@id' => 'stub' ) ) ), 'yoast-stub' );
			if ( $data ) {
				echo '<script type="application/ld+json" class="yoast-schema-graph">' . wp_json_encode( $data ) . '</script>';
			}
		},
		1
	);
}
