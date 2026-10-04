<?php
/**
 * Site header — one DOM for all widths.
 * ≥1200px: approved desktop header (logo · sections · search · Subscribe).
 * 768–1199px: sections move into the menu drawer (no tablet design exists; nav does not fit).
 * ≤767px: approved 56px mobile header (logo · search · menu).
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

$tdd_sections   = tdd_menu_items( 'primary' );
$tdd_newsletter = get_page_by_path( 'newsletter' );
$tdd_subscribe  = $tdd_newsletter ? get_permalink( $tdd_newsletter ) : home_url( '/newsletter/' );
$tdd_search     = add_query_arg( 's', '', home_url( '/' ) );
?>
<a class="tdd-skip" href="#main"><?php esc_html_e( 'Skip to content', 'techdosedaily' ); ?></a>
<header class="tdd-header" data-tdd-header>
	<div class="tdd-container tdd-header__inner">
		<?php echo tdd_logo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		<?php if ( $tdd_sections ) : ?>
			<nav class="tdd-nav" aria-label="<?php esc_attr_e( 'Sections', 'techdosedaily' ); ?>"><?php echo tdd_links( $tdd_sections, '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></nav>
		<?php endif; ?>
		<div class="tdd-header__tools">
			<a class="tdd-btn tdd-btn--icon" href="<?php echo esc_url( $tdd_search ); ?>" aria-label="<?php esc_attr_e( 'Search', 'techdosedaily' ); ?>"<?php echo is_search() ? ' aria-current="page"' : ''; ?>><?php echo tdd_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			<a class="tdd-btn tdd-btn--primary tdd-header__subscribe" href="<?php echo esc_url( $tdd_subscribe ); ?>"><?php esc_html_e( 'Subscribe', 'techdosedaily' ); ?></a>
			<a class="tdd-btn tdd-btn--icon tdd-header__menu" href="#site-footer" role="button" aria-controls="tdd-menu" aria-expanded="false" aria-label="<?php esc_attr_e( 'Open menu', 'techdosedaily' ); ?>"><?php echo tdd_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
		</div>
	</div>
</header>
<div class="tdd-drawer" id="tdd-menu" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Menu', 'techdosedaily' ); ?>" hidden>
	<div class="tdd-drawer__head">
		<?php echo tdd_logo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<button class="tdd-btn tdd-btn--icon tdd-drawer__close" type="button" aria-label="<?php esc_attr_e( 'Close menu', 'techdosedaily' ); ?>"><?php echo tdd_icon( 'x' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
	</div>
	<nav class="tdd-drawer__nav" aria-label="<?php esc_attr_e( 'Sections', 'techdosedaily' ); ?>"><ul><?php echo tdd_links( $tdd_sections ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></ul></nav>
	<?php foreach ( array( 'drawer-more' => __( 'More from Tech Dose Daily', 'techdosedaily' ), 'drawer-about' => __( 'About', 'techdosedaily' ) ) as $tdd_loc => $tdd_label ) : ?>
		<?php $tdd_items = tdd_menu_items( $tdd_loc ); ?>
		<?php if ( $tdd_items ) : ?>
			<nav class="tdd-drawer__nav tdd-drawer__nav--secondary" aria-label="<?php echo esc_attr( $tdd_label ); ?>"><ul><?php echo tdd_links( $tdd_items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></ul></nav>
		<?php endif; ?>
	<?php endforeach; ?>
</div>
