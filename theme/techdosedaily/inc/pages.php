<?php
/**
 * Phase 4 page modules: policy components, About components, Contact page and form,
 * Newsletter page and the 404 page. Approved markup and class names only.
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

/** Rows attribute → list of associative arrays with the given keys (trimmed, empty rows dropped). */
function tdd_rows( $rows, array $keys ): array {
	$out = array();
	foreach ( (array) $rows as $r ) {
		if ( ! is_array( $r ) ) {
			continue;
		}
		$row = array();
		foreach ( $keys as $k ) {
			$row[ $k ] = trim( (string) ( $r[ $k ] ?? '' ) );
		}
		if ( '' !== $row[ $keys[0] ] ) {
			$out[] = $row;
		}
	}
	return $out;
}

/** Inline HTML allowed in short editorial strings (links, emphasis). */
function tdd_inline_kses( string $html ): string {
	return wp_kses( $html, array( 'a' => array( 'href' => true ), 'b' => array(), 'strong' => array(), 'em' => array(), 'i' => array() ) );
}

/** Text links to policies that exist (EditorialPrinciples / NewsletterPromise .tdd-polinks). */
function tdd_policy_links( array $paths ): string {
	$out = '';
	foreach ( $paths as $path ) {
		$url = tdd_static_url( $path );
		if ( '' === $url ) {
			continue;
		}
		$page = 'privacy-policy' === $path && (int) get_option( 'wp_page_for_privacy_policy' ) ? get_post( (int) get_option( 'wp_page_for_privacy_policy' ) ) : get_page_by_path( $path );
		$out .= '<a href="' . esc_url( $url ) . '">' . esc_html( $page ? get_the_title( $page ) : $path ) . ' →</a>';
	}
	return '' === $out ? '' : '<div class="tdd-polinks">' . $out . '</div>';
}

/* ---------- Policy components ---------- */

/** PolicyCallout: "In short" (primary) or neutral disclosure. */
function tdd_policy_callout( string $title, string $content, bool $neutral ): string {
	if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
		return '';
	}
	$content = str_replace( array( ' tdd-list"', '"tdd-list ', ' class="tdd-list"' ), array( '"', '"', '' ), $content ); // Callout lists use callout styling.
	return '<div class="tdd-pcallout' . ( $neutral ? ' tdd-pcallout--neutral' : '' ) . '" role="note" aria-label="' . esc_attr( $title ) . '"><div class="tdd-pcallout__t">' . esc_html( $title ) . '</div>' . $content . '</div>';
}

/** PolicyNotice: important rule, or "What changed on …". */
function tdd_policy_notice( string $title, string $content, bool $change ): string {
	$text = trim( preg_replace( '#</?p[^>]*>#', ' ', $content ) );
	if ( '' === $title && '' === trim( wp_strip_all_tags( $text ) ) ) {
		return '';
	}
	return '<div class="tdd-pnotice' . ( $change ? ' tdd-pnotice--change' : '' ) . '" role="note">' . ( '' !== $title ? '<b>' . esc_html( $title ) . '</b>' : '' ) . wp_kses_post( $text ) . '</div>';
}

/** Definition list (Labels and formats, partnership options). */
function tdd_deflist( array $rows ): string {
	$rows = tdd_rows( $rows, array( 'term', 'text' ) );
	if ( ! $rows ) {
		return '<!--tdd:empty-->';
	}
	$dl = '';
	foreach ( $rows as $r ) {
		$dl .= '<dt>' . esc_html( $r['term'] ) . '</dt><dd>' . tdd_inline_kses( $r['text'] ) . '</dd>';
	}
	return '<dl class="tdd-deflist">' . $dl . '</dl>';
}

/* ---------- About components ---------- */

/** CoverageList: sections with stories, in navigation order, with their one-line description. */
function tdd_coverage_list( array $exclude ): string {
	$order = function_exists( 'tdd_core_default_sections' ) ? array_keys( tdd_core_default_sections() ) : array();
	$terms = get_terms( array( 'taxonomy' => 'category', 'parent' => 0, 'hide_empty' => true ) );
	if ( is_wp_error( $terms ) ) {
		return '<!--tdd:empty-->';
	}
	$pos = static fn( WP_Term $t ): int => false !== ( $i = array_search( $t->slug, $order, true ) ) ? (int) $i : 99;
	usort( $terms, static fn( $a, $b ) => $pos( $a ) <=> $pos( $b ) );
	$li = '';
	foreach ( $terms as $t ) {
		if ( 'uncategorized' === $t->slug || in_array( $t->slug, $exclude, true ) ) {
			continue;
		}
		$line = trim( (string) get_term_meta( $t->term_id, 'tdd_short_description', true ) );
		$line = '' !== $line ? $line : wp_trim_words( wp_strip_all_tags( $t->description ), 14 );
		$li  .= '<li><a href="' . esc_url( get_term_link( $t ) ) . '"><b>' . esc_html( $t->name ) . '</b>' . ( '' !== $line ? '<span>' . esc_html( $line ) . '</span>' : '' ) . '</a></li>';
	}
	return '' === $li ? '<!--tdd:empty-->' : '<ul class="tdd-cov">' . $li . '</ul>';
}

/** EditorialPrinciples (01–04) + links to the full policies. */
function tdd_principles( array $rows ): string {
	$rows = tdd_rows( $rows, array( 'title', 'text' ) );
	if ( ! $rows ) {
		return '<!--tdd:empty-->';
	}
	$li = '';
	foreach ( $rows as $r ) {
		$li .= '<li><b>' . esc_html( $r['title'] ) . '</b><span>' . esc_html( $r['text'] ) . '</span></li>';
	}
	return '<ol class="tdd-princ">' . $li . '</ol>' . tdd_policy_links( array( 'editorial-standards', 'source-policy', 'corrections-policy' ) );
}

/** TeamCard list: real people who opted in (user setting "Show on About"), in their set order. */
function tdd_team(): string {
	$users = get_users(
		array(
			'meta_key'   => 'tdd_show_on_about', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	if ( ! $users ) {
		return '<!--tdd:empty-->';
	}
	usort( $users, static fn( $a, $b ) => (int) get_user_meta( $a->ID, 'tdd_about_order', true ) <=> (int) get_user_meta( $b->ID, 'tdd_about_order', true ) );
	$li = '';
	foreach ( $users as $u ) {
		$photo = (int) get_user_meta( $u->ID, 'tdd_photo', true );
		$img   = $photo ? wp_get_attachment_image( $photo, 'tdd-1x1', false, array( 'alt' => '', 'loading' => 'lazy' ) ) : '';
		$role  = trim( (string) get_user_meta( $u->ID, 'tdd_title', true ) );
		$cov   = trim( (string) get_user_meta( $u->ID, 'tdd_short_bio', true ) );
		$link  = count_user_posts( $u->ID, 'post', true ) > 0
			/* translators: %s: person's name. */
			? '<a class="tdd-tcard__link" href="' . esc_url( get_author_posts_url( $u->ID ) ) . '" aria-label="' . esc_attr( sprintf( __( '%s: author page', 'techdosedaily' ), $u->display_name ) ) . '">' . esc_html__( 'Author page →', 'techdosedaily' ) . '</a>'
			: '';
		$li .= '<li class="tdd-tcard"><div class="tdd-tcard__ph' . ( '' !== $img ? ' has-photo' : '' ) . '" aria-hidden="true">' . ( '' !== $img ? $img : esc_html( tdd_initials( $u->display_name ) ) ) . '</div><div class="tdd-tcard__body"><span class="tdd-tcard__name">' . esc_html( $u->display_name ) . '</span>'
			. ( '' !== $role ? '<span class="tdd-tcard__role">' . esc_html( $role ) . '</span>' : '' ) . ( '' !== $cov ? '<span class="tdd-tcard__cov">' . esc_html( $cov ) . '</span>' : '' ) . $link . '</div></li>';
	}
	return '<ul class="tdd-team">' . $li . '</ul>';
}

/** FundingDisclosure: sources with Planned / Active status, exactly as the newsroom states them. */
function tdd_funding( array $rows ): string {
	$rows = tdd_rows( $rows, array( 'name', 'status', 'text' ) );
	if ( ! $rows ) {
		return '<!--tdd:empty-->';
	}
	$li = '';
	foreach ( $rows as $r ) {
		$active = 'active' === strtolower( $r['status'] );
		$li    .= '<li><b>' . esc_html( $r['name'] ) . '</b><span class="tdd-fund__st' . ( $active ? ' tdd-fund__st--active' : '' ) . '">' . esc_html( $active ? __( 'Active', 'techdosedaily' ) : __( 'Planned', 'techdosedaily' ) ) . '</span>' . ( '' !== $r['text'] ? '<span>' . esc_html( $r['text'] ) . '</span>' : '' ) . '</li>';
	}
	return '<ul class="tdd-fund">' . $li . '</ul>';
}

/* ---------- Contact ---------- */

/** The published contact page URL with a topic preselected (#form). */
function tdd_contact_url( string $topic = '' ): string {
	$url = tdd_page_url( 'contact' );
	if ( '' === $url ) {
		return '';
	}
	return ( '' !== $topic ? add_query_arg( 'topic', $topic, $url ) : $url ) . '#form';
}

/**
 * ContactRoute list.
 *
 * @param string   $variant full | compact
 * @param string[] $only    Route keys to show (compact on About).
 */
function tdd_contact_routes_html( string $variant = 'full', array $only = array() ): string {
	if ( ! function_exists( 'tdd_core_contact_routes' ) ) {
		return '';
	}
	$contact = tdd_page_url( 'contact' );
	$li      = '';
	foreach ( tdd_core_contact_routes() as $key => $r ) {
		if ( $only && ! in_array( $key, $only, true ) ) {
			continue;
		}
		$main = '<div class="tdd-route__main"><span class="tdd-route__t">' . esc_html( $r['title'] ) . '</span><span class="tdd-route__d">' . esc_html( $r['desc'] ) . '</span></div>';
		if ( 'compact' === $variant ) {
			if ( '' === $contact ) {
				continue;
			}
			/* translators: %s: route name. */
			$acts = '<a href="' . esc_url( tdd_contact_url( $key ) ) . '" aria-label="' . esc_attr( sprintf( __( 'Contact %s', 'techdosedaily' ), $r['title'] ) ) . '">' . esc_html__( 'Contact page →', 'techdosedaily' ) . '</a>';
		} else {
			$acts = '<a href="?topic=' . esc_attr( $key ) . '#form" data-tdd-topic="' . esc_attr( $key ) . '">' . esc_html( $r['cta'] ) . '</a>';
			$sec  = '';
			if ( '#secure' === $r['policy'] ) {
				$sec = '<a href="#secure">' . esc_html( $r['policy_label'] ) . '</a>';
			} elseif ( 'media-kit' === $r['policy'] ) {
				$kit = esc_url( (string) get_option( 'tdd_media_kit_url', '' ) );
				$sec = '' !== $kit ? '<a href="' . $kit . '">' . esc_html( $r['policy_label'] ) . '</a>' : '';
			} elseif ( '' !== $r['policy'] && '' !== tdd_static_url( $r['policy'] ) ) {
				$sec = '<a href="' . esc_url( tdd_static_url( $r['policy'] ) ) . '">' . esc_html( $r['policy_label'] ) . '</a>';
			}
			$note  = tdd_core_contact_note( $key );
			$acts .= $sec . ( '' !== $note ? '<small>' . esc_html( $note ) . '</small>' : '' );
		}
		$li .= '<li class="tdd-route">' . $main . '<div class="tdd-route__acts">' . $acts . '</div></li>';
	}
	return '' === $li ? '<!--tdd:empty-->' : '<ul class="tdd-routes' . ( 'compact' === $variant ? ' tdd-route--compact' : '' ) . '">' . $li . '</ul>';
}

/** ContactCTA (moved to the end of the page by the static template). Only existing links. */
function tdd_contact_cta( array $a ): string {
	$acts = '';
	foreach ( array( array( 'primaryLabel', 'primaryUrl', 'primary' ), array( 'secondaryLabel', 'secondaryUrl', 'secondary' ) ) as [ $l, $u, $kind ] ) {
		$label = trim( (string) ( $a[ $l ] ?? '' ) );
		$url   = trim( (string) ( $a[ $u ] ?? '' ) );
		if ( '' === $label || '' === $url ) {
			continue;
		}
		if ( str_starts_with( $url, 'contact:' ) ) { // contact:<topic> → the contact form with that topic.
			$url = tdd_contact_url( substr( $url, 8 ) );
		} elseif ( str_starts_with( $url, 'page:' ) ) { // page:<path> → only if the page exists.
			$url = tdd_static_url( substr( $url, 5 ) );
		}
		if ( '' !== $url ) {
			$acts .= '<a class="tdd-btn tdd-btn--' . $kind . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
	}
	if ( '' === $acts || '' === trim( (string) ( $a['title'] ?? '' ) ) ) {
		return '';
	}
	return '<!--tdd:cta--><aside class="tdd-contactcta" aria-label="' . esc_attr( $a['title'] ) . '"><div><b>' . esc_html( $a['title'] ) . '</b>' . ( ! empty( $a['text'] ) ? '<p>' . esc_html( $a['text'] ) . '</p>' : '' ) . '</div><div class="tdd-contactcta__acts">' . $acts . '</div></aside><!--/tdd:cta-->';
}

/** SecureTipNotice. The dashed box holds real instructions, or says plainly that none exist yet. */
function tdd_secure_tip_html(): string {
	$tip = function_exists( 'tdd_core_secure_tip' ) ? tdd_core_secure_tip() : '';
	$box = '' !== $tip ? '<div class="tdd-stip__ph tdd-stip__ph--live">' . $tip . '</div>' : '<div class="tdd-stip__ph">' . esc_html__( 'We don’t offer a secure tip channel yet. Until we do, please don’t send sensitive information through this site.', 'techdosedaily' ) . '</div>';
	return '<aside class="tdd-stip" id="secure" aria-labelledby="stip-t"><h2 class="tdd-stip__t" id="stip-t">' . esc_html__( 'Have a sensitive tip?', 'techdosedaily' ) . '</h2><p>' . esc_html__( 'Don’t use this form for information that could put you at risk.', 'techdosedaily' ) . '</p>' . $box
		. '<ul><li>' . esc_html__( 'Use a personal device and network, not a work one.', 'techdosedaily' ) . '</li><li>' . esc_html__( 'Don’t include your name unless you want to.', 'techdosedaily' ) . '</li></ul></aside>';
}

/** "What to expect": only commitments the newsroom has written down (Core setting). */
function tdd_contact_expectations_html(): string {
	$rows = function_exists( 'tdd_core_contact_expectations' ) ? tdd_core_contact_expectations() : array();
	if ( ! $rows ) {
		return '';
	}
	$li = '';
	foreach ( $rows as $r ) {
		$li .= '<li><b>' . esc_html( $r['title'] ) . '</b>' . esc_html( $r['text'] ) . '</li>';
	}
	return '<section class="tdd-cpage__resp" aria-labelledby="resp-t"><h2 class="tdd-aside-t" id="resp-t">' . esc_html__( 'What to expect', 'techdosedaily' ) . '</h2><ul class="tdd-resp">' . $li . '</ul></section>';
}

/** ContactForm with all approved states (default, error + summary, submitting, success). */
function tdd_contact_form(): string {
	if ( ! function_exists( 'tdd_core_contact_routes' ) ) {
		return '';
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display state (?tdd_cf=sent after PRG, ?topic= preselect).
	$state = isset( $_GET['tdd_cf'] ) && 'sent' === $_GET['tdd_cf'] ? 'sent' : '';
	$topic = isset( $_GET['topic'] ) ? sanitize_key( wp_unslash( $_GET['topic'] ) ) : '';
	// phpcs:enable
	// No-JS failure in this same request: Core re-supplies the entered values (never stored).
	$flash = function_exists( 'tdd_core_contact_posted' ) ? tdd_core_contact_posted() : array();
	if ( $flash ) {
		$state = $flash['state'];
	}
	$routes = tdd_core_contact_routes();
	$home   = '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Back to Tech Dose Daily →', 'techdosedaily' ) . '</a>';
	if ( 'sent' === $state ) {
		return '<div id="form"><div class="tdd-cform__ok" role="status" tabindex="-1"><b>' . esc_html__( 'Message sent', 'techdosedaily' ) . '</b>' . esc_html( tdd_core_contact_success( isset( $routes[ $topic ] ) ? $topic : 'general' ) ) . ' ' . $home . '</div></div>';
	}
	$values = $flash['values'] ?? array();
	$errs   = array_flip( $flash['errors'] ?? array() );
	$msgs   = tdd_core_contact_messages();
	$labels = array( 'name' => __( 'Name', 'techdosedaily' ), 'email' => __( 'Email', 'techdosedaily' ), 'topic' => __( 'Topic', 'techdosedaily' ), 'url' => __( 'Article URL', 'techdosedaily' ), 'message' => __( 'Message', 'techdosedaily' ) );
	$topic  = isset( $values['topic'] ) && isset( $routes[ $values['topic'] ] ) ? $values['topic'] : ( isset( $routes[ $topic ] ) ? $topic : 'editorial' );
	$req    = '<span class="tdd-field__req">' . esc_html__( '(required)', 'techdosedaily' ) . '</span>';
	$opt    = '<span class="tdd-field__req">' . esc_html__( '(optional)', 'techdosedaily' ) . '</span>';
	$field  = static function ( string $k, string $control, string $hint = '' ) use ( $errs, $msgs, $labels, $req, $opt ): string {
		$err  = isset( $errs[ $k ] );
		$desc = trim( ( '' !== $hint ? 'cf-' . $k . '-h ' : '' ) . ( $err ? 'cf-' . $k . '-e' : '' ) );
		// Placeholders are filled in the opening tag only, never inside submitted text.
		$cut     = strpos( $control, '>' ) + 1;
		$tag     = str_replace( array( '{desc}', '{invalid}' ), array( '' !== $desc ? ' aria-describedby="' . esc_attr( $desc ) . '"' : '', $err ? ' aria-invalid="true"' : '' ), substr( $control, 0, $cut ) );
		$control = $tag . substr( $control, $cut );
		return '<div class="tdd-field' . ( $err ? ' is-error' : '' ) . '" data-field="' . esc_attr( $k ) . '"><label class="tdd-field__label" for="cf-' . esc_attr( $k ) . '">' . esc_html( $labels[ $k ] ) . ' ' . ( 'url' === $k ? $opt : $req ) . '</label>' . $control
			. ( '' !== $hint ? '<span class="tdd-field__hint" id="cf-' . esc_attr( $k ) . '-h">' . esc_html( $hint ) . '</span>' : '' )
			. '<span class="tdd-field__err" id="cf-' . esc_attr( $k ) . '-e"' . ( $err ? '' : ' hidden' ) . '>' . esc_html( $err ? $msgs[ $k ] : '' ) . '</span></div>';
	};
	$options = '';
	foreach ( $routes as $key => $r ) {
		$options .= '<option value="' . esc_attr( $key ) . '"' . selected( $topic, $key, false ) . '>' . esc_html( $r['topic'] ) . '</option>';
	}
	$summary = '';
	if ( $errs ) {
		$items = '';
		foreach ( array_keys( $errs ) as $k ) {
			$items .= '<li><a href="#cf-' . esc_attr( $k ) . '">' . esc_html( $labels[ $k ] . ': ' . lcfirst( $msgs[ $k ] ) ) . '</a></li>';
		}
		/* translators: %d: number of fields. */
		$summary = '<div class="tdd-errsum" role="alert" tabindex="-1"><b>' . esc_html( sprintf( _n( 'Check %d field', 'Check %d fields', count( $errs ), 'techdosedaily' ), count( $errs ) ) ) . '</b><ul>' . $items . '</ul></div>';
	} elseif ( 'error' === $state || 'retry' === $state ) {
		$summary = '<div class="tdd-errsum" role="alert" tabindex="-1"><b>' . esc_html__( 'Your message wasn’t sent', 'techdosedaily' ) . '</b>' . esc_html( 'retry' === $state ? __( 'Please wait a moment and send it again. Your message is still in the form below.', 'techdosedaily' ) : __( 'Please try again in a few minutes. Your message is still in the form below.', 'techdosedaily' ) ) . '</div>';
	}
	$privacy = tdd_static_url( 'privacy-policy' );
	ob_start();
	?>
	<form class="tdd-cform" id="form" method="post" action="<?php echo esc_url( get_permalink() . '#form' ); ?>" data-rest="<?php echo esc_url( rest_url( 'tdd/v1/contact' ) ); ?>" aria-labelledby="cf-t" data-tdd-contact data-msgs="<?php echo esc_attr( wp_json_encode( $msgs ) ); ?>" data-labels="<?php echo esc_attr( wp_json_encode( $labels ) ); ?>" data-home="<?php echo esc_url( home_url( '/' ) ); ?>">
		<h2 class="tdd-cform__title" id="cf-t"><?php esc_html_e( 'Send us a message', 'techdosedaily' ); ?></h2>
		<p class="tdd-cform__intro"><?php esc_html_e( 'All fields have visible labels. We only use your details to reply.', 'techdosedaily' ); ?></p>
		<div class="tdd-cform__sum" data-tdd-summary><?php echo $summary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></div>
		<input type="hidden" name="tdd_contact" value="1">
		<input type="hidden" name="tdd_token" value="<?php echo esc_attr( tdd_core_form_token() ); ?>">
		<?php wp_nonce_field( 'tdd_contact', '_tdd_nonce', false ); ?>
		<div class="tdd-hp" aria-hidden="true"><label for="cf-hp">Leave this empty</label><input type="text" id="cf-hp" name="tdd_hp" tabindex="-1" autocomplete="off"></div>
		<fieldset><legend><?php esc_html_e( 'About you', 'techdosedaily' ); ?></legend><div class="tdd-cform__row">
			<?php
			echo $field( 'name', '<input class="tdd-input" id="cf-name" name="cf_name" type="text" autocomplete="name" required maxlength="100" value="' . esc_attr( $values['name'] ?? '' ) . '"{desc}{invalid}>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $field( 'email', '<input class="tdd-input" id="cf-email" name="cf_email" type="email" autocomplete="email" required maxlength="254" value="' . esc_attr( $values['email'] ?? '' ) . '"{desc}{invalid}>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div></fieldset>
		<fieldset><legend><?php esc_html_e( 'Your message', 'techdosedaily' ); ?></legend>
			<?php
			echo $field( 'topic', '<select class="tdd-input tdd-select" id="cf-topic" name="cf_topic" required{desc}{invalid}>' . $options . '</select>', __( 'Routes your message to the right person.', 'techdosedaily' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $field( 'url', '<input class="tdd-input" id="cf-url" name="cf_url" type="url" inputmode="url" maxlength="500" value="' . esc_attr( $values['url'] ?? '' ) . '"{desc}{invalid}>', __( 'For editorial questions and corrections.', 'techdosedaily' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $field( 'message', '<textarea class="tdd-input tdd-textarea" id="cf-message" name="cf_message" required maxlength="5000"{desc}{invalid}>' . esc_textarea( $values['message'] ?? '' ) . '</textarea>', __( 'For corrections, tell us what is wrong and what you believe is right.', 'techdosedaily' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
			<label class="tdd-check"><input type="checkbox" name="cf_not_urgent" value="1"<?php checked( ! empty( $values['not_urgent'] ) ); ?>> <span><?php esc_html_e( 'I understand this form is not for urgent security reports', 'techdosedaily' ); ?></span></label>
		</fieldset>
		<div class="tdd-cform__foot"><p class="tdd-cform__privacy"><?php esc_html_e( 'We don’t add you to any list.', 'techdosedaily' ); ?><?php echo '' !== $privacy ? ' ' . esc_html__( 'See our', 'techdosedaily' ) . ' <a href="' . esc_url( $privacy ) . '">' . esc_html__( 'Privacy Policy', 'techdosedaily' ) . '</a>.' : ''; ?></p><button class="tdd-btn tdd-btn--primary" type="submit"><?php esc_html_e( 'Send message', 'techdosedaily' ); ?></button></div>
	</form>
	<?php
	return (string) ob_get_clean();
}

/** The Contact page (approved ContactDesktop / ContactMobile). */
function tdd_contact_page( WP_Post $p ): string {
	$GLOBALS['tdd_in_static'] = true;
	$extra                    = trim( apply_filters( 'the_content', get_the_content( null, false, $p ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
	$GLOBALS['tdd_in_static'] = false;
	$main = '<h2 class="tdd-cpage__h">' . esc_html__( 'Where to send your message', 'techdosedaily' ) . '</h2>' . str_replace( '<!--tdd:empty-->', '', tdd_contact_routes_html() )
		. ( '' !== $extra ? '<div class="tdd-prose tdd-cpage__extra">' . $extra . '</div>' : '' )
		. '<div class="tdd-cpage__form">' . tdd_contact_form() . '</div>'
		. '<div class="tdd-cpage__rel">' . tdd_related_policies( $p->ID, __( 'Related pages', 'techdosedaily' ), array( 'about', 'editorial-standards', 'corrections-policy', 'privacy-policy', 'advertise', 'source-policy' ) ) . '</div>';
	$aside = tdd_secure_tip_html() . tdd_contact_expectations_html();
	return tdd_breadcrumbs_html() . '<article class="tdd-cpage m-sp">' . tdd_static_header( $p ) . '<div class="tdd-cpage__main">' . $main . '</div><div class="tdd-cpage__aside"><div class="tdd-cpage__aside-inner">' . $aside . '</div></div></article>';
}

/** The contact page is never cached (nonce + per-visitor form state). */
add_action(
	'template_redirect',
	static function () {
		if ( is_page() && 'page-contact' === get_page_template_slug() ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			nocache_headers();
		}
	}
);

/* ---------- Newsletter page ---------- */

/** NewsletterSignup (light panel, or inline without the panel). Same states/JS as the CTA. */
function tdd_newsletter_signup( bool $inline = false ): string {
	static $n = 0;
	++$n;
	$id      = 'tdd-nls-' . $n;
	$privacy = tdd_static_url( 'privacy-policy' );
	$token   = function_exists( 'tdd_core_form_token' ) ? tdd_core_form_token() : '';
	$rest    = function_exists( 'tdd_core_form_token' ) ? rest_url( 'tdd/v1/subscribe' ) : '';
	$state   = isset( $_GET['tdd_nl'] ) ? sanitize_key( wp_unslash( $_GET['tdd_nl'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$msgs    = function_exists( 'tdd_core_newsletter_messages' ) ? tdd_core_newsletter_messages() : array();
	$titles  = function_exists( 'tdd_core_newsletter_titles' ) ? tdd_core_newsletter_titles() : array();
	$status  = '';
	if ( 1 === $n && $state && isset( $msgs[ $state ] ) ) {
		$status = isset( $titles[ $state ] ) ? '<div class="tdd-nlok"><b>' . esc_html( $titles[ $state ] ) . '</b>' . esc_html( $msgs[ $state ] ) . '</div>' : '<p class="tdd-nlsign__note">' . esc_html( $msgs[ $state ] ) . '</p>';
	}
	return '<div class="tdd-nlsign-wrap" data-tdd-newsletter' . ( 1 === $n ? ' id="newsletter"' : '' ) . '><form class="tdd-nlsign' . ( $inline ? ' tdd-nlsign--inline' : '' ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-rest="' . esc_url( $rest ) . '" novalidate aria-label="' . esc_attr__( 'Subscribe to the Tech Dose Daily newsletter', 'techdosedaily' ) . '">'
		. '<input type="hidden" name="action" value="tdd_subscribe"><input type="hidden" name="tdd_token" value="' . esc_attr( $token ) . '">'
		. '<div class="tdd-hp" aria-hidden="true"><label for="' . esc_attr( $id ) . '-hp">Leave this empty</label><input type="text" id="' . esc_attr( $id ) . '-hp" name="tdd_hp" tabindex="-1" autocomplete="off"></div>'
		. '<label class="tdd-field__label" for="' . esc_attr( $id ) . '">' . esc_html__( 'Email address', 'techdosedaily' ) . '</label>'
		. '<div class="tdd-nlsign__row"><input class="tdd-input" id="' . esc_attr( $id ) . '" name="email" type="email" autocomplete="email" inputmode="email" required aria-describedby="' . esc_attr( $id ) . '-fine"><button class="tdd-btn tdd-btn--primary" type="submit">' . esc_html__( 'Subscribe', 'techdosedaily' ) . '</button></div>'
		. '<p class="tdd-nlsign__fine" id="' . esc_attr( $id ) . '-fine">' . esc_html__( 'Free. Unsubscribe anytime. We only use your email to send the newsletter.', 'techdosedaily' ) . ( '' !== $privacy ? ' <a href="' . esc_url( $privacy ) . '">' . esc_html__( 'Privacy Policy', 'techdosedaily' ) . '</a>' : '' ) . '</p>'
		. '<div class="tdd-nl-status" role="status" aria-live="polite">' . $status . '</div></form></div>';
}

/** NewsletterHero + signup. */
function tdd_newsletter_hero( WP_Post $p, array $facts ): string {
	$deck = tdd_page_field( $p, 'tdd_intro' );
	$li   = '';
	foreach ( $facts as $f ) {
		if ( '' !== trim( (string) $f ) ) {
			$li .= '<li>' . esc_html( $f ) . '</li>';
		}
	}
	return '<div class="tdd-nlpage__crumbs">' . tdd_breadcrumbs_html() . '</div><section class="tdd-nlhero" aria-labelledby="nl-title"><div><div class="tdd-nlhero__kicker">' . tdd_icon( 'mail', '' ) . esc_html__( 'Newsletter', 'techdosedaily' ) . '</div><h1 class="tdd-nlhero__title" id="nl-title">' . esc_html( tdd_static_headline( $p ) ) . '</h1>'
		. ( '' !== $deck ? '<p class="tdd-nlhero__deck">' . esc_html( $deck ) . '</p>' : '' ) . ( '' !== $li ? '<ul class="tdd-nlfacts" aria-label="' . esc_attr__( 'Delivery', 'techdosedaily' ) . '">' . $li . '</ul>' : '' ) . '</div><div>' . tdd_newsletter_signup() . '</div></section>';
}

/** A section on the Newsletter page (.tdd-pgsec with a ruled H2). */
function tdd_pgsec( string $title, string $inner, string $link = '' ): string {
	return '' === $inner ? '' : '<section class="tdd-pgsec" aria-label="' . esc_attr( $title ) . '"><h2 class="tdd-pgsec__h">' . esc_html( $title ) . $link . '</h2>' . $inner . '</section>';
}

/** NewsletterBenefit list. */
function tdd_nl_benefits( string $title, array $rows ): string {
	$rows = tdd_rows( $rows, array( 'title', 'text' ) );
	$li   = '';
	foreach ( $rows as $r ) {
		$li .= '<li><b>' . esc_html( $r['title'] ) . '</b><span>' . esc_html( $r['text'] ) . '</span></li>';
	}
	return '' === $li ? '' : tdd_pgsec( $title, '<ol class="tdd-nlben">' . $li . '</ol>' );
}

/** NewsletterPromise list + policy links. */
function tdd_nl_promise( string $title, array $rows ): string {
	$check = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
	$li    = '';
	foreach ( (array) $rows as $r ) {
		$t = trim( (string) ( is_array( $r ) ? ( $r['text'] ?? '' ) : $r ) );
		if ( '' !== $t ) {
			$li .= '<li>' . $check . '<span>' . esc_html( $t ) . '</span></li>';
		}
	}
	return '' === $li ? '' : tdd_pgsec( $title, '<ul class="tdd-nlprom">' . $li . '</ul>' . tdd_policy_links( array( 'editorial-standards', 'source-policy', 'privacy-policy' ) ) );
}

/** NewsletterFAQ: native details; first open. */
function tdd_faq( string $title, array $rows ): string {
	$rows = tdd_rows( $rows, array( 'q', 'a' ) );
	$out  = '';
	foreach ( $rows as $i => $r ) {
		$out .= '<details' . ( 0 === $i ? ' open' : '' ) . '><summary>' . esc_html( $r['q'] ) . tdd_icon( 'chevron-down', '' ) . '</summary><p>' . tdd_inline_kses( $r['a'] ) . '</p></details>';
	}
	if ( '' === $out ) {
		return '';
	}
	// Approved mobile: "Delivery and questions" — the delivery list moves here from "A look inside".
	$delivery = '';
	foreach ( $GLOBALS['tdd_nl_delivery'] ?? array() as $r ) {
		$delivery .= '<li><b>' . esc_html( $r['title'] ) . '</b>' . esc_html( $r['text'] ) . '</li>';
	}
	if ( '' === $delivery ) {
		return tdd_pgsec( $title, '<div class="tdd-faq">' . $out . '</div>' );
	}
	$h = '<span class="tdd-hide-m">' . esc_html( $title ) . '</span><span class="tdd-only-m">' . esc_html__( 'Delivery and questions', 'techdosedaily' ) . '</span>';
	return '<section class="tdd-pgsec" aria-labelledby="tdd-faq-t"><h2 class="tdd-pgsec__h" id="tdd-faq-t">' . $h . '</h2><ul class="tdd-resp tdd-nlfaq-delivery tdd-only-m is-block">' . $delivery . '</ul><div class="tdd-faq">' . $out . '</div></section>';
}

/**
 * "A look inside": today's Daily Tech Brief items, shown in the approved issue frame. Real
 * stories from the daily_brief placement — never invented issues. Hidden below three items.
 */
function tdd_nl_sample( string $title, string $how, array $delivery ): string {
	$GLOBALS['tdd_nl_delivery'] = tdd_rows( $delivery, array( 'title', 'text' ) ); // Approved mobile shows Delivery with the questions.
	$items = function_exists( 'tdd_core_fill_placement' ) ? tdd_core_fill_placement( 'daily_brief', 10, array( 'fallback' => false ) ) : array();
	if ( count( $items ) < 3 ) {
		return '';
	}
	$li = '';
	foreach ( array_slice( $items, 0, 5 ) as $p ) {
		$deck = tdd_deck( $p );
		$li  .= '<li>' . tdd_labels( $p, array( 'format' => false, 'breaking' => false ) ) . '<a class="tdd-issue__h" href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( function_exists( 'tdd_core_short_title' ) ? tdd_core_short_title( $p ) : get_the_title( $p ) ) . '</a>' . ( '' !== $deck ? '<span class="tdd-issue__t">' . esc_html( $deck ) . '</span>' : '' ) . '</li>';
	}
	$more = count( $items ) > 5 ? '<p class="tdd-issue__more">' . esc_html( sprintf( /* translators: %d: number */ _n( '+ %d more story in the full issue', '+ %d more stories in the full issue', count( $items ) - 5, 'techdosedaily' ), count( $items ) - 5 ) ) . '</p>' : '';
	/* translators: %d: number of items. */
	$head  = sprintf( __( '%d things worth knowing today', 'techdosedaily' ), count( $items ) );
	$issue = '<article class="tdd-issue" aria-label="' . esc_attr__( 'Today’s Daily Tech Brief preview', 'techdosedaily' ) . '"><div class="tdd-issue__tag">' . esc_html__( 'Preview · today’s stories from the Daily Tech Brief', 'techdosedaily' ) . '</div>'
		. '<header class="tdd-issue__head"><img src="' . tdd_asset( 'images/tdd-mark.svg' ) . '" alt="" width="32" height="32"><div><span class="tdd-issue__brand">' . esc_html__( 'DAILY TECH BRIEF', 'techdosedaily' ) . '</span><span class="tdd-issue__date">' . esc_html( wp_date( 'l, F j' ) . ' · ' . get_bloginfo( 'name' ) ) . '</span></div></header>'
		. '<div class="tdd-issue__body"><h3 class="tdd-issue__title">' . esc_html( $head ) . '</h3><ol>' . $li . '</ol>' . $more . '</div>'
		. '<footer class="tdd-issue__foot">' . esc_html__( 'Sponsored placements, when present, are labelled “Sponsored” and kept separate from the editors’ picks.', 'techdosedaily' ) . '</footer></article>';
	$notes = '';
	if ( '' !== trim( $how ) ) {
		$notes .= '<div><h3>' . esc_html__( 'How an issue is built', 'techdosedaily' ) . '</h3><p class="tdd-sp__intro tdd-issue-notes__p">' . esc_html( $how ) . '</p></div>';
	}
	$rows = tdd_rows( $delivery, array( 'title', 'text' ) );
	if ( $rows ) {
		$d = '';
		foreach ( $rows as $r ) {
			$d .= '<li><b>' . esc_html( $r['title'] ) . '</b>' . esc_html( $r['text'] ) . '</li>';
		}
		$notes .= '<div><h3>' . esc_html__( 'Delivery', 'techdosedaily' ) . '</h3><ul class="tdd-resp">' . $d . '</ul></div>';
	}
	return tdd_pgsec( $title, '<div class="tdd-issue-wrap">' . $issue . ( '' !== $notes ? '<div class="tdd-issue-notes tdd-hide-m">' . $notes . '</div>' : '' ) . '</div>' );
}

/** Closing signup line. */
function tdd_newsletter_repeat( string $title, string $text ): string {
	return '<div class="tdd-pgsec tdd-hide-m"><div class="tdd-nlrepeat"><div><b>' . esc_html( $title ) . '</b>' . ( '' !== $text ? '<p>' . esc_html( $text ) . '</p>' : '' ) . '</div>' . tdd_newsletter_signup( true ) . '</div></div>';
}

/* ---------- 404 ---------- */

function tdd_not_found(): string {
	$latest = get_posts( array( 'post_type' => 'post', 'posts_per_page' => 5, 'no_found_rows' => true ) );
	$rows   = '';
	foreach ( $latest as $i => $p ) {
		$ts    = (int) get_post_timestamp( $p );
		$rows .= '<li' . ( 4 === $i ? ' class="tdd-hide-m"' : '' ) . '><a class="tdd-latest-row tdd-latest-row--nothumb" href="' . esc_url( get_permalink( $p ) ) . '"><span class="tdd-latest-row__time">' . tdd_time( $ts, wp_date( 'M j, Y', $ts ) ) . '</span><span class="tdd-latest-row__body">' . tdd_labels( $p, array( 'format' => false, 'breaking' => false ) ) . '<span class="tdd-latest-row__title">' . esc_html( get_the_title( $p ) ) . '</span></span></a></li>';
	}
	$secs = '';
	foreach ( array( 'ai', 'tech', 'cybersecurity', 'developer', 'cloud', 'guides' ) as $slug ) {
		$t = get_term_by( 'slug', $slug, 'category' );
		if ( $t ) {
			$secs .= '<li><a href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a></li>';
		}
	}
	return '<div class="tdd-nf"><section aria-labelledby="nf-t"><div class="tdd-nf__kicker">' . esc_html__( 'Error 404', 'techdosedaily' ) . '</div><h1 class="tdd-nf__title" id="nf-t">' . esc_html__( 'Page not found', 'techdosedaily' ) . '</h1>'
		. '<p class="tdd-nf__text">' . esc_html__( 'The page may have moved, been renamed or no longer exists. Try searching, or start from the latest stories.', 'techdosedaily' ) . '</p>'
		. '<form class="tdd-nfsearch" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '"><label class="tdd-field__label" for="nf-q">' . esc_html__( 'Search Tech Dose Daily', 'techdosedaily' ) . '</label><div class="tdd-search">' . tdd_icon( 'search', '' ) . '<input id="nf-q" name="s" type="search" autocomplete="off"><button class="tdd-btn tdd-btn--primary" type="submit">' . esc_html__( 'Search', 'techdosedaily' ) . '</button></div></form>'
		. '<div class="tdd-nf__acts"><a class="tdd-btn tdd-btn--primary" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Go to homepage', 'techdosedaily' ) . '</a>' . ( $rows ? '<a class="tdd-btn tdd-btn--secondary" href="#latest">' . esc_html__( 'Browse latest stories', 'techdosedaily' ) . '</a>' : '' ) . '</div></section>'
		. ( $rows ? '<section aria-labelledby="nf-l" id="latest"><h2 class="tdd-nf__h" id="nf-l">' . esc_html__( 'Latest stories', 'techdosedaily' ) . '<a class="tdd-hide-m" href="' . esc_url( tdd_latest_url() ) . '">' . esc_html__( 'All latest →', 'techdosedaily' ) . '</a></h2><ul>' . $rows . '</ul></section>' : '' )
		. ( $secs ? '<section aria-labelledby="nf-s"><h2 class="tdd-nf__h" id="nf-s">' . esc_html__( 'Popular sections', 'techdosedaily' ) . '</h2><ul class="tdd-nfsec">' . $secs . '</ul></section>' : '' ) . '</div>';
}

/** 404 title separator (approved "Page not found · Tech Dose Daily"). Robots for the 404: Core seo.php. */
add_filter( 'document_title_separator', static fn( $sep ) => is_404() ? '·' : $sep );
