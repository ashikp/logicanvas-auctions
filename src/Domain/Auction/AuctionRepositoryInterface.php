<?php
/**
 * Auction repository contract.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Auction;

interface AuctionRepositoryInterface {

	public function find( int $auction_id ): ?Auction;

	public function find_for_update( int $auction_id ): ?Auction;

	/**
	 * @param array<string, mixed> $data
	 */
	public function insert_state( array $data ): void;

	/**
	 * @param array<string, mixed> $data
	 */
	public function update_state( int $auction_id, array $data ): bool;

	/**
	 * @param array<string, mixed> $args
	 * @return Auction[]
	 */
	public function query( array $args ): array;

	public function count( array $args ): int;
}
