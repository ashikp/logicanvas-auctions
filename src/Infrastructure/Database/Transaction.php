<?php
/**
 * Database transaction helper.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\Database;

use Throwable;
use wpdb;

final class Transaction {

	private static int $depth = 0;

	public static function run( callable $callback ): mixed {
		global $wpdb;

		if ( 0 === self::$depth ) {
			$cache_key = 'wcap_tx_start';
			wp_cache_get( $cache_key, QueryCache::GROUP );
			$wpdb->query( 'START TRANSACTION' );
			wp_cache_set( $cache_key, microtime( true ), QueryCache::GROUP, MINUTE_IN_SECONDS );
		}

		++self::$depth;

		try {
			$result = $callback( $wpdb );
			--self::$depth;
			if ( 0 === self::$depth ) {
				$cache_key = 'wcap_tx_commit';
				wp_cache_get( $cache_key, QueryCache::GROUP );
				$wpdb->query( 'COMMIT' );
				wp_cache_set( $cache_key, microtime( true ), QueryCache::GROUP, MINUTE_IN_SECONDS );
				wp_cache_delete( 'wcap_tx_start', QueryCache::GROUP );
			}
			return $result;
		} catch ( Throwable $e ) {
			--self::$depth;
			if ( 0 === self::$depth ) {
				$cache_key = 'wcap_tx_rollback';
				wp_cache_get( $cache_key, QueryCache::GROUP );
				$wpdb->query( 'ROLLBACK' );
				wp_cache_set( $cache_key, microtime( true ), QueryCache::GROUP, MINUTE_IN_SECONDS );
				wp_cache_delete( 'wcap_tx_start', QueryCache::GROUP );
			}
			throw $e;
		}
	}

	public static function db(): wpdb {
		global $wpdb;
		return $wpdb;
	}
}
