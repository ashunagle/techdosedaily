<?php
/**
 * Shared component renderers used by several blocks (SectionHeading, Breadcrumbs,
 * NewsletterCTA, Pagination) and the post-source resolver with page-level de-duplication.
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

/* ---------- De-duplication: a story appears once per page ---------- */

function tdd_mark_shown( int $id ): void {
	$GLOBALS['tdd_shown'][ $id ] = true;
}

/** @return int[] */
function tdd_shown(): array {
	return array_keys( $GLOBALS['tdd_shown'] ?? array() );
}

/**
 * Resolve posts for a block from: post | placement | latest | section.
 *
 * @return WP_Post[]
 */
function tdd_block_posts( array $a, int $limit ): array {
	$source  = $a['source'] ?? 'latest';
	$section = ! empty( $a['section'] ) ? get_term_by( 'slug', $a['section'], 'category' ) : null;
	$sid     = $section ? (int) $section->term_id : 0;

	if ( 'post' === $source ) {
		$p = get_post( (int) ( $a['postId'] ?? 0 ) );
		return ( $p && 'publish' === $p->post_status ) ? array( $p ) : array();
	}
	if ( 'placement' === $source && function_exists( 'tdd_core_placement_resolve' ) && ! empty( $a['placement'] ) ) {
		// Slot resolution lives in Core: active entry → next valid entry → newest eligible story.
		// Stories already on the page are excluded, so adjacent slots never repeat a story.
		if ( isset( $a['position'] ) ) {
			$cands = tdd_core_placement_candidates( $a['placement'], $sid )[ (int) $a['position'] ] ?? array();
			foreach ( array_diff( $cands, tdd_shown() ) as $id ) {
				$p = get_post( $id );
				if ( $p ) {
					return array( $p );
				}
			}
			return tdd_latest_posts( 1, $sid );
		}
		$ids = tdd_core_placement_resolve( $a['placement'], $limit, array( 'section' => $sid, 'exclude' => tdd_shown() ) );
		return array_values( array_filter( array_map( 'get_post', $ids ) ) );
	}
	return tdd_latest_posts( $limit, $sid );
}

/** @return WP_Post[] */
function tdd_latest_posts( int $limit, int $section_id = 0 ): array {
	$q = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => $limit,
		'post__not_in'        => tdd_shown(),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);
	if ( $section_id ) {
		$q['cat'] = $section_id;
	}
	return $limit > 0 ? get_posts( $q ) : array();
}

/* ---------- SectionHeading ---------- */

function tdd_section_heading( array $a ): string {
	$a     = wp_parse_args( $a, array( 'title' => '', 'description' => '', 'linkText' => '', 'linkUrl' => '', 'size' => 'default', 'fresh' => false, 'freshTs' => 0, 'level' => 'h2' ) );
	$level = in_array( $a['level'], array( 'h1', 'h2', 'h3' ), true ) ? $a['level'] : 'h2';
	$title = '<' . $level . ' class="tdd-sh__title">' . esc_html( $a['title'] ) . '</' . $level . '>';
	$sub   = '';
	if ( $a['fresh'] ) {
		$sub = tdd_freshness( (int) $a['freshTs'] ?: null );
	} elseif ( '' !== $a['description'] ) {
		$sub = '<p class="tdd-sh__desc">' . esc_html( $a['description'] ) . '</p>';
	}
	$more = ( $a['linkText'] && $a['linkUrl'] ) ? '<a class="tdd-btn tdd-btn--text tdd-sh__more' . ( ! empty( $a['linkHideMobile'] ) ? ' tdd-hide-m' : '' ) . '" href="' . esc_url( $a['linkUrl'] ) . '">' . esc_html( $a['linkText'] ) . '</a>' : '';
	$cls  = 'tdd-sh' . ( 'small' === $a['size'] ? ' tdd-sh--small' : '' );
	return '<div class="' . $cls . '">' . ( $sub ? '<div>' . $title . $sub . '</div>' : $title ) . $more . '</div>';
}

/* ---------- Breadcrumbs ---------- */

/** @return array<int, array{0:string,1:string}> [label, url] */
function tdd_breadcrumb_items(): array {
	$items = array( array( __( 'Home', 'techdosedaily' ), home_url( '/' ) ) );
	if ( is_singular( 'post' ) ) {
		$section = tdd_section();
		if ( $section ) {
			$items[] = array( $section->name, get_term_link( $section ) );
		}
		$format  = tdd_format();
		$items[] = array( $format ? $format->name : get_the_title(), '' );
	} elseif ( is_category() ) {
		$items[] = array( single_cat_title( '', false ), '' );
	} elseif ( is_tax( 'tdd_topic' ) || is_tag() ) {
		$items[] = array( __( 'Topics', 'techdosedaily' ), '' );
		$items[] = array( single_term_title( '', false ), '' );
	} elseif ( is_author() ) {
		$items[] = array( __( 'Authors', 'techdosedaily' ), '' );
		$items[] = array( get_the_author_meta( 'display_name', (int) get_query_var( 'author' ) ), '' );
	} elseif ( is_search() ) {
		$items[] = array( __( 'Search', 'techdosedaily' ), '' );
	} elseif ( is_page() ) {
		$ancestors = get_post_ancestors( get_the_ID() );
		// Policy pages sit under About in the approved breadcrumb (Home › About › Page), even when top-level.
		$policies = function_exists( 'tdd_core_policy_pages' ) ? array_keys( tdd_core_policy_pages() ) : array();
		$slug     = get_post_field( 'post_name', get_the_ID() );
		$is_priv  = (int) get_option( 'wp_page_for_privacy_policy' ) === get_the_ID();
		if ( ! $ancestors && ( in_array( $slug, $policies, true ) || $is_priv ) ) {
			$about = get_page_by_path( 'about' );
			if ( $about && 'publish' === $about->post_status ) {
				$items[] = array( get_the_title( $about ), get_permalink( $about ) );
			}
		}
		foreach ( array_reverse( $ancestors ) as $ancestor ) {
			$items[] = array( get_the_title( $ancestor ), get_permalink( $ancestor ) );
		}
		$items[] = array( get_the_title(), '' );
	} elseif ( is_home() && ! is_front_page() ) {
		$items[] = array( get_the_title( (int) get_option( 'page_for_posts' ) ), '' );
	}
	return $items;
}

/** Breadcrumbs markup (Breadcrumbs component); '' when there is no trail. */
function tdd_breadcrumbs_html(): string {
	$items = tdd_breadcrumb_items();
	if ( count( $items ) < 2 ) {
		return '';
	}
	$html = '';
	$last = count( $items ) - 1;
	foreach ( $items as $i => [ $label, $url ] ) {
		if ( $i ) {
			$html .= '<span aria-hidden="true">›</span>';
		}
		$html .= ( $i < $last && $url ) ? '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>' : '<span' . ( $i === $last ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</span>';
	}
	return '<nav class="tdd-crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'techdosedaily' ) . '">' . $html . '</nav>';
}

/* ---------- NewsletterCTA ---------- */

function tdd_newsletter_cta( array $a ): string {
	static $n = 0;
	++$n;
	$id    = 'tdd-nl-' . $n;
	$token = function_exists( 'tdd_core_form_token' ) ? tdd_core_form_token() : '';
	$rest  = function_exists( 'tdd_core_form_token' ) ? rest_url( 'tdd/v1/subscribe' ) : '';
	// No-JS result after the Post/Redirect/Get round trip (?tdd_nl=state).
	$state  = isset( $_GET['tdd_nl'] ) ? sanitize_key( wp_unslash( $_GET['tdd_nl'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$msgs   = function_exists( 'tdd_core_newsletter_messages' ) ? tdd_core_newsletter_messages() : array();
	$status = '';
	if ( 1 === $n && $state && isset( $msgs[ $state ] ) ) {
		$titles = function_exists( 'tdd_core_newsletter_titles' ) ? tdd_core_newsletter_titles() : array();
		$status = isset( $titles[ $state ] )
			? '<div class="tdd-nlok"><b>' . esc_html( $titles[ $state ] ) . '</b>' . esc_html( $msgs[ $state ] ) . '</div>'
			: '<p class="tdd-nlsign__note">' . esc_html( $msgs[ $state ] ) . '</p>';
	}
	ob_start();
	?>
	<?php $inline = 'inline' === ( $a['layout'] ?? '' ); // Approved article variant: copy left, form right (≥1024). ?>
	<div class="tdd-newsletter<?php echo $inline ? ' tdd-newsletter--inline' : ''; ?>" data-tdd-newsletter<?php echo 1 === $n ? ' id="newsletter"' : ''; ?>>
		<?php echo $inline ? '<div>' : ''; ?>
		<div class="tdd-newsletter__kicker"><?php echo tdd_icon( 'mail', 'tdd-ic tdd-ic--16' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Newsletter', 'techdosedaily' ); ?></div>
		<h2 class="tdd-newsletter__title"><?php echo esc_html( $a['title'] ); ?></h2>
		<p class="tdd-newsletter__text"><?php echo esc_html( $a['text'] ); ?></p>
		<?php echo $inline ? '</div>' : ''; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-rest="<?php echo esc_url( $rest ); ?>" novalidate>
			<input type="hidden" name="action" value="tdd_subscribe">
			<input type="hidden" name="tdd_token" value="<?php echo esc_attr( $token ); ?>">
			<div class="tdd-hp" aria-hidden="true"><label for="<?php echo esc_attr( $id ); ?>-hp">Leave this empty</label><input type="text" id="<?php echo esc_attr( $id ); ?>-hp" name="tdd_hp" tabindex="-1" autocomplete="off"></div>
			<label class="tdd-field__label" for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Email address', 'techdosedaily' ); ?></label>
			<div class="tdd-newsletter__row"><input class="tdd-input" id="<?php echo esc_attr( $id ); ?>" name="email" type="email" autocomplete="email" inputmode="email" required placeholder="you@company.com" aria-describedby="<?php echo esc_attr( $id ); ?>-fine"><button class="tdd-btn tdd-btn--primary" type="submit"><?php esc_html_e( 'Subscribe', 'techdosedaily' ); ?></button></div>
			<p class="tdd-newsletter__fine" id="<?php echo esc_attr( $id ); ?>-fine"><?php esc_html_e( 'Free. Unsubscribe anytime.', 'techdosedaily' ); ?></p>
			<div class="tdd-nl-status" role="status" aria-live="polite"><?php echo $status; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></div>
		</form>
	</div>
	<?php
	return (string) ob_get_clean();
}

/* ---------- Pagination ---------- */

/**
 * Pagination (approved Pagination component) for the main query.
 *
 * @param array $a { moreLabel, feed, label (aria), prev/next (desktop words), note (escaped HTML),
 *                   noteCount (bool, default true), moreMobileOnly (bool) }
 */
function tdd_pagination( array $a ): string {
	global $wp_query;
	$a = wp_parse_args( $a, array( 'moreLabel' => '', 'feed' => '[data-tdd-feed]', 'label' => '', 'prev' => __( 'Newer', 'techdosedaily' ), 'next' => __( 'Older', 'techdosedaily' ), 'note' => '', 'noteCount' => true, 'moreMobileOnly' => false ) );
	$total   = (int) $wp_query->max_num_pages;
	$current = max( 1, (int) get_query_var( 'paged' ) );
	if ( $total < 2 ) {
		return '';
	}
	$term  = ( is_category() || is_tax() || is_tag() ) ? get_queried_object() : null;
	$noun  = $term ? $term->name : '';
	/* translators: %s: section name. */
	$label = '' !== $a['label'] ? $a['label'] : ( $noun ? sprintf( __( '%s news pages', 'techdosedaily' ), $noun ) : __( 'Pages', 'techdosedaily' ) );
	/* translators: %s: section name. */
	$more  = $a['moreLabel'] ? $a['moreLabel'] : ( $noun ? sprintf( __( 'Load more %s news', 'techdosedaily' ), $noun ) : __( 'Load more stories', 'techdosedaily' ) );

	$pages = array_unique( array_filter( array( 1, 2, 3, $current - 1, $current, $current + 1, $total ), static fn( $p ) => $p >= 1 && $p <= $total ) );
	sort( $pages );
	// Desktop "← Newer / Older →"; approved mobile "← Prev / Next →".
	$newer = '← <span class="tdd-hide-m">' . esc_html( $a['prev'] ) . '</span><span class="tdd-only-m">' . esc_html__( 'Prev', 'techdosedaily' ) . '</span>';
	$older = '<span class="tdd-hide-m">' . esc_html( $a['next'] ) . '</span><span class="tdd-only-m">' . esc_html__( 'Next', 'techdosedaily' ) . '</span> →';
	$nav   = $current > 1 ? '<a href="' . esc_url( get_pagenum_link( $current - 1 ) ) . '" rel="prev">' . $newer . '</a>' : '<span class="is-dis">' . $newer . '</span>';
	$prev = 0;
	foreach ( $pages as $p ) {
		if ( $prev && $p > $prev + 1 ) {
			$nav .= '<span aria-hidden="true">…</span>';
		}
		// The approved mobile pager drops the far last page number ("1 2 3 … Next →").
		$far  = $p === $total && $total > 3 && $p > $current + 1 ? ' class="tdd-hide-m"' : '';
		$nav .= $p === $current ? '<a href="' . esc_url( get_pagenum_link( $p ) ) . '" aria-current="page">' . (int) $p . '</a>' : '<a href="' . esc_url( get_pagenum_link( $p ) ) . '"' . $far . '>' . (int) $p . '</a>';
		$prev = $p;
	}
	$next_url = $current < $total ? get_pagenum_link( $current + 1 ) : '';
	$nav     .= $next_url ? '<a href="' . esc_url( $next_url ) . '" rel="next" data-tdd-next>' . $older . '</a>' : '<span class="is-dis">' . $older . '</span>';

	$count = number_format_i18n( (int) $wp_query->found_posts );
	/* translators: 1: current page, 2: total pages. */
	$page = sprintf( __( 'Page %1$d of %2$d', 'techdosedaily' ), $current, $total );
	/* translators: 1: number of stories, 2: section name. */
	$tail = $noun ? sprintf( __( '%1$s %2$s stories', 'techdosedaily' ), $count, $noun ) : sprintf( __( '%s stories', 'techdosedaily' ), $count );
	$note = '' !== $a['note'] ? $a['note'] : esc_html( $page ) . ( $a['noteCount'] ? '<span class="tdd-hide-m"> · ' . esc_html( $tail ) . '</span>' : '' ); // Approved mobile: "Page 1 of 214".

	$button = $next_url ? '<button class="tdd-btn tdd-btn--secondary tdd-pager__more' . ( $a['moreMobileOnly'] ? ' tdd-only-m' : '' ) . '" type="button" data-feed="' . esc_attr( $a['feed'] ) . '" hidden>' . esc_html( $more ) . '</button>' : '';
	return '<nav class="tdd-pager" aria-label="' . esc_attr( $label ) . '">' . $button . '<div class="tdd-pager__nav">' . $nav . '</div><span class="tdd-pager__note">' . $note . '</span></nav>';
}
