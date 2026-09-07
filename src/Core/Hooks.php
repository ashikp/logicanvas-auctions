<?php
/**
 * Documented public hook names (actions/filters registered around domain events).
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Core;

final class Hooks {

	public function register(): void {
		// Hook names are fired from domain services. This class exists so the
		// plugin always has a stable place to attach cross-cutting listeners.
		add_action( 'wcap_auction_state_changed', array( $this, 'on_state_changed' ), 10, 4 );
	}

	/**
	 * @param array<string, mixed> $metadata
	 */
	public function on_state_changed( int $auction_id, string $from, string $to, array $metadata ): void {
		unset( $auction_id, $from, $to, $metadata );
	}
}
