<?php
/**
 * LOCAL TEST FIXTURE ONLY — never run on staging or production.
 * Turns the sample AI lead story into the full illustrative article used by the approved
 * ArticleDesktop / ArticleMobile designs (same fictional content, people and figures), so the
 * single template can be compared with the frozen references. Everything is marked [Sample].
 * Run after phase2-content.php: wp eval-file tests/fixtures/phase3-article.php
 */
if ( ! function_exists( 'tdd_core_place' ) ) { echo "Activate techdosedaily-core first\n"; return; }

$author = get_user_by( 'login', 'priya-sample' );
update_user_meta( $author->ID, 'tdd_title', 'Senior AI Correspondent' );
update_user_meta( $author->ID, 'tdd_location', 'Bengaluru' );
update_user_meta( $author->ID, 'tdd_social', array( array( 'label' => 'LinkedIn', 'url' => 'https://www.example.com/sample-linkedin' ), array( 'label' => 'X', 'url' => 'https://www.example.com/sample-x' ) ) );
update_user_meta( $author->ID, 'tdd_public_email', true );
wp_update_user( array( 'ID' => $author->ID, 'description' => 'Priya covers AI models, developer tools and the companies building them. She previously reported on enterprise software for six years. [Sample profile]' ) );

$editor = get_user_by( 'login', 'mira-sample' );
if ( ! $editor ) {
	$uid    = wp_insert_user( array( 'user_login' => 'mira-sample', 'user_pass' => wp_generate_password( 24 ), 'display_name' => 'Mira Castellanos', 'role' => 'editor', 'user_email' => 'mira-sample@example.com' ) );
	$editor = get_userdata( $uid );
}
update_user_meta( $editor->ID, 'tdd_title', 'Deputy Editor (sample)' );

$post = get_posts( array( 'post_type' => 'post', 'name' => 'major-ai-provider-unveils-a-reasoning-model-built-for-long-running-coding-agents', 'numberposts' => 1 ) )[0] ?? null;
if ( ! $post ) { echo "Run phase2-content.php first\n"; return; }

$hero = (int) get_post_thumbnail_id( $post );
$dev  = get_posts( array( 'post_type' => 'post', 'name' => 'code-hosting-platform-adds-a-review-workflow-for-ai-generated-pull-requests', 'numberposts' => 1 ) )[0] ?? null;
$pr   = $dev ? (int) get_post_thumbnail_id( $dev ) : 0;
wp_update_post( array( 'ID' => $hero, 'post_excerpt' => 'The developer console shows the model’s plan before it edits code. Each step can be paused or rolled back.' ) );
update_post_meta( $hero, 'tdd_credit', 'Company screenshot via Tech Dose Daily' );
update_post_meta( $hero, '_wp_attachment_image_alt', 'The company’s developer console running a multi-step coding task with a 14-step plan' );
update_post_meta( $pr, 'tdd_credit', 'Company screenshot via Tech Dose Daily' );
$pr_src = wp_get_attachment_image_url( $pr, 'tdd-16x9-1200' );

$ref = static fn( int $n ) => '<sup><a href="#src-' . $n . '" aria-label="Source ' . $n . '">' . $n . '</a></sup>';
$p   = static fn( string $t ) => "<!-- wp:paragraph -->\n<p>{$t}</p>\n<!-- /wp:paragraph -->\n\n";
$h   = static fn( string $t, int $l = 2 ) => '<!-- wp:heading' . ( 2 === $l ? '' : ' {"level":' . $l . '}' ) . " -->\n<h{$l} class=\"wp-block-heading\">{$t}</h{$l}>\n<!-- /wp:heading -->\n\n";

$table = array(
	'title'    => 'Multi-step coding tasks completed without human help',
	'subtitle' => 'Share of 500 tasks, vendor-reported',
	'head'     => array( 'Model', 'Tasks solved', 'Median time', 'Price per task-hour' ),
	'short'    => array( '', 'Solved', 'Median', 'Price/hr' ),
	'rows'     => array( array( 'New reasoning model', '71%', '38 min', '$4.00' ), array( 'Previous flagship', '54%', '22 min', '—' ), array( 'Open-weight model A', '47%', '41 min', 'Self-hosted' ), array( 'Open-weight model B', '39%', '35 min', 'Self-hosted' ) ),
	'focus'    => 1,
	'note'     => 'Source: company technical report' . $ref( 2 ) . ' and pricing documentation' . $ref( 3 ) . '. Illustrative figures for design review.',
);

$content  = "<!-- wp:tdd/key-takeaways -->\n<!-- wp:list -->\n<ul class=\"wp-block-list\"><!-- wp:list-item --><li>The model is built to work through multi-step coding tasks for hours, pausing for human approval at checkpoints.</li><!-- /wp:list-item --><!-- wp:list-item --><li>The company reports 71% on a 500-task benchmark, up from 54% for its previous flagship. The results have not yet been independently reproduced.</li><!-- /wp:list-item --><!-- wp:list-item --><li>Access starts as a paid developer preview, priced per task-hour rather than per token.</li><!-- /wp:list-item --><!-- wp:list-item --><li>The practical shift for teams is in review: more work arrives as finished pull requests.</li><!-- /wp:list-item --></ul>\n<!-- /wp:list -->\n<!-- /wp:tdd/key-takeaways -->\n\n";
$content .= $h( 'What was announced' );
$content .= $p( '[Sample] The company on Saturday released a preview of a new reasoning model that it says can plan, write and test changes across a codebase for hours at a time, rather than answering one prompt and stopping.' . $ref( 1 ) . ' In a briefing with reporters, executives described it as the first model they had trained specifically for “long-horizon” software work.' );
$content .= $p( 'The model ships alongside an updated developer console that shows a step-by-step plan before any code is changed, and a context window of one million tokens — enough, the company says, to hold a mid-sized repository in memory.' . $ref( 2 ) );
$content .= $p( 'It is not a general release. Access is limited to paying developer accounts, and the company says it will widen availability “over the coming months” as it monitors reliability and cost.' );
$content .= $h( 'How it works in practice' );
$content .= $p( 'Rather than generating a single answer, the model writes a plan, executes it one step at a time and runs the project’s tests between steps. If a test fails, it revises the plan. At configurable checkpoints it stops and asks a human to approve before continuing.' );
$content .= '<!-- wp:image {"id":' . $pr . ',"sizeSlug":"tdd-16x9-1200","linkDestination":"none"} -->' . "\n" . '<figure class="wp-block-image size-tdd-16x9-1200"><img src="' . esc_url( $pr_src ) . '" alt="A pull request opened by the model, with a review comment suggesting a per-tenant retry budget" class="wp-image-' . $pr . '"/><figcaption class="wp-element-caption">A pull request opened by the model during a preview customer’s trial. The suggested change came from the company’s review assistant.</figcaption></figure>' . "\n<!-- /wp:image -->\n\n";
$content .= $p( 'Two engineering teams that tested the preview told Tech Dose Daily the planning step was the most useful part. It made the model’s intent visible before anything changed, which shortened review.' . $ref( 4 ) );
$content .= "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>“We stopped reading every line it wrote and started reading its plan. That’s a different job, and honestly a better one.”</p>\n<!-- /wp:paragraph --><cite><b>Dana Whitfield</b>, engineering director at a payments company testing the preview</cite></blockquote>\n<!-- /wp:quote -->\n\n";
$content .= $h( 'Where it still struggles', 3 );
$content .= $p( 'Both teams said the model was weakest on tasks that depended on knowledge outside the repository — internal conventions, unwritten deployment steps, or decisions recorded only in chat threads. In those cases it tended to produce changes that passed tests but did not match how the team actually works.' );
$content .= $h( 'The numbers, with caveats' );
$content .= $p( 'The company’s technical report includes results on an internal benchmark of 500 multi-step coding tasks, graded by whether the final change passes a hidden test suite.' . $ref( 2 ) . ' The headline figures are below. They are vendor-reported, and the task set has not been published.' );
$content .= '<!-- wp:tdd/data-table ' . wp_json_encode( $table, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_APOS ) . " /-->\n\n";
$content .= $p( 'An independent evaluation group said it had requested access to the task set and would publish its own results once it could run them.' . $ref( 5 ) . ' Until then, the figures are best read as the company’s own measure of progress against its previous model, not as a comparison across vendors.' );
$content .= "<!-- wp:tdd/why-this-matters -->\n" . $p( 'For most teams, the cost of AI coding tools has been measured in tokens and minutes. A model built to run for hours changes the unit: you are effectively assigning work, not asking questions.' ) . $p( 'That moves the bottleneck from writing code to reviewing it — and puts a premium on clear tests, documented conventions and review policies that most organisations have never written down.' ) . "<!-- /wp:tdd/why-this-matters -->\n\n";
$content .= $h( 'What to watch next' );
$content .= $p( 'Three things will decide whether this release matters beyond a benchmark chart: whether independent testers can reproduce the results, how the per-task-hour pricing compares with the engineering time it replaces, and whether the approval checkpoints hold up when teams run dozens of agents at once.' );
$content .= $p( 'Tech Dose Daily will update this analysis as independent results are published.' );

$pub = time() - 150 * MINUTE_IN_SECONDS;
wp_update_post(
	array(
		'ID'            => $post->ID,
		'post_content'  => wp_slash( $content ), // wp_update_post() unslashes.
		'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $pub ),
		'post_date'     => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $pub ) ),
	)
);
update_post_meta( $post->ID, 'tdd_deck', 'The release is aimed at hours-long software tasks rather than chat. Here is what it changes for developers, what the company has and hasn’t shown, and which claims still need independent testing.' );
update_post_meta( $post->ID, 'tdd_editor', $editor->ID );
update_post_meta( $post->ID, 'tdd_updated_at', gmdate( DATE_ATOM, time() - 18 * MINUTE_IN_SECONDS ) );
update_post_meta( $post->ID, 'tdd_update_note', 'Adds pricing details from the company’s developer documentation and a response from an independent evaluation group.' );
update_post_meta( $post->ID, 'tdd_corrections', array( array( 'time' => gmdate( DATE_ATOM, $pub + 45 * MINUTE_IN_SECONDS ), 'text' => 'An earlier version of this article said the model’s context window was two million tokens. It is one million tokens.' ) ) );
update_post_meta(
	$post->ID,
	'tdd_sources',
	array(
		array( 'title' => 'Announcement: “A reasoning model for long-running agents”', 'url' => 'https://example-ai.dev/blog/sample', 'type' => 'primary', 'publisher' => 'Company blog · example-ai.dev', 'date' => wp_date( 'M j, Y', $pub ) ),
		array( 'title' => 'Technical report and system card (PDF, 48 pages)', 'url' => 'https://example-ai.dev/report-sample.pdf', 'type' => 'primary', 'publisher' => 'example-ai.dev', 'date' => wp_date( 'M j, Y', $pub ) ),
		array( 'title' => 'Developer documentation: pricing and rate limits', 'url' => '', 'type' => 'primary', 'publisher' => 'docs.example-ai.dev', 'date' => 'Accessed ' . wp_date( 'M j, Y, g:i A T', $pub ) ),
		array( 'title' => 'Interviews with two engineering teams testing the preview', 'url' => '', 'type' => 'interview', 'publisher' => 'Conducted by Tech Dose Daily', 'date' => wp_date( 'M j, Y', $pub - DAY_IN_SECONDS ), 'note' => 'Names used with permission' ),
		array( 'title' => 'Statement from an independent AI evaluation group', 'url' => 'https://eval-lab.example.org/sample', 'type' => 'supporting', 'publisher' => 'eval-lab.example.org', 'date' => wp_date( 'M j, Y', $pub ) ),
	)
);
wp_set_object_terms( $post->ID, array( 'AI models', 'Coding agents', 'Developer tools', 'Benchmarks' ), 'tdd_topic' );
update_post_meta( $post->ID, 'tdd_reading_time', 9 ); // Fixture: the design's 9-minute piece (body is shorter than the real thing).
echo "article fixture: " . get_permalink( $post ) . "\n";
