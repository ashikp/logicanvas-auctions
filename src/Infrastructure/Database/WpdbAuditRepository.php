<?php
/**
 * Audit log repository.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\Database;

use LogicanvasAuctions\Config;

final class WpdbAuditRepository {

	/**
	 * @param array<string, mixed> $metadata
	 */
	public function write(
		int $auction_id,
		string $action,
		int $actor_id,
		string $reason,
		array $metadata,
		string $created_at_utc,
		string $actor_type = 'user',
		?string $correlation_key = null
	): void {
		global $wpdb;

		$wpdb->insert(
			Config::table( Config::TABLE_AUDIT ),
			array(
				'auction_id'       => $auction_id,
				'action'           => $action,
				'actor_id'         => $actor_id,
				'actor_type'       => $actor_type,
				'reason'           => $reason,
				'correlation_key'  => $correlation_key,
				'metadata'         => wp_json_encode( $metadata ),
				'created_at_utc'   => $created_at_utc,
			)
		);

		if ( $auction_id > 0 ) {
			QueryCache::bust_auction( $auction_id );
		} else {
			QueryCache::flush_group();
		}
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function for_auction( int $auction_id, int $limit = 100 ): array {
		global $wpdb;

		$table = Config::table( Config::TABLE_AUDIT );
		$key   = QueryCache::key( 'audit', $auction_id, $limit );
		$rows  = QueryCache::remember(
			$key,
			60,
			static function () use ( $wpdb, $table, $auction_id, $limit ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE auction_id = %d ORDER BY id DESC LIMIT %d',
						$table,
						$auction_id,
						$limit
					),
					ARRAY_A
				);
			}
		);

		return is_array( $rows ) ? $rows : array();
	}
}
