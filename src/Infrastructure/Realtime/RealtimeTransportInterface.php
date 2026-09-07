<?php
/**
 * Baseline polling transport + push-ready interface.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\Realtime;

interface RealtimeTransportInterface {

	public function mode(): string;

	/**
	 * Optional ping that new state exists. Must never accept bids.
	 *
	 * @param array<string, mixed> $payload
	 */
	public function notify( int $auction_id, array $payload ): void;
}
