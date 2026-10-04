<?php
/**
 * Story format (tdd_format) and Topic (tdd_topic) taxonomies.
 *
 * Format answers "what kind of journalism is this" — News, Analysis, Explainer, Guide,
 * Review, Sponsored. Breaking is NOT a format; it is a time-limited state (see editorial.php).
 * Topics are the secondary discovery layer (OpenAI, AI Agents …); sections stay single.
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

function tdd_core_register_taxonomies(): void {
	register_taxonomy(
		'tdd_format',
		array( 'post' ),
		array(
			'labels'            => array(
				'name'          => __( 'Story types', 'techdosedaily-core' ),
				'singular_name' => __( 'Story type', 'techdosedaily-core' ),
				'menu_name'     => __( 'Story types', 'techdosedaily-core' ),
			),
			'description'       => __( 'One per story. Drives the editorial label and schema type.', 'techdosedaily-core' ),
			'public'            => true,
			'hierarchical'      => true, // Checkbox UI; a single choice is enforced on save.
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'story-type', 'with_front' => false ),
			'capabilities'      => array(
				'manage_terms' => 'manage_categories',
				'edit_terms'   => 'manage_options', // The format list is fixed by editorial policy.
				'delete_terms' => 'manage_options',
				'assign_terms' => 'edit_posts',
			),
		)
	);

	register_taxonomy(
		'tdd_topic',
		array( 'post' ),
		array(
			'labels'            => array(
				'name'          => __( 'Topics', 'techdosedaily-core' ),
				'singular_name' => __( 'Topic', 'techdosedaily-core' ),
				'add_new_item'  => __( 'Add topic', 'techdosedaily-core' ),
			),
			'description'       => __( 'Companies, products and themes followed across sections.', 'techdosedaily-core' ),
			'public'            => true,
			'hierarchical'      => false,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'topic', 'with_front' => false ),
		)
	);
}
add_action( 'init', 'tdd_core_register_taxonomies', 5 );

/** Fixed story-type list. Breaking intentionally absent. */
function tdd_core_default_formats(): array {
	return array(
		'news'      => 'News',
		'analysis'  => 'Analysis',
		'explainer' => 'Explainer',
		'guide'     => 'Guide',
		'review'    => 'Review',
		'sponsored' => 'Sponsored',
	);
}

function tdd_core_seed_formats(): void {
	foreach ( tdd_core_default_formats() as $slug => $name ) {
		if ( ! term_exists( $slug, 'tdd_format' ) ) {
			wp_insert_term( $name, 'tdd_format', array( 'slug' => $slug ) );
		}
	}
}

/** Exactly one story type per post; default News. Sponsor meta forces Sponsored. */
add_action(
	'save_post_post',
	static function ( int $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$terms = wp_get_object_terms( $post_id, 'tdd_format', array( 'fields' => 'slugs' ) );
		$terms = is_wp_error( $terms ) ? array() : $terms;
		if ( get_post_meta( $post_id, 'tdd_sponsor', true ) ) {
			$want = 'sponsored';
		} elseif ( empty( $terms ) ) {
			$want = 'news';
		} elseif ( count( $terms ) > 1 ) {
			$want = in_array( 'sponsored', $terms, true ) ? 'sponsored' : $terms[0];
		} else {
			return;
		}
		if ( term_exists( $want, 'tdd_format' ) ) {
			wp_set_object_terms( $post_id, $want, 'tdd_format', false );
		}
	},
	30
);
