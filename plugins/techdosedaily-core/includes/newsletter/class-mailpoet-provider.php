<?php
/**
 * MailPoet adapter (MailPoet API v1: \MailPoet\API\API::MP('v1')).
 * List: option `tdd_newsletter_mailpoet_list` (MailPoet list ID). Confirmation e-mail
 * behaviour follows MailPoet's own "Sign-up confirmation" setting.
 *
 * @package TDD\Core
 */

namespace TDD\Core\Newsletter;

defined( 'ABSPATH' ) || exit;

final class MailPoet_Provider implements Provider {

	public function id(): string {
		return 'mailpoet';
	}

	public function available(): bool {
		return class_exists( '\MailPoet\API\API' ) && $this->list_id() > 0;
	}

	private function list_id(): int {
		return (int) get_option( 'tdd_newsletter_mailpoet_list', 0 );
	}

	private function api() {
		return \MailPoet\API\API::MP( 'v1' );
	}

	public function subscribe( string $email, array $context = array() ): Result {
		if ( ! $this->available() ) {
			return new Result( Result::UNAVAILABLE );
		}
		$list = $this->list_id();
		try {
			$existing = $this->find( $email );
			if ( $existing ) {
				$on_list = $this->subscribed_to_list( $existing, $list );
				if ( $on_list && 'subscribed' === ( $existing['status'] ?? '' ) ) {
					return new Result( Result::ALREADY );
				}
				$sub = $this->api()->subscribeToList( $existing['id'], $list );
				return new Result( 'unconfirmed' === ( $sub['status'] ?? '' ) ? Result::PENDING : Result::SUBSCRIBED );
			}
			$sub = $this->api()->addSubscriber( array( 'email' => $email ), array( $list ) );
			return new Result( 'unconfirmed' === ( $sub['status'] ?? '' ) ? Result::PENDING : Result::SUBSCRIBED );
		} catch ( \Throwable $e ) {
			return new Result( Result::ERROR, $e->getMessage() ); // Detail is logged, never shown.
		}
	}

	public function unsubscribe( string $email ): Result {
		if ( ! $this->available() ) {
			return new Result( Result::UNAVAILABLE );
		}
		try {
			$existing = $this->find( $email );
			if ( ! $existing ) {
				return new Result( Result::NOT_FOUND );
			}
			$this->api()->unsubscribeFromList( $existing['id'], $this->list_id() );
			return new Result( Result::UNSUBSCRIBED );
		} catch ( \Throwable $e ) {
			return new Result( Result::ERROR, $e->getMessage() );
		}
	}

	public function status( string $email ): Result {
		if ( ! $this->available() ) {
			return new Result( Result::UNAVAILABLE );
		}
		$existing = $this->find( $email );
		if ( ! $existing ) {
			return new Result( Result::NOT_FOUND );
		}
		if ( ! $this->subscribed_to_list( $existing, $this->list_id() ) ) {
			return new Result( Result::UNSUBSCRIBED );
		}
		return new Result( 'unconfirmed' === ( $existing['status'] ?? '' ) ? Result::PENDING : Result::ALREADY );
	}

	private function find( string $email ): ?array {
		try {
			$sub = $this->api()->getSubscriber( $email );
			return is_array( $sub ) ? $sub : null;
		} catch ( \Throwable $e ) {
			return null; // MailPoet throws when the subscriber does not exist.
		}
	}

	private function subscribed_to_list( array $subscriber, int $list ): bool {
		foreach ( (array) ( $subscriber['subscriptions'] ?? array() ) as $s ) {
			if ( (int) ( $s['segment_id'] ?? 0 ) === $list && 'subscribed' === ( $s['status'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}
}
