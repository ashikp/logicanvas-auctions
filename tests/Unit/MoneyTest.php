<?php

declare(strict_types=1);

namespace LogicanvasAuctions\Tests\Unit;

use LogicanvasAuctions\Domain\Money\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase {

	public function test_add_and_compare(): void {
		$a = Money::from_string( '10.50', 'USD' );
		$b = Money::from_string( '2.25', 'USD' );
		$sum = $a->add( $b );
		$this->assertSame( '12.75', $sum->amount() );
		$this->assertTrue( $a->greater_than( $b ) );
		$this->assertTrue( $a->equals( Money::from_string( '10.50', 'USD' ) ) );
	}

	public function test_percent(): void {
		$gross = Money::from_string( '100.00', 'USD' );
		$this->assertSame( '10.00', $gross->percent( '10' )->amount() );
	}

	public function test_rejects_floaty_input(): void {
		$this->expectException( \InvalidArgumentException::class );
		Money::from_string( '10.5a', 'USD' );
	}

	public function test_currency_mismatch(): void {
		$this->expectException( \InvalidArgumentException::class );
		Money::from_string( '1.00', 'USD' )->add( Money::from_string( '1.00', 'EUR' ) );
	}

	public function test_rest_payload(): void {
		$money = Money::from_string( '5.00', 'USD' );
		$rest  = $money->to_rest();
		$this->assertSame( '5.00', $rest['amount'] );
		$this->assertSame( 'USD', $rest['currency'] );
	}
}
