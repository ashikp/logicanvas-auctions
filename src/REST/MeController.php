<?php
/**
 * Authenticated dashboard payload.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\REST;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository;
use WP_REST_Request;
use WP_REST_Response;

final class MeController {

	public function dashboard( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$user_id = get_current_user_id();
		$repo    = new WpdbAuctionRepository();
		global $wpdb;

		$state_t = Config::table( Config::TABLE_AUCTION_STATE );
		$leading = QueryCache::remember(
			QueryCache::key( 'me_leading', $user_id ),
			45,
			static function () use ( $wpdb, $state_t, $user_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT auction_id, current_amount, currency, state FROM %i WHERE current_leader_id = %d ORDER BY updated_at_utc DESC LIMIT 50',
						$state_t,
						$user_id
					),
					ARRAY_A
				);
			}
		);

		$award_t = Config::table( Config::TABLE_AWARDS );
		$awards  = QueryCache::remember(
			QueryCache::key( 'me_awards', $user_id ),
			45,
			static function () use ( $wpdb, $award_t, $user_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT id, auction_id, amount, currency, status, payment_deadline_utc, order_id FROM %i WHERE winner_id = %d ORDER BY id DESC LIMIT 50',
						$award_t,
						$user_id
					),
					ARRAY_A
				);
			}
		);

		$owned = $repo->query(
			array(
				'holder_id'        => $user_id,
				'include_unlisted' => true,
				'limit'            => 50,
			)
		);

		return new WP_REST_Response(
			array(
				'leading'  => $leading ?: array(),
				'awards'   => $awards ?: array(),
				'auctions' => array_map(
					static fn( $a ) => array(
						'id'    => $a->id(),
						'state' => $a->state(),
						'type'  => $a->type(),
					),
					$owned
				),
			)
		);
	}
}
