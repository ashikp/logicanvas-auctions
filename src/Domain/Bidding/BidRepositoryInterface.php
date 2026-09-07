<?php
/**
 * Bid repository contract.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Bidding;

interface BidRepositoryInterface {

	/**
	 * @param array<string, mixed> $data
	 */
	public function insert( array $data ): int;

	public function find( int $bid_id ): ?Bid;

	public function find_by_idempotency( int $auction_id, string $key ): ?Bid;

	/**
	 * @return Bid[]
	 */
	public function for_auction( int $auction_id, int $limit = 50, int $offset = 0, bool $accepted_only = true ): array;

	public function highest_accepted( int $auction_id ): ?Bid;

	/**
	 * @return Bid[]
	 */
	public function accepted_for_auction( int $auction_id ): array;

	public function void( int $bid_id, int $actor_id, string $reason, string $at_utc ): bool;

	public function count_for_auction( int $auction_id, bool $accepted_only = true ): int;

	public function proxy_max_for( int $auction_id, int $bidder_id ): ?string;
}
