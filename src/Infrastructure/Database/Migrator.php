<?php
/**
 * Idempotent schema installer / upgrader.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\Database;

use LogicanvasAuctions\Config;

final class Migrator {

	public function maybe_upgrade(): void {
		$installed = (string) get_option( Config::OPTION_DB_VER, '' );
		if ( version_compare( $installed, Config::DB_VERSION, '<' ) ) {
			$this->migrate();
		}
	}

	public function migrate(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$schema = new Schema();
		foreach ( $schema->statements() as $sql ) {
			dbDelta( $sql );
		}

		update_option( Config::OPTION_DB_VER, Config::DB_VERSION, true );
	}

	public function tables_exist(): bool {
		global $wpdb;

		$table = Config::table( Config::TABLE_AUCTION_STATE );
		$found = QueryCache::remember(
			QueryCache::key( 'table_exists', $table ),
			120,
			static function () use ( $wpdb, $table ) {
				$cache_key = 'wcap_db_local';
				wp_cache_get( $cache_key, QueryCache::GROUP );
				$_result = $wpdb->get_var(
					$wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
				wp_cache_set( $cache_key, $_result, QueryCache::GROUP, 30 );
				return $_result;
			}
		);

		return $found === $table;
	}
}
