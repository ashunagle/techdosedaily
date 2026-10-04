<?php
/**
 * Site footer. The approved desktop (six columns) and mobile (two columns) footers
 * differ in structure, so both are rendered from the same menus and switched with CSS.
 * Only one is ever visible or exposed to assistive technology (display:none).
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

$tdd_about = __( 'Daily reporting on AI, software, security and the companies shaping technology — explained clearly.', 'techdosedaily' );
$tdd_cols  = array(
	__( 'Categories', 'techdosedaily' ) => tdd_menu_items( 'footer-categories' ),
	__( 'Company', 'techdosedaily' )    => tdd_menu_items( 'footer-company' ),
	__( 'Policies', 'techdosedaily' )   => tdd_menu_items( 'footer-policies' ),
	__( 'Newsletter', 'techdosedaily' ) => tdd_menu_items( 'footer-newsletter' ),
	__( 'Follow', 'techdosedaily' )     => tdd_menu_items( 'footer-follow' ),
);
$tdd_cols  = array_filter( $tdd_cols );
$tdd_legal = tdd_menu_items( 'footer-legal' );
/* translators: 1: year, 2: site name. */
$tdd_copy = sprintf( __( '© %1$s %2$s', 'techdosedaily' ), gmdate( 'Y' ), get_bloginfo( 'name' ) );
?>
<div id="site-footer">
<footer class="tdd-footer tdd-footer--desktop">
	<div class="tdd-container">
		<div class="tdd-footer__top">
			<div><?php echo tdd_logo( false, 'white' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><p class="tdd-footer__about"><?php echo esc_html( $tdd_about ); ?></p></div>
			<?php foreach ( $tdd_cols as $tdd_title => $tdd_items ) : ?>
				<div><h4><?php echo esc_html( $tdd_title ); ?></h4><ul><?php echo tdd_links( $tdd_items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></ul></div>
			<?php endforeach; ?>
		</div>
		<div class="tdd-footer__bottom"><span><?php echo esc_html( $tdd_copy ); ?></span>
			<?php if ( $tdd_legal ) : ?>
				<nav aria-label="<?php esc_attr_e( 'Legal', 'techdosedaily' ); ?>"><?php echo tdd_links( $tdd_legal, '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></nav>
			<?php endif; ?>
		</div>
	</div>
</footer>
<footer class="tdd-footer tdd-footer--mobile">
	<?php echo tdd_logo( false, 'white' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<p class="tdd-footer__about"><?php echo esc_html( $tdd_about ); ?></p>
	<div class="m-footer__grid">
		<?php if ( ! empty( $tdd_cols[ __( 'Categories', 'techdosedaily' ) ] ) ) : ?>
			<div><h4><?php esc_html_e( 'Sections', 'techdosedaily' ); ?></h4><ul><?php echo tdd_links( $tdd_cols[ __( 'Categories', 'techdosedaily' ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></ul></div>
		<?php endif; ?>
		<div>
			<?php
			$tdd_company = array_merge( $tdd_cols[ __( 'Company', 'techdosedaily' ) ] ?? array(), array_map( static fn( $i ) => array( __( 'Newsletter', 'techdosedaily' ), $i[1], $i[2] ), array_slice( $tdd_cols[ __( 'Newsletter', 'techdosedaily' ) ] ?? array(), 0, 1 ) ) );
			if ( $tdd_company ) :
				?>
				<h4><?php esc_html_e( 'Company', 'techdosedaily' ); ?></h4><ul><?php echo tdd_links( $tdd_company ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></ul>
			<?php endif; ?>
			<?php if ( ! empty( $tdd_cols[ __( 'Follow', 'techdosedaily' ) ] ) ) : ?>
				<h4 class="m-footer__h4-gap"><?php esc_html_e( 'Follow', 'techdosedaily' ); ?></h4><ul><?php echo tdd_links( $tdd_cols[ __( 'Follow', 'techdosedaily' ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></ul>
			<?php endif; ?>
		</div>
	</div>
	<?php if ( ! empty( $tdd_cols[ __( 'Policies', 'techdosedaily' ) ] ) ) : ?>
		<nav class="m-footer__legal" aria-label="<?php esc_attr_e( 'Policies', 'techdosedaily' ); ?>"><?php echo tdd_links( $tdd_cols[ __( 'Policies', 'techdosedaily' ) ], '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></nav>
	<?php endif; ?>
	<p class="m-footer__copy"><?php echo esc_html( $tdd_copy ); ?></p>
</footer>
</div>
