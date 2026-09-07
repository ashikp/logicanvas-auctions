<?php

declare(strict_types=1);

namespace LogicanvasAuctions\Tests\Unit;

use LogicanvasAuctions\Domain\Bidding\ProxyBidding;
use PHPUnit\Framework\TestCase;

final class ProxyBiddingTest extends TestCase {

	public function test_first_proxy_places_starting_price(): void {
		$algo = new ProxyBidding();
		$result = $algo->resolve(
			array( 'bidder_id' => 0, 'max' => '0', 'placed_at' => '' ),
			array( 'bidder_id' => 2, 'max' => '50.00', 'placed_at' => '2026-01-01 00:00:01' ),
			'10.00',
			'1.00',
			'USD'
		);

		$this->assertFalse( $result['rejected'] );
		$this->assertSame( 2, $result['leader_id'] );
		$this->assertSame( '10.00', $result['price'] );
	}

	public function test_earlier_maximum_wins_exact_tie(): void {
		$algo = new ProxyBidding();
		$result = $algo->resolve(
			array( 'bidder_id' => 1, 'max' => '40.00', 'placed_at' => '2026-01-01 00:00:01' ),
			array( 'bidder_id' => 2, 'max' => '40.00', 'placed_at' => '2026-01-01 00:00:02' ),
			'11.00',
			'1.00',
			'USD'
		);

		$this->assertSame( 1, $result['leader_id'] );
		$this->assertSame( 2, $result['outbid_id'] );
	}

	public function test_higher_challenger_takes_lead(): void {
		$algo = new ProxyBidding();
		$result = $algo->resolve(
			array( 'bidder_id' => 1, 'max' => '20.00', 'placed_at' => '2026-01-01 00:00:01' ),
			array( 'bidder_id' => 2, 'max' => '50.00', 'placed_at' => '2026-01-01 00:00:02' ),
			'11.00',
			'1.00',
			'USD'
		);

		$this->assertSame( 2, $result['leader_id'] );
		$this->assertSame( 1, $result['outbid_id'] );
	}
}
