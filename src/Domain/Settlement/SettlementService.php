<?php
/**
 * Commission calculator and settlement ledger.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Settlement;

use LogicanvasAuctions\Admin\Settings;
use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Money\Money;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository;

final class SettlementService {

	public const PAYOUT_PENDING   = 'pending';
	public const PAYOUT_APPROVED  = 'approved';
	public const PAYOUT_PAID      = 'paid';
	public const PAYOUT_REVERSED  = 'reversed';
	public const PAYOUT_DISPUTED  = 'disputed';

	public function create_pending_for_award( int $auction_id, int $award_id ): int {
		global $wpdb;

		$table    = Config::table( Config::TABLE_SETTLEMENTS );
		$existing = QueryCache::remember(
			QueryCache::key( 'settlement_id', $auction_id ),
			60,
			static function () use ( $wpdb, $table, $auction_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_var(
					$wpdb->prepare( 'SELECT id FROM %i WHERE auction_id = %d', $table, $auction_id )
				);
			}
		);
		if ( $existing ) {
			return (int) $existing;
		}

		$auction = ( new WpdbAuctionRepository() )->find( $auction_id );
		if ( ! $auction ) {
			return 0;
		}

		$award = ( new \LogicanvasAuctions\Domain\Award\AwardService() )->find( $award_id );
		if ( ! $award ) {
			return 0;
		}

		$gross  = Money::from_string( (string) $award['amount'], $auction->currency() );
		$calc   = $this->calculate( $auction_id, $auction->holder_id(), $gross );
		$now    = gmdate( 'Y-m-d H:i:s' );

		$wpdb->insert(
			$table,
			array(
				'auction_id'            => $auction_id,
				'award_id'              => $award_id,
				'holder_id'             => $auction->holder_id(),
				'currency'              => $auction->currency(),
				'gross_amount'          => $calc['gross']->amount(),
				'commission_amount'     => $calc['commission']->amount(),
				'fee_amount'            => $calc['fees']->amount(),
				'refund_amount'         => '0.00',
				'net_amount'            => $calc['net']->amount(),
				'payout_status'         => self::PAYOUT_PENDING,
				'commission_snapshot'   => wp_json_encode( $calc['snapshot'] ),
				'created_at_utc'        => $now,
				'updated_at_utc'        => $now,
			)
		);

		QueryCache::bust_auction( $auction_id );

		$id = (int) $wpdb->insert_id;
		( new WpdbAuctionRepository() )->update_state( $auction_id, array( 'settlement_id' => $id, 'updated_at_utc' => $now ) );

		do_action( 'wcap_settlement_calculated', $auction_id, $id, $calc );

		return $id;
	}

	/**
	 * @return array{gross:Money,commission:Money,fees:Money,net:Money,snapshot:array<string,mixed>}
	 */
	public function calculate( int $auction_id, int $holder_id, Money $gross ): array {
		$settings = Settings::get();
		$fixed    = Money::from_string( (string) $settings['commission_fixed'], $gross->currency() );
		$percent  = $gross->percent( (string) $settings['commission_percent'] );
		$min      = Money::from_string( (string) $settings['commission_minimum'], $gross->currency() );

		$holder_row = ( new \LogicanvasAuctions\Domain\Holder\HolderService() )->for_user( $holder_id );
		if ( $holder_row && null !== $holder_row['commission_percent'] && '' !== $holder_row['commission_percent'] ) {
			$percent = $gross->percent( (string) $holder_row['commission_percent'] );
		}
		if ( $holder_row && null !== $holder_row['commission_fixed'] && '' !== $holder_row['commission_fixed'] ) {
			$fixed = Money::from_string( (string) $holder_row['commission_fixed'], $gross->currency() );
		}

		$override = get_post_meta( $auction_id, '_wcap_commission_override', true );
		if ( is_array( $override ) ) {
			if ( isset( $override['percent'] ) ) {
				$percent = $gross->percent( (string) $override['percent'] );
			}
			if ( isset( $override['fixed'] ) ) {
				$fixed = Money::from_string( (string) $override['fixed'], $gross->currency() );
			}
		}

		$commission = $fixed->add( $percent );
		if ( $commission->less_than( $min ) ) {
			$commission = $min;
		}
		if ( $commission->greater_than( $gross ) ) {
			$commission = $gross;
		}

		$fees = Money::zero( $gross->currency() );

		/**
		 * Filter calculated commission.
		 *
		 * @param Money $commission Commission.
		 * @param Money $gross      Gross.
		 * @param int   $auction_id Auction ID.
		 */
		$commission = apply_filters( 'wcap_commission_amount', $commission, $gross, $auction_id );

		$net = $gross->subtract( $commission )->subtract( $fees );

		return array(
			'gross'      => $gross,
			'commission' => $commission,
			'fees'       => $fees,
			'net'        => $net,
			'snapshot'   => array(
				'fixed'   => $fixed->amount(),
				'percent' => $percent->amount(),
				'minimum' => $min->amount(),
			),
		);
	}

	public function mark_order_paid( int $auction_id, int $order_id ): void {
		global $wpdb;

		$wpdb->update(
			Config::table( Config::TABLE_SETTLEMENTS ),
			array(
				'order_id'       => $order_id,
				'updated_at_utc' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'auction_id' => $auction_id )
		);

		QueryCache::bust_auction( $auction_id );
	}

	public function apply_refund( int $auction_id, string $refund_amount ): void {
		global $wpdb;

		$table = Config::table( Config::TABLE_SETTLEMENTS );
		$row   = QueryCache::remember(
			QueryCache::key( 'settlement', $auction_id ),
			60,
			static function () use ( $wpdb, $table, $auction_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_row(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE auction_id = %d',
						$table,
						$auction_id
					),
					ARRAY_A
				);
			}
		);
		if ( ! is_array( $row ) ) {
			return;
		}

		$currency = (string) $row['currency'];
		$refund   = Money::from_string( $refund_amount, $currency )->add( Money::from_string( (string) $row['refund_amount'], $currency ) );
		$gross    = Money::from_string( (string) $row['gross_amount'], $currency );
		$comm     = Money::from_string( (string) $row['commission_amount'], $currency );
		$fees     = Money::from_string( (string) $row['fee_amount'], $currency );
		$net      = $gross->subtract( $comm )->subtract( $fees )->subtract( $refund );
		if ( $net->is_negative() ) {
			$net = Money::zero( $currency );
		}

		$wpdb->update(
			$table,
			array(
				'refund_amount'  => $refund->amount(),
				'net_amount'     => $net->amount(),
				'payout_status'  => self::PAYOUT_REVERSED,
				'updated_at_utc' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => (int) $row['id'] )
		);

		QueryCache::bust_auction( $auction_id );
	}

	public function set_payout_status( int $settlement_id, string $status, int $actor_id ): void {
		$allowed = array( self::PAYOUT_PENDING, self::PAYOUT_APPROVED, self::PAYOUT_PAID, self::PAYOUT_REVERSED, self::PAYOUT_DISPUTED );
		if ( ! in_array( $status, $allowed, true ) ) {
			return;
		}

		global $wpdb;
		$wpdb->update(
			Config::table( Config::TABLE_SETTLEMENTS ),
			array(
				'payout_status'  => $status,
				'updated_at_utc' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $settlement_id )
		);

		QueryCache::flush_group();

		do_action( 'wcap_payout_status_changed', $settlement_id, $status, $actor_id );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function for_auction( int $auction_id ): ?array {
		global $wpdb;

		$table = Config::table( Config::TABLE_SETTLEMENTS );
		$row   = QueryCache::remember(
			QueryCache::key( 'settlement', $auction_id ),
			60,
			static function () use ( $wpdb, $table, $auction_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_row(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE auction_id = %d',
						$table,
						$auction_id
					),
					ARRAY_A
				);
			}
		);

		return is_array( $row ) ? $row : null;
	}
}
