<?php
/**
 * Rate limiter with atomic counters (object cache or options SQL).
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\Database;

use LogicanvasAuctions\Admin\Settings;
use LogicanvasAuctions\Config;

final class RateLimiter {

	private const CACHE_GROUP = 'logicanvas-auctions-rl';

	/**
	 * Record one hit against a bucket. Returns false when the limit is exceeded.
	 *
	 * Concurrent requests cannot evade the limit via a non-atomic read/write race.
	 */
	public function hit( string $bucket, int $limit, int $window_seconds = 60 ): bool {
		if ( $limit < 1 ) {
			return false;
		}

		$key = Config::PREFIX . 'rl_' . md5( $bucket );

		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			return $this->hit_object_cache( $key, $limit, $window_seconds );
		}

		return $this->hit_options( $key, $limit, $window_seconds );
	}

	public function allow_bid( int $user_id, int $auction_id, string $ip_hash ): bool {
		$settings = Settings::get();
		$user_ok  = $this->hit( 'bid_user_' . $user_id, (int) $settings['bid_rate_limit_user'] );
		$auc_ok   = $this->hit( 'bid_auc_' . $auction_id . '_' . $user_id, (int) $settings['bid_rate_limit_auction'] );
		$ip_ok    = $this->hit( 'bid_ip_' . $ip_hash, (int) $settings['bid_rate_limit_ip'] );

		return $user_ok && $auc_ok && $ip_ok;
	}

	private function hit_object_cache( string $key, int $limit, int $window_seconds ): bool {
		// Seed the counter once; concurrent adders lose and then incr.
		wp_cache_add( $key, 0, self::CACHE_GROUP, $window_seconds );
		$count = wp_cache_incr( $key, 1, self::CACHE_GROUP );

		if ( false === $count ) {
			return false;
		}

		return (int) $count <= $limit;
	}

	/**
	 * Atomic increment against the options table (transient storage without object cache).
	 *
	 * A single UPDATE with a limit predicate is race-safe at the database level.
	 */
	private function hit_options( string $key, int $limit, int $window_seconds ): bool {
		global $wpdb;

		$name         = '_transient_' . $key;
		$timeout_name = '_transient_timeout_' . $key;
		$now          = time();
		$expires      = $now + max( 1, $window_seconds );

		$this->ensure_rate_window( $name, $timeout_name, $now, $expires );

		// Increment only while under the limit (atomic at DB level).
		$rows = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s AND option_value+0 < %d",
				$name,
				$limit
			)
		);

		if ( false === $rows ) {
			return false;
		}

		if ( $rows > 0 ) {
			$this->bust_option_cache( $name );
			return true;
		}

		// First writer may need to create the counter row.
		if ( false !== add_option( $name, '1', '', false ) ) {
			$this->bust_option_cache( $name );
			return true;
		}

		// Lost the create race — retry the guarded increment once.
		$rows = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s AND option_value+0 < %d",
				$name,
				$limit
			)
		);

		if ( is_int( $rows ) && $rows > 0 ) {
			$this->bust_option_cache( $name );
			return true;
		}

		return false;
	}

	/**
	 * Create or rotate the rate-limit window using atomic option operations.
	 */
	private function ensure_rate_window( string $name, string $timeout_name, int $now, int $expires ): void {
		global $wpdb;

		if ( false !== add_option( $timeout_name, (string) $expires, '', false ) ) {
			add_option( $name, '0', '', false );
			$this->bust_option_cache( $name );
			$this->bust_option_cache( $timeout_name );
			return;
		}

		$timeout = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				$timeout_name
			)
		);

		if ( null === $timeout || (int) $timeout >= $now ) {
			return;
		}

		// Only one request wins the window rotation.
		$claimed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value+0 < %d",
				(string) $expires,
				$timeout_name,
				$now
			)
		);

		if ( ! is_int( $claimed ) || $claimed < 1 ) {
			return;
		}

		$wpdb->update(
			$wpdb->options,
			array( 'option_value' => '0' ),
			array( 'option_name' => $name ),
			array( '%s' ),
			array( '%s' )
		);

		if ( 0 === (int) $wpdb->rows_affected ) {
			add_option( $name, '0', '', false );
		}

		$this->bust_option_cache( $name );
		$this->bust_option_cache( $timeout_name );
	}

	private function bust_option_cache( string $option_name ): void {
		wp_cache_delete( $option_name, 'options' );
	}
}
