<?php
/**
 * tdd/latest-feed — LatestUpdateRow list. Substantial stories (image + deck + 4 min read or more) get the thumbnail row; quick updates get the text-only row.
 *
 * @package techdosedaily
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$tdd_args = array(
	'post_type'           => 'post',
	'post_status'         => 'publish',
	'posts_per_page'      => max( 1, (int) $attributes['count'] ),
	'ignore_sticky_posts' => true,
	'no_found_rows'       => true,
	'post__not_in'        => tdd_shown(), // A story appears once per page (hero/top stories first).
);
$tdd_section = $attributes['section'] ? get_term_by( 'slug', $attributes['section'], 'category' ) : null;
if ( $tdd_section ) {
	$tdd_args['cat'] = $tdd_section->term_id;
}
$tdd_posts = get_posts( $tdd_args );
if ( ! $tdd_posts ) {
	return;
}
$tdd_rows = '';
foreach ( $tdd_posts as $tdd_post ) {
	tdd_mark_shown( $tdd_post->ID );
	$tdd_rows .= tdd_latest_row( $tdd_post ); // inc/archive.php
}
if ( $attributes['showHeading'] ) {
	echo tdd_section_heading( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		array(
			'title'    => $attributes['title'],
			'fresh'    => true,
			'freshTs'  => (int) get_post_timestamp( $tdd_posts[0] ),
			'linkText' => __( 'All latest →', 'techdosedaily' ),
			'linkHideMobile' => true,
			'linkUrl'  => $tdd_section ? get_term_link( $tdd_section ) : tdd_latest_url(),
		)
	);
}
echo '<ol class="tdd-latest" data-tdd-feed>' . $tdd_rows . '</ol>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
if ( ! empty( $attributes['moreButton'] ) ) {
	// Approved desktop homepage: "Load more updates" (a plain link to the Latest page).
	echo '<div class="hp-more tdd-hide-m"><a class="tdd-btn tdd-btn--secondary" href="' . esc_url( $tdd_section ? get_term_link( $tdd_section ) : tdd_latest_url() ) . '">' . esc_html( $attributes['moreButton'] ) . '</a></div>';
}
if ( $attributes['showHeading'] ) {
	// Approved mobile: the "All latest" link moves below the list as a full-width button.
	echo '<a class="m-more tdd-only-m" href="' . esc_url( $tdd_section ? get_term_link( $tdd_section ) : tdd_latest_url() ) . '">' . esc_html__( 'All latest updates', 'techdosedaily' ) . '</a>';
}
