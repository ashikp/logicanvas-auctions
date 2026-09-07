<?php

declare(strict_types=1);

namespace LogicanvasAuctions\Tests\Unit;

use LogicanvasAuctions\Domain\Auction\Auction;
use LogicanvasAuctions\Domain\Bidding\IncrementCalculator;
use PHPUnit\Framework\TestCase;

final class IncrementCalculatorTest extends TestCase {

	public function test_first_bid_is_starting_price(): void {
		$auction = new Auction(
			array(
				'auction_id'           => 1,
				'holder_id'            => 2,
				'product_id'           => 3,
				'type'                 => 'timed',
				'visibility'           => 'public',
				'state'                => 'active',
				'currency'             => 'USD',
				'starting_amount'      => '10.00',
				'reserve_amount'       => null,
				'current_amount'       => '10.00',
				'min_increment'        => '1.00',
				'increment_strategy'   => 'fixed',
				'buy_now_amount'       => null,
				'current_leader_id'    => null,
				'bid_count'            => 0,
				'sequence'             => 0,
				'start_at_utc'         => '2026-01-01 00:00:00',
				'end_at_utc'           => '2026-01-02 00:00:00',
				'payment_deadline_hours' => 48,
			)
		);

		$calc = new IncrementCalculator();
		$this->assertSame( '10.00', $calc->minimum_next( $auction )->amount() );
		$this->assertTrue( $calc->is_valid_amount( $auction, \LogicanvasAuctions\Domain\Money\Money::from_string( '10.00', 'USD' ) ) );
		$this->assertFalse( $calc->is_valid_amount( $auction, \LogicanvasAuctions\Domain\Money\Money::from_string( '9.99', 'USD' ) ) );
	}

	public function test_next_bid_adds_increment(): void {
		$auction = new Auction(
			array(
				'auction_id'           => 1,
				'holder_id'            => 2,
				'product_id'           => 3,
				'type'                 => 'timed',
				'visibility'           => 'public',
				'state'                => 'active',
				'currency'             => 'USD',
				'starting_amount'      => '10.00',
				'reserve_amount'       => '50.00',
				'current_amount'       => '25.00',
				'min_increment'        => '5.00',
				'increment_strategy'   => 'fixed',
				'buy_now_amount'       => null,
				'current_leader_id'    => 9,
				'bid_count'            => 3,
				'sequence'             => 3,
				'start_at_utc'         => '2026-01-01 00:00:00',
				'end_at_utc'           => '2026-01-02 00:00:00',
				'payment_deadline_hours' => 48,
			)
		);

		$calc = new IncrementCalculator();
		$this->assertSame( '30.00', $calc->minimum_next( $auction )->amount() );
		$this->assertTrue( $auction->reserve_met() === false );
		$met = $auction->with( array( 'current_amount' => '50.00' ) );
		$this->assertTrue( $met->reserve_met() );
	}
}
