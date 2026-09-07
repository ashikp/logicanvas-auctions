<?php
/**
 * Atomic bid acceptance service.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Bidding;

use LogicanvasAuctions\Admin\Settings;
use LogicanvasAuctions\Core\ClockInterface;
use LogicanvasAuctions\Core\SystemClock;
use LogicanvasAuctions\Domain\Auction\Auction;
use LogicanvasAuctions\Domain\Auction\AuctionRepositoryInterface;
use LogicanvasAuctions\Domain\Auction\AuctionState;
use LogicanvasAuctions\Domain\Money\Money;
use LogicanvasAuctions\Infrastructure\Database\RateLimiter;
use LogicanvasAuctions\Infrastructure\Database\Transaction;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository;
use LogicanvasAuctions\Infrastructure\Database\WpdbBidRepository;
use LogicanvasAuctions\Infrastructure\Database\WpdbEventRepository;
use LogicanvasAuctions\Infrastructure\Scheduler\AuctionScheduler;

final class BidService {

	public function __construct(
		private AuctionRepositoryInterface $auctions = new WpdbAuctionRepository(),
		private BidRepositoryInterface $bids = new WpdbBidRepository(),
		private WpdbEventRepository $events = new WpdbEventRepository(),
		private IncrementCalculator $increments = new IncrementCalculator(),
		private RateLimiter $limiter = new RateLimiter(),
		private ClockInterface $clock = new SystemClock(),
		private AuctionScheduler $scheduler = new AuctionScheduler()
	) {}

	/**
	 * @param array{auction_id:int,user_id:int,amount:string,idempotency_key:string,type?:string,max_amount?:string,ip?:string,user_agent?:string} $request
	 * @return array{bid:Bid,auction:Auction,replay:bool,extended:bool}
	 */
	public function place( array $request ): array {
		$auction_id = (int) $request['auction_id'];
		$user_id    = (int) $request['user_id'];
		$key        = sanitize_text_field( (string) $request['idempotency_key'] );

		if ( $user_id < 1 ) {
			throw BidRejectedException::unauthenticated();
		}

		if ( '' === $key ) {
			throw new BidRejectedException( 'Idempotency key is required.', 'idempotency' );
		}

		/**
		 * Fires before bid validation.
		 *
		 * @param array<string, mixed> $request Request.
		 */
		do_action( 'wcap_before_bid_validation', $request );

		$result = Transaction::run(
			function () use ( $request, $auction_id, $user_id, $key ) {
				$auction = $this->auctions->find_for_update( $auction_id );
				if ( ! $auction ) {
					throw BidRejectedException::not_found();
				}

				$existing = $this->bids->find_by_idempotency( $auction_id, $key );
				if ( $existing ) {
					return array(
						'bid'      => $existing,
						'auction'  => $auction,
						'replay'   => true,
						'extended' => false,
					);
				}

				$this->assert_eligible( $auction, $user_id, $request );

				$now       = $this->clock->utc_mysql();
				$ip_hash   = $this->hash_ip( (string) ( $request['ip'] ?? '' ) );
				$ua_hash   = $this->hash_ua( (string) ( $request['user_agent'] ?? '' ) );
				$bid_type  = (string) ( $request['type'] ?? Bid::TYPE_REGULAR );
				$extended  = false;
				$end_at    = $auction->end_at_utc();
				$ext_count = $auction->extension_count();
				$previous_leader = $auction->current_leader_id();

				if ( $auction->proxy_enabled() && $auction->is_timed() ) {
					$resolved = $this->place_proxy( $auction, $user_id, $request, $key, $now, $ip_hash, $ua_hash );
					$amount   = $resolved['amount'];
					$bid_id   = $resolved['bid_id'];
					$leader   = $resolved['leader_id'];
					$added    = $resolved['added'];
				} else {
					$amount = Money::from_string( (string) $request['amount'], $auction->currency() );
					if ( $amount->currency() !== $auction->currency() ) {
						throw BidRejectedException::currency();
					}
					if ( ! $this->increments->is_valid_amount( $auction, $amount ) ) {
						throw BidRejectedException::increment();
					}
					$bid_id = $this->bids->insert(
						array(
							'auction_id'      => $auction_id,
							'bidder_id'       => $user_id,
							'amount'          => $amount->amount(),
							'currency'        => $auction->currency(),
							'type'            => $bid_type,
							'max_amount'      => isset( $request['max_amount'] ) ? (string) $request['max_amount'] : null,
							'status'          => Bid::STATUS_ACCEPTED,
							'idempotency_key' => $key,
							'ip_hash'         => $ip_hash,
							'user_agent_hash' => $ua_hash,
							'created_at_utc'  => $now,
						)
					);
					$leader = $user_id;
					$added  = 1;
				}

				if ( $auction->is_timed() && $end_at ) {
					$extended_pair = $this->maybe_soft_close( $auction, $now );
					$extended      = $extended_pair['extended'];
					$end_at        = $extended_pair['end_at'];
					$ext_count     = $extended_pair['extension_count'];
				}

				if ( $auction->is_live() && in_array( $auction->state(), array( AuctionState::GOING_ONCE, AuctionState::GOING_TWICE ), true ) ) {
					$new_state = AuctionState::LIVE;
				} else {
					$new_state = $auction->state();
				}

				$sequence = $auction->sequence() + 1;

				$this->auctions->update_state(
					$auction_id,
					array(
						'current_amount'    => $amount->amount(),
						'current_leader_id' => $leader,
						'bid_count'         => $auction->bid_count() + $added,
						'sequence'          => $sequence,
						'end_at_utc'        => $end_at,
						'extension_count'   => $ext_count,
						'state'             => $new_state,
						'updated_at_utc'    => $now,
					)
				);

				$this->events->append(
					$auction_id,
					$sequence,
					'bid_accepted',
					$user_id,
					'user',
					array(
						'bid_id'     => $bid_id,
						'amount'     => $amount->to_rest(),
						'extended'   => $extended,
						'end_at_utc' => $end_at,
					),
					$now,
					$key
				);

				$fresh = $this->auctions->find_for_update( $auction_id );
				$bid   = $this->bids->find( $bid_id );

				return array(
					'bid'              => $bid,
					'auction'          => $fresh ?? $auction,
					'replay'           => false,
					'extended'         => $extended,
					'previous_leader'  => $previous_leader,
					'end_at'           => $end_at,
				);
			}
		);

		if ( empty( $result['replay'] ) ) {
			wp_cache_delete( 'wcap_auction_' . $auction_id, 'logicanvas-auctions' );

			if ( ! empty( $result['extended'] ) && ! empty( $result['end_at'] ) ) {
				$this->scheduler->reschedule_close( $auction_id, (string) $result['end_at'] );
				/**
				 * Fires when a timed auction end is extended by anti-sniping.
				 *
				 * @param int    $auction_id Auction ID.
				 * @param string $end_at     New UTC end.
				 */
				do_action( 'wcap_auction_extended', $auction_id, (string) $result['end_at'] );
			}

			/**
			 * Fires after a bid is accepted and committed.
			 *
			 * @param Bid     $bid     Bid.
			 * @param Auction $auction Auction.
			 */
			do_action( 'wcap_bid_accepted', $result['bid'], $result['auction'] );

			if ( ! empty( $result['previous_leader'] ) && (int) $result['previous_leader'] !== (int) $result['auction']->current_leader_id() ) {
				do_action( 'wcap_bidder_outbid', (int) $result['previous_leader'], $result['auction'], $result['bid'] );
			}
		}

		return array(
			'bid'      => $result['bid'],
			'auction'  => $result['auction'],
			'replay'   => (bool) $result['replay'],
			'extended' => (bool) $result['extended'],
		);
	}

	/**
	 * @param array<string, mixed> $request
	 * @return array{amount:\LogicanvasAuctions\Domain\Money\Money,bid_id:int,leader_id:int,added:int}
	 */
	private function place_proxy( Auction $auction, int $user_id, array $request, string $key, string $now, string $ip_hash, string $ua_hash ): array {
		$max = (string) ( $request['max_amount'] ?? $request['amount'] ?? '' );
		Money::from_string( $max, $auction->currency() );

		$min_next   = $this->increments->minimum_next( $auction );
		$leader_id  = $auction->current_leader_id();
		$leader_row = array(
			'bidder_id' => 0,
			'max'       => '0',
			'placed_at' => '',
		);
		if ( $leader_id ) {
			$leader_max = $this->bids->proxy_max_for( $auction->id(), $leader_id );
			$leader_row = array(
				'bidder_id' => $leader_id,
				'max'       => $leader_max ?: $auction->current_amount()->amount(),
				'placed_at' => '1970-01-01 00:00:00',
			);
		}

		$resolved = ( new ProxyBidding() )->resolve(
			$leader_row,
			array(
				'bidder_id' => $user_id,
				'max'       => $max,
				'placed_at' => $now,
			),
			$min_next->amount(),
			$auction->min_increment()->amount(),
			$auction->currency()
		);

		if ( ! empty( $resolved['rejected'] ) ) {
			throw BidRejectedException::increment();
		}

		$bid_id = 0;
		$added  = 0;
		foreach ( $resolved['generated_bids'] as $i => $generated ) {
			$idem = ( 0 === $i ) ? $key : $key . '-fill-' . $i;
			$bid_id = $this->bids->insert(
				array(
					'auction_id'      => $auction->id(),
					'bidder_id'       => (int) $generated['bidder_id'],
					'amount'          => (string) $generated['amount'],
					'currency'        => $auction->currency(),
					'type'            => Bid::TYPE_PROXY_FILL,
					'max_amount'      => (int) $generated['bidder_id'] === $user_id ? $max : null,
					'status'          => Bid::STATUS_ACCEPTED,
					'idempotency_key' => $idem,
					'ip_hash'         => $ip_hash,
					'user_agent_hash' => $ua_hash,
					'created_at_utc'  => $now,
				)
			);
			++$added;
		}

		return array(
			'amount'    => Money::from_string( (string) $resolved['price'], $auction->currency() ),
			'bid_id'    => $bid_id,
			'leader_id' => (int) $resolved['leader_id'],
			'added'     => max( 1, $added ),
		);
	}

	/**
	 * @param array<string, mixed> $request
	 */
	private function assert_eligible( Auction $auction, int $user_id, array $request ): void {
		if ( ! $auction->accepts_bids() ) {
			throw BidRejectedException::not_accepting();
		}

		if ( ! user_can( $user_id, \LogicanvasAuctions\Config::CAP_BID ) ) {
			throw BidRejectedException::ineligible();
		}

		if ( $auction->is_holder( $user_id ) ) {
			throw BidRejectedException::self_bid();
		}

		$now_ts = $this->clock->timestamp();
		if ( $auction->is_timed() ) {
			$start = strtotime( $auction->start_at_utc() . ' UTC' );
			$end   = $auction->end_at_utc() ? strtotime( $auction->end_at_utc() . ' UTC' ) : 0;
			if ( $start && $now_ts < $start ) {
				throw BidRejectedException::outside_window();
			}
			if ( $end && $now_ts >= $end ) {
				throw BidRejectedException::outside_window();
			}
		}

		$access = new \LogicanvasAuctions\Domain\Invitation\AccessService();
		if ( ! $access->can_view( $auction, $user_id, (string) ( $request['invite_token'] ?? '' ) ) ) {
			throw BidRejectedException::access();
		}

		$ip_hash = $this->hash_ip( (string) ( $request['ip'] ?? '' ) );
		if ( ! $this->limiter->allow_bid( $user_id, $auction->id(), $ip_hash ) ) {
			throw BidRejectedException::rate_limited();
		}

		$holder = new \LogicanvasAuctions\Domain\Holder\HolderService();
		if ( $holder->is_suspended_user( $user_id ) ) {
			throw BidRejectedException::ineligible();
		}
	}

	/**
	 * @return array{extended:bool,end_at:?string,extension_count:int}
	 */
	private function maybe_soft_close( Auction $auction, string $now ): array {
		$end_at = $auction->end_at_utc();
		if ( ! $end_at ) {
			return array(
				'extended'        => false,
				'end_at'          => $end_at,
				'extension_count' => $auction->extension_count(),
			);
		}

		$row     = $auction->to_array();
		$window  = (int) ( $row['soft_close_window'] ?? 120 );
		$extend  = (int) ( $row['soft_close_extend'] ?? 120 );
		$max     = (int) ( $row['soft_close_max'] ?? 20 );
		$end_ts  = strtotime( $end_at . ' UTC' );
		$now_ts  = strtotime( $now . ' UTC' );

		if ( ! $end_ts || ( $end_ts - $now_ts ) > $window ) {
			return array(
				'extended'        => false,
				'end_at'          => $end_at,
				'extension_count' => $auction->extension_count(),
			);
		}

		if ( $auction->extension_count() >= $max ) {
			return array(
				'extended'        => false,
				'end_at'          => $end_at,
				'extension_count' => $auction->extension_count(),
			);
		}

		$new_end = gmdate( 'Y-m-d H:i:s', $end_ts + $extend );

		return array(
			'extended'        => true,
			'end_at'          => $new_end,
			'extension_count' => $auction->extension_count() + 1,
		);
	}

	private function hash_ip( string $ip ): string {
		$settings = Settings::get();
		$salt     = wp_salt( 'auth' );
		if ( empty( $settings['hash_ip_addresses'] ) ) {
			return hash_hmac( 'sha256', $ip, $salt );
		}

		return hash_hmac( 'sha256', $ip, $salt );
	}

	private function hash_ua( string $ua ): string {
		return hash_hmac( 'sha256', $ua, wp_salt( 'auth' ) );
	}
}
