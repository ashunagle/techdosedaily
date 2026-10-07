<?php
/**
 * Removes the [Sample] review dataset loaded on staging by deployment/staging-samples.sh.
 * Deletes every post/page with _tdd_fixture meta, the sample images, the *-sample users, their
 * placements and view counts, topics the samples created, and the AI desk settings phase3-sections
 * wrote. Gates D1 (fixtures), U1 (sample users) and E2 (sample content) must then pass.
 *
 * Run: wp eval-file tests/fixtures/remove-samples.php --user=<admin login>
 */
global $wpdb;

$ids = array_map( 'intval', get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_tdd_fixture' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
if ( $ids ) {
	$in = implode( ',', $ids );
	$wpdb->query( "DELETE FROM {$wpdb->prefix}tdd_placements WHERE post_id IN ($in)" ); // phpcs:ignore WordPress.DB
	$wpdb->query( "DELETE FROM {$wpdb->prefix}tdd_view_buckets WHERE post_id IN ($in)" ); // phpcs:ignore WordPress.DB
}
foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}
echo 'sample posts deleted: ', count( $ids ), "\n";

// Sample images: uploaded by phase2-content.php as "Sample editorial image (<name>)" with the credit
// "TDD sample graphic" — but phase3-article.php re-credits the article's images, so match the title too.
$imgs = array_map(
	'intval',
	array_unique(
		array_merge(
			get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => 'tdd_credit', 'meta_value' => 'TDD sample graphic' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			(array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_title LIKE 'Sample editorial image (%'" ) // phpcs:ignore WordPress.DB
		)
	)
);
foreach ( $imgs as $id ) {
	wp_delete_attachment( $id, true );
}
echo 'sample images deleted: ', count( $imgs ), "\n";

require_once ABSPATH . 'wp-admin/includes/user.php';
$users = get_users( array( 'search' => '*-sample', 'search_columns' => array( 'user_login' ), 'fields' => array( 'ID', 'user_login' ) ) );
foreach ( $users as $u ) {
	wp_delete_user( (int) $u->ID ); // Their posts were all samples (deleted above).
}
$mp_ids = array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}mailpoet_subscribers WHERE email LIKE '%-sample@example.com'" ) ); // phpcs:ignore WordPress.DB
if ( $mp_ids && class_exists( '\MailPoet\DI\ContainerWrapper' ) ) {
	\MailPoet\DI\ContainerWrapper::getInstance()->get( \MailPoet\Subscribers\SubscribersRepository::class )->bulkDelete( $mp_ids );
}
echo 'MailPoet sample subscribers deleted: ', count( $mp_ids ), "\n";
echo 'sample users deleted: ', implode( ', ', wp_list_pluck( $users, 'user_login' ) ) ?: 'none', "\n";

$keep   = array_map( 'intval', (array) get_option( 'tdd_sample_preexisting_topics', array() ) );
$topics = 0;
foreach ( get_terms( array( 'taxonomy' => 'tdd_topic', 'hide_empty' => false ) ) as $t ) {
	if ( ! in_array( (int) $t->term_id, $keep, true ) && 0 === (int) $t->count ) {
		wp_delete_term( $t->term_id, 'tdd_topic' );
		++$topics;
	}
}
delete_option( 'tdd_sample_preexisting_topics' );
echo 'sample topics deleted: ', $topics, "\n";

$ai = get_term_by( 'slug', 'ai', 'category' );
if ( $ai ) {
	if ( str_contains( (string) get_term_meta( $ai->term_id, 'tdd_desk_note', true ), '[Sample]' ) ) {
		delete_term_meta( $ai->term_id, 'tdd_desk_note' );
		delete_term_meta( $ai->term_id, 'tdd_desk_members' );
		delete_term_meta( $ai->term_id, 'tdd_desk_url' );
	}
	$before = get_option( 'tdd_sample_ai_description' );
	if ( false !== $before ) {
		wp_update_term( $ai->term_id, 'category', array( 'description' => (string) $before ) );
		delete_option( 'tdd_sample_ai_description' );
	}
	echo "AI desk settings and description restored\n";
}

foreach ( $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_tdd_%'" ) as $o ) { // phpcs:ignore WordPress.DB
	delete_option( $o );
}
$left = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_tdd_fixture'" ); // phpcs:ignore WordPress.DB
echo "fixture rows left: $left\n";
