<?php
/**
 * Atomic option-backed locks using add_option() return value.
 *
 * add_option() performs an INSERT and returns false when the row already
 * exists, so it is safe under concurrency without a prior get_option() check.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\Database;

use LogicanvasAuctions\Config;

final class OptionLock {

	private const CACHE_GROUP = 'logicanvas-auctions-locks';

	/**
	 * Acquire an exclusive lock. Returns true only for the winning request.
	 *
	 * @param string $key Logical lock key (without plugin prefix).
	 * @param int    $ttl Seconds before a crash-orphaned option lock is considered stale.
	 */
	public static function acquire( string $key, int $ttl = 30 ): bool {
		$cache_key = Config::PREFIX . $key;

		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			return (bool) wp_cache_add( $cache_key, time(), self::CACHE_GROUP, $ttl );
		}

		$option = Config::PREFIX . $key;
		$now    = time();

		if ( false !== add_option( $option, (string) $now, '', false ) ) {
			return true;
		}

		// Recover orphaned locks left after a fatal error / killed request.
		$existing = get_option( $option, false );
		if ( ! is_numeric( $existing ) || ( $now - (int) $existing ) <= $ttl ) {
			return false;
		}

		delete_option( $option );

		return false !== add_option( $option, (string) $now, '', false );
	}

	/**
	 * Release a previously acquired lock.
	 *
	 * @param string $key Logical lock key (without plugin prefix).
	 */
	public static function release( string $key ): void {
		$cache_key = Config::PREFIX . $key;

		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			wp_cache_delete( $cache_key, self::CACHE_GROUP );
			return;
		}

		delete_option( Config::PREFIX . $key );
	}

	/**
	 * Claim a permanent one-shot marker (idempotency). Never releases.
	 *
	 * @param string $key Full option name (already unique / hashed).
	 * @return bool True if this request claimed the marker; false if already claimed.
	 */
	public static function claim_once( string $key ): bool {
		return false !== add_option( $key, '1', '', false );
	}
}
