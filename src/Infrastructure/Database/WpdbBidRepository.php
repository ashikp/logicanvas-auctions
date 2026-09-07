<?php
/**
 * wpdb bid repository.
 *
 * Custom InnoDB bids table — WP_Query cannot serve these reads/writes.
 * Each method caches in the same scope as the $wpdb call (WPCS requirement).
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
				'auction_id'      => (int) $data['auction_id'],
				'bidder_id'       => (int) $data['bidder_id'],
				'amount'          => (string) $data['amount'],
				'currency'        => (string) $data['currency'],
				'type'            => (string) ( $data['type'] ?? Bid::TYPE_REGULAR ),
				'max_amount'      => $data['max_amount'] ?? null,
				'status'          => (string) ( $data['status'] ?? Bid::STATUS_ACCEPTED ),
				'idempotency_key' => (string) $data['idempotency_key'],
				'ip_hash'         => $data['ip_hash'] ?? null,
				'user_agent_hash' => $data['user_agent_hash'] ?? null,
				'created_at_utc'  => (string) $data['created_at_utc'],
			)
		);

		$insert_id = (int) $wpdb->insert_id;
		wp_cache_delete( QueryCache::key( 'bid', $insert_id ), QueryCache::GROUP );
		wp_cache_delete( QueryCache::key( 'bids_list', (int) $data['auction_id'] ), QueryCache::GROUP );
		wp_cache_delete( QueryCache::key( 'bid_highest', (int) $data['auction_id'] ), QueryCache::GROUP );
		wp_cache_delete( QueryCache::key( 'bids_accepted', (int) $data['auction_id'] ), QueryCache::GROUP );
		wp_cache_delete( QueryCache::key( 'bid_count', (int) $data['auction_id'], 1 ), QueryCache::GROUP );
		wp_cache_delete( QueryCache::key( 'bid_count', (int) $data['auction_id'], 0 ), QueryCache::GROUP );
		QueryCache::bust_auction( (int) $data['auction_id'] );

		return $insert_id;
	}

	public function find( int $bid_id ): ?Bid {
		global $wpdb;

		$cache_key = QueryCache::key( 'bid', $bid_id );
		$row       = wp_cache_get( $cache_key, QueryCache::GROUP );

		if ( false === $row ) {
			$row = $wpdb->get_row(
				$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table(), $bid_id ),
				ARRAY_A
			);
			wp_cache_set( $cache_key, is_array( $row ) ? $row : array(), QueryCache::GROUP, 60 );
		}

		return is_array( $row ) && isset( $row['id'] ) ? new Bid( $row ) : null;
	}

	public function find_by_idempotency( int $auction_id, string $key ): ?Bid {
		global $wpdb;

		$cache_key = QueryCache::key( 'bid_idem', $auction_id, $key );
		$row       = wp_cache_get( $cache_key, QueryCache::GROUP );

		if ( false === $row ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE auction_id = %d AND idempotency_key = %s',
					$this->table(),
					$auction_id,
					$key
				),
				ARRAY_A
			);
			wp_cache_set( $cache_key, is_array( $row ) ? $row : array(), QueryCache::GROUP, 30 );
		}

		return is_array( $row ) && isset( $row['id'] ) ? new Bid( $row ) : null;
	}

	/**
	 * @return Bid[]
	 */
	public function for_auction( int $auction_id, int $limit = 50, int $offset = 0, bool $accepted_only = true ): array {
		global $wpdb;

		$cache_key = QueryCache::key( 'bids_list', $auction_id, $limit, $offset, $accepted_only ? 1 : 0 );
		$rows      = wp_cache_get( $cache_key, QueryCache::GROUP );

		if ( false === $rows ) {
			if ( $accepted_only ) {
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE auction_id = %d AND status = %s ORDER BY created_at_utc DESC, id DESC LIMIT %d OFFSET %d',
						$this->table(),
						$auction_id,
						Bid::STATUS_ACCEPTED,
						$limit,
						$offset
					),
					ARRAY_A
				);
			} else {
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE auction_id = %d ORDER BY created_at_utc DESC, id DESC LIMIT %d OFFSET %d',
						$this->table(),
						$auction_id,
						$limit,
						$offset
					),
					ARRAY_A
				);
			}
			$rows = is_array( $rows ) ? $rows : array();
			wp_cache_set( $cache_key, $rows, QueryCache::GROUP, 30 );
		}

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( static fn( array $row ) => new Bid( $row ), $rows );
	}

	public function highest_accepted( int $auction_id ): ?Bid {
		global $wpdb;

		$cache_key = QueryCache::key( 'bid_highest', $auction_id );
		$row       = wp_cache_get( $cache_key, QueryCache::GROUP );

		if ( false === $row ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE auction_id = %d AND status = %s ORDER BY amount DESC, id ASC LIMIT 1',
					$this->table(),
					$auction_id,
					Bid::STATUS_ACCEPTED
				),
				ARRAY_A
			);
			wp_cache_set( $cache_key, is_array( $row ) ? $row : array(), QueryCache::GROUP, 30 );
		}

		return is_array( $row ) && isset( $row['id'] ) ? new Bid( $row ) : null;
	}

	/**
	 * @return Bid[]
	 */
	public function accepted_for_auction( int $auction_id ): array {
		global $wpdb;

		$cache_key = QueryCache::key( 'bids_accepted', $auction_id );
		$rows      = wp_cache_get( $cache_key, QueryCache::GROUP );

		if ( false === $rows ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE auction_id = %d AND status = %s ORDER BY id ASC',
					$this->table(),
					$auction_id,
					Bid::STATUS_ACCEPTED
				),
				ARRAY_A
			);
			$rows = is_array( $rows ) ? $rows : array();
			wp_cache_set( $cache_key, $rows, QueryCache::GROUP, 30 );
		}

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

		wp_cache_delete( QueryCache::key( 'bid', $bid_id ), QueryCache::GROUP );
		QueryCache::flush_group();

		return false !== $result;
	}

	public function count_for_auction( int $auction_id, bool $accepted_only = true ): int {
		global $wpdb;

		$cache_key = QueryCache::key( 'bid_count', $auction_id, $accepted_only ? 1 : 0 );
		$count     = wp_cache_get( $cache_key, QueryCache::GROUP );

		if ( false === $count ) {
			if ( $accepted_only ) {
				$count = $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM %i WHERE auction_id = %d AND status = %s',
						$this->table(),
						$auction_id,
						Bid::STATUS_ACCEPTED
					)
				);
			} else {
				$count = $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM %i WHERE auction_id = %d',
						$this->table(),
						$auction_id
					)
				);
			}
			$count = (int) $count;
			wp_cache_set( $cache_key, $count, QueryCache::GROUP, 30 );
		}

		return (int) $count;
	}

	public function proxy_max_for( int $auction_id, int $bidder_id ): ?string {
		global $wpdb;

		$cache_key = QueryCache::key( 'bid_proxy_max', $auction_id, $bidder_id );
		$val       = wp_cache_get( $cache_key, QueryCache::GROUP );

		if ( false === $val ) {
			$val = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COALESCE(max_amount, amount) FROM %i WHERE auction_id = %d AND bidder_id = %d AND status = %s ORDER BY id DESC LIMIT 1',
					$this->table(),
					$auction_id,
					$bidder_id,
					Bid::STATUS_ACCEPTED
				)
			);
			wp_cache_set( $cache_key, null === $val ? '' : (string) $val, QueryCache::GROUP, 30 );
		}

		$val = (string) $val;
		return '' !== $val ? $val : null;
	}
}
