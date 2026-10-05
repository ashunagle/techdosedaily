<?php
/**
 * Owner decisions of 2026-10-05, applied with WP-CLI (staging now; production at setup). Idempotent.
 *
 *   wp eval-file owner-settings.php --user=<admin login>
 *
 * - Contact: all five routes go to contact@techdosedaily.com (receiving only; routes stay separate so
 *   dedicated inboxes can replace them). The contact sender stays unset until an SMTP mailbox exists.
 * - Section one-liners (tdd_short_description), as approved by the owner.
 * - Newsletter: MailPoet's default list, renamed (its name appears in MailPoet's confirmation email and
 *   subscription-management page); double opt-in on.
 * - Structural pages as EMPTY DRAFTS with their final slugs and templates. No wording is written:
 *   legal and operational copy comes from the newsroom.
 */

$inbox = 'contact@techdosedaily.com';
$boxes = array();
foreach ( array_keys( tdd_core_contact_routes() ) as $route ) {
	$boxes[ $route ] = $inbox;
}
update_option( 'tdd_contact_inboxes', $boxes );
echo 'contact routes: ', implode( ', ', array_keys( $boxes ) ), " → $inbox\n";

$lines = array(
	'ai'            => 'Artificial intelligence models, products, research and policy.',
	'tech'          => 'Broad technology news that doesn’t fit a specialist desk.',
	'software'      => 'Applications, platforms, operating systems and the business of software.',
	'cybersecurity' => 'Vulnerabilities, breaches, defenses and the policies shaping digital security.',
	'startups'      => 'New technology companies, funding, products and the people building them.',
	'big-tech'      => 'The companies whose platforms, products and decisions shape the technology industry.',
	'cloud'         => 'Cloud infrastructure, data centers, enterprise platforms and distributed systems.',
	'developer'     => 'Tools, languages, open source and the craft of building software.',
	'guides'        => 'Practical, tested guidance for choosing and using technology.',
);
foreach ( $lines as $slug => $line ) {
	$t = get_term_by( 'slug', $slug, 'category' );
	if ( ! $t ) {
		echo "!! section not found: $slug\n";
		continue;
	}
	update_term_meta( $t->term_id, 'tdd_short_description', $line );
	echo "one-liner: {$t->name}\n";
}

$list_name = 'Tech Dose Daily Newsletter';
try {
	$api   = \MailPoet\API\API::MP( 'v1' );
	$lists = $api->getLists();
	$list  = null;
	foreach ( $lists as $l ) {
		if ( in_array( $l['name'], array( 'Newsletter mailing list', 'TechDoseDaily Newsletter', $list_name ), true ) ) {
			$list = $l;
			break;
		}
	}
	if ( ! $list ) {
		echo "!! MailPoet default list not found. Lists: ", implode( ', ', wp_list_pluck( $lists, 'name' ) ), "\n";
	} else {
		if ( $list['name'] !== $list_name ) {
			$api->updateList( array( 'id' => $list['id'], 'name' => $list_name, 'description' => $list['description'] ?? '' ) );
		}
		update_option( 'tdd_newsletter_mailpoet_list', (int) $list['id'] );
		\MailPoet\Settings\SettingsController::getInstance()->set( 'signup_confirmation.enabled', true );
		// Owner decision 2026-10-05: no anonymous usage data to MailPoet (Mixpanel/Tracks).
		\MailPoet\Settings\SettingsController::getInstance()->set( 'analytics.enabled', false );
		echo "newsletter list: #{$list['id']} $list_name (double opt-in on)\n";
	}
} catch ( \Throwable $e ) {
	echo '!! MailPoet: ', $e->getMessage(), "\n";
}

$pages = array(
	// slug => [ title, template ]
	'privacy-policy'      => array( 'Privacy Policy', '' ),
	'contact'             => array( 'Contact', 'page-contact' ),
	'newsletter'          => array( 'Newsletter', 'page-newsletter' ),
	'editorial-standards' => array( 'Editorial Standards', '' ),
	'corrections-policy'  => array( 'Corrections Policy', '' ),
	'source-policy'       => array( 'Source Policy', '' ),
	'ai-use-policy'       => array( 'AI Use Policy', '' ),
	'terms'               => array( 'Terms of Use', '' ),
	'advertise'           => array( 'Advertise', 'page-short' ),
);
foreach ( $pages as $slug => [ $title, $template ] ) {
	$found = get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1 ) );
	if ( $found ) {
		echo "page exists: /$slug/ ({$found[0]->post_status}) — left unchanged\n";
		$id = $found[0]->ID;
	} else {
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_name' => $slug, 'post_title' => $title, 'post_content' => '', 'page_template' => $template ), true );
		if ( is_wp_error( $id ) ) {
			echo "!! /$slug/: ", $id->get_error_message(), "\n";
			continue;
		}
		echo "draft created: /$slug/ “{$title}”\n";
	}
	if ( 'privacy-policy' === $slug ) {
		update_option( 'wp_page_for_privacy_policy', (int) $id );
	}
}
