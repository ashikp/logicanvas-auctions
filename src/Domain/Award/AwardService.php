<?php
/**
 * Closing, winner determination, awards.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Award;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Core\ClockInterface;
use LogicanvasAuctions\Core\SystemClock;
use LogicanvasAuctions\Domain\Auction\Auction;
use LogicanvasAuctions\Domain\Auction\AuctionRepositoryInterface;
use LogicanvasAuctions\Domain\Auction\AuctionService;
use LogicanvasAuctions\Domain\Auction\AuctionState;
use LogicanvasAuctions\Domain\Bidding\Bid;
use LogicanvasAuctions\Domain\Bidding\BidRepositoryInterface;
use LogicanvasAuctions\Infrastructure\Database\Transaction;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository;
use LogicanvasAuctions\Infrastructure\Database\WpdbBidRepository;
use LogicanvasAuctions\Infrastructure\Database\WpdbEventRepository;
use LogicanvasAuctions\Infrastructure\Scheduler\AuctionScheduler;
use LogicanvasAuctions\Domain\Settlement\SettlementService;

final class AwardService {

	public const PENDING   = 'pending';
	public const PAID      = 'paid';
	public const DEFAULTED = 'defaulted';
	public const CANCELLED = 'cancelled';
	public const EXPIRED   = 'expired';

	public function __construct(
		private AuctionRepositoryInterface $auctions = new WpdbAuctionRepository(),
		private BidRepositoryInterface $bids = new WpdbBidRepository(),
		private WpdbEventRepository $events = new WpdbEventRepository(),
		private ClockInterface $clock = new SystemClock(),
		private AuctionService $auction_service = new AuctionService(),
		private AuctionScheduler $scheduler = new AuctionScheduler()
	) {}

	/**
	 * Idempotent close for timed auctions (and live sell path).
	 */
	public function close( int $auction_id, int $actor_id = 0, string $reason = 'Scheduled close' ): void {
		$lock = 'wcap_close_' . $auction_id;
		if ( ! $this->acquire( $lock ) ) {
			return;
		}

		try {
			Transaction::run(
				function () use ( $auction_id, $actor_id, $reason ) {
					$auction = $this->auctions->find_for_update( $auction_id );
					if ( ! $auction ) {
						return;
					}

					if ( in_array( $auction->state(), array( AuctionState::PAYMENT_PENDING, AuctionState::SOLD_PAYMENT_PENDING, AuctionState::PAID, AuctionState::COMPLETED, AuctionState::UNSOLD, AuctionState::RESERVE_NOT_MET, AuctionState::CANCELLED, AuctionState::PAYMENT_DEFAULTED ), true ) ) {
						return;
					}

					if ( $auction->is_live() && 0 === $actor_id ) {
						return;
					}

					if ( $auction->is_timed() && $auction->end_at_utc() ) {
						$end = strtotime( $auction->end_at_utc() . ' UTC' );
						if ( $end && $this->clock->timestamp() < $end && 0 === $actor_id ) {
							return;
						}
					}

					$this->auction_service->transition( $auction_id, AuctionState::CLOSING, $actor_id, $actor_id ? 'user' : 'system', $reason, 'close-' . $auction_id );
					$auction = $this->auctions->find_for_update( $auction_id );
					if ( ! $auction ) {
						return;
					}

					$this->auction_service->transition( $auction_id, AuctionState::ENDED, $actor_id, $actor_id ? 'user' : 'system', $reason, 'ended-' . $auction_id );

					$highest = $this->bids->highest_accepted( $auction_id );
					if ( ! $highest || $auction->bid_count() < 1 ) {
						$this->auction_service->transition( $auction_id, AuctionState::UNSOLD, 0, 'system', 'No valid bids', 'unsold-' . $auction_id );
						do_action( 'wcap_auction_closed', $auction_id, 'unsold' );
						return;
					}

					if ( ! $auction->reserve_met() ) {
						$this->auction_service->transition( $auction_id, AuctionState::RESERVE_NOT_MET, 0, 'system', 'Reserve not met', 'reserve-' . $auction_id );
						do_action( 'wcap_auction_closed', $auction_id, 'reserve_not_met' );
						return;
					}

					$award_id = $this->create_award( $auction, $highest );
					$payment_state = $auction->is_live() ? AuctionState::SOLD_PAYMENT_PENDING : AuctionState::PAYMENT_PENDING;

					// Live machine has no ENDED; map via closing already happened. Timed uses payment_pending from ended.
					if ( $auction->is_timed() ) {
						$this->auction_service->transition( $auction_id, AuctionState::PAYMENT_PENDING, 0, 'system', 'Winner awarded', 'award-' . $auction_id );
					}

					$this->auctions->update_state(
						$auction_id,
						array(
							'award_id'       => $award_id,
							'updated_at_utc' => $this->clock->utc_mysql(),
						)
					);

					$hours    = $auction->payment_deadline_hours();
					$deadline = gmdate( 'Y-m-d H:i:s', $this->clock->timestamp() + ( $hours * HOUR_IN_SECONDS ) );
					$this->scheduler->schedule_payment_deadline( $award_id, $auction_id, $deadline );

					( new SettlementService() )->create_pending_for_award( $auction_id, $award_id );

					do_action( 'wcap_award_created', $auction_id, $award_id, $highest->bidder_id() );
					do_action( 'wcap_auction_closed', $auction_id, 'sold' );
				}
			);
		} finally {
			$this->release( $lock );
		}
	}

	public function sell_live( int $auction_id, int $host_id ): void {
		$auction = $this->auctions->find( $auction_id );
		if ( ! $auction || ! $auction->is_live() ) {
			return;
		}

		if ( $auction->holder_id() !== $host_id && ! user_can( $host_id, Config::CAP_MODERATE_AUCTIONS ) ) {
			return;
		}

		$this->auction_service->transition( $auction_id, AuctionState::CLOSING, $host_id, 'user', 'Sold by host' );

		$highest = $this->bids->highest_accepted( $auction_id );
		if ( ! $highest ) {
			$this->auction_service->transition( $auction_id, AuctionState::UNSOLD, $host_id, 'user', 'No bids' );
			return;
		}

		$award_id = $this->create_award( $auction, $highest );
		$this->auction_service->transition( $auction_id, AuctionState::SOLD_PAYMENT_PENDING, $host_id, 'user', 'Sold' );
		$this->auctions->update_state( $auction_id, array( 'award_id' => $award_id, 'updated_at_utc' => $this->clock->utc_mysql() ) );
		( new SettlementService() )->create_pending_for_award( $auction_id, $award_id );
		do_action( 'wcap_award_created', $auction_id, $award_id, $highest->bidder_id() );
	}

	/**
	 * Holder (or moderator) accepts the current highest bid on a timed auction immediately.
	 * Waives an unmet reserve because the holder explicitly chose the current price.
	 *
	 * @return true|\WP_Error
	 */
	public function accept_highest_bid( int $auction_id, int $actor_id ) {
		$auction = $this->auctions->find( $auction_id );
		if ( ! $auction ) {
			return new \WP_Error( 'not_found', __( 'Auction not found.', 'logicanvas-auctions' ) );
		}

		if ( ! $auction->is_timed() ) {
			return new \WP_Error( 'invalid_type', __( 'Only timed auctions can accept the current bid this way. Use the live host console to sell.', 'logicanvas-auctions' ) );
		}

		if ( $auction->holder_id() !== $actor_id && ! user_can( $actor_id, Config::CAP_MODERATE_AUCTIONS ) ) {
			return new \WP_Error( 'forbidden', __( 'Only the auction holder can accept the current bid.', 'logicanvas-auctions' ) );
		}

		if ( ! in_array( $auction->state(), array( AuctionState::ACTIVE, AuctionState::PAUSED_ADMIN ), true ) ) {
			return new \WP_Error( 'invalid_state', __( 'This auction is not open for an early sale.', 'logicanvas-auctions' ) );
		}

		$highest = $this->bids->highest_accepted( $auction_id );
		if ( ! $highest || $auction->bid_count() < 1 ) {
			return new \WP_Error( 'no_bids', __( 'There is no accepted bid to sell to yet.', 'logicanvas-auctions' ) );
		}

		$existing = $this->for_auction( $auction_id );
		if ( $existing && self::CANCELLED !== $existing['status'] ) {
			return true;
		}

		$lock = 'wcap_close_' . $auction_id;
		if ( ! $this->acquire( $lock ) ) {
			return new \WP_Error( 'busy', __( 'This auction is already closing. Please wait a moment.', 'logicanvas-auctions' ) );
		}

		try {
			Transaction::run(
				function () use ( $auction_id, $actor_id, $highest ) {
					$auction = $this->auctions->find_for_update( $auction_id );
					if ( ! $auction || ! in_array( $auction->state(), array( AuctionState::ACTIVE, AuctionState::PAUSED_ADMIN ), true ) ) {
						throw new \RuntimeException( 'invalid_state' );
					}

					$existing = $this->for_auction( $auction_id );
					if ( $existing && self::CANCELLED !== $existing['status'] ) {
						return true;
					}

					$reason = 'Holder accepted highest bid';
					$this->auction_service->transition( $auction_id, AuctionState::CLOSING, $actor_id, 'user', $reason, 'accept-' . $auction_id );
					$this->auction_service->transition( $auction_id, AuctionState::ENDED, $actor_id, 'user', $reason, 'accept-ended-' . $auction_id );

					$award_id = $this->create_award( $auction, $highest );
					$this->auction_service->transition( $auction_id, AuctionState::PAYMENT_PENDING, $actor_id, 'user', 'Winner awarded by holder', 'accept-award-' . $auction_id );

					$this->auctions->update_state(
						$auction_id,
						array(
							'award_id'       => $award_id,
							'updated_at_utc' => $this->clock->utc_mysql(),
						)
					);

					$hours    = $auction->payment_deadline_hours();
					$deadline = gmdate( 'Y-m-d H:i:s', $this->clock->timestamp() + ( $hours * HOUR_IN_SECONDS ) );
					$this->scheduler->unschedule_close( $auction_id );
					$this->scheduler->schedule_payment_deadline( $award_id, $auction_id, $deadline );

					( new SettlementService() )->create_pending_for_award( $auction_id, $award_id );

					do_action( 'wcap_award_created', $auction_id, $award_id, $highest->bidder_id() );
					do_action( 'wcap_auction_closed', $auction_id, 'sold_early' );
					do_action( 'wcap_holder_accepted_bid', $auction_id, $award_id, $actor_id );

					return true;
				}
			);
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'accept_failed', __( 'Could not accept the current bid. Please try again.', 'logicanvas-auctions' ) );
		} finally {
			$this->release( $lock );
		}

		return true;
	}

	public function mark_unsold( int $auction_id, int $actor_id, string $reason ): void {
		$auction = $this->auctions->find( $auction_id );
		if ( ! $auction ) {
			return;
		}
		$to = AuctionState::UNSOLD;
		if ( $auction->is_live() && AuctionState::CLOSING !== $auction->state() ) {
			$this->auction_service->transition( $auction_id, AuctionState::CLOSING, $actor_id, 'user', $reason );
		}
		$this->auction_service->transition( $auction_id, $to, $actor_id, 'user', $reason );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find( int $award_id ): ?array {
		global $wpdb;

		$table = Config::table( Config::TABLE_AWARDS );
		$row   = QueryCache::remember(
			QueryCache::key( 'award', $award_id ),
			60,
			static function () use ( $wpdb, $table, $award_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_row(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE id = %d',
						$table,
						$award_id
					),
					ARRAY_A
				);
			}
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function for_auction( int $auction_id ): ?array {
		global $wpdb;

		$table = Config::table( Config::TABLE_AWARDS );
		$row   = QueryCache::remember(
			QueryCache::key( 'award_auction', $auction_id ),
			60,
			static function () use ( $wpdb, $table, $auction_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_row(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE auction_id = %d ORDER BY id DESC LIMIT 1',
						$table,
						$auction_id
					),
					ARRAY_A
				);
			}
		);

		return is_array( $row ) ? $row : null;
	}

	public function mark_paid( int $award_id, int $order_id ): void {
		global $wpdb;

		$award = $this->find( $award_id );
		if ( ! $award ) {
			return;
		}

		if ( self::PAID !== $award['status'] ) {
			$wpdb->update(
				Config::table( Config::TABLE_AWARDS ),
				array(
					'status'         => self::PAID,
					'order_id'       => $order_id,
					'updated_at_utc' => $this->clock->utc_mysql(),
				),
				array( 'id' => $award_id )
			);
		} else {
			$wpdb->update(
				Config::table( Config::TABLE_AWARDS ),
				array(
					'order_id'       => $order_id,
					'updated_at_utc' => $this->clock->utc_mysql(),
				),
				array( 'id' => $award_id )
			);
		}

		QueryCache::bust_auction( (int) $award['auction_id'] );

		$auction_id = (int) $award['auction_id'];
		$this->auctions->update_state(
			$auction_id,
			array(
				'order_id'       => $order_id,
				'updated_at_utc' => $this->clock->utc_mysql(),
			)
		);

		$auction = $this->auctions->find( $auction_id );
		$state   = $auction ? $auction->state() : '';

		if ( in_array( $state, array( AuctionState::PAYMENT_PENDING, AuctionState::SOLD_PAYMENT_PENDING ), true ) ) {
			$paid = $this->auction_service->transition( $auction_id, AuctionState::PAID, 0, 'system', 'Order paid' );
			if ( is_wp_error( $paid ) ) {
				$this->auctions->update_state(
					$auction_id,
					array(
						'state'          => AuctionState::PAID,
						'updated_at_utc' => $this->clock->utc_mysql(),
					)
				);
			}
		}

		$auction = $this->auctions->find( $auction_id );
		$state   = $auction ? $auction->state() : '';
		if ( AuctionState::PAID === $state ) {
			$done = $this->auction_service->transition( $auction_id, AuctionState::COMPLETED, 0, 'system', 'Order paid' );
			if ( is_wp_error( $done ) ) {
				$this->auctions->update_state(
					$auction_id,
					array(
						'state'          => AuctionState::COMPLETED,
						'updated_at_utc' => $this->clock->utc_mysql(),
					)
				);
			}
		}

		( new SettlementService() )->mark_order_paid( $auction_id, $order_id );
	}

	public function expire( int $award_id ): void {
		$award = $this->find( $award_id );
		if ( ! $award || self::PENDING !== $award['status'] ) {
			return;
		}

		global $wpdb;
		$wpdb->update(
			Config::table( Config::TABLE_AWARDS ),
			array(
				'status'         => self::DEFAULTED,
				'updated_at_utc' => $this->clock->utc_mysql(),
			),
			array( 'id' => $award_id )
		);

		QueryCache::bust_auction( (int) $award['auction_id'] );

		$this->auction_service->transition( (int) $award['auction_id'], AuctionState::PAYMENT_DEFAULTED, 0, 'system', 'Payment deadline expired' );
		do_action( 'wcap_payment_deadline_expired', (int) $award['auction_id'], $award_id );
	}

	private function create_award( Auction $auction, Bid $bid ): int {
		global $wpdb;

		$existing = $this->for_auction( $auction->id() );
		if ( $existing && self::CANCELLED !== $existing['status'] ) {
			return (int) $existing['id'];
		}

		$key      = 'award-' . $auction->id();
		$deadline = gmdate( 'Y-m-d H:i:s', $this->clock->timestamp() + ( $auction->payment_deadline_hours() * HOUR_IN_SECONDS ) );
		$now      = $this->clock->utc_mysql();

		$wpdb->insert(
			Config::table( Config::TABLE_AWARDS ),
			array(
				'auction_id'            => $auction->id(),
				'winner_id'             => $bid->bidder_id(),
				'bid_id'                => $bid->id(),
				'amount'                => $bid->amount()->amount(),
				'currency'              => $auction->currency(),
				'status'                => self::PENDING,
				'payment_deadline_utc'  => $deadline,
				'correlation_key'       => $key,
				'created_at_utc'        => $now,
				'updated_at_utc'        => $now,
			)
		);

		QueryCache::bust_auction( $auction->id() );

		return (int) $wpdb->insert_id;
	}

	private function acquire( string $key ): bool {
		return \LogicanvasAuctions\Infrastructure\Database\OptionLock::acquire( $key, 30 );
	}

	private function release( string $key ): void {
		\LogicanvasAuctions\Infrastructure\Database\OptionLock::release( $key );
	}
}
