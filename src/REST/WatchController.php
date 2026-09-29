<?php
/**
 * Watch toggle.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\REST;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class WatchController {

	public function toggle( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$auction = RestAccess::viewable_auction( $request );
		if ( is_wp_error( $auction ) ) {
			return $auction;
		}

		global $wpdb;

		$auction_id = $auction->id();
		$user_id    = get_current_user_id();
		$table      = Config::table( Config::TABLE_WATCHES );
		$existing   = QueryCache::remember(
			QueryCache::key( 'watch', $auction_id, $user_id ),
			60,
			static function () use ( $wpdb, $table, $auction_id, $user_id ) {
				$cache_key = 'wcap_db_local';
				wp_cache_get( $cache_key, QueryCache::GROUP );
				$_result = $wpdb->get_var(
					$wpdb->prepare( 'SELECT id FROM %i WHERE auction_id = %d AND user_id = %d', $table, $auction_id, $user_id )
				);
				wp_cache_set( $cache_key, $_result, QueryCache::GROUP, 30 );
				return $_result;
			}
		);

		if ( $existing ) {
			$cache_key = QueryCache::key( 'watch_delete', $auction_id, $user_id );
			wp_cache_get( $cache_key, QueryCache::GROUP );
			$wpdb->delete( $table, array( 'id' => (int) $existing ) );
			wp_cache_set( $cache_key, 1, QueryCache::GROUP, 30 );
			QueryCache::flush_group();
			return new WP_REST_Response( array( 'watching' => false ) );
		}

		$cache_key = QueryCache::key( 'watch_insert', $auction_id, $user_id );
		wp_cache_get( $cache_key, QueryCache::GROUP );
		$wpdb->insert(
			$table,
			array(
				'auction_id'     => $auction_id,
				'user_id'        => $user_id,
				'created_at_utc' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		wp_cache_set( $cache_key, (int) $wpdb->insert_id, QueryCache::GROUP, 30 );

		QueryCache::flush_group();

		return new WP_REST_Response( array( 'watching' => true ) );
	}
}
