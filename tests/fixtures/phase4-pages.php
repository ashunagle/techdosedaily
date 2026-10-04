<?php
/**
 * LOCAL TEST FIXTURE ONLY — never run on staging or production.
 * Fills the static pages with the approved design copy so they can be compared with the frozen
 * designs: Editorial Standards (long), Advertise (short), About, Contact, Newsletter.
 * Policy wording, inboxes, expectations, funding status and the media kit are SAMPLE values that
 * the newsroom must confirm or replace (see PRE-LAUNCH-PLACEHOLDERS.md). The About team uses only
 * the existing [sample] fixture users; no new identities are created.
 *
 * Run: wp eval-file tests/fixtures/phase4-pages.php --user=admin
 */
if ( ! function_exists( 'tdd_core_contact_routes' ) ) { echo "Activate techdosedaily-core first\n"; return; }

$b    = static fn( string $name, array $attrs = array(), string $inner = '' ) => get_comment_delimited_block_content( $name, $attrs, $inner );
$p    = static fn( string $html ) => $b( 'core/paragraph', array(), '<p>' . $html . '</p>' );
$h2   = static fn( string $id, string $t ) => $b( 'core/heading', array( 'anchor' => $id ), '<h2 class="wp-block-heading" id="' . $id . '">' . $t . '</h2>' );
$h3   = static fn( string $t ) => $b( 'core/heading', array( 'level' => 3 ), '<h3 class="wp-block-heading">' . $t . '</h3>' );
$list = static function ( array $items, bool $ordered = false ) use ( $b ) {
	$li = implode( '', array_map( static fn( $i ) => $b( 'core/list-item', array(), '<li>' . $i . '</li>' ), $items ) );
	$t  = $ordered ? 'ol' : 'ul';
	return $b( 'core/list', $ordered ? array( 'ordered' => true ) : array(), '<' . $t . ' class="wp-block-list">' . $li . '</' . $t . '>' );
};
$page = static fn( string $path ) => get_page_by_path( $path );
$url  = static fn( string $path ) => ( $pg = get_page_by_path( $path ) ) ? get_permalink( $pg ) : '#';
$save = static function ( string $path, string $title, string $template, array $blocks, array $meta ) {
	$pg = get_page_by_path( $path );
	$id = wp_insert_post(
		wp_slash(
			array(
				'ID'            => $pg ? $pg->ID : 0,
				'post_type'     => 'page',
				'post_status'   => 'publish',
				'post_name'     => $path,
				'post_title'    => $title,
				'post_content'  => implode( "\n\n", $blocks ),
				'page_template' => $template,
			)
		)
	);
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	update_post_meta( $id, '_tdd_fixture', 'phase4-pages' );
	echo "{$path}: {$id}\n";
	return $id;
};

/* Privacy: the WordPress privacy page setting points at the published page. */
$privacy = get_page_by_path( 'privacy-policy-2' );
if ( $privacy ) {
	update_option( 'wp_page_for_privacy_policy', $privacy->ID );
}
foreach ( array( 'corrections-policy' => 'Corrections Policy', 'source-policy' => 'Source Policy', 'ai-use-policy' => 'AI Use Policy', 'terms' => 'Terms of Use' ) as $path => $t ) {
	if ( $pg = get_page_by_path( $path ) ) {
		update_post_meta( $pg->ID, 'tdd_kicker', 'Policy' );
	}
}

/* ---------- Editorial Standards (long, numbered) ---------- */
$save(
	'editorial-standards',
	'Editorial Standards',
	'',
	array(
		$b( 'tdd/policy-callout', array(), $list( array( 'Every story shows its sources. Unverified company claims are labelled.', 'We correct errors openly, with a dated note, and never quietly.', 'A named editor approves every story before it is published.', 'AI tools may assist; people report, write and decide.' ) ) ),
		$h2( 'standards', 'Our standards' ),
		$p( 'Tech Dose Daily exists to explain the most important technology stories clearly and accurately. These standards apply to everyone who reports, writes, edits or produces work for us, including freelancers.' ),
		$list( array( '<strong>Accuracy</strong> comes before speed. If we can’t verify it, we don’t publish it as fact.', '<strong>Clarity</strong> means explaining technical subjects without hype or jargon.', '<strong>Fairness</strong> means giving people and companies a real chance to respond.', '<strong>Independence</strong> means advertisers and partners never shape coverage.' ) ),
		$h2( 'sourcing', 'How we source' ),
		$p( 'We prefer primary sources: the announcement, the filing, the documentation, the code. When we rely on a company’s own figures, we say so. Read the full <a href="' . esc_url( $url( 'source-policy' ) ) . '">Source Policy</a> for detail.' ),
		$b(
			'tdd/data-table',
			array(
				'variant'  => 'policy',
				'title'    => 'How each type of source appears in a story',
				'subtitle' => 'Applies to news, analysis, explainers and guides',
				'head'     => array( 'Source type', 'What it is', 'How we show it' ),
				'rows'     => array(
					array( 'Primary', 'Documents, data or statements from the organisation itself', 'Linked directly; labelled vendor-reported where unverified' ),
					array( 'Interview', 'On-record conversations conducted by our reporters', 'Named, with role and date' ),
					array( 'Supporting', 'Independent experts, researchers, other reporting', 'Credited and linked' ),
					array( 'Public record', 'Filings, court records, regulatory notices', 'Linked to the official copy' ),
					array( 'Confidential', 'People who cannot be named for a stated reason', 'Editor approval; reason given in the story' ),
				),
			)
		),
		$h3( 'Confidential sources' ),
		$p( 'We use confidential sources only when the information is important, cannot be obtained on the record, and the person faces a real risk. At least one editor must know the source’s identity.' ),
		$h2( 'labels', 'Labels and formats' ),
		$p( 'Every story carries a label so readers know what kind of work they are reading.' ),
		$b( 'tdd/deflist', array( 'rows' => array( array( 'term' => 'News', 'text' => 'What happened, verified and sourced. No opinion.' ), array( 'term' => 'Analysis', 'text' => 'Context and judgement from a named reporter, clearly labelled.' ), array( 'term' => 'Explainer', 'text' => 'How something works, in plain language.' ), array( 'term' => 'Guide', 'text' => 'Practical steps the reader can follow, tested where possible.' ), array( 'term' => 'Review', 'text' => 'Hands-on evaluation. We say how we got the product.' ), array( 'term' => 'Sponsored', 'text' => 'Paid content. Always labelled; never written by the newsroom.' ) ) ) ),
		$h2( 'corrections', 'Corrections and updates' ),
		$p( 'When we get something wrong, we fix it and tell readers what changed.' ),
		$list( array( 'The error is corrected in the story as soon as it is confirmed.', 'A dated correction note explains what was wrong and what is right.', 'Significant corrections are listed on our <a href="' . esc_url( $url( 'corrections-policy' ) ) . '">Corrections page</a>.' ), true ),
		$b( 'tdd/policy-notice', array( 'title' => 'We do not delete stories to hide mistakes' ), $p( 'Unpublishing is reserved for legal or safety reasons, and is noted on the Corrections page when it happens.' ) ),
		$h3( 'Updates' ),
		$p( 'Developing stories are updated with a timestamp and a short note at the top. Updates add information; corrections fix errors.' ),
		$h2( 'ai', 'AI-assisted work' ),
		$p( 'Reporters may use AI tools for tasks such as transcription, translation drafts or searching documents. A person checks every output before it is used, and no story is written or published by an AI system. See the <a href="' . esc_url( $url( 'ai-use-policy' ) ) . '">AI Use Policy</a>.' ),
		$b( 'tdd/policy-callout', array( 'title' => 'Disclosure', 'neutral' => true ), $p( 'If AI-generated material, such as an illustration, appears in a story, it is labelled where it appears.' ) ),
		$h2( 'independence', 'Independence and conflicts' ),
		$p( 'Staff do not cover companies in which they hold a financial interest, and declare gifts, travel and outside work to an editor.' ),
		$list( array( 'We do not accept payment for coverage.', 'Review products are returned or donated unless we bought them.', 'Sponsored content is labelled and produced separately from the newsroom.' ) ),
		$b( 'tdd/policy-notice', array( 'title' => 'What changed on Sep 12, 2026', 'change' => true ), $p( 'Added the section on AI-assisted work and clarified how review products are handled.' ) ),
		$h2( 'contact', 'Contact the standards desk' ),
		$p( 'To report an error or raise a concern about our journalism, write to the standards desk. We read every message and aim to reply within two working days.' ),
		$b( 'tdd/contact-cta', array( 'title' => 'Spotted an error?', 'text' => 'Tell the standards desk. Include the story link and what you believe is wrong.', 'primaryLabel' => 'Report an error', 'primaryUrl' => 'contact:correction', 'secondaryLabel' => 'Email standards@', 'secondaryUrl' => 'mailto:standards@example.com' ) ),
	),
	array(
		'tdd_kicker'         => 'Policy',
		'tdd_intro'          => 'How Tech Dose Daily reports, sources, labels and corrects its journalism — and what readers can expect from every story we publish.',
		'tdd_last_reviewed'  => '2026-09-12',
		'tdd_review_cadence' => 'Reviewed every six months',
		'tdd_numbered'       => true,
		'tdd_summary'        => 'How we report',
		'tdd_related_docs'   => array( array( 'title' => 'Source Policy', 'url' => $url( 'source-policy' ), 'kind' => 'policy' ), array( 'title' => 'Corrections log', 'url' => $url( 'corrections-policy' ), 'kind' => 'policy' ), array( 'title' => 'Example: an external journalism code of practice', 'url' => 'https://example.org/', 'kind' => 'external' ) ),
	)
);

/* ---------- Advertise (short) ---------- */
update_option( 'tdd_media_kit_url', 'https://example.com/sample-media-kit.pdf' );
$save(
	'advertise',
	'Advertise',
	'page-short',
	array(
		$h2( 'options', 'Partnership options' ),
		$b( 'tdd/deflist', array( 'rows' => array( array( 'term' => 'Newsletter sponsorship', 'text' => 'One labelled sponsor slot in the Daily Tech Brief.' ), array( 'term' => 'Sponsored explainer', 'text' => 'Produced by a separate team, always labelled Sponsored.' ), array( 'term' => 'Display placements', 'text' => 'Fixed slots on section and article pages. No pop-ups or autoplay.' ), array( 'term' => 'Events and briefings', 'text' => 'Partner sessions, clearly separated from editorial events.' ) ) ) ),
		$h2( 'rules', 'Our rules for partners' ),
		$list( array( 'Sponsored work is labelled where it appears and in search results.', 'Partners do not review or approve editorial coverage.', 'We decline categories that conflict with our <a href="' . esc_url( $url( 'editorial-standards' ) ) . '">Editorial Standards</a>.' ) ),
		$b( 'tdd/policy-callout', array( 'title' => 'Audience figures', 'neutral' => true ), $p( 'Audience and rate details are in the media kit and are shared on request. Figures shown in the kit are dated and sourced.' ) ),
		$b( 'tdd/contact-cta', array( 'title' => 'Talk to our partnerships team', 'text' => 'Tell us what you have in mind; we reply within two working days.', 'primaryLabel' => 'Contact partnerships', 'primaryUrl' => 'contact:partnership', 'secondaryLabel' => 'Media kit (PDF)', 'secondaryUrl' => 'https://example.com/sample-media-kit.pdf' ) ),
	),
	array(
		'tdd_kicker'        => 'Partner with us',
		'tdd_page_headline'      => 'Advertise with Tech Dose Daily',
		'tdd_intro'         => 'Reach readers who follow AI, software, cybersecurity and cloud closely. Partnerships are labelled, and never influence our journalism.',
		'tdd_last_reviewed' => '2026-09-01',
		'tdd_version_url'   => 'https://example.com/sample-media-kit.pdf',
		'tdd_version_label' => 'Download media kit (PDF) →',
		'tdd_summary'       => 'Partnerships and media kit',
	)
);

/* ---------- About ---------- */
$save(
	'about',
	'About',
	'page-about',
	array(
		$h2( 'cover', 'What we cover' ),
		$p( 'Eight sections, updated every weekday. Each links to its own page.' ),
		$b( 'tdd/coverage-list' ),
		$h2( 'report', 'How we report' ),
		$p( 'Our reporting follows published standards. In short:' ),
		$b( 'tdd/principles', array( 'rows' => array( array( 'title' => 'Primary sources first', 'text' => 'We go to the announcement, filing, documentation or code, and link to it.' ), array( 'title' => 'Claims are labelled', 'text' => 'Company figures we cannot verify are marked vendor-reported.' ), array( 'title' => 'Every story is edited', 'text' => 'A named editor reviews each story before publication.' ), array( 'title' => 'Errors are corrected openly', 'text' => 'Corrections carry a dated note explaining what changed.' ) ) ) ),
		$h2( 'ai', 'How we use AI' ),
		$p( 'We use AI tools for specific tasks, and a person stays responsible for everything we publish.' ),
		$list( array( 'Transcribing interviews and recordings', 'Searching long documents and filings', 'Draft support, such as summarising notes for a reporter', 'Workflow help, such as tagging and formatting' ) ),
		$b( 'tdd/policy-callout', array( 'title' => 'Human review', 'neutral' => true ), $p( 'An editor reviews every story before it is published. No story is written or published by an AI system. <a href="' . esc_url( $url( 'ai-use-policy' ) ) . '">Read the AI Use Policy →</a>' ) ),
		$h2( 'editors', 'Our editors' ),
		$p( 'A small desk of editors and reporters, each responsible for a beat.' ),
		$b( 'tdd/team' ),
		$h2( 'funding', 'How we’re funded' ),
		$p( 'Tech Dose Daily is free to read. We plan to fund it through the sources below and will update this list when each one starts.' ),
		$b( 'tdd/funding', array( 'rows' => array( array( 'name' => 'Newsletter sponsorship', 'status' => 'planned', 'text' => 'One clearly labelled sponsor slot in the Daily Tech Brief.' ), array( 'name' => 'Display advertising', 'status' => 'planned', 'text' => 'Fixed ad slots on section and article pages. No pop-ups or autoplay.' ), array( 'name' => 'Sponsorships', 'status' => 'planned', 'text' => 'Sponsored explainers or events, always labelled Sponsored.' ), array( 'name' => 'Affiliate links', 'status' => 'planned', 'text' => 'Only in guides where relevant, disclosed where they appear.' ) ) ) ),
		$b( 'tdd/policy-notice', array( 'title' => 'Commercial relationships do not influence coverage' ), $p( 'Advertisers and partners never review, approve or shape our journalism. Sponsored work is labelled where it appears. <a href="' . esc_url( $url( 'advertise' ) ) . '">Advertise / Partner with us →</a>' ) ),
		$h2( 'contact', 'Contact us' ),
		$p( 'Choose the route that fits your message.' ),
		$b( 'tdd/contact-routes' ),
	),
	array(
		'tdd_kicker'    => 'About',
		'tdd_page_headline'  => 'About Tech Dose Daily',
		'tdd_statement' => 'The most important AI and technology stories, explained clearly.',
		'tdd_intro'     => 'Tech Dose Daily covers the most important developments in AI, software, security, cloud and the companies shaping technology. We publish news, analysis, explainers and practical guides for developers, businesses and curious readers.',
		'tdd_focus'     => array( array( 'title' => 'What happened', 'text' => 'The facts, verified and sourced.' ), array( 'title' => 'Why it matters', 'text' => 'Context, not just the announcement.' ), array( 'title' => 'What changes', 'text' => 'For developers, businesses and readers.' ) ),
		'tdd_numbered'  => true,
		'tdd_summary'   => 'Who we are',
	)
);
// Section one-liners (approved CoverageList copy).
foreach ( array( 'ai' => 'Models, agents, research and the companies building them.', 'software' => 'Releases, platforms and the products people use at work.', 'cybersecurity' => 'Vulnerabilities, advisories, breaches and how to respond.', 'cloud' => 'Infrastructure, capacity, pricing and enterprise adoption.', 'developer' => 'Tools, languages, open source and coding workflows.', 'big-tech' => 'Strategy, regulation and products from the largest platforms.', 'startups' => 'Funding, founders and new companies worth watching.', 'guides' => 'Practical, tested steps for readers and teams.' ) as $slug => $line ) {
	if ( $t = get_term_by( 'slug', $slug, 'category' ) ) {
		update_term_meta( $t->term_id, 'tdd_short_description', $line );
	}
}
// Team: existing [sample] fixture users only (local). Production shows nobody until real people opt in.
foreach ( array( 'mira-sample', 'priya-sample', 'daniel-sample', 'arjun-sample' ) as $i => $login ) {
	if ( $u = get_user_by( 'login', $login ) ) {
		update_user_meta( $u->ID, 'tdd_show_on_about', '1' );
		update_user_meta( $u->ID, 'tdd_about_order', $i + 1 );
	}
}

/* ---------- Contact ---------- */
$save(
	'contact',
	'Contact',
	'page-contact',
	array(),
	array(
		'tdd_kicker'        => 'Contact',
		'tdd_page_headline'      => 'Contact Tech Dose Daily',
		'tdd_intro'         => 'Choose the route that fits your message so it reaches the right person. We use role inboxes and this form rather than publishing individual staff addresses.',
		'tdd_last_reviewed' => '2026-09-12',
		'tdd_summary'       => 'How to reach us',
	)
);
update_option( 'tdd_contact_inboxes', array( 'editorial' => 'editors@example.com', 'correction' => 'standards@example.com', 'tip' => 'tips@example.com', 'partnership' => 'partners@example.com', 'general' => 'hello@example.com' ) );
update_option( 'tdd_contact_notes', array( 'editorial' => 'Answered as capacity allows' ) );
update_option( 'tdd_contact_expectations', array( array( 'title' => 'Corrections', 'text' => 'Reviewed promptly by an editor.' ), array( 'title' => 'Editorial questions', 'text' => 'Answered as capacity allows.' ), array( 'title' => 'Tips', 'text' => 'Read by the relevant editor; we reply if we follow up.' ), array( 'title' => 'Partnerships', 'text' => 'Usually answered within a few working days.' ) ) );
delete_option( 'tdd_secure_tip' ); // No secure channel configured: the page says so plainly.

/* ---------- Newsletter ---------- */
$save(
	'newsletter',
	'Newsletter',
	'page-newsletter',
	array(
		$b( 'tdd/nl-benefits', array( 'rows' => array( array( 'title' => 'The day’s essential stories', 'text' => 'The biggest technology developments, selected by the editors.' ), array( 'title' => 'Why they matter', 'text' => 'Context beyond the announcement, in a sentence or two.' ), array( 'title' => 'Developer and business impact', 'text' => 'What changes for people building and using technology.' ), array( 'title' => 'Worth your time', 'text' => 'A short list of stories, tools or research to read next.' ) ) ) ),
		$b( 'tdd/nl-sample', array( 'how' => 'The editors pick the day’s stories, explain each in a sentence or two, and link to the full coverage and its sources.', 'delivery' => array( array( 'title' => 'When', 'text' => 'Weekday mornings' ), array( 'title' => 'Cost', 'text' => 'Free' ), array( 'title' => 'Leaving', 'text' => 'One-click unsubscribe in every email' ) ) ) ),
		$b( 'tdd/nl-promise', array( 'rows' => array( array( 'text' => 'No clickbait or invented urgency' ), array( 'text' => 'Only the stories that matter that day' ), array( 'text' => 'Every item links to its sources' ), array( 'text' => 'Sponsored material is clearly labelled' ), array( 'text' => 'Coverage is independent from advertisers' ), array( 'text' => 'Your email is never sold or shared' ) ) ) ),
		$b( 'tdd/faq', array( 'rows' => array( array( 'q' => 'Is it free?', 'a' => 'Yes. The newsletter is free to read.' ), array( 'q' => 'How often is it sent?', 'a' => 'One email every weekday morning. No weekend sends.' ), array( 'q' => 'Can I unsubscribe?', 'a' => 'Yes, with one click from any email.' ), array( 'q' => 'Will my email be shared?', 'a' => 'No. We use it only to send the newsletter. See the <a href="' . esc_url( get_privacy_policy_url() ) . '">Privacy Policy</a>.' ), array( 'q' => 'Does sponsored content appear?', 'a' => 'Possibly in future. If it does, it is labelled “Sponsored” and kept separate from the editors’ picks.' ) ) ) ),
	),
	array(
		'tdd_page_headline' => 'Your Daily Dose of Technology',
		'tdd_intro'    => 'The most important AI and technology stories, explained clearly. One email every weekday morning.',
		'tdd_summary'  => 'The Daily Tech Brief',
	)
);
echo "Done.\n";
