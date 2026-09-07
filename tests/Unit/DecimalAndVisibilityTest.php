<?php

declare(strict_types=1);

namespace LogicanvasAuctions\Tests\Unit;

use LogicanvasAuctions\Domain\Auction\Visibility;
use LogicanvasAuctions\Domain\Money\Decimal;
use PHPUnit\Framework\TestCase;

final class DecimalAndVisibilityTest extends TestCase {

	public function test_decimal_cmp(): void {
		$this->assertSame( 1, Decimal::cmp( '10.01', '10.00', 2 ) );
		$this->assertSame( 0, Decimal::cmp( '10.00', '10.0', 2 ) );
		$this->assertSame( -1, Decimal::cmp( '9.99', '10.00', 2 ) );
	}

	public function test_visibility(): void {
		$this->assertTrue( Visibility::is_valid( Visibility::PRIVATE_INVITE ) );
		$this->assertTrue( Visibility::is_publicly_listed( Visibility::PUBLIC_LISTED ) );
		$this->assertFalse( Visibility::is_publicly_listed( Visibility::UNLISTED ) );
	}

	public function test_self_bid_rule_on_auction(): void {
		$auction = new \LogicanvasAuctions\Domain\Auction\Auction(
			array(
				'auction_id' => 1,
				'holder_id'  => 7,
				'product_id' => 1,
				'type'       => 'timed',
				'visibility' => 'public',
				'state'      => 'active',
				'currency'   => 'USD',
				'starting_amount' => '1.00',
				'reserve_amount' => null,
				'current_amount' => '1.00',
				'min_increment' => '1.00',
				'bid_count' => 0,
				'sequence' => 0,
				'start_at_utc' => '2026-01-01 00:00:00',
				'end_at_utc' => null,
				'payment_deadline_hours' => 48,
			)
		);
		$this->assertTrue( $auction->is_holder( 7 ) );
		$this->assertFalse( $auction->is_holder( 8 ) );
		$this->assertTrue( $auction->accepts_bids() );
	}
}
