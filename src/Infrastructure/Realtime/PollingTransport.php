<?php
/**
 * REST polling transport (default).
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\Realtime;

final class PollingTransport implements RealtimeTransportInterface {

	public function mode(): string {
		return 'polling';
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public function notify( int $auction_id, array $payload ): void {
		/**
		 * Inform push providers that new authoritative state exists.
		 *
		 * @param int                  $auction_id Auction ID.
		 * @param array<string, mixed> $payload    Safe payload (no secrets).
		 */
		do_action( 'wcap_realtime_notify', $auction_id, $payload );
	}
}
