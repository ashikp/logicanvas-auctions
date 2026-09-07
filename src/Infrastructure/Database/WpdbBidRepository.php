<?php
/**
 * wpdb bid repository.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\Database;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Bidding\Bid;
use LogicanvasAuctions\Domain\Bidding\BidRepositoryInterface;

final class WpdbBidRepository implements BidRepositoryInterface {

	private function table(): string {
		return Config::table( Config::TABLE_BIDS );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function insert( array $data ): int {
		global $wpdb;

		$wpdb->insert(
			$this->table(),
			array(
				'auction_id'       => (int) $data['auction_id'],
				'bidder_id'        => (int) $data['bidder_id'],
				'amount'           => (string) $data['amount'],
				'currency'         => (string) $data['currency'],
				'type'             => (string) ( $data['type'] ?? Bid::TYPE_REGULAR ),
				'max_amount'       => $data['max_amount'] ?? null,
				'status'           => (string) ( $data['status'] ?? Bid::STATUS_ACCEPTED ),
				'idempotency_key'  => (string) $data['idempotency_key'],
				'ip_hash'          => $data['ip_hash'] ?? null,
				'user_agent_hash'  => $data['user_agent_hash'] ?? null,
				'created_at_utc'   => (string) $data['created_at_utc'],
			)
		);

		QueryCache::bust_auction( (int) $data['auction_id'] );

		return (int) $wpdb->insert_id;
	}

	public function find( int $bid_id ): ?Bid {
		global $wpdb;

		$table = $this->table();
		$key   = QueryCache::key( 'bid', $bid_id );
		$row   = QueryCache::remember(
			$key,
			60,
			static function () use ( $wpdb, $table, $bid_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_row(
					$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $bid_id ),
					ARRAY_A
				);
			}
		);

		return is_array( $row ) ? new Bid( $row ) : null;
	}

	public function find_by_idempotency( int $auction_id, string $key ): ?Bid {
		global $wpdb;

		$table    = $this->table();
		$cache_key = QueryCache::key( 'bid_idem', $auction_id, $key );
		$row       = QueryCache::remember(
			$cache_key,
			30,
			static function () use ( $wpdb, $table, $auction_id, $key ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_row(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE auction_id = %d AND idempotency_key = %s',
						$table,
						$auction_id,
						$key
					),
					ARRAY_A
				);
			}
		);

		return is_array( $row ) ? new Bid( $row ) : null;
	}

	/**
	 * @return Bid[]
	 */
	public function for_auction( int $auction_id, int $limit = 50, int $offset = 0, bool $accepted_only = true ): array {
		global $wpdb;

		$table     = $this->table();
		$cache_key = QueryCache::key( 'bids_list', $auction_id, $limit, $offset, $accepted_only ? 1 : 0 );
		$rows      = QueryCache::remember(
			$cache_key,
			30,
			static function () use ( $wpdb, $table, $auction_id, $limit, $offset, $accepted_only ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				if ( $accepted_only ) {
					return $wpdb->get_results(
						$wpdb->prepare(
							'SELECT * FROM %i WHERE auction_id = %d AND status = %s ORDER BY created_at_utc DESC, id DESC LIMIT %d OFFSET %d',
							$table,
							$auction_id,
							Bid::STATUS_ACCEPTED,
							$limit,
							$offset
						),
						ARRAY_A
					);
				}

				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE auction_id = %d ORDER BY created_at_utc DESC, id DESC LIMIT %d OFFSET %d',
						$table,
						$auction_id,
						$limit,
						$offset
					),
					ARRAY_A
				);
			}
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( static fn( array $row ) => new Bid( $row ), $rows );
	}

	public function highest_accepted( int $auction_id ): ?Bid {
		global $wpdb;

		$table = $this->table();
		$key   = QueryCache::key( 'bid_highest', $auction_id );
		$row   = QueryCache::remember(
			$key,
			30,
			static function () use ( $wpdb, $table, $auction_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_row(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE auction_id = %d AND status = %s ORDER BY amount DESC, id ASC LIMIT 1',
						$table,
						$auction_id,
						Bid::STATUS_ACCEPTED
					),
					ARRAY_A
				);
			}
		);

		return is_array( $row ) ? new Bid( $row ) : null;
	}

	/**
	 * @return Bid[]
	 */
	public function accepted_for_auction( int $auction_id ): array {
		global $wpdb;

		$table = $this->table();
		$key   = QueryCache::key( 'bids_accepted', $auction_id );
		$rows  = QueryCache::remember(
			$key,
			30,
			static function () use ( $wpdb, $table, $auction_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE auction_id = %d AND status = %s ORDER BY id ASC',
						$table,
						$auction_id,
						Bid::STATUS_ACCEPTED
					),
					ARRAY_A
				);
			}
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( static fn( array $row ) => new Bid( $row ), $rows );
	}

	public function void( int $bid_id, int $actor_id, string $reason, string $at_utc ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$this->table(),
			array(
				'status'        => Bid::STATUS_VOIDED,
				'voided_at_utc' => $at_utc,
				'voided_by'     => $actor_id,
				'void_reason'   => $reason,
			),
			array( 'id' => $bid_id )
		);

		QueryCache::flush_group();

		return false !== $result;
	}

	public function count_for_auction( int $auction_id, bool $accepted_only = true ): int {
		global $wpdb;

		$table = $this->table();
		$key   = QueryCache::key( 'bid_count', $auction_id, $accepted_only ? 1 : 0 );

		return (int) QueryCache::remember(
			$key,
			30,
			static function () use ( $wpdb, $table, $auction_id, $accepted_only ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				if ( $accepted_only ) {
					return $wpdb->get_var(
						$wpdb->prepare(
							'SELECT COUNT(*) FROM %i WHERE auction_id = %d AND status = %s',
							$table,
							$auction_id,
							Bid::STATUS_ACCEPTED
						)
					);
				}

				return $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM %i WHERE auction_id = %d',
						$table,
						$auction_id
					)
				);
			}
		);
	}

	public function proxy_max_for( int $auction_id, int $bidder_id ): ?string {
		global $wpdb;

		$table = $this->table();
		$key   = QueryCache::key( 'bid_proxy_max', $auction_id, $bidder_id );
		$val   = QueryCache::remember(
			$key,
			30,
			static function () use ( $wpdb, $table, $auction_id, $bidder_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COALESCE(max_amount, amount) FROM %i WHERE auction_id = %d AND bidder_id = %d AND status = %s ORDER BY id DESC LIMIT 1',
						$table,
						$auction_id,
						$bidder_id,
						Bid::STATUS_ACCEPTED
					)
				);
			}
		);

		return $val ? (string) $val : null;
	}
}
