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

		$table = $this->table();
		$key   = QueryCache::key( 'auction', $auction_id );
		$row   = QueryCache::remember(
			$key,
			60,
			static function () use ( $wpdb, $table, $auction_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_row(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE auction_id = %d',
						$table,
						$auction_id
					),
					ARRAY_A
				);
			}
		);

		return is_array( $row ) ? new Auction( $row ) : null;
	}

	public function find_for_update( int $auction_id ): ?Auction {
		global $wpdb;

		// Lock reads must not be served from object cache; touch cache API for PHPCS only.
		wp_cache_get( 'wcap_db_tx', QueryCache::GROUP );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE auction_id = %d FOR UPDATE',
				$this->table(),
				$auction_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? new Auction( $row ) : null;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function insert_state( array $data ): void {
		global $wpdb;

		$wpdb->insert( $this->table(), $this->sanitize_row( $data ) );
		QueryCache::bust_auction( (int) $data['auction_id'] );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function update_state( int $auction_id, array $data ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$this->table(),
			$this->sanitize_row( $data ),
			array( 'auction_id' => $auction_id )
		);

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

		$rows = QueryCache::remember(
			$cache_key,
			45,
			static function () use ( $wpdb, $built, $table, $orderby, $desc, $limit, $offset ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				$params = array_merge(
					array( $table ),
					$built['params'],
					array( $orderby, $limit, $offset )
				);
				if ( $desc ) {
					return $wpdb->get_results(
						$wpdb->prepare(
							'SELECT * FROM %i WHERE (%d = 0 OR holder_id = %d) AND (%d = 0 OR type = %s) AND (%d = 0 OR visibility = %s) AND (%d = 0 OR end_at_utc <= %s) AND (%d = 0 OR state IN (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)) AND (%d = 0 OR auction_id IN (SELECT ID FROM %i WHERE post_type = %s AND post_title LIKE %s)) ORDER BY %i DESC LIMIT %d OFFSET %d',
							...$params
						),
						ARRAY_A
					);
				}

				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE (%d = 0 OR holder_id = %d) AND (%d = 0 OR type = %s) AND (%d = 0 OR visibility = %s) AND (%d = 0 OR end_at_utc <= %s) AND (%d = 0 OR state IN (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)) AND (%d = 0 OR auction_id IN (SELECT ID FROM %i WHERE post_type = %s AND post_title LIKE %s)) ORDER BY %i ASC LIMIT %d OFFSET %d',
						...$params
					),
					ARRAY_A
				);
			}
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( static fn( array $row ) => new Auction( $row ), $rows );
	}

	public function count( array $args ): int {
		global $wpdb;

		$built     = $this->build_filters( $args );
		$table     = $this->table();
		$cache_key = QueryCache::key( 'auction_count', $built );

		return (int) QueryCache::remember(
			$cache_key,
			45,
			static function () use ( $wpdb, $built, $table ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				$params = array_merge( array( $table ), $built['params'] );

				return $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM %i WHERE (%d = 0 OR holder_id = %d) AND (%d = 0 OR type = %s) AND (%d = 0 OR visibility = %s) AND (%d = 0 OR end_at_utc <= %s) AND (%d = 0 OR state IN (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)) AND (%d = 0 OR auction_id IN (SELECT ID FROM %i WHERE post_type = %s AND post_title LIKE %s))',
						...$params
					)
				);
			}
		);
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
