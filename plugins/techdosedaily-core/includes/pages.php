<?php
/**
 * Static page fields (Editorial Standards, policies, About, Newsletter, Contact).
 * All optional; the theme omits whatever is empty. "Last updated" must be a real date:
 * tdd_last_reviewed when set (a substantive policy change), otherwise the page's modified date.
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		$can = static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id );
		$str = static fn( string $desc, string $san = 'sanitize_text_field' ) => array( 'type' => 'string', 'single' => true, 'default' => '', 'description' => $desc, 'show_in_rest' => true, 'sanitize_callback' => $san, 'auth_callback' => $can );
		$fields = array(
			'tdd_kicker'          => $str( 'Small caps line above the title, e.g. "Policy", "About", "Partner with us".' ),
			'tdd_intro'           => $str( 'Intro paragraph under the title.', 'sanitize_textarea_field' ),
			'tdd_statement'       => $str( 'About only: one-line mission statement.' ),
			'tdd_page_headline'        => $str( 'Page headline (H1) when it differs from the short page title, e.g. title "About", headline "About Tech Dose Daily". The title stays in breadcrumbs and menus.' ),
			'tdd_last_reviewed'   => $str( 'Date of the last substantive change (YYYY-MM-DD). Shown as "Last updated" and used as dateModified.', 'tdd_core_sanitize_date' ),
			'tdd_review_cadence'  => $str( 'e.g. "Reviewed every six months" — only if true.' ),
			'tdd_version_url'     => $str( 'Version history (or media kit) link.', 'esc_url_raw' ),
			'tdd_version_label'   => $str( 'Label for that link. Default "Version history →".' ),
			'tdd_summary'         => $str( 'Short description used in "Related policies" lists, e.g. "How we fix errors".' ),
		);
		foreach ( $fields as $key => $args ) {
			register_post_meta( 'page', $key, $args );
		}
		register_post_meta( 'page', 'tdd_numbered', array( 'type' => 'boolean', 'single' => true, 'default' => false, 'description' => 'Number the H2 sections (policies people cite as "section 4").', 'show_in_rest' => true, 'auth_callback' => $can ) );
		register_post_meta(
			'page',
			'tdd_focus',
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'description'       => 'About only: the three-item focus row [{title, text}].',
				'auth_callback'     => $can,
				'sanitize_callback' => static fn( $v ) => array_slice( array_values( array_filter( array_map( static fn( $r ) => empty( $r['title'] ) ? null : array( 'title' => sanitize_text_field( $r['title'] ), 'text' => sanitize_text_field( $r['text'] ?? '' ) ), (array) $v ) ) ), 0, 3 ),
				'show_in_rest'      => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'object', 'properties' => array( 'title' => array( 'type' => 'string' ), 'text' => array( 'type' => 'string' ) ) ) ) ),
			)
		);
		register_post_meta(
			'page',
			'tdd_related_docs',
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'description'       => 'Related documents [{title, url, kind: policy|external}].',
				'auth_callback'     => $can,
				'sanitize_callback' => static fn( $v ) => array_values( array_filter( array_map( static fn( $r ) => ( empty( $r['title'] ) || empty( $r['url'] ) ) ? null : array( 'title' => sanitize_text_field( $r['title'] ), 'url' => esc_url_raw( $r['url'] ), 'kind' => 'external' === ( $r['kind'] ?? '' ) ? 'external' : 'policy' ), (array) $v ) ) ),
				'show_in_rest'      => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'object', 'properties' => array( 'title' => array( 'type' => 'string' ), 'url' => array( 'type' => 'string' ), 'kind' => array( 'type' => 'string', 'enum' => array( 'policy', 'external' ) ) ) ) ) ),
			)
		);
	},
	6
);

function tdd_core_sanitize_date( $v ): string {
	$v = trim( (string) $v );
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) && strtotime( $v ) ? $v : '';
}

/**
 * Real "Last updated" for a page: last substantive review date, else post_modified (site time).
 *
 * @return int Unix timestamp.
 */
function tdd_core_page_updated( $post = null ): int {
	$post = get_post( $post );
	if ( ! $post ) {
		return 0;
	}
	$d = (string) get_post_meta( $post->ID, 'tdd_last_reviewed', true );
	if ( '' !== $d ) {
		$dt = date_create_immutable( $d . ' 12:00:00', wp_timezone() );
		return $dt ? $dt->getTimestamp() : 0;
	}
	return (int) get_post_timestamp( $post, 'modified' );
}

/** The policy pages, in the approved "Related policies" order: [ path => default description ]. */
function tdd_core_policy_pages(): array {
	return apply_filters(
		'tdd_core_policy_pages',
		array(
			'editorial-standards' => __( 'How we report', 'techdosedaily-core' ),
			'corrections-policy'  => __( 'How we fix errors', 'techdosedaily-core' ),
			'source-policy'       => __( 'How we choose sources', 'techdosedaily-core' ),
			'ai-use-policy'       => __( 'Where AI tools are allowed', 'techdosedaily-core' ),
			'privacy-policy'      => __( 'What data we collect', 'techdosedaily-core' ),
			'terms'               => __( 'Using this site', 'techdosedaily-core' ),
			'advertise'           => __( 'Partnerships and media kit', 'techdosedaily-core' ),
		)
	);
}
