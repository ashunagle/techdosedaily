<?php
/**
 * LOCAL TEST FIXTURE ONLY (copy into wp-content/mu-plugins on a local site).
 * Fake newsletter provider to exercise the approved form states without MailPoet:
 * already@example.com → already subscribed; instant@example.com → subscribed (single opt-in flow);
 * anything else → pending (double opt-in, the production MailPoet setting). Stores nothing.
 */
add_filter(
	'tdd_core_newsletter_provider',
	static function () {
		return new class() implements TDD\Core\Newsletter\Provider {
			public function id(): string { return 'fake'; }
			public function available(): bool { return true; }
			public function subscribe( string $email, array $context = array() ): TDD\Core\Newsletter\Result {
				return new TDD\Core\Newsletter\Result( 'already@example.com' === $email ? 'already' : ( 'instant@example.com' === $email ? 'subscribed' : 'pending' ) );
			}
			public function unsubscribe( string $email ): TDD\Core\Newsletter\Result { return new TDD\Core\Newsletter\Result( 'unsubscribed' ); }
			public function status( string $email ): TDD\Core\Newsletter\Result { return new TDD\Core\Newsletter\Result( 'not_found' ); }
		};
	}
);
