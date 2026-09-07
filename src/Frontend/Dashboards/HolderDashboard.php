<?php
/**
 * Holder dashboard data.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Frontend\Dashboards;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Auction\Auction;
use LogicanvasAuctions\Domain\Holder\HolderService;
use LogicanvasAuctions\Domain\Order\HolderOrderService;
use LogicanvasAuctions\Domain\Order\OrderManagement;
use LogicanvasAuctions\Domain\Settlement\PayoutRequestService;
use LogicanvasAuctions\Frontend\PluginPages;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository;

final class HolderDashboard {

	/**
	 * @return array<string, mixed>
	 */
	public function data( int $user_id ): array {
		$holders = new HolderService();
		$account = $holders->for_user( $user_id );
		$admin   = user_can( $user_id, 'manage_options' );
		$status  = $admin ? HolderService::APPROVED : ( is_array( $account ) ? (string) $account['status'] : '' );
		$can     = $holders->is_approved( $user_id ) && ! $holders->is_suspended_user( $user_id );

		// Heal stuck payment_pending auctions when WooCommerce order is already paid.
		( new HolderOrderService() )->reconcile_for_holder( $user_id );

		$repo     = new WpdbAuctionRepository();
		$auctions = $repo->query(
			array(
				'holder_id'        => $user_id,
				'include_unlisted' => true,
				'limit'            => 100,
				'orderby'          => 'updated_at_utc',
				'order'            => 'DESC',
			)
		);

		$groups = array(
			'drafts'    => array(),
			'pending'   => array(),
			'upcoming'  => array(),
			'live'      => array(),
			'payment'   => array(),
			'completed' => array(),
		);

		foreach ( $auctions as $auction ) {
			$groups[ $this->bucket( $auction ) ][] = $this->row( $auction );
		}

		$settlements = array();
		global $wpdb;

		$table = Config::table( Config::TABLE_SETTLEMENTS );
		$rows  = QueryCache::remember(
			QueryCache::key( 'holder_settlements', $user_id ),
			60,
			static function () use ( $wpdb, $table, $user_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT auction_id, gross_amount, commission_amount, net_amount, released_amount, currency, payout_status FROM %i WHERE holder_id = %d ORDER BY id DESC LIMIT 20',
						$table,
						$user_id
					),
					ARRAY_A
				);
			}
		);
		if ( is_array( $rows ) ) {
			$settlements = $rows;
		}

		$payouts          = new PayoutRequestService();
		$available        = $payouts->available_balance( $user_id );
		$payment_method   = $payouts->get_payment_method( $user_id );
		$payout_requests  = $payouts->for_holder( $user_id );
		$featured         = $groups['live'][0] ?? $groups['upcoming'][0] ?? $groups['payment'][0] ?? null;

		$accept_ready = array();
		foreach ( $groups['live'] as $row ) {
			if ( ! empty( $row['can_accept_bid'] ) ) {
				$accept_ready[] = $row;
			}
		}

		return array(
			'status'              => $status,
			'account'             => $account,
			'can_create'          => $can,
			'is_admin'            => $admin,
			'profile'             => AccountProfile::for_user( $user_id ),
			'watch_count'         => AccountProfile::watch_count( $user_id ),
			'pending_payout'      => $available['formatted'],
			'available_balance'   => $available,
			'payment_method'      => $payment_method,
			'payment_method_ok'   => $payouts->payment_method_is_complete( $user_id ),
			'payment_methods'     => PayoutRequestService::method_labels(),
			'payout_requests'     => $payout_requests,
			'featured'            => $featured,
			'accept_ready'        => $accept_ready,
			'counts'              => array(
				'total'        => count( $auctions ),
				'drafts'       => count( $groups['drafts'] ),
				'pending'      => count( $groups['pending'] ),
				'upcoming'     => count( $groups['upcoming'] ),
				'live'         => count( $groups['live'] ),
				'payment'      => count( $groups['payment'] ),
				'completed'    => count( $groups['completed'] ),
				'accept_ready' => count( $accept_ready ),
			),
			'groups'              => $groups,
			'settlements'         => $settlements,
			'orders'              => ( new HolderOrderService() )->list_for_holder( $user_id ),
			'order_managed_by'    => OrderManagement::mode(),
			'urls'                => array(
				'submit' => PluginPages::url( 'submit' ),
				'apply'  => PluginPages::url( 'apply' ),
				'live'   => PluginPages::url( 'live' ),
				'host'   => PluginPages::url( 'live' ),
			),
		);
	}

	private function bucket( Auction $auction ): string {
		$state = $auction->state();
		if ( in_array( $state, array( 'draft', 'rejected' ), true ) ) {
			return 'drafts';
		}
		if ( 'pending_review' === $state ) {
			return 'pending';
		}
		if ( in_array( $state, array( 'scheduled', 'lobby' ), true ) ) {
			return 'upcoming';
		}
		if ( in_array( $state, array( 'live', 'paused', 'going_once', 'going_twice', 'active', 'paused_admin', 'closing' ), true ) ) {
			return 'live';
		}
		if ( in_array( $state, array( 'payment_pending', 'sold_payment_pending' ), true ) ) {
			return 'payment';
		}

		return 'completed';
	}

	/**
	 * @return array<string, mixed>
	 */
	private function row( Auction $auction ): array {
		$post = get_post( $auction->id() );
		$live = PluginPages::url( 'live', array( 'auction_id' => $auction->id() ) );
		$edit = '';
		if ( in_array( $auction->state(), array( 'draft', 'rejected', 'pending_review', 'scheduled' ), true ) ) {
			$edit = AccountRouter::url(
				AccountRouter::EDIT,
				AccountRouter::MODE_SELLER,
				array( 'auction_id' => $auction->id() )
			);
		}

		return array(
			'id'             => $auction->id(),
			'title'          => $post ? $post->post_title : '#' . $auction->id(),
			'type'           => $auction->type(),
			'state'          => $auction->state(),
			'price'          => $auction->current_amount()->formatted() . ' ' . $auction->currency(),
			'bids'           => $auction->bid_count(),
			'permalink'      => get_permalink( $auction->id() ),
			'edit'           => $edit,
			'host'           => $auction->is_live() ? $live : '',
			'can_accept_bid' => $auction->is_timed()
				&& in_array( $auction->state(), array( 'active', 'paused_admin' ), true )
				&& $auction->bid_count() > 0,
			'end'            => $auction->end_at_utc(),
			'image'          => get_the_post_thumbnail_url( $auction->id(), 'medium' ) ?: '',
		);
	}
}
