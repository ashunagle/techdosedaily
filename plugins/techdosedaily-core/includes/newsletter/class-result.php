<?php
/**
 * Provider-neutral result. Maps 1:1 to the approved NewsletterFormStates.
 *
 * @package TDD\Core
 */

namespace TDD\Core\Newsletter;

defined( 'ABSPATH' ) || exit;

final class Result {
	public const SUBSCRIBED   = 'subscribed';    // State 5 · success.
	public const ALREADY      = 'already';       // State 3 · already subscribed.
	public const PENDING      = 'pending';       // Double opt-in only (copy not yet approved).
	public const INVALID      = 'invalid';       // State 2 · invalid email.
	public const UNSUBSCRIBED = 'unsubscribed';
	public const NOT_FOUND    = 'not_found';
	public const UNAVAILABLE  = 'unavailable';   // Provider missing/misconfigured.
	public const ERROR        = 'error';

	public function __construct( public readonly string $status, public readonly string $detail = '' ) {}

	public function ok(): bool {
		return in_array( $this->status, array( self::SUBSCRIBED, self::ALREADY, self::PENDING, self::UNSUBSCRIBED ), true );
	}
}
