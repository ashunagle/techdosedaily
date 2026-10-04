<?php
/**
 * Newsletter provider contract. Templates and blocks never talk to a provider directly —
 * they call the REST endpoint, which resolves the active provider via
 * `tdd_core_newsletter_provider()`. Swapping MailPoet for Beehiiv/Brevo/etc. means adding
 * one class that implements this interface.
 *
 * @package TDD\Core
 */

namespace TDD\Core\Newsletter;

defined( 'ABSPATH' ) || exit;

interface Provider {
	/** Stable identifier, e.g. "mailpoet". */
	public function id(): string;

	/** Whether the provider is installed and configured. */
	public function available(): bool;

	/** Subscribe an address. Must not reveal more than the approved states need. */
	public function subscribe( string $email, array $context = array() ): Result;

	public function unsubscribe( string $email ): Result;

	public function status( string $email ): Result;
}
