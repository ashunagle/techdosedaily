<?php
/**
 * Used when no provider is configured. Never pretends a subscription happened.
 *
 * @package TDD\Core
 */

namespace TDD\Core\Newsletter;

defined( 'ABSPATH' ) || exit;

final class Null_Provider implements Provider {
	public function id(): string {
		return 'none';
	}
	public function available(): bool {
		return false;
	}
	public function subscribe( string $email, array $context = array() ): Result {
		return new Result( Result::UNAVAILABLE );
	}
	public function unsubscribe( string $email ): Result {
		return new Result( Result::UNAVAILABLE );
	}
	public function status( string $email ): Result {
		return new Result( Result::UNAVAILABLE );
	}
}
