<?php
/**
 * Append-only auction event / outbox store.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\Database;

use LogicanvasAuctions\Config;

final class WpdbEventRepository {

	private function table(): string {
		return Config::table( Config::TABLE_EVENTS );
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public function append(
		int $auction_id,
		int $sequence,
		string $event_type,
		int $actor_id,
		string $actor_type,
		array $payload,
		string $created_at_utc,
		?string $correlation_key = null
	): int {
		global $wpdb;

		$wpdb->insert(
			$this->table(),
			array(
				'auction_id'       => $auction_id,
				'sequence'         => $sequence,
				'event_type'       => $event_type,
				'actor_id'         => $actor_id,
				'actor_type'       => $actor_type,
				'payload'          => wp_json_encode( $payload ),
				'correlation_key'  => $correlation_key,
				'created_at_utc'   => $created_at_utc,
			)
		);

		QueryCache::bust_auction( $auction_id );

		return (int) $wpdb->insert_id;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function since( int $auction_id, int $after_sequence, int $limit = 100 ): array {
		global $wpdb;

		$table = $this->table();
		$key   = QueryCache::key( 'events_since', $auction_id, $after_sequence, $limit );
		$rows  = QueryCache::remember(
			$key,
			15,
			static function () use ( $wpdb, $table, $auction_id, $after_sequence, $limit ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE auction_id = %d AND sequence > %d ORDER BY sequence ASC LIMIT %d',
						$table,
						$auction_id,
						$after_sequence,
						$limit
					),
					ARRAY_A
				);
			}
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( array $row ): array {
				$row['payload'] = json_decode( (string) $row['payload'], true ) ?: array();
				return $row;
			},
			$rows
		);
	}

	public function latest_sequence( int $auction_id ): int {
		global $wpdb;

		$table = $this->table();
		$key   = QueryCache::key( 'event_seq', $auction_id );

		return (int) QueryCache::remember(
			$key,
			15,
			static function () use ( $wpdb, $table, $auction_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_var(
					$wpdb->prepare(
						'SELECT MAX(sequence) FROM %i WHERE auction_id = %d',
						$table,
						$auction_id
					)
				);
			}
		);
	}
}
