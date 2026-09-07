<?php
/**
 * Bidder dashboard data.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Frontend\Dashboards;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Bidding\Bid;
use LogicanvasAuctions\Domain\Holder\HolderService;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository;

final class BidderDashboard {

	/**
	 * @return array<string, mixed>
	 */
	public function data( int $user_id ): array {
		global $wpdb;

		$profile = AccountProfile::for_user( $user_id );
		$holders = new HolderService();
		$repo    = new WpdbAuctionRepository();

		$state_t      = Config::table( Config::TABLE_AUCTION_STATE );
		$leading_rows = QueryCache::remember(
			QueryCache::key( 'bidder_leading', $user_id ),
			45,
			static function () use ( $wpdb, $state_t, $user_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT auction_id, current_amount, currency, state, bid_count, end_at_utc FROM %i WHERE current_leader_id = %d ORDER BY updated_at_utc DESC LIMIT 20',
						$state_t,
						$user_id
					),
					ARRAY_A
				);
			}
		);
		if ( ! is_array( $leading_rows ) ) {
			$leading_rows = array();
		}

		$leading = array();
		foreach ( $leading_rows as $row ) {
			$auction = $repo->find( (int) $row['auction_id'] );
			$post    = get_post( (int) $row['auction_id'] );
			$leading[] = array(
				'id'        => (int) $row['auction_id'],
				'title'     => $post ? $post->post_title : '#' . $row['auction_id'],
				'state'     => (string) $row['state'],
				'price'     => (string) $row['current_amount'] . ' ' . (string) $row['currency'],
				'bids'      => (int) $row['bid_count'],
				'end'       => (string) $row['end_at_utc'],
				'permalink' => get_permalink( (int) $row['auction_id'] ),
				'image'     => get_the_post_thumbnail_url( (int) $row['auction_id'], 'medium' ) ?: '',
				'type'      => $auction ? $auction->type() : 'timed',
			);
		}

		$award_t    = Config::table( Config::TABLE_AWARDS );
		$award_rows = QueryCache::remember(
			QueryCache::key( 'bidder_awards', $user_id ),
			45,
			static function () use ( $wpdb, $award_t, $user_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT id, auction_id, amount, currency, status, payment_deadline_utc, order_id FROM %i WHERE winner_id = %d ORDER BY id DESC LIMIT 30',
						$award_t,
						$user_id
					),
					ARRAY_A
				);
			}
		);
		if ( ! is_array( $award_rows ) ) {
			$award_rows = array();
		}

		$awards = array();
		$due    = 0;
		$order_service = new \LogicanvasAuctions\Domain\Order\HolderOrderService();
		foreach ( $award_rows as $row ) {
			$post = get_post( (int) $row['auction_id'] );
			if ( 'pending' === $row['status'] ) {
				++$due;
			}
			$order_fields = $order_service->award_order_fields( (int) ( $row['order_id'] ?? 0 ) );
			$awards[]     = array_merge(
				array(
					'id'         => (int) $row['id'],
					'auction_id' => (int) $row['auction_id'],
					'title'      => $post ? $post->post_title : '#' . $row['auction_id'],
					'amount'     => (string) $row['amount'] . ' ' . (string) $row['currency'],
					'status'     => (string) $row['status'],
					'deadline'   => (string) $row['payment_deadline_utc'],
					'pay_url'    => add_query_arg( 'award_id', (int) $row['id'], $profile['urls']['pay'] ?: get_permalink() ),
				),
				$order_fields
			);
		}

		return array(
			'profile'     => $profile,
			'can_create'  => $holders->is_approved( $user_id ) && ! $holders->is_suspended_user( $user_id ),
			'is_admin'    => user_can( $user_id, 'manage_options' ),
			'holder'      => $holders->for_user( $user_id ),
			'watch_count' => AccountProfile::watch_count( $user_id ),
			'leading'     => $leading,
			'bids'        => $this->bids( $user_id, $repo ),
			'watches'     => AccountProfile::watched( $user_id ),
			'awards'      => $awards,
			'counts'      => array(
				'leading' => count( $leading ),
				'awards'  => count( $awards ),
				'due'     => $due,
			),
		);
	}

	/**
	 * Latest accepted bid per auction for this bidder.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function bids( int $user_id, WpdbAuctionRepository $repo ): array {
		global $wpdb;

		$bids_t  = Config::table( Config::TABLE_BIDS );
		$state_t = Config::table( Config::TABLE_AUCTION_STATE );
		$rows    = QueryCache::remember(
			QueryCache::key( 'bidder_bids', $user_id ),
			45,
			static function () use ( $wpdb, $bids_t, $state_t, $user_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT b.auction_id, b.amount, b.currency, b.created_at_utc, b.status, s.current_leader_id, s.current_amount, s.state, s.bid_count
						FROM %i b
						LEFT JOIN %i s ON s.auction_id = b.auction_id
						WHERE b.bidder_id = %d AND b.status = %s AND b.voided_at_utc IS NULL
						ORDER BY b.id DESC LIMIT 80',
						$bids_t,
						$state_t,
						$user_id,
						Bid::STATUS_ACCEPTED
					),
					ARRAY_A
				);
			}
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			$id = (int) $row['auction_id'];
			if ( isset( $out[ $id ] ) ) {
				continue;
			}
			$post    = get_post( $id );
			$auction = $repo->find( $id );
			$leader  = (int) ( $row['current_leader_id'] ?? 0 );
			$out[ $id ] = array(
				'id'        => $id,
				'title'     => $post ? $post->post_title : '#' . $id,
				'state'     => (string) ( $row['state'] ?? '' ),
				'your_bid'  => (string) $row['amount'] . ' ' . (string) $row['currency'],
				'price'     => (string) ( $row['current_amount'] ?? $row['amount'] ) . ' ' . (string) $row['currency'],
				'bids'      => (int) ( $row['bid_count'] ?? 0 ),
				'status'    => $leader === $user_id ? __( 'Leading', 'logicanvas-auctions' ) : __( 'Outbid', 'logicanvas-auctions' ),
				'leading'   => $leader === $user_id,
				'permalink' => get_permalink( $id ),
				'image'     => get_the_post_thumbnail_url( $id, 'medium' ) ?: '',
				'type'      => $auction ? $auction->type() : 'timed',
			);
		}

		return array_values( $out );
	}
}
