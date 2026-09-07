<?php
/**
 * Next valid bid amount calculator.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Bidding;

use LogicanvasAuctions\Domain\Auction\Auction;
use LogicanvasAuctions\Domain\Money\Money;

final class IncrementCalculator {

	public function minimum_next( Auction $auction ): Money {
		if ( $auction->bid_count() < 1 ) {
			return $auction->starting_amount();
		}

		$increment = $this->increment_for( $auction );
		return $auction->current_amount()->add( $increment );
	}

	public function increment_for( Auction $auction ): Money {
		$strategy = $auction->increment_strategy();
		$current  = $auction->current_amount();
		$fixed    = $auction->min_increment();

		if ( 'percent' === $strategy ) {
			$pct = $current->percent( $fixed->amount() );
			return $pct->greater_than( Money::from_string( '0.01', $auction->currency() ) ) ? $pct : $fixed;
		}

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter the increment used for the next valid bid.
			 *
			 * @param Money   $fixed   Configured increment.
			 * @param Auction $auction Auction.
			 */
			return apply_filters( 'wcap_bid_increment', $fixed, $auction );
		}

		return $fixed;
	}

	public function is_valid_amount( Auction $auction, Money $amount ): bool {
		$min = $this->minimum_next( $auction );
		return $amount->greater_than_or_equal( $min ) && $amount->currency() === $auction->currency();
	}
}
