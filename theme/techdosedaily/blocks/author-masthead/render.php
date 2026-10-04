<?php
/**
 * tdd/author-masthead — see inc/author.php.
 *
 * @package techdosedaily
 */

defined( 'ABSPATH' ) || exit;

$tdd_user = tdd_author_user();
if ( $tdd_user ) {
	echo tdd_author_masthead( $tdd_user ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
}
