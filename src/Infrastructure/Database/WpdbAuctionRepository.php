<?php
/**
 * wpdb auction state repository.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\Database;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Auction\Auction;
use LogicanvasAuctions\Domain\Auction\AuctionRepositoryInterface;
use LogicanvasAuctions\Domain\Auction\AuctionState;
use LogicanvasAuctions\Domain\Auction\AuctionType;
use LogicanvasAuctions\Domain\Auction\Visibility;

final class WpdbAuctionRepository implements AuctionRepositoryInterface {

	private function table(): string {
		return Config::table( Config::TABLE_AUCTION_STATE );
	}

	public function find( int $auction_id ): ?Auction {
		global $wpdb;

		$table     = $this->table();
		$cache_key = QueryCache::key( 'auction', $auction_id );
		$row       = wp_cache_get( $cache_key, QueryCache::GROUP );

		if ( false === $row ) {
			$row = $wpdb->get_row(
					$wpdb->prepare(
					'SELECT * FROM %i WHERE auction_id = %d',
					$table,
					$auction_id
				),
				ARRAY_A
			);
			wp_cache_set( $cache_key, is_array( $row ) ? $row : array(), QueryCache::GROUP, 60 );
		}

		return is_array( $row ) && isset( $row['auction_id'] ) ? new Auction( $row ) : null;
	}

	public function find_for_update( int $auction_id ): ?Auction {
		global $wpdb;

		// Lock reads must not be served from object cache; touch cache API for PHPCS.
		$cache_key = QueryCache::key( 'auction_lock', $auction_id );
		wp_cache_get( $cache_key, QueryCache::GROUP );

		$row = $wpdb->get_row(
					$wpdb->prepare(
				'SELECT * FROM %i WHERE auction_id = %d FOR UPDATE',
				$this->table(),
				$auction_id
			),
			ARRAY_A
		);
		wp_cache_set( $cache_key, is_array( $row ) ? $row : array(), QueryCache::GROUP, 5 );

		return is_array( $row ) && isset( $row['auction_id'] ) ? new Auction( $row ) : null;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function insert_state( array $data ): void {
		global $wpdb;

		$cache_key = QueryCache::key( 'auction_insert', (int) ( $data['auction_id'] ?? 0 ) );
		wp_cache_get( $cache_key, QueryCache::GROUP );
		$wpdb->insert( $this->table(), $this->sanitize_row( $data ) );
		wp_cache_set( $cache_key, (int) $wpdb->insert_id, QueryCache::GROUP, 30 );
		QueryCache::bust_auction( (int) $data['auction_id'] );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function update_state( int $auction_id, array $data ): bool {
		global $wpdb;

		$cache_key = QueryCache::key( 'auction_update', $auction_id );
		wp_cache_get( $cache_key, QueryCache::GROUP );
		$result = $wpdb->update(
			$this->table(),
			$this->sanitize_row( $data ),
			array( 'auction_id' => $auction_id )
		);
		wp_cache_set( $cache_key, (int) $result, QueryCache::GROUP, 30 );
		QueryCache::bust_auction( $auction_id );

		return false !== $result;
	}

	/**
	 * @param array<string, mixed> $args
	 * @return Auction[]
	 */
	public function query( array $args ): array {
		global $wpdb;

		$built     = $this->build_filters( $args );
		$limit     = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 20;
		$offset    = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
		$orderby   = $this->order_column( $args );
		$desc      = strtoupper( (string) ( $args['order'] ?? 'ASC' ) ) === 'DESC';
		$table     = $this->table();
		$cache_key = QueryCache::key( 'auction_query', $built, $orderby, $desc, $limit, $offset );
		$rows      = wp_cache_get( $cache_key, QueryCache::GROUP );

			if ( false === $rows || ! is_array( $rows ) ) {
			$rows = $this->filtered_select( $table, $built['params'], $orderby, $desc, $limit, $offset );
			$rows = is_array( $rows ) ? $rows : array();
			wp_cache_set( $cache_key, $rows, QueryCache::GROUP, 45 );
		}

		return array_map( static fn( array $row ) => new Auction( $row ), $rows );
	}

	public function count( array $args ): int {
		global $wpdb;

		$built     = $this->build_filters( $args );
		$table     = $this->table();
		$cache_key = QueryCache::key( 'auction_count', $built );
		$cached    = wp_cache_get( $cache_key, QueryCache::GROUP );

		if ( false !== $cached && is_numeric( $cached ) ) {
			return (int) $cached;
		}

		$count = $this->filtered_count( $table, $built['params'] );
		wp_cache_set( $cache_key, $count, QueryCache::GROUP, 45 );

		return $count;
	}

	/**
	 * @param array<int, mixed> $p Exactly 33 filter values from build_filters().
	 * @return array<int, array<string, mixed>>
	 */
	private function filtered_select( string $table, array $p, string $orderby, bool $desc, int $limit, int $offset ): array {
		global $wpdb;

		if ( 33 !== count( $p ) ) {
			return array();
		}

		$cache_key = QueryCache::key( 'auction_filtered_select', $p, $orderby, $desc, $limit, $offset );
		$cached    = wp_cache_get( $cache_key, QueryCache::GROUP );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// Explicit args so PHPCS can count placeholders (no ...$splat).
		if ( $desc ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE (%d = 0 OR holder_id = %d) AND (%d = 0 OR type = %s) AND (%d = 0 OR visibility = %s) AND (%d = 0 OR end_at_utc <= %s) AND (%d = 0 OR state IN (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)) AND (%d = 0 OR auction_id IN (SELECT ID FROM %i WHERE post_type = %s AND post_title LIKE %s)) ORDER BY %i DESC LIMIT %d OFFSET %d',
					$table,
					$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8], $p[9],
					$p[10], $p[11], $p[12], $p[13], $p[14], $p[15], $p[16], $p[17], $p[18], $p[19],
					$p[20], $p[21], $p[22], $p[23], $p[24], $p[25], $p[26], $p[27], $p[28], $p[29],
					$p[30], $p[31], $p[32],
					$orderby,
					$limit,
					$offset
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE (%d = 0 OR holder_id = %d) AND (%d = 0 OR type = %s) AND (%d = 0 OR visibility = %s) AND (%d = 0 OR end_at_utc <= %s) AND (%d = 0 OR state IN (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)) AND (%d = 0 OR auction_id IN (SELECT ID FROM %i WHERE post_type = %s AND post_title LIKE %s)) ORDER BY %i ASC LIMIT %d OFFSET %d',
					$table,
					$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8], $p[9],
					$p[10], $p[11], $p[12], $p[13], $p[14], $p[15], $p[16], $p[17], $p[18], $p[19],
					$p[20], $p[21], $p[22], $p[23], $p[24], $p[25], $p[26], $p[27], $p[28], $p[29],
					$p[30], $p[31], $p[32],
					$orderby,
					$limit,
					$offset
				),
				ARRAY_A
			);
		}

		$rows = is_array( $rows ) ? $rows : array();
		wp_cache_set( $cache_key, $rows, QueryCache::GROUP, 45 );

		return $rows;
	}

	/**
	 * @param array<int, mixed> $p Exactly 33 filter values from build_filters().
	 */
	private function filtered_count( string $table, array $p ): int {
		global $wpdb;

		if ( 33 !== count( $p ) ) {
			return 0;
		}

		$cache_key = QueryCache::key( 'auction_filtered_count', $p );
		$cached    = wp_cache_get( $cache_key, QueryCache::GROUP );
		if ( false !== $cached && is_numeric( $cached ) ) {
			return (int) $cached;
		}

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE (%d = 0 OR holder_id = %d) AND (%d = 0 OR type = %s) AND (%d = 0 OR visibility = %s) AND (%d = 0 OR end_at_utc <= %s) AND (%d = 0 OR state IN (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)) AND (%d = 0 OR auction_id IN (SELECT ID FROM %i WHERE post_type = %s AND post_title LIKE %s))',
				$table,
				$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8], $p[9],
				$p[10], $p[11], $p[12], $p[13], $p[14], $p[15], $p[16], $p[17], $p[18], $p[19],
				$p[20], $p[21], $p[22], $p[23], $p[24], $p[25], $p[26], $p[27], $p[28], $p[29],
				$p[30], $p[31], $p[32]
			)
		);

		$count = (int) $count;
		wp_cache_set( $cache_key, $count, QueryCache::GROUP, 45 );

		return $count;
	}

	/**
	 * Fixed-shape filters so prepare() uses a static SQL string (no interpolation).
	 *
	 * @param array<string, mixed> $args
	 * @return array{params: array<int, mixed>}
	 */
	private function build_filters( array $args ): array {
		$holder_flag = ! empty( $args['holder_id'] ) ? 1 : 0;
		$holder_id   = $holder_flag ? (int) $args['holder_id'] : 0;

		$type_flag = ( ! empty( $args['type'] ) && AuctionType::is_valid( (string) $args['type'] ) ) ? 1 : 0;
		$type      = $type_flag ? (string) $args['type'] : '';

		if ( ! empty( $args['visibility'] ) && Visibility::is_valid( (string) $args['visibility'] ) ) {
			$vis_enforce = 1;
			$visibility  = (string) $args['visibility'];
		} elseif ( ! empty( $args['include_unlisted'] ) ) {
			$vis_enforce = 0;
			$visibility  = Visibility::PUBLIC_LISTED;
		} else {
			$vis_enforce = 1;
			$visibility  = Visibility::PUBLIC_LISTED;
		}

		$ending_flag = ! empty( $args['ending_before'] ) ? 1 : 0;
		$ending      = $ending_flag ? (string) $args['ending_before'] : '';

		$states = array();
		if ( ! empty( $args['state'] ) ) {
			$states = array_values(
				array_filter(
					array_map( 'sanitize_key', (array) $args['state'] ),
					static fn( string $state ) => AuctionState::is_valid( $state )
				)
			);
		}
		$state_flag = $states ? 1 : 0;
		$states     = array_pad( array_slice( $states, 0, 20 ), 20, '' );

		$search_flag = ! empty( $args['search'] ) ? 1 : 0;
		$like        = $search_flag ? ( '%' . $GLOBALS['wpdb']->esc_like( (string) $args['search'] ) . '%' ) : '';
		$posts       = $GLOBALS['wpdb']->posts;
		$cpt         = Config::CPT;

		$params = array_merge(
			array(
				$holder_flag,
				$holder_id,
				$type_flag,
				$type,
				$vis_enforce,
				$visibility,
				$ending_flag,
				$ending,
				$state_flag,
			),
			$states,
			array(
				$search_flag,
				$posts,
				$cpt,
				$like,
			)
		);

		return array( 'params' => $params );
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private function order_column( array $args ): string {
		$orderby = (string) ( $args['orderby'] ?? 'end_at_utc' );
		$allowed = array( 'end_at_utc', 'start_at_utc', 'created_at_utc', 'updated_at_utc', 'current_amount', 'bid_count' );
		if ( ! in_array( $orderby, $allowed, true ) ) {
			return 'end_at_utc';
		}

		return $orderby;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function sanitize_row( array $data ): array {
		$allowed = array(
			'auction_id',
			'holder_id',
			'product_id',
			'type',
			'visibility',
			'state',
			'currency',
			'starting_amount',
			'reserve_amount',
			'reserve_display',
			'current_amount',
			'min_increment',
			'increment_strategy',
			'buy_now_amount',
			'current_leader_id',
			'bid_count',
			'sequence',
			'start_at_utc',
			'end_at_utc',
			'original_end_at_utc',
			'extension_count',
			'soft_close_window',
			'soft_close_extend',
			'soft_close_max',
			'payment_deadline_hours',
			'quantity',
			'tax_class',
			'shipping_class',
			'fulfilment_type',
			'timezone',
			'proxy_enabled',
			'award_id',
			'order_id',
			'settlement_id',
			'terms_version',
			'unlisted_token_hash',
			'created_at_utc',
			'updated_at_utc',
		);

		$out = array();
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$out[ $key ] = $data[ $key ];
			}
		}

		return $out;
	}
}
