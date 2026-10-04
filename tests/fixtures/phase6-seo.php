<?php
/**
 * LOCAL TEST FIXTURE ONLY — never run on staging or production.
 * Two extra [Sample] stories for the Phase 6 schema tests:
 *   - a Sponsored story with a sample sponsor (AdvertiserContentArticle + sponsor);
 *   - a bare News story: no editor, image, topics, corrections or update (omission rules).
 * Run: wp eval-file tests/fixtures/phase6-seo.php --user=admin
 */
foreach ( get_posts( array( 'post_type' => 'post', 'numberposts' => -1, 'post_status' => 'any', 'meta_key' => '_tdd_fixture', 'meta_value' => 'phase6' ) ) as $p ) {
	wp_delete_post( $p->ID, true );
}
$author = get_user_by( 'login', 'leah-sample' );
$make   = static function ( string $title, string $section, string $format, array $meta ) use ( $author ) {
	$cat = get_term_by( 'slug', $section, 'category' );
	$id  = wp_insert_post(
		wp_slash(
			array(
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_title'    => $title,
				'post_author'   => $author ? $author->ID : 1,
				'post_content'  => '<!-- wp:paragraph --><p>[Sample] Illustrative fixture text for local schema testing. Not real news.</p><!-- /wp:paragraph -->',
				'post_category' => array( $cat->term_id ),
			)
		)
	);
	wp_set_object_terms( $id, $format, 'tdd_format' );
	update_post_meta( $id, 'tdd_primary_section', $cat->term_id );
	update_post_meta( $id, '_tdd_fixture', 'phase6' );
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	echo "{$format}: {$id} " . get_permalink( $id ) . "\n";
};
$make( '[Sample] Sponsored explainer: what a managed vector database does', 'developer', 'sponsored', array( 'tdd_sponsor' => 'Example Sponsor Co [Sample]', 'tdd_deck' => 'Produced separately from the newsroom. [Sample]' ) );
$make( '[Sample] A short news story with no image, editor or topics', 'software', 'news', array() );
