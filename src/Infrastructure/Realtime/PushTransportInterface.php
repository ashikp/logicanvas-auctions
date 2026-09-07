<?php
/**
 * Optional push provider. Clients must still fetch WordPress state.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\Realtime;

interface PushTransportInterface {

	public function enabled(): bool;

	/**
	 * @param array<string, mixed> $payload
	 */
	public function ping( int $auction_id, array $payload ): void;
}
