<?php
/**
 * Tech Dose Daily → Site settings (administrators): launch readiness, contact routing, partnerships,
 * newsletter, Most Read, Breaking and structured-data ownership. Standard Settings API: every option
 * has a sanitizer, the form posts to options.php with its nonce.
 *
 * @package TDD\Core
 */

defined( 'ABSPATH' ) || exit;

const TDD_CORE_SETTINGS = 'tdd_settings';

/** True for placeholder domains that must never be used in production. */
function tdd_core_is_sample_address( string $value ): bool {
	return (bool) preg_match( '/(^|[@.\/])example\.(com|org|net)\b/i', $value );
}

add_action(
	'admin_init',
	static function () {
		$routes = array_keys( tdd_core_contact_routes() );
		register_setting(
			TDD_CORE_SETTINGS,
			'tdd_contact_inboxes',
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => static function ( $v ) use ( $routes ) {
					$out = array();
					foreach ( $routes as $r ) {
						$e = sanitize_email( (string) ( $v[ $r ] ?? '' ) );
						if ( '' !== trim( (string) ( $v[ $r ] ?? '' ) ) && ! is_email( $e ) ) {
							add_settings_error( 'tdd_contact_inboxes', 'bad-' . $r, sprintf( /* translators: %s: route */ __( 'The %s inbox is not a valid email address. The previous address was kept.', 'techdosedaily-core' ), $r ) );
							$old = (array) get_option( 'tdd_contact_inboxes', array() );
							if ( ! empty( $old[ $r ] ) ) {
								$out[ $r ] = $old[ $r ];
							}
							continue;
						}
						if ( $e ) {
							$out[ $r ] = $e;
						}
					}
					return $out;
				},
			)
		);
		register_setting(
			TDD_CORE_SETTINGS,
			'tdd_contact_notes',
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => static fn( $v ) => array_filter( array_map( static fn( $r ) => mb_substr( sanitize_text_field( (string) ( $v[ $r ] ?? '' ) ), 0, 80 ), array_combine( $routes, $routes ) ) ),
			)
		);
		register_setting(
			TDD_CORE_SETTINGS,
			'tdd_contact_expectations',
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => static fn( $v ) => array_values( array_filter( array_map( static fn( $r ) => empty( $r['title'] ) ? null : array( 'title' => sanitize_text_field( $r['title'] ), 'text' => sanitize_text_field( $r['text'] ?? '' ) ), (array) $v ) ) ),
			)
		);
		register_setting( TDD_CORE_SETTINGS, 'tdd_secure_tip', array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'wp_kses_post' ) );
		register_setting(
			TDD_CORE_SETTINGS,
			'tdd_contact_from',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => static function ( $v ) {
					$e = sanitize_email( (string) $v );
					return is_email( $e ) ? $e : '';
				},
			)
		);
		register_setting( TDD_CORE_SETTINGS, 'tdd_media_kit_url', array( 'type' => 'string', 'default' => '', 'sanitize_callback' => static fn( $v ) => esc_url_raw( (string) $v, array( 'http', 'https' ) ) ) );
		register_setting( TDD_CORE_SETTINGS, 'tdd_newsletter_mailpoet_list', array( 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ) );
		register_setting(
			TDD_CORE_SETTINGS,
			'tdd_core_most_read_windows',
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => static function ( $v ) {
					$out = array();
					foreach ( array( 'home' => 24, 'section' => 168, 'author' => 720 ) as $k => $d ) {
						$h         = absint( $v[ $k ] ?? $d );
						$out[ $k ] = min( 2160, max( 1, $h ?: $d ) );
					}
					return $out;
				},
			)
		);
		register_setting( TDD_CORE_SETTINGS, 'tdd_core_schema_owner', array( 'type' => 'string', 'default' => 'yoast', 'sanitize_callback' => static fn( $v ) => 'core' === $v ? 'core' : 'yoast' ) );
		register_setting( TDD_CORE_SETTINGS, 'tdd_breaking_hours', array( 'type' => 'integer', 'default' => TDD_CORE_BREAKING_HOURS, 'sanitize_callback' => static fn( $v ) => min( 72, max( 1, absint( $v ) ?: TDD_CORE_BREAKING_HOURS ) ) ) );
	}
);

/**
 * What still has to be done before launch. Each row: [ state ok|todo|info, title, detail, link ].
 *
 * @return array<int, array{0:string,1:string,2:string,3:string}>
 */
function tdd_core_launch_checks(): array {
	global $wpdb;
	$out     = array();
	$inboxes = (array) get_option( 'tdd_contact_inboxes', array() );
	$missing = array();
	$sample  = array();
	foreach ( tdd_core_contact_routes() as $k => $r ) {
		if ( empty( $inboxes[ $k ] ) ) {
			$missing[] = $r['title'];
		} elseif ( tdd_core_is_sample_address( $inboxes[ $k ] ) ) {
			$sample[] = $r['title'];
		}
	}
	$out[] = ( $missing || $sample )
		? array( 'todo', __( 'Contact role inboxes', 'techdosedaily-core' ), trim( ( $missing ? sprintf( /* translators: %s: routes */ __( 'Not set (goes to the admin email): %s.', 'techdosedaily-core' ), implode( ', ', $missing ) ) : '' ) . ' ' . ( $sample ? sprintf( /* translators: %s: routes */ __( 'Sample address: %s.', 'techdosedaily-core' ), implode( ', ', $sample ) ) : '' ) ), '#tdd-contact' )
		: array( 'ok', __( 'Contact role inboxes', 'techdosedaily-core' ), __( 'Every route has its own inbox.', 'techdosedaily-core' ), '#tdd-contact' );

	$from  = (string) get_option( 'tdd_contact_from', '' );
	$host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$out[] = '' === $from
		? array( 'info', __( 'Contact mail sender', 'techdosedaily-core' ), sprintf( /* translators: %s: address */ __( 'Messages are sent from WordPress’s default address (wordpress@%s). Set a monitored site mailbox if you prefer.', 'techdosedaily-core' ), preg_replace( '/^www\./', '', $host ) ), '#tdd-contact' )
		: array( tdd_core_is_sample_address( $from ) ? 'todo' : 'ok', __( 'Contact mail sender', 'techdosedaily-core' ), $from, '#tdd-contact' );

	$pid   = (int) get_option( 'wp_page_for_privacy_policy' );
	$pp    = $pid ? get_post( $pid ) : null;
	$out[] = ( $pp && 'publish' === $pp->post_status && '' !== trim( wp_strip_all_tags( $pp->post_content ) ) )
		? array( 'ok', __( 'Privacy Policy', 'techdosedaily-core' ), __( 'Published and selected in Settings → Privacy.', 'techdosedaily-core' ), admin_url( 'options-privacy.php' ) )
		: array( 'todo', __( 'Privacy Policy', 'techdosedaily-core' ), __( 'Choose a published Privacy Policy page with real content in Settings → Privacy.', 'techdosedaily-core' ), admin_url( 'options-privacy.php' ) );

	$empty = array();
	foreach ( tdd_core_policy_pages() as $path => $desc ) {
		if ( 'privacy-policy' === $path ) {
			continue;
		}
		$p = get_page_by_path( $path );
		if ( ! $p || 'publish' !== $p->post_status || '' === trim( wp_strip_all_tags( $p->post_content ) ) ) {
			$empty[] = $p ? get_the_title( $p ) : $path;
		}
	}
	$out[] = $empty
		? array( 'todo', __( 'Policy pages', 'techdosedaily-core' ), sprintf( /* translators: %s: page names */ __( 'Missing or empty: %s. Final wording needs editorial (and legal) approval.', 'techdosedaily-core' ), implode( ', ', $empty ) ), admin_url( 'edit.php?post_type=page' ) )
		: array( 'ok', __( 'Policy pages', 'techdosedaily-core' ), __( 'All published with content. Confirm the wording was approved.', 'techdosedaily-core' ), admin_url( 'edit.php?post_type=page' ) );

	$mp    = class_exists( '\MailPoet\API\API' );
	$list  = (int) get_option( 'tdd_newsletter_mailpoet_list', 0 );
	$out[] = ( $mp && $list )
		? array( 'ok', __( 'Newsletter', 'techdosedaily-core' ), __( 'MailPoet is active and a list is selected. Confirm double opt-in is on in MailPoet.', 'techdosedaily-core' ), '#tdd-newsletter' )
		: array( 'todo', __( 'Newsletter', 'techdosedaily-core' ), $mp ? __( 'Choose the MailPoet list for sign-ups.', 'techdosedaily-core' ) : __( 'Install and activate MailPoet, then choose its list. Until then sign-up says it is temporarily unavailable.', 'techdosedaily-core' ), '#tdd-newsletter' );

	$out[] = '' !== trim( (string) get_option( 'tdd_secure_tip', '' ) )
		? array( 'ok', __( 'Secure tips', 'techdosedaily-core' ), __( 'Instructions are shown on the Contact page.', 'techdosedaily-core' ), '#tdd-contact' )
		: array( 'info', __( 'Secure tips', 'techdosedaily-core' ), __( 'No channel configured. The Contact page says plainly that none exists yet.', 'techdosedaily-core' ), '#tdd-contact' );

	$kit   = (string) get_option( 'tdd_media_kit_url', '' );
	$out[] = '' === $kit
		? array( 'info', __( 'Media kit', 'techdosedaily-core' ), __( 'Not set: media kit links are hidden.', 'techdosedaily-core' ), '#tdd-partners' )
		: array( tdd_core_is_sample_address( $kit ) ? 'todo' : 'ok', __( 'Media kit', 'techdosedaily-core' ), $kit, '#tdd-partners' );

	$team  = get_users( array( 'meta_key' => 'tdd_show_on_about', 'meta_value' => '1', 'fields' => 'ID' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$out[] = $team
		? array( 'ok', __( 'About page editors', 'techdosedaily-core' ), sprintf( /* translators: %d: people */ _n( '%d person listed.', '%d people listed.', count( $team ), 'techdosedaily-core' ), count( $team ) ), admin_url( 'users.php' ) )
		: array( 'info', __( 'About page editors', 'techdosedaily-core' ), __( 'Nobody listed, so “Our editors” is hidden. Turn on “List on the About page” in real staff profiles.', 'techdosedaily-core' ), admin_url( 'users.php' ) );

	$no_line = array();
	foreach ( tdd_core_section_options() as $s ) {
		if ( '' === (string) get_term_meta( $s['id'], 'tdd_short_description', true ) ) {
			$no_line[] = $s['name'];
		}
	}
	$out[] = $no_line
		? array( 'todo', __( 'Section one-liners', 'techdosedaily-core' ), sprintf( /* translators: %s: sections */ __( 'Missing for: %s.', 'techdosedaily-core' ), implode( ', ', $no_line ) ), admin_url( 'edit-tags.php?taxonomy=category' ) )
		: array( 'ok', __( 'Section one-liners', 'techdosedaily-core' ), __( 'Every section has one.', 'techdosedaily-core' ), admin_url( 'edit-tags.php?taxonomy=category' ) );

	$out[] = get_option( 'site_icon' )
		? array( 'ok', __( 'Site icon', 'techdosedaily-core' ), __( 'Set.', 'techdosedaily-core' ), admin_url( 'options-general.php' ) )
		: array( 'todo', __( 'Site icon', 'techdosedaily-core' ), __( 'Upload the final icon in Settings → General. It is also the publisher logo in structured data.', 'techdosedaily-core' ), admin_url( 'options-general.php' ) );

	$out[] = tdd_core_yoast_active()
		? array( 'ok', __( 'Yoast SEO', 'techdosedaily-core' ), __( 'Active: titles, descriptions, canonicals, robots, social tags and sitemaps.', 'techdosedaily-core' ), admin_url( 'admin.php?page=wpseo_dashboard' ) )
		: array( 'todo', __( 'Yoast SEO', 'techdosedaily-core' ), __( 'Not active. Install and configure it (see SEO-SCHEMA.md): without it there are no meta descriptions, social tags or Yoast sitemaps.', 'techdosedaily-core' ), admin_url( 'plugins.php' ) );
	$out[] = array( 'info', __( 'Structured data', 'techdosedaily-core' ), 'core' === tdd_core_schema_owner() ? __( 'TechDoseDaily Core graph is printed.', 'techdosedaily-core' ) : __( 'Yoast SEO graph is printed.', 'techdosedaily-core' ), admin_url( 'admin.php?page=tdd-settings#tdd-schema' ) );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$fixtures = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_tdd_fixture'" );
	$samples  = count( get_users( array( 'search' => '*-sample', 'search_columns' => array( 'user_login' ), 'fields' => 'ID' ) ) );
	$out[]    = ( $fixtures || $samples )
		? array( 'todo', __( 'Sample data', 'techdosedaily-core' ), sprintf( /* translators: 1: posts, 2: users */ __( '%1$d test-fixture posts/pages and %2$d sample users are present. Never launch with them.', 'techdosedaily-core' ), $fixtures, $samples ), admin_url( 'edit.php' ) )
		: array( 'ok', __( 'Sample data', 'techdosedaily-core' ), __( 'No test fixtures found.', 'techdosedaily-core' ), '' );

	$out[] = get_option( 'blog_public' )
		? array( 'info', __( 'Search engines', 'techdosedaily-core' ), __( 'Indexing is allowed. On staging, discourage it in Settings → Reading.', 'techdosedaily-core' ), admin_url( 'options-reading.php' ) )
		: array( 'info', __( 'Search engines', 'techdosedaily-core' ), __( 'Indexing is discouraged — switch it on at launch (Settings → Reading).', 'techdosedaily-core' ), admin_url( 'options-reading.php' ) );
	return $out;
}

function tdd_core_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to change site settings.', 'techdosedaily-core' ) );
	}
	$routes  = tdd_core_contact_routes();
	$inboxes = (array) get_option( 'tdd_contact_inboxes', array() );
	$notes   = (array) get_option( 'tdd_contact_notes', array() );
	$exp     = (array) get_option( 'tdd_contact_expectations', array() );
	$win     = function_exists( 'tdd_core_most_read_windows' ) ? tdd_core_most_read_windows() : array( 'home' => 24, 'section' => 168, 'author' => 720 );
	$labels  = array( 'correction' => __( 'Corrections — standards desk', 'techdosedaily-core' ) );
	?>
	<div class="wrap tdd-form-section">
		<h1><?php esc_html_e( 'Tech Dose Daily — Site settings', 'techdosedaily-core' ); ?></h1>
		<?php settings_errors(); ?>
		<h2 id="tdd-ready" style="border-top:0;margin-top:16px;padding-top:0"><?php esc_html_e( 'Launch readiness', 'techdosedaily-core' ); ?></h2>
		<div class="tdd-ready"><ul>
			<?php foreach ( tdd_core_launch_checks() as [ $state, $title, $detail, $link ] ) : ?>
				<li class="is-<?php echo esc_attr( $state ); ?>"><span><strong><?php echo '' !== $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ); ?></strong>
				<span class="screen-reader-text"><?php echo esc_html( 'ok' === $state ? __( '(done)', 'techdosedaily-core' ) : ( 'todo' === $state ? __( '(to do)', 'techdosedaily-core' ) : __( '(note)', 'techdosedaily-core' ) ) ); ?></span>
				<small><?php echo esc_html( $detail ); ?></small></span></li>
			<?php endforeach; ?>
		</ul></div>

		<form method="post" action="options.php">
			<?php settings_fields( TDD_CORE_SETTINGS ); ?>

			<h2 id="tdd-contact"><?php esc_html_e( 'Contact', 'techdosedaily-core' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Each Contact page route is delivered to its own role inbox. Inboxes are never shown on the site. Without an inbox a route goes to the site admin email.', 'techdosedaily-core' ); ?></p>
			<table class="form-table" role="presentation">
				<?php foreach ( $routes as $k => $r ) : ?>
				<tr>
					<th scope="row"><label for="tdd-in-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $labels[ $k ] ?? $r['title'] ); ?></label></th>
					<td>
						<input type="email" class="regular-text" id="tdd-in-<?php echo esc_attr( $k ); ?>" name="tdd_contact_inboxes[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( (string) ( $inboxes[ $k ] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
						<?php if ( ! empty( $inboxes[ $k ] ) && tdd_core_is_sample_address( $inboxes[ $k ] ) ) : ?><p class="description tdd-missing"><?php esc_html_e( 'Sample address — replace before launch.', 'techdosedaily-core' ); ?></p><?php endif; ?>
						<p><label for="tdd-note-<?php echo esc_attr( $k ); ?>" class="description"><?php esc_html_e( 'Note under the link (optional):', 'techdosedaily-core' ); ?></label>
						<input type="text" class="regular-text" id="tdd-note-<?php echo esc_attr( $k ); ?>" name="tdd_contact_notes[<?php echo esc_attr( $k ); ?>]" maxlength="80" value="<?php echo esc_attr( (string) ( $notes[ $k ] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Answered as capacity allows', 'techdosedaily-core' ); ?>"></p>
					</td>
				</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><label for="tdd-from"><?php esc_html_e( 'Send contact mail from', 'techdosedaily-core' ); ?></label></th>
					<td><input type="email" class="regular-text" id="tdd-from" name="tdd_contact_from" value="<?php echo esc_attr( (string) get_option( 'tdd_contact_from', '' ) ); ?>" placeholder="<?php echo esc_attr( 'newsroom@' . preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ); ?>">
					<p class="description"><?php esc_html_e( 'A mailbox on the site’s own domain. The reader’s address is only ever used as Reply-To.', 'techdosedaily-core' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'What to expect', 'techdosedaily-core' ); ?></th>
					<td>
						<div class="tdd-repeat" data-tdd-repeat>
							<input type="hidden" name="tdd_contact_expectations" value="">
							<div data-rows>
								<?php foreach ( array_values( $exp ) as $i => $row ) : ?>
								<div class="tdd-repeat__row"><input type="text" aria-label="<?php esc_attr_e( 'Route', 'techdosedaily-core' ); ?>" name="tdd_contact_expectations[<?php echo (int) $i; ?>][title]" value="<?php echo esc_attr( (string) ( $row['title'] ?? '' ) ); ?>"><input type="text" aria-label="<?php esc_attr_e( 'Commitment', 'techdosedaily-core' ); ?>" name="tdd_contact_expectations[<?php echo (int) $i; ?>][text]" value="<?php echo esc_attr( (string) ( $row['text'] ?? '' ) ); ?>"><button type="button" class="button-link button-link-delete" data-remove><?php esc_html_e( 'Remove', 'techdosedaily-core' ); ?></button></div>
								<?php endforeach; ?>
							</div>
							<template><div class="tdd-repeat__row"><input type="text" aria-label="<?php esc_attr_e( 'Route', 'techdosedaily-core' ); ?>" name="tdd_contact_expectations[__i__][title]" placeholder="<?php esc_attr_e( 'Corrections', 'techdosedaily-core' ); ?>"><input type="text" aria-label="<?php esc_attr_e( 'Commitment', 'techdosedaily-core' ); ?>" name="tdd_contact_expectations[__i__][text]" placeholder="<?php esc_attr_e( 'Reviewed promptly by an editor.', 'techdosedaily-core' ); ?>"><button type="button" class="button-link button-link-delete" data-remove><?php esc_html_e( 'Remove', 'techdosedaily-core' ); ?></button></div></template>
							<p><button type="button" class="button" data-add><?php esc_html_e( 'Add a line', 'techdosedaily-core' ); ?></button></p>
						</div>
						<p class="description"><?php esc_html_e( 'Only commitments the desk can keep. With no lines, the box is hidden.', 'techdosedaily-core' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tdd-tip"><?php esc_html_e( 'Secure tip instructions', 'techdosedaily-core' ); ?></label></th>
					<td><textarea id="tdd-tip" name="tdd_secure_tip" rows="4" class="large-text"><?php echo esc_textarea( (string) get_option( 'tdd_secure_tip', '' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Only once a real secure channel works (e.g. Signal number, SecureDrop address). Links allowed. Leave empty and the page says no secure channel exists yet.', 'techdosedaily-core' ); ?></p></td>
				</tr>
			</table>

			<h2 id="tdd-partners"><?php esc_html_e( 'Partnerships', 'techdosedaily-core' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="tdd-kit"><?php esc_html_e( 'Media kit (PDF) URL', 'techdosedaily-core' ); ?></label></th>
					<td><input type="url" class="regular-text" id="tdd-kit" name="tdd_media_kit_url" value="<?php echo esc_attr( (string) get_option( 'tdd_media_kit_url', '' ) ); ?>" placeholder="https://">
					<p class="description"><?php esc_html_e( 'Used by the Partnerships route. Empty = the link is hidden. Upload the PDF to the Media Library and paste its URL.', 'techdosedaily-core' ); ?></p></td>
				</tr>
			</table>

			<h2 id="tdd-newsletter"><?php esc_html_e( 'Newsletter', 'techdosedaily-core' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Provider', 'techdosedaily-core' ); ?></th>
					<td><?php
					$prov = function_exists( 'tdd_core_newsletter_provider' ) ? tdd_core_newsletter_provider() : null;
					echo esc_html( $prov ? ( 'mailpoet' === $prov->id() ? 'MailPoet' : ( 'none' === $prov->id() ? __( 'None — sign-up shows “temporarily unavailable”', 'techdosedaily-core' ) : $prov->id() ) ) : '—' );
					echo $prov && $prov->available() ? ' <span class="tdd-chip tdd-chip--placed">' . esc_html__( 'Ready', 'techdosedaily-core' ) . '</span>' : ' <span class="tdd-chip">' . esc_html__( 'Not ready', 'techdosedaily-core' ) . '</span>';
					?><p class="description"><?php esc_html_e( 'Sign-ups go through Core’s provider adapter; switching provider later doesn’t change the forms.', 'techdosedaily-core' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="tdd-mplist"><?php esc_html_e( 'MailPoet list', 'techdosedaily-core' ); ?></label></th>
					<td><?php
					$lists = array();
					if ( class_exists( '\MailPoet\API\API' ) ) {
						try {
							$lists = \MailPoet\API\API::MP( 'v1' )->getLists();
						} catch ( \Throwable $e ) {
							$lists = array();
						}
					}
					$cur = (int) get_option( 'tdd_newsletter_mailpoet_list', 0 );
					if ( $lists ) {
						echo '<select id="tdd-mplist" name="tdd_newsletter_mailpoet_list"><option value="0">' . esc_html__( '— Choose a list —', 'techdosedaily-core' ) . '</option>';
						foreach ( $lists as $l ) {
							echo '<option value="' . esc_attr( (string) $l['id'] ) . '"' . selected( $cur, (int) $l['id'], false ) . '>' . esc_html( $l['name'] ) . '</option>';
						}
						echo '</select>';
					} else {
						echo '<input type="number" min="0" class="small-text" id="tdd-mplist" name="tdd_newsletter_mailpoet_list" value="' . esc_attr( (string) $cur ) . '">';
					}
					?><p class="description"><?php esc_html_e( 'Turn on double opt-in in MailPoet → Settings → Sign-up confirmation (decided for production).', 'techdosedaily-core' ); ?></p></td>
				</tr>
			</table>

			<h2 id="tdd-publishing"><?php esc_html_e( 'Publishing', 'techdosedaily-core' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="tdd-bh"><?php esc_html_e( 'Default Breaking length', 'techdosedaily-core' ); ?></label></th>
					<td><input type="number" min="1" max="72" class="small-text" id="tdd-bh" name="tdd_breaking_hours" value="<?php echo esc_attr( (string) tdd_core_breaking_hours() ); ?>"> <?php esc_html_e( 'hours', 'techdosedaily-core' ); ?>
					<p class="description"><?php esc_html_e( 'Editors can change the end time per story.', 'techdosedaily-core' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Most Read windows', 'techdosedaily-core' ); ?></th>
					<td><fieldset><legend class="screen-reader-text"><?php esc_html_e( 'Most Read windows', 'techdosedaily-core' ); ?></legend>
					<?php foreach ( array( 'home' => __( 'Homepage and article sidebar', 'techdosedaily-core' ), 'section' => __( 'Section pages', 'techdosedaily-core' ), 'author' => __( 'Author pages', 'techdosedaily-core' ) ) as $k => $l ) : ?>
						<p><label for="tdd-w-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></label> <input type="number" min="1" max="2160" class="small-text" id="tdd-w-<?php echo esc_attr( $k ); ?>" name="tdd_core_most_read_windows[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( (string) (int) $win[ $k ] ); ?>"> <?php esc_html_e( 'hours', 'techdosedaily-core' ); ?></p>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Counted anonymously; the module hides itself until there is enough real data.', 'techdosedaily-core' ); ?></p></fieldset></td>
				</tr>
			</table>

			<h2 id="tdd-schema"><?php esc_html_e( 'Structured data', 'techdosedaily-core' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Owner', 'techdosedaily-core' ); ?></th>
					<td><fieldset><legend class="screen-reader-text"><?php esc_html_e( 'Structured data owner', 'techdosedaily-core' ); ?></legend>
						<label><input type="radio" name="tdd_core_schema_owner" value="yoast" <?php checked( 'yoast', tdd_core_schema_setting() ); ?>> <?php esc_html_e( 'Yoast SEO graph', 'techdosedaily-core' ); ?></label><br>
						<label><input type="radio" name="tdd_core_schema_owner" value="core" <?php checked( 'core', tdd_core_schema_setting() ); ?>> <?php esc_html_e( 'TechDoseDaily Core news graph (NewsArticle, Person, NewsMediaOrganization…)', 'techdosedaily-core' ); ?></label></fieldset>
						<?php
						$owner = tdd_core_schema_owner();
						if ( 'core' === $owner && 'yoast' === tdd_core_schema_setting() ) {
							echo '<p class="tdd-note is-warn">' . esc_html__( 'Yoast SEO is not active, so the Core graph is printed instead. The site is never without structured data.', 'techdosedaily-core' ) . '</p>';
						}
						?>
						<p class="description"><strong><?php esc_html_e( 'Printing now:', 'techdosedaily-core' ); ?></strong> <?php echo esc_html( 'core' === $owner ? ( tdd_core_yoast_active() ? __( 'TechDoseDaily Core graph (Yoast JSON-LD switched off)', 'techdosedaily-core' ) : __( 'TechDoseDaily Core graph', 'techdosedaily-core' ) ) : __( 'Yoast SEO graph (Core prints none)', 'techdosedaily-core' ) ); ?>.
						<?php esc_html_e( 'Exactly one graph is printed. Titles, descriptions, canonicals, robots, social tags and sitemaps stay with Yoast either way.', 'techdosedaily-core' ); ?></p></td>
				</tr>
			</table>

			<?php submit_button( __( 'Save settings', 'techdosedaily-core' ) ); ?>
		</form>
	</div>
	<?php
}
