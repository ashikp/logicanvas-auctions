<?php
/**
 * Object-cache helpers for custom-table reads.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\Database;

final class QueryCache {

	public const GROUP = 'wcap';

	/**
	 * @template T
	 * @param callable():T $callback
	 * @return T
	 */
	public static function remember( string $key, int $ttl, callable $callback ): mixed {
		$found = false;
		$cached = wp_cache_get( $key, self::GROUP, false, $found );
		if ( $found ) {
			return $cached;
		}

		$value = $callback();
		wp_cache_set( $key, $value, self::GROUP, $ttl );

		return $value;
	}

	public static function delete( string $key ): void {
		wp_cache_delete( $key, self::GROUP );
	}

	public static function flush_group(): void {
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::GROUP );
			return;
		}

		// Without a persistent group-flush backend, bump a generation key.
		$gen = (int) wp_cache_get( 'wcap_cache_gen', self::GROUP );
		wp_cache_set( 'wcap_cache_gen', $gen + 1, self::GROUP, DAY_IN_SECONDS );
	}

	public static function key( string $prefix, mixed ...$parts ): string {
		$gen = (int) wp_cache_get( 'wcap_cache_gen', self::GROUP );

		return $prefix . ':' . $gen . ':' . md5( wp_json_encode( $parts ) );
	}

	public static function bust_auction( int $auction_id ): void {
		self::delete( self::key( 'auction', $auction_id ) );
		self::delete( self::key( 'auction_lock', $auction_id ) );
		self::flush_group();
	}
}
