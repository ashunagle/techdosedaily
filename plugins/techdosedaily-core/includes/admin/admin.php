<?php
/**
 * Newsroom admin: the "Tech Dose Daily" menu, block-editor story panels and media fields.
 * Every screen asks only for what applies (conditional fields), and saves through the
 * registered meta (sanitizers + auth callbacks in meta.php) or nonce-checked forms.
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

/** Asset URL inside the plugin. */
function tdd_core_asset( string $path ): string {
	return plugins_url( 'assets/' . ltrim( $path, '/' ), TDD_CORE_FILE );
}

/** Version string that changes with the file (cache-safe during development and after deploys). */
function tdd_core_asset_ver( string $path ): string {
	$file = TDD_CORE_DIR . '/assets/' . ltrim( $path, '/' );
	return TDD_CORE_VERSION . '.' . ( is_readable( $file ) ? (string) filemtime( $file ) : '0' );
}

/** People who can be named as editor / desk members (real accounts with editing rights). */
function tdd_core_editor_users(): array {
	return array_map(
		static fn( WP_User $u ) => array( 'id' => $u->ID, 'name' => $u->display_name ),
		get_users( array( 'capability' => 'edit_others_posts', 'orderby' => 'display_name', 'fields' => 'all' ) )
	);
}

/** Top-level sections as {id, name, slug}. */
function tdd_core_section_options(): array {
	$terms = get_terms( array( 'taxonomy' => 'category', 'parent' => 0, 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	$order = array_keys( tdd_core_default_sections() );
	$pos   = static fn( WP_Term $t ): int => false !== ( $i = array_search( $t->slug, $order, true ) ) ? (int) $i : 99;
	usort( $terms, static fn( $a, $b ) => $pos( $a ) <=> $pos( $b ) );
	$out = array();
	foreach ( $terms as $t ) {
		if ( 'uncategorized' !== $t->slug ) {
			$out[] = array( 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug );
		}
	}
	return $out;
}

/* ---------- Menu ---------- */

add_action(
	'admin_menu',
	static function () {
		add_menu_page( __( 'Tech Dose Daily', 'techdosedaily-core' ), __( 'Tech Dose Daily', 'techdosedaily-core' ), 'edit_others_posts', 'tdd-placements', 'tdd_core_placements_page', 'dashicons-megaphone', 3 );
		add_submenu_page( 'tdd-placements', __( 'Placements', 'techdosedaily-core' ), __( 'Placements', 'techdosedaily-core' ), 'edit_others_posts', 'tdd-placements', 'tdd_core_placements_page' );
		add_submenu_page( 'tdd-placements', __( 'Sections', 'techdosedaily-core' ), __( 'Sections', 'techdosedaily-core' ), 'manage_categories', 'edit-tags.php?taxonomy=category' );
		add_submenu_page( 'tdd-placements', __( 'Site settings', 'techdosedaily-core' ), __( 'Site settings', 'techdosedaily-core' ), 'manage_options', 'tdd-settings', 'tdd_core_settings_page' );
	}
);

/** Keep "Sections" highlighted under our menu while editing a category. */
add_filter(
	'parent_file',
	static function ( $parent ) {
		$screen = get_current_screen();
		return ( $screen && 'category' === $screen->taxonomy ) ? 'tdd-placements' : $parent;
	}
);

/** Rename "Categories" to "Sections" in the admin (one section per story; Topics do the rest). */
add_action(
	'init',
	static function () {
		$tax = get_taxonomy( 'category' );
		if ( $tax ) {
			$tax->labels->name          = __( 'Sections', 'techdosedaily-core' );
			$tax->labels->singular_name = __( 'Section', 'techdosedaily-core' );
			$tax->labels->menu_name     = __( 'Sections', 'techdosedaily-core' );
			$tax->labels->edit_item     = __( 'Edit section', 'techdosedaily-core' );
			$tax->labels->update_item   = __( 'Update section', 'techdosedaily-core' );
			$tax->labels->add_new_item  = __( 'Add section', 'techdosedaily-core' );
			$tax->labels->all_items     = __( 'All sections', 'techdosedaily-core' );
			$tax->labels->view_item     = __( 'View section', 'techdosedaily-core' );
			$tax->labels->search_items  = __( 'Search sections', 'techdosedaily-core' );
			$tax->labels->back_to_items = __( '← Go to Sections', 'techdosedaily-core' );
		}
	},
	20
);

add_action(
	'admin_enqueue_scripts',
	static function () {
		wp_register_style( 'tdd-admin', tdd_core_asset( 'admin/admin.css' ), array( 'wp-components' ), tdd_core_asset_ver( 'admin/admin.css' ) );
		wp_enqueue_style( 'tdd-admin' );
	}
);

/* ---------- Block editor: story panels ---------- */

add_action(
	'enqueue_block_editor_assets',
	static function () {
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->post_type ) {
			return;
		}
		wp_enqueue_script(
			'tdd-story-panels',
			tdd_core_asset( 'admin/story.js' ),
			array( 'wp-plugins', 'wp-editor', 'wp-data', 'wp-core-data', 'wp-components', 'wp-element', 'wp-i18n', 'wp-api-fetch', 'wp-date', 'wp-url' ),
			tdd_core_asset_ver( 'admin/story.js' ),
			true
		);
		wp_enqueue_style( 'tdd-admin', tdd_core_asset( 'admin/admin.css' ), array( 'wp-components' ), tdd_core_asset_ver( 'admin/admin.css' ) );
		$formats = get_terms( array( 'taxonomy' => 'tdd_format', 'hide_empty' => false ) );
		$order   = array_keys( tdd_core_default_formats() );
		$fmt     = array();
		foreach ( is_wp_error( $formats ) ? array() : $formats as $t ) {
			$i                                   = array_search( $t->slug, $order, true );
			$fmt[ false === $i ? 99 + $t->term_id : $i ] = array( 'id' => $t->term_id, 'slug' => $t->slug, 'name' => $t->name );
		}
		ksort( $fmt );
		$types = array();
		foreach ( tdd_core_placement_types() as $key => [ $label, $max, $needs ] ) {
			$types[] = array( 'key' => $key, 'label' => $label, 'max' => $max, 'needsSection' => $needs );
		}
		wp_localize_script(
			'tdd-story-panels',
			'tddStory',
			array(
				'sections'      => tdd_core_section_options(),
				'formats'       => array_values( $fmt ),
				'editors'       => tdd_core_editor_users(),
				'sourceTypes'   => array(
					array( 'value' => 'primary', 'label' => __( 'Primary — the announcement, filing, documentation or code', 'techdosedaily-core' ) ),
					array( 'value' => 'interview', 'label' => __( 'Interview — on the record, by our reporter', 'techdosedaily-core' ) ),
					array( 'value' => 'supporting', 'label' => __( 'Supporting — experts, research, other reporting', 'techdosedaily-core' ) ),
					array( 'value' => 'public-record', 'label' => __( 'Public record — filings, court records, notices', 'techdosedaily-core' ) ),
					array( 'value' => 'confidential', 'label' => __( 'Confidential — editor approval needed', 'techdosedaily-core' ) ),
				),
				'imageTypes'    => array(
					array( 'value' => '', 'label' => __( '— Choose —', 'techdosedaily-core' ) ),
					array( 'value' => 'official', 'label' => __( 'Official / company handout', 'techdosedaily-core' ) ),
					array( 'value' => 'photo', 'label' => __( 'Our photo', 'techdosedaily-core' ) ),
					array( 'value' => 'licensed', 'label' => __( 'Licensed (agency / stock)', 'techdosedaily-core' ) ),
					array( 'value' => 'screenshot', 'label' => __( 'Screenshot', 'techdosedaily-core' ) ),
					array( 'value' => 'graphic', 'label' => __( 'Our graphic', 'techdosedaily-core' ) ),
					array( 'value' => 'ai-generated', 'label' => __( 'AI-generated (labelled where shown)', 'techdosedaily-core' ) ),
				),
				'breakingHours' => tdd_core_breaking_hours(),
				'canPlace'      => current_user_can( 'edit_others_posts' ),
				'placements'    => $types,
				'boardUrl'      => admin_url( 'admin.php?page=tdd-placements' ),
			)
		);
	}
);

/* ---------- Block editor: page details (static pages) ---------- */

add_action(
	'enqueue_block_editor_assets',
	static function () {
		$screen = get_current_screen();
		if ( ! $screen || 'page' !== $screen->post_type ) {
			return;
		}
		wp_enqueue_script( 'tdd-page-panel', tdd_core_asset( 'admin/page.js' ), array( 'wp-plugins', 'wp-editor', 'wp-data', 'wp-components', 'wp-element', 'wp-i18n', 'wp-date' ), tdd_core_asset_ver( 'admin/page.js' ), true );
		wp_enqueue_style( 'tdd-admin', tdd_core_asset( 'admin/admin.css' ), array( 'wp-components' ), tdd_core_asset_ver( 'admin/admin.css' ) );
		wp_localize_script(
			'tdd-page-panel',
			'tddPage',
			array(
				'templates'   => array(
					''                => __( 'Policy page', 'techdosedaily-core' ),
					'page-short'      => __( 'Short page', 'techdosedaily-core' ),
					'page-about'      => __( 'About', 'techdosedaily-core' ),
					'page-contact'    => __( 'Contact', 'techdosedaily-core' ),
					'page-newsletter' => __( 'Newsletter', 'techdosedaily-core' ),
				),
				'summaries'   => tdd_core_policy_pages(),
				'settingsUrl' => current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=tdd-settings#tdd-contact' ) : '',
			)
		);
	}
);

/* ---------- Media library: credit, source, licence ---------- */

add_filter(
	'attachment_fields_to_edit',
	static function ( array $fields, WP_Post $post ) {
		if ( ! wp_attachment_is_image( $post ) ) {
			return $fields;
		}
		$v    = static fn( string $k ) => (string) get_post_meta( $post->ID, $k, true );
		$opts = '';
		foreach ( array_merge( array( '' ), tdd_core_image_source_types() ) as $t ) {
			$opts .= '<option value="' . esc_attr( $t ) . '"' . selected( $v( 'tdd_source_type' ), $t, false ) . '>' . esc_html( '' === $t ? __( '— Choose —', 'techdosedaily-core' ) : ucfirst( str_replace( '-', ' ', $t ) ) ) . '</option>';
		}
		$fields['tdd_credit']       = array( 'label' => __( 'Credit', 'techdosedaily-core' ), 'input' => 'text', 'value' => $v( 'tdd_credit' ), 'helps' => __( 'Shown under the image, e.g. "Company handout" or "Photo: Agency".', 'techdosedaily-core' ) );
		$fields['tdd_source_type']  = array( 'label' => __( 'Image source', 'techdosedaily-core' ), 'input' => 'html', 'html' => '<select name="attachments[' . $post->ID . '][tdd_source_type]">' . $opts . '</select>' );
		$fields['tdd_source_url']   = array( 'label' => __( 'Source link', 'techdosedaily-core' ), 'input' => 'text', 'value' => $v( 'tdd_source_url' ), 'helps' => __( 'Where the image came from, or the licence record.', 'techdosedaily-core' ) );
		$fields['tdd_license_note'] = array( 'label' => __( 'Licence', 'techdosedaily-core' ), 'input' => 'text', 'value' => $v( 'tdd_license_note' ), 'helps' => __( 'Usage terms, e.g. "Editorial use only, until 2027".', 'techdosedaily-core' ) );
		return $fields;
	},
	10,
	2
);

add_filter(
	'attachment_fields_to_save',
	static function ( array $post, array $attachment ) {
		if ( ! current_user_can( 'edit_post', (int) $post['ID'] ) ) {
			return $post;
		}
		foreach ( array( 'tdd_credit', 'tdd_source_type', 'tdd_source_url', 'tdd_license_note' ) as $k ) {
			if ( isset( $attachment[ $k ] ) ) {
				update_post_meta( (int) $post['ID'], $k, wp_unslash( $attachment[ $k ] ) ); // Registered sanitizers run here.
			}
		}
		return $post;
	},
	10,
	2
);

/* ---------- Story list: section + story type at a glance ---------- */

add_filter(
	'manage_post_posts_columns',
	static function ( array $cols ) {
		unset( $cols['categories'] );
		$out = array();
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'title' === $k ) {
				$out['tdd_section'] = __( 'Section', 'techdosedaily-core' );
			}
		}
		return $out;
	}
);
add_action(
	'manage_post_posts_custom_column',
	static function ( string $col, int $post_id ) {
		if ( 'tdd_section' !== $col ) {
			return;
		}
		$t = tdd_core_primary_section( $post_id );
		echo $t ? esc_html( $t->name ) : '<span class="tdd-missing">' . esc_html__( 'No section', 'techdosedaily-core' ) . '</span>';
		if ( tdd_core_is_breaking( $post_id ) ) {
			echo ' <span class="tdd-chip tdd-chip--breaking">' . esc_html__( 'Breaking', 'techdosedaily-core' ) . '</span>';
		}
	},
	10,
	2
);
