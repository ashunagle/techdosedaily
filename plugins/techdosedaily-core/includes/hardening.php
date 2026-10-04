<?php
/**
 * Security and privacy hardening (Phase 8). See SECURITY.md for the threat model and decisions.
 *
 * Every switch below is on by default and has a filter so a documented operational need can turn
 * it back on without editing code:
 *
 *   tdd_core_allow_xmlrpc                 false  XML-RPC (no app, Jetpack or pingback use)
 *   tdd_core_allow_application_passwords  false  REST basic-auth passwords (no integrations)
 *   tdd_core_public_user_endpoints        false  /wp/v2/users for anonymous visitors
 *   tdd_core_allow_file_edit              false  Theme/plugin file editors in wp-admin
 *   tdd_core_upload_mimes                 images + PDF
 *   tdd_core_strip_image_metadata         true   EXIF/IPTC/XMP (GPS, camera serials, names) removed on upload
 *   tdd_core_security_headers             array  Response headers for public pages and the login screen
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

/* ---------- XML-RPC and pingbacks: no operational need ---------- */

add_filter( 'xmlrpc_enabled', static fn( $on ) => (bool) apply_filters( 'tdd_core_allow_xmlrpc', false ) && $on );
add_filter( 'xmlrpc_methods', static fn( $methods ) => apply_filters( 'tdd_core_allow_xmlrpc', false ) ? $methods : array() );
add_action(
	'plugins_loaded',
	static function () {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST && ! apply_filters( 'tdd_core_allow_xmlrpc', false ) ) {
			status_header( 403 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo 'XML-RPC is disabled.';
			exit;
		}
	},
	0
);
add_filter(
	'wp_headers',
	static function ( array $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
);
add_filter( 'pings_open', '__return_false', 99 );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

/* ---------- Application passwords: no integration uses them ---------- */

add_filter( 'wp_is_application_passwords_available', static fn( $on ) => (bool) apply_filters( 'tdd_core_allow_application_passwords', false ) && $on );

/* ---------- REST users: not a public directory ---------- */

/**
 * Anonymous visitors cannot list or read accounts through /wp/v2/users (it exposes login-derived
 * slugs of non-publishing accounts and Gravatar hashes of account emails). Author pages and bylines
 * stay public; logged-in users keep WordPress's normal permissions (the editor needs them).
 */
add_filter(
	'rest_pre_dispatch',
	static function ( $result, WP_REST_Server $server, WP_REST_Request $request ) {
		if ( null !== $result || is_user_logged_in() || apply_filters( 'tdd_core_public_user_endpoints', false ) ) {
			return $result;
		}
		if ( preg_match( '#^/wp/v2/users(/|$)#', (string) $request->get_route() ) ) {
			return new WP_Error( 'rest_user_cannot_view', __( 'Sorry, you are not allowed to list users.', 'techdosedaily-core' ), array( 'status' => 401 ) );
		}
		return $result;
	},
	10,
	3
);

/* ---------- Login ---------- */

/** One message for unknown user, unknown email and wrong password (no account discovery). */
add_filter(
	'login_errors',
	static function ( $message ) {
		global $errors;
		if ( $errors instanceof WP_Error && array_intersect( $errors->get_error_codes(), array( 'invalid_username', 'invalid_email', 'incorrect_password' ) ) ) {
			return '<strong>' . esc_html__( 'Error:', 'techdosedaily-core' ) . '</strong> ' . esc_html__( 'The username, email address or password is incorrect.', 'techdosedaily-core' );
		}
		return $message;
	}
);

/* ---------- Raw HTML: administrators only ---------- */

/**
 * WordPress gives single-site Editors `unfiltered_html`: they could save <script> in a story, a
 * block attribute or a term description that then runs in an administrator's browser (privilege
 * escalation from editor to administrator). Editors keep every editorial tool; their HTML is
 * filtered like Authors' (embeds work through embed blocks). Administrators are unchanged.
 */
add_filter(
	'map_meta_cap',
	static function ( array $caps, string $cap, int $user_id ) {
		if ( 'unfiltered_html' === $cap && ! user_can( $user_id, 'manage_options' ) && ! apply_filters( 'tdd_core_editors_unfiltered_html', false ) ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	},
	10,
	3
);

/* ---------- The published record: editors and administrators remove it, not authors ---------- */

/**
 * Phase 8 (approved): Authors may not delete their own published stories. The role loses
 * `delete_published_posts` (stored in the roles option, so it is changed once, versioned), and —
 * because "unpublish, then delete the draft" would get round that — any story that has ever been
 * published (`_tdd_first_published`) can only be deleted or trashed by someone who can delete
 * others' stories (editors, administrators). Authors still delete their own never-published drafts.
 */
const TDD_CORE_ROLES_VERSION = '1';
add_action(
	'init',
	static function () {
		if ( TDD_CORE_ROLES_VERSION === get_option( 'tdd_core_roles_version' ) ) {
			return;
		}
		$author = get_role( 'author' );
		if ( $author ) {
			$author->remove_cap( 'delete_published_posts' );
		}
		update_option( 'tdd_core_roles_version', TDD_CORE_ROLES_VERSION, true );
	},
	1
);
/** Plugin deactivation restores WordPress's default Author role (and re-applies on reactivation). */
register_deactivation_hook(
	TDD_CORE_FILE,
	static function () {
		$author = get_role( 'author' );
		if ( $author ) {
			$author->add_cap( 'delete_published_posts' );
		}
		delete_option( 'tdd_core_roles_version' );
	}
);

/** True for a story that has been published at some point (its public record exists). */
function tdd_core_was_published( int $post_id ): bool {
	return 'post' === get_post_type( $post_id ) && ( 'publish' === get_post_status( $post_id ) || '' !== (string) get_post_meta( $post_id, '_tdd_first_published', true ) );
}

add_filter(
	'map_meta_cap',
	static function ( array $caps, string $cap, int $user_id, array $args ) {
		if ( 'delete_post' === $cap && ! empty( $args[0] ) && tdd_core_was_published( (int) $args[0] ) && ! user_can( $user_id, 'delete_others_posts' ) ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	},
	10,
	4
);

/**
 * Taking a live story down (back to draft/pending/private) is also removal of the published record:
 * editors and administrators only. Filter `tdd_core_authors_can_unpublish` to allow it.
 */
function tdd_core_may_unpublish(): bool {
	return current_user_can( 'delete_others_posts' ) || (bool) apply_filters( 'tdd_core_authors_can_unpublish', false );
}
add_filter(
	'rest_pre_insert_post',
	static function ( $prepared, WP_REST_Request $request ) {
		if ( is_wp_error( $prepared ) || empty( $prepared->ID ) || ! isset( $prepared->post_status ) ) {
			return $prepared;
		}
		if ( 'publish' === get_post_status( $prepared->ID ) && 'publish' !== $prepared->post_status && 'future' !== $prepared->post_status && ! tdd_core_may_unpublish() ) {
			return new WP_Error( 'tdd_unpublish_locked', __( 'Only an editor can take a published story down. Ask your editor, or publish an update instead.', 'techdosedaily-core' ), array( 'status' => 403 ) );
		}
		return $prepared;
	},
	9,
	2
);
add_filter(
	'wp_insert_post_data',
	static function ( array $data, array $postarr ) {
		$id = (int) ( $postarr['ID'] ?? 0 );
		if ( $id && 'post' === ( $data['post_type'] ?? '' ) && 'publish' === get_post_status( $id ) && ! in_array( $data['post_status'], array( 'publish', 'future' ), true ) && is_user_logged_in() && ! tdd_core_may_unpublish() ) {
			$data['post_status'] = 'publish'; // Classic/quick edit: keep it live.
		}
		return $data;
	},
	10,
	2
);

/* ---------- File editing in wp-admin ---------- */

add_filter(
	'map_meta_cap',
	static function ( array $caps, string $cap ) {
		if ( in_array( $cap, array( 'edit_files', 'edit_plugins', 'edit_themes' ), true ) && ! apply_filters( 'tdd_core_allow_file_edit', false ) ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	},
	10,
	2
);

/* ---------- Response headers ---------- */

/** Headers for public pages and wp-login.php. wp-admin keeps WordPress's own (it already sends frame protection). */
function tdd_core_security_headers(): array {
	$h = array(
		'X-Content-Type-Options' => 'nosniff',
		'Referrer-Policy'        => 'strict-origin-when-cross-origin',
		'X-Frame-Options'        => 'SAMEORIGIN',
		'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()',
		// Scripts are not restricted here (WordPress prints inline data); these directives block
		// clickjacking, <base> hijacking, plugins and forms posting off-site — all safe for this theme.
		'Content-Security-Policy' => "frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'",
	);
	if ( is_ssl() && 'production' === wp_get_environment_type() ) {
		$h['Strict-Transport-Security'] = 'max-age=31536000'; // No includeSubDomains/preload until the whole domain is confirmed HTTPS-only.
	}
	return (array) apply_filters( 'tdd_core_security_headers', $h );
}
$tdd_core_send_headers = static function ( $wp = null ) {
	if ( headers_sent() ) {
		return;
	}
	$headers = tdd_core_security_headers();
	if ( $wp instanceof WP && isset( $wp->query_vars['embed'] ) ) {
		// oEmbed cards (/…/embed/) exist to be framed by other sites that embed our stories.
		unset( $headers['X-Frame-Options'] );
		$headers['Content-Security-Policy'] = str_replace( "frame-ancestors 'self'; ", '', (string) ( $headers['Content-Security-Policy'] ?? '' ) );
	}
	foreach ( $headers as $name => $value ) {
		if ( '' !== (string) $value ) {
			header( $name . ': ' . $value );
		}
	}
};
add_action( 'send_headers', $tdd_core_send_headers );
add_action( 'login_init', $tdd_core_send_headers );
unset( $tdd_core_send_headers );

/* ---------- Uploads ---------- */

/** Only what the newsroom publishes: images and PDFs (no SVG, HTML, scripts, office files, archives, media). */
add_filter(
	'upload_mimes',
	static function ( $mimes ) {
		$allowed = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
			'avif'         => 'image/avif',
			'pdf'          => 'application/pdf',
		);
		return (array) apply_filters( 'tdd_core_upload_mimes', $allowed, $mimes );
	},
	99
);

/** Does an image file carry EXIF / IPTC / XMP / text metadata? (cheap header scan, no decoding) */
function tdd_core_image_has_metadata( string $file, string $mime ): bool {
	$head = (string) @file_get_contents( $file, false, null, 0, 262144 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	switch ( $mime ) {
		case 'image/jpeg':
			return str_contains( $head, "Exif\0\0" ) || str_contains( $head, 'http://ns.adobe.com/xap/' ) || str_contains( $head, 'Photoshop 3.0' );
		case 'image/png':
			return (bool) preg_match( '/eXIf|tEXt|iTXt|zTXt/', $head );
		case 'image/webp':
		case 'image/avif':
			return str_contains( $head, 'EXIF' ) || str_contains( $head, 'Exif' ) || str_contains( $head, 'XMP ' ) || str_contains( $head, 'http://ns.adobe.com/xap/' );
	}
	return false;
}

/**
 * Remove metadata from uploaded originals: GPS position, camera/lens serial numbers, owner names,
 * capture software, editing history and embedded captions can identify a photographer or a source.
 * Orientation is applied to the pixels first; the colour profile is kept (Imagick). Without Imagick
 * the image is re-saved with GD (quality 90), which drops all metadata.
 */
function tdd_core_strip_image_metadata( string $file, string $mime ): bool {
	if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp', 'image/avif' ), true ) || ! tdd_core_image_has_metadata( $file, $mime ) ) {
		return false;
	}
	if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
		try {
			$im = new Imagick( $file );
			if ( method_exists( $im, 'autoOrient' ) ) {
				$im->autoOrient();
			}
			$icc = $im->getImageProfiles( 'icc', true );
			$q   = $im->getImageCompressionQuality();
			$im->stripImage();
			if ( ! empty( $icc['icc'] ) ) {
				$im->profileImage( 'icc', $icc['icc'] );
			}
			if ( $q > 0 ) {
				$im->setImageCompressionQuality( $q );
			}
			$im->writeImage( $file );
			$im->clear();
			return true;
		} catch ( Exception $e ) {
			return false; // Leave the upload as it is rather than fail it; reported by the launch check.
		}
	}
	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) ) {
		return false;
	}
	if ( method_exists( $editor, 'maybe_exif_rotate' ) ) {
		$editor->maybe_exif_rotate();
	}
	$editor->set_quality( 90 );
	// Same file, same format: the WebP sub-size mapping (performance.php) must not apply to the original.
	$keep = static fn() => array();
	add_filter( 'image_editor_output_format', $keep, 999 );
	$saved = $editor->save( $file, $mime );
	remove_filter( 'image_editor_output_format', $keep, 999 );
	return ! is_wp_error( $saved ) && ( $saved['path'] ?? '' ) === $file;
}

$tdd_core_strip_upload = static function ( array $upload ) {
	if ( empty( $upload['error'] ) && ! empty( $upload['file'] ) && apply_filters( 'tdd_core_strip_image_metadata', true, $upload ) ) {
		tdd_core_strip_image_metadata( (string) $upload['file'], (string) ( $upload['type'] ?? '' ) );
	}
	return $upload;
};
add_filter( 'wp_handle_upload', $tdd_core_strip_upload );   // Media Library / classic uploads.
add_filter( 'wp_handle_sideload', $tdd_core_strip_upload ); // REST /wp/v2/media and the block editor.
unset( $tdd_core_strip_upload );

/* ---------- Profile fields: editors-only rules that REST can't express ---------- */

/** Featured Reporting may only list the person's own published stories (whatever path wrote it). */
$tdd_core_featured_guard = static function ( $meta_id, $user_id, $meta_key, $value ) {
	static $busy = false;
	if ( 'tdd_featured_posts' !== $meta_key || $busy ) {
		return;
	}
	$clean = array_values( array_filter( array_map( 'absint', (array) $value ), static fn( $id ) => (int) get_post_field( 'post_author', $id ) === (int) $user_id && 'publish' === get_post_status( $id ) ) );
	if ( $clean !== array_values( array_map( 'absint', (array) $value ) ) ) {
		$busy = true;
		update_user_meta( (int) $user_id, 'tdd_featured_posts', array_slice( $clean, 0, 3 ) );
		$busy = false;
	}
};
add_action( 'added_user_meta', $tdd_core_featured_guard, 10, 4 );
add_action( 'updated_user_meta', $tdd_core_featured_guard, 10, 4 );
unset( $tdd_core_featured_guard );
