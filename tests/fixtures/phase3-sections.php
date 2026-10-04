<?php
/**
 * LOCAL TEST FIXTURE ONLY — never run on staging or production.
 * Fills the AI section with the illustrative stories, people and settings used by the approved
 * CategoryDesktop / CategoryMobile designs (fictional, marked [Sample]) so the section template can
 * be compared with the frozen references: pinned top stories, a two-day feed with pagination,
 * analysis/explainers, guides, topics, the AI desk and section-scoped Most Read counts.
 * Run after phase2-content.php and phase3-article.php:
 *   wp eval-file tests/fixtures/phase3-sections.php --user=admin
 */
if ( ! function_exists( 'tdd_core_place' ) ) { echo "Activate techdosedaily-core first\n"; return; }
global $wpdb;

// Remove a previous run of this fixture only.
foreach ( get_posts( array( 'post_type' => 'post', 'numberposts' => -1, 'meta_key' => '_tdd_fixture', 'meta_value' => 'phase3', 'post_status' => 'any' ) ) as $p ) { wp_delete_post( $p->ID, true ); }

$user = static function ( string $login, string $name, string $title ) {
	$u = get_user_by( 'login', $login );
	if ( ! $u ) {
		$u = get_userdata( wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password( 24 ), 'display_name' => $name, 'role' => 'author', 'user_email' => $login . '@example.com' ) ) );
	}
	update_user_meta( $u->ID, 'tdd_title', $title );
	return $u->ID;
};
$priya  = get_user_by( 'login', 'priya-sample' )->ID;
$mira   = $user( 'mira-sample', 'Mira Castellanos', 'Editor, AI & Infrastructure' );
$daniel = $user( 'daniel-sample', 'Daniel Okafor', 'AI Agents & Enterprise' );
$arjun  = $user( 'arjun-sample', 'Arjun Mehta', 'Developer Tools Reporter' );
$leah   = $user( 'leah-sample', 'Leah Brennan', 'Cloud & Infrastructure Reporter' );
get_user_by( 'id', $mira )->set_role( 'editor' );

$ai = get_term_by( 'slug', 'ai', 'category' );
wp_update_term( $ai->term_id, 'category', array( 'description' => 'Models, agents, tools and research — what’s new in artificial intelligence, and what it means for the people who build and use it.' ) );
update_term_meta( $ai->term_id, 'tdd_desk_note', 'Reported and edited by journalists who cover AI full time. Vendor claims are labelled; every story lists its sources. [Sample]' );
update_term_meta( $ai->term_id, 'tdd_desk_members', array( $priya, $daniel, $mira ) );
update_term_meta( $ai->term_id, 'tdd_desk_url', home_url( '/editorial-standards/' ) );

$imgs = array_values( array_filter( array_map( static fn( $p ) => (int) get_post_thumbnail_id( $p ), get_posts( array( 'post_type' => 'post', 'numberposts' => 16, 'meta_key' => '_tdd_fixture', 'meta_value' => 1 ) ) ) ) );

$stories = array(
	// title, format, topic, author, hours ago, minutes, image?, deck
	array( 'Agents that use a computer are moving from demos to daily work', 'explainer', 'AI Agents', $daniel, 3, 11, true, 'What changes when an assistant can click, type and file the paperwork itself.' ),
	array( 'Regulators publish draft guidance on disclosing AI-generated content', 'news', 'AI Policy', $priya, 4, 3, true, '' ),
	array( 'Cloud providers race to add GPU capacity across Asia-Pacific regions', 'news', 'AI Infrastructure', $leah, 1, 3, true, '' ),
	array( 'Survey: most large companies now run at least one AI agent in production', 'news', 'Enterprise AI', $daniel, 2.5, 3, false, '' ),
	array( 'New study measures how often coding assistants introduce security bugs', 'explainer', 'AI Research', $arjun, 3.5, 5, false, '' ),
	array( 'Popular note-taking app adds on-device transcription and summaries', 'news', 'AI Tools', $arjun, 4.5, 2, false, '' ),
	array( 'Chipmaker outlines a next-generation data-center accelerator roadmap', 'news', 'AI Infrastructure', $leah, 23, 4, true, 'The roadmap promises more memory per chip and lower power per query.' ),
	array( 'Browser maker tests an agent that can fill forms with user approval', 'news', 'AI Agents', $daniel, 26, 3, false, '' ),
	array( 'Smaller open models close the gap on reasoning benchmarks, researchers say', 'analysis', 'AI Models', $priya, 29, 6, false, '' ),
	array( 'Bank pilots an internal assistant for compliance document review', 'news', 'Enterprise AI', $daniel, 31, 3, false, '' ),
	array( 'Popular code editor ships an extension API for local models', 'news', 'AI Coding', $arjun, 34, 4, true, 'Extensions can now call a model running on the developer’s own machine.' ),
	array( 'Speech model update cuts transcription errors in noisy rooms', 'news', 'AI Models', $priya, 36, 3, false, '' ),
	array( 'Open-source agent framework reaches its first stable release', 'news', 'AI Agents', $daniel, 47, 3, false, '' ),
	array( 'Research lab publishes a dataset for testing AI on spreadsheets', 'news', 'AI Research', $arjun, 50, 3, false, '' ),
	array( 'Design tool adds an assistant that explains layout decisions', 'news', 'AI Tools', $arjun, 53, 2, false, '' ),
	array( 'Inside the race to build data centers where the power grid can keep up', 'analysis', 'AI Infrastructure', $mira, 60, 16, true, 'Utilities, chipmakers and cloud providers are negotiating a new map of where computing gets built — and who pays for it.' ),
	array( 'What “open-weight” means, and why it isn’t open source', 'explainer', 'AI Models', $arjun, 70, 6, false, 'The licensing details that matter for companies building on these models.' ),
	array( 'How AI agents decide when to ask a human', 'explainer', 'AI Agents', $daniel, 80, 7, false, 'Checkpoints, confidence thresholds and the approval patterns teams are adopting.' ),
	array( 'The quiet cost of long context windows', 'analysis', 'AI Models', $leah, 90, 9, false, 'Bigger memory makes models more useful — and inference bills harder to predict.' ),
	array( 'Write better prompts for code review assistants', 'guide', 'AI Coding', $arjun, 200, 8, true, '' ),
	array( 'Add vector search to an existing Postgres app', 'guide', 'AI Coding', $arjun, 260, 10, true, '' ),
	array( 'Set up on-device AI features on a new laptop — and what to switch off', 'guide', 'AI Tools', $priya, 300, 6, true, '' ),
	array( 'Choosing between a hosted model and an open-weight one', 'guide', 'AI Models', $priya, 340, 9, false, '' ),
);
$ids = array();
foreach ( $stories as $i => [ $title, $fmt, $topic, $author, $hours, $mins, $image, $deck ] ) {
	$date = gmdate( 'Y-m-d H:i:s', (int) ( time() - $hours * HOUR_IN_SECONDS ) );
	$id   = wp_insert_post(
		array(
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_title'    => $title,
			'post_content'  => '<!-- wp:paragraph --><p>[Sample] Illustrative fixture text for local design testing. Not real news.</p><!-- /wp:paragraph -->',
			'post_author'   => $author,
			'post_date_gmt' => $date,
			'post_date'     => get_date_from_gmt( $date ),
			'post_category' => array( $ai->term_id ),
		)
	);
	update_post_meta( $id, '_tdd_fixture', 'phase3' );
	update_post_meta( $id, 'tdd_primary_section', $ai->term_id );
	if ( '' !== $deck ) { update_post_meta( $id, 'tdd_deck', $deck ); }
	wp_set_object_terms( $id, $fmt, 'tdd_format' );
	wp_set_object_terms( $id, array( $topic ), 'tdd_topic' );
	if ( $image && $imgs ) { set_post_thumbnail( $id, $imgs[ $i % count( $imgs ) ] ); }
	update_post_meta( $id, 'tdd_reading_time', $mins );
	if ( 'guide' === $fmt ) { update_post_meta( $id, 'tdd_updated_at', gmdate( DATE_ATOM, (int) ( time() - ( 30 + 20 * $i ) * HOUR_IN_SECONDS ) ) ); }
	$ids[ $title ] = $id;
}

// Pinned top stories: lead = the approved feature; secondaries = the three approved stories.
$lead = get_posts( array( 'post_type' => 'post', 'name' => 'major-ai-provider-unveils-a-reasoning-model-built-for-long-running-coding-agents', 'numberposts' => 1, 'fields' => 'ids' ) )[0];
tdd_core_place( $lead, 'section_lead', 1, $ai->term_id );
tdd_core_place( $ids['Agents that use a computer are moving from demos to daily work'], 'section_secondary', 1, $ai->term_id );
tdd_core_place( $ids['Regulators publish draft guidance on disclosing AI-generated content'], 'section_secondary', 2, $ai->term_id );
tdd_core_place( $ids['Cloud providers race to add GPU capacity across Asia-Pacific regions'], 'section_secondary', 3, $ai->term_id );

// Section-scoped Most Read (7-day window), synthetic local counts only.
foreach ( array( 'How AI agents decide when to ask a human' => 520, 'Inside the race to build data centers where the power grid can keep up' => 430, 'Set up on-device AI features on a new laptop — and what to switch off' => 300, 'Choosing between a hosted model and an open-weight one' => 260, 'The quiet cost of long context windows' => 190 ) as $t => $v ) {
	$wpdb->insert( $wpdb->prefix . 'tdd_view_buckets', array( 'post_id' => $ids[ $t ], 'bucket' => gmdate( 'Y-m-d H:00:00', time() - 2 * DAY_IN_SECONDS ), 'views' => $v ) );
}
foreach ( $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_tdd_%'" ) as $o ) { delete_option( $o ); }
echo 'seeded ' . count( $ids ) . " AI section stories\n";
