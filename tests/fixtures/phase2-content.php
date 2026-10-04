<?php
/**
 * LOCAL TEST FIXTURE ONLY — never run on staging or production.
 * Seeds illustrative stories (the same sample headlines used in the approved designs, all
 * marked "[Sample]" in the excerpt), images, topics, placements and synthetic view counts
 * so Phase 2 components can be rendered and compared with the design system.
 * Run: wp eval-file tests/fixtures/phase2-content.php
 */
if ( ! function_exists( 'tdd_core_place' ) ) { echo "Activate techdosedaily-core first\n"; return; }
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// Clean previous fixture run.
foreach ( get_posts( array( 'post_type' => 'post', 'numberposts' => -1, 'meta_key' => '_tdd_fixture', 'post_status' => 'any' ) ) as $p ) { wp_delete_post( $p->ID, true ); }
global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->prefix}tdd_placements" ); $wpdb->query( "DELETE FROM {$wpdb->prefix}tdd_view_buckets" );

$author = get_user_by( 'login', 'priya-sample' );
if ( ! $author ) {
	$uid = wp_insert_user( array( 'user_login' => 'priya-sample', 'user_pass' => wp_generate_password( 24 ), 'display_name' => 'Priya Raman', 'role' => 'author', 'user_email' => 'priya-sample@example.com' ) );
	$author = get_userdata( $uid );
}
update_user_meta( $author->ID, 'tdd_title', 'Senior AI Correspondent (sample)' );

$img = array();
foreach ( glob( __DIR__ . '/images/*.webp' ) as $file ) {
	$name = basename( $file, '.webp' );
	$existing = get_posts( array( 'post_type' => 'attachment', 'name' => $name, 'numberposts' => 1 ) );
	if ( $existing ) { $img[ $name ] = $existing[0]->ID; continue; }
	$tmp = wp_tempnam( $name ); copy( $file, $tmp );
	$id = media_handle_sideload( array( 'name' => "$name.webp", 'tmp_name' => $tmp ), 0, "Sample editorial image ($name)" );
	if ( ! is_wp_error( $id ) ) { $img[ $name ] = $id; update_post_meta( $id, 'tdd_credit', 'TDD sample graphic' ); update_post_meta( $id, '_wp_attachment_image_alt', "Sample image: $name" ); }
}

$stories = array(
	// section, format, title, deck, image, minutes ago, topics
	array( 'ai', 'analysis', 'Major AI provider unveils a reasoning model built for long-running coding agents', 'What it changes for developers, and which claims still need independent testing.', 'ai-console', 30, array( 'AI Models', 'AI Coding' ) ),
	array( 'cybersecurity', 'news', 'Browser makers patch an actively exploited flaw on desktop and mobile', 'Update now: the fix is rolling out across all major browsers.', 'sec-browser-update', 60, array( 'Zero-day patches' ) ),
	array( 'ai', 'news', 'Open-weight model family adds a one-million-token context window', 'The update ships with new evaluation results and a smaller variant sized for laptops.', 'ai-benchmark', 85, array( 'AI Models' ) ),
	array( 'cloud', 'news', 'A cloud provider adds data-center capacity in two new regions', 'The expansion targets AI workloads and lowers latency for nearby customers.', 'cloud-capacity', 120, array( 'AI Infrastructure' ) ),
	array( 'developer', 'news', 'Code-hosting platform adds a review workflow for AI-generated pull requests', 'Reviewers can now see which changes an assistant proposed, and why.', 'dev-pr-review', 150, array( 'AI Coding' ) ),
	array( 'startups', 'news', 'Developer-tools startup raises a Series B to build agent infrastructure', 'The round values the company at an undisclosed amount.', 'startup-funding', 200, array( 'AI Agents' ) ),
	array( 'ai', 'explainer', 'What a “token budget” is, and why your AI bill depends on it', 'A plain-language guide to how usage-based pricing actually adds up.', 'ai-agents', 260, array( 'AI Infrastructure' ) ),
	array( 'software', 'news', 'Popular database ships native vector search in its latest release', 'The feature removes the need for a separate vector store for many teams.', 'sw-release', 330, array( 'Developer Tools' ) ),
	array( 'guides', 'guide', 'Run a local AI coding assistant without sending code off your machine', 'Choosing a model that fits your laptop and keeping it offline.', 'dev-terminal', 400, array( 'AI Coding' ) ),
	array( 'big-tech', 'analysis', 'Benchmarks are saturating. Labs are quietly changing how they report progress', 'Why headline scores tell you less every quarter, and what to read instead.', 'policy-doc', 480, array( 'AI Models' ) ),
	array( 'cybersecurity', 'explainer', 'How passkeys replace passwords, and where they still fall short', 'A practical look at what changes for everyday accounts.', 'auth-code', 560, array( 'Security' ) ),
	array( 'tech', 'review', 'A week with a lightweight laptop built around an on-device AI chip', 'Battery life is the headline; the AI features are mostly promise.', 'device-laptop', 700, array( 'Hardware' ) ),
	array( 'guides', 'guide', 'Back up your work the 3-2-1 way, without paying for three services', 'A simple setup that survives a lost laptop and a cloud outage.', 'backup-321', 900, array( 'How-to' ) ),
	array( 'cloud', 'analysis', 'Why data-center power is becoming the AI industry’s real bottleneck', 'Grid capacity, not chips, is now the slowest part of the build-out.', 'cloud-datacenter', 1300, array( 'AI Infrastructure' ) ),
	array( 'startups', 'news', 'Workplace-software startup opens a second office as its team doubles', 'The company says it has been profitable for two years.', 'startup-flatlay', 1600, array( 'Funding' ) ),
	array( 'cybersecurity', 'news', 'Researchers detail a critical flaw in a widely deployed VPN appliance', 'Administrators are advised to apply the vendor update.', 'sec-advisory', 2000, array( 'Zero-day patches' ) ),
);
$ids = array();
foreach ( $stories as $i => [ $sec, $fmt, $title, $deck, $image, $mins, $topics ] ) {
	$cat = get_term_by( 'slug', $sec, 'category' );
	$date = gmdate( 'Y-m-d H:i:s', time() - $mins * 60 );
	$body = '<!-- wp:paragraph --><p>[Sample] Illustrative fixture text for local design testing. ' . str_repeat( 'This paragraph stands in for reported copy and is not real news. ', 0 === $i % 2 ? 140 : 40 ) . '</p><!-- /wp:paragraph -->';
	$id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $body, 'post_excerpt' => '[Sample] ' . $deck, 'post_author' => $author->ID, 'post_date_gmt' => $date, 'post_date' => get_date_from_gmt( $date ), 'post_category' => array( $cat->term_id ) ) );
	update_post_meta( $id, '_tdd_fixture', 1 );
	update_post_meta( $id, 'tdd_deck', $deck );
	update_post_meta( $id, 'tdd_primary_section', $cat->term_id );
	wp_set_object_terms( $id, $fmt, 'tdd_format' );
	wp_set_object_terms( $id, $topics, 'tdd_topic' );
	if ( isset( $img[ $image ] ) ) { set_post_thumbnail( $id, $img[ $image ] ); }
	$ids[] = $id;
}
update_post_meta( $ids[1], 'tdd_breaking_until', gmdate( DATE_ATOM, time() + 3 * HOUR_IN_SECONDS ) );

// Placements.
tdd_core_place( $ids[0], 'homepage_lead', 1 );
foreach ( array( 2, 3, 4, 5 ) as $p => $i ) { tdd_core_place( $ids[ $i ], 'homepage_secondary', $p + 1 ); }
foreach ( array( 9, 13, 6, 10 ) as $p => $i ) { tdd_core_place( $ids[ $i ], 'editors_pick', $p + 1 ); }
foreach ( array( 0, 1, 2, 3, 4, 7, 8, 5 ) as $p => $i ) { tdd_core_place( $ids[ $i ], 'daily_brief', $p + 1 ); }
$ai = get_term_by( 'slug', 'ai', 'category' );
tdd_core_place( $ids[0], 'section_lead', 1, $ai->term_id );

// Synthetic view counts (local only) so Most Read renders.
// Spread over the last 3 hours (inside the 24h homepage window) plus older buckets for the 7/30-day windows.
foreach ( array( 6 => 940, 9 => 610, 8 => 455, 1 => 380, 10 => 210, 0 => 190 ) as $i => $v ) {
	foreach ( array( 0, 1, 2 ) as $h ) {
		$wpdb->insert( $wpdb->prefix . 'tdd_view_buckets', array( 'post_id' => $ids[ $i ], 'bucket' => gmdate( 'Y-m-d H:00:00', time() - $h * HOUR_IN_SECONDS ), 'views' => (int) ceil( $v / 3 ) ) );
	}
}
foreach ( array( 13 => 820, 3 => 640, 11 => 300, 2 => 280, 4 => 150 ) as $i => $v ) {
	$wpdb->insert( $wpdb->prefix . 'tdd_view_buckets', array( 'post_id' => $ids[ $i ], 'bucket' => gmdate( 'Y-m-d H:00:00', time() - 3 * DAY_IN_SECONDS ), 'views' => $v ) );
}
echo 'seeded ' . count( $ids ) . " sample stories\n";
