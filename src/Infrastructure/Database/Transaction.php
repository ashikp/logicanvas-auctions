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
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional control; not a cacheable read.
			$wpdb->query( 'START TRANSACTION' );
		}

		++self::$depth;

		try {
			$result = $callback( $wpdb );
			--self::$depth;
			if ( 0 === self::$depth ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional control; not a cacheable read.
				$wpdb->query( 'COMMIT' );
			}
			return $result;
		} catch ( Throwable $e ) {
			--self::$depth;
			if ( 0 === self::$depth ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional control; not a cacheable read.
				$wpdb->query( 'ROLLBACK' );
			}
			throw $e;
		}
	}

	public static function db(): wpdb {
		global $wpdb;
		return $wpdb;
	}
}
