<?php
/**
 * LOCAL TEST FIXTURE — local, or staging via deployment/staging-samples.sh (owner decision 2026-10-05). Never production.
 * Adds the illustrative Cybersecurity / Developer / Startups / Big Tech / Tech stories used by the
 * approved homepage section row and Editor's Picks (fictional, marked [Sample]).
 * Run after phase3-sections.php: wp eval-file tests/fixtures/phase3-home.php --user=admin
 */
if ( ! function_exists( 'tdd_core_place' ) ) { echo "Activate techdosedaily-core first\n"; return; }
foreach ( get_posts( array( 'post_type' => 'post', 'numberposts' => -1, 'meta_key' => '_tdd_fixture', 'meta_value' => 'phase3-home', 'post_status' => 'any' ) ) as $p ) { wp_delete_post( $p->ID, true ); }
$imgs  = array_values( array_filter( array_map( static fn( $p ) => (int) get_post_thumbnail_id( $p ), get_posts( array( 'post_type' => 'post', 'numberposts' => 16, 'meta_key' => '_tdd_fixture', 'meta_value' => 1 ) ) ) ) );
$u     = static fn( $l ) => get_user_by( 'login', $l )->ID;
$items = array(
	// section, format, title, deck, author, hours ago, minutes, image, severity
	array( 'cybersecurity', 'guide', 'VPN appliance flaw is being exploited. Here’s what admins should do today', 'Update to the fixed release and rotate credentials on exposed devices.', 'priya-sample', 10, 5, true, 'Patch now · Critical' ),
	array( 'cybersecurity', 'news', 'Ransomware groups shift to targeting backup systems first', '', 'leah-sample', 15, 4, false, '' ),
	array( 'cybersecurity', 'explainer', 'How attackers abuse OAuth consent screens — and how to spot it', '', 'arjun-sample', 27, 6, false, '' ),
	array( 'cybersecurity', 'news', 'Patch roundup: the updates to install this weekend', '', 'leah-sample', 30, 4, false, '' ),
	array( 'developer', 'analysis', 'AI code review is now the default on big repos. Maintainers say the hard part is trust', 'Teams are writing review policies for assistants the same way they once did for linters and CI.', 'arjun-sample', 11, 9, true, '' ),
	array( 'developer', 'guide', 'Add vector search to an existing Postgres app in an afternoon', '', 'arjun-sample', 13, 10, true, '' ),
	array( 'developer', 'news', 'Language server update brings faster indexing to large monorepos', '', 'arjun-sample', 14, 3, true, '' ),
	array( 'startups', 'news', 'Seed funding for AI infrastructure climbs as investors chase tooling', '', 'daniel-sample', 12, 4, true, '' ),
	array( 'tech', 'review', 'A month with an AI-first laptop: fast, quiet and still missing the killer app', 'Battery life impresses; the on-device assistant is uneven.', 'leah-sample', 50, 8, true, '' ),
	array( 'startups', 'analysis', 'The small teams shipping profitable AI products without raising a round', '', 'daniel-sample', 60, 12, false, '' ),
);
$ids = array();
foreach ( $items as $i => [ $sec, $fmt, $title, $deck, $author, $hours, $mins, $image, $sev ] ) {
	$cat  = get_term_by( 'slug', $sec, 'category' );
	$date = gmdate( 'Y-m-d H:i:s', (int) ( time() - $hours * HOUR_IN_SECONDS ) );
	$id   = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => '<!-- wp:paragraph --><p>[Sample] Illustrative fixture text for local design testing. Not real news.</p><!-- /wp:paragraph -->', 'post_author' => $u( $author ), 'post_date_gmt' => $date, 'post_date' => get_date_from_gmt( $date ), 'post_category' => array( $cat->term_id ) ) );
	update_post_meta( $id, '_tdd_fixture', 'phase3-home' );
	update_post_meta( $id, 'tdd_primary_section', $cat->term_id );
	update_post_meta( $id, 'tdd_reading_time', $mins );
	if ( $deck ) { update_post_meta( $id, 'tdd_deck', $deck ); }
	if ( $sev ) { update_post_meta( $id, 'tdd_severity', $sev ); }
	wp_set_object_terms( $id, $fmt, 'tdd_format' );
	if ( $image && $imgs ) { set_post_thumbnail( $id, $imgs[ ( $i * 5 ) % count( $imgs ) ] ); }
	$ids[] = $id;
}
// Editor's Picks as in the approved homepage: long read, review, explainer, startups analysis.
$pick = static fn( $name ) => get_posts( array( 'post_type' => 'post', 'name' => $name, 'numberposts' => 1, 'fields' => 'ids' ) )[0] ?? 0;
tdd_core_place( $pick( 'inside-the-race-to-build-data-centers-where-the-power-grid-can-keep-up' ), 'editors_pick', 1 );
tdd_core_place( $ids[8], 'editors_pick', 2 );
tdd_core_place( $pick( 'what-open-weight-means-and-why-it-isnt-open-source' ), 'editors_pick', 3 );
tdd_core_place( $ids[9], 'editors_pick', 4 );
tdd_core_place( $pick( 'agents-that-use-a-computer-are-moving-from-demos-to-daily-work' ), 'ai_band_lead', 1 );
echo 'seeded ' . count( $ids ) . " homepage section stories\n";
