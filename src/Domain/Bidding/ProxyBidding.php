<?php
/**
 * Timed-auction proxy / maximum bidding algorithm.
 *
 * Pure domain logic. Maximum values must stay private to the caller.
 *
 * Tie rule: earlier maximum wins an exact tie.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Bidding;

use LogicanvasAuctions\Domain\Money\Decimal;
use LogicanvasAuctions\Domain\Money\Money;

final class ProxyBidding {

	/**
	 * @param array{bidder_id:int,max:string,placed_at:string} $leader Current proxy leader (may be empty).
	 * @param array{bidder_id:int,max:string,placed_at:string} $challenger New proxy or regular bid expressed as max.
	 * @return array{leader_id:int,price:string,outbid_id:?int,generated_bids:array<int, array{bidder_id:int,amount:string}>}
	 */
	public function resolve(
		array $leader,
		array $challenger,
		string $min_next,
		string $increment,
		string $currency,
		int $scale = 2
	): array {
		$generated = array();

		if ( empty( $leader['bidder_id'] ) ) {
			$price = Decimal::max( $min_next, $min_next, $scale );
			if ( Decimal::cmp( $challenger['max'], $min_next, $scale ) < 0 ) {
				return array(
					'leader_id'      => 0,
					'price'          => $min_next,
					'outbid_id'      => null,
					'generated_bids' => array(),
					'rejected'       => true,
				);
			}

			$generated[] = array(
				'bidder_id' => (int) $challenger['bidder_id'],
				'amount'    => $min_next,
			);

			return array(
				'leader_id'      => (int) $challenger['bidder_id'],
				'price'          => $min_next,
				'outbid_id'      => null,
				'generated_bids' => $generated,
				'rejected'       => false,
			);
		}

		$leader_max      = $leader['max'];
		$challenger_max  = $challenger['max'];
		$leader_earlier  = strcmp( (string) $leader['placed_at'], (string) $challenger['placed_at'] ) <= 0;

		$cmp = Decimal::cmp( $challenger_max, $leader_max, $scale );

		if ( $cmp < 0 || ( 0 === $cmp && $leader_earlier ) ) {
			$price = Decimal::add( $challenger_max, $increment, $scale );
			if ( Decimal::cmp( $price, $leader_max, $scale ) > 0 ) {
				$price = $leader_max;
			}
			if ( Decimal::cmp( $price, $min_next, $scale ) < 0 ) {
				$price = $min_next;
			}

			$generated[] = array(
				'bidder_id' => (int) $challenger['bidder_id'],
				'amount'    => $challenger_max,
			);
			$generated[] = array(
				'bidder_id' => (int) $leader['bidder_id'],
				'amount'    => $price,
			);

			return array(
				'leader_id'      => (int) $leader['bidder_id'],
				'price'          => $price,
				'outbid_id'      => (int) $challenger['bidder_id'],
				'generated_bids' => $generated,
				'rejected'       => false,
			);
		}

		$price = Decimal::add( $leader_max, $increment, $scale );
		if ( Decimal::cmp( $price, $challenger_max, $scale ) > 0 ) {
			$price = $challenger_max;
		}
		if ( Decimal::cmp( $price, $min_next, $scale ) < 0 ) {
			$price = $min_next;
		}

		$generated[] = array(
			'bidder_id' => (int) $challenger['bidder_id'],
			'amount'    => $price,
		);

		return array(
			'leader_id'      => (int) $challenger['bidder_id'],
			'price'          => $price,
			'outbid_id'      => (int) $leader['bidder_id'],
			'generated_bids' => $generated,
			'rejected'       => false,
		);
	}

	public function as_money( string $amount, string $currency ): Money {
		return Money::from_string( $amount, $currency );
	}
}
