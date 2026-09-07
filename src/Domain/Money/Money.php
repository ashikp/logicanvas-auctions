<?php
/**
 * Decimal money value object. Never uses binary floats for decisions.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Money;

use InvalidArgumentException;

final class Money {

	private string $amount;

	private string $currency;

	private int $scale;

	public function __construct( string $amount, string $currency, int $scale = 2 ) {
		$currency = strtoupper( $currency );
		if ( ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			throw new InvalidArgumentException( 'Currency must be a 3-letter ISO code.' );
		}

		$this->currency = $currency;
		$this->scale    = $scale;
		$this->amount   = self::normalize( $amount, $scale );
	}

	public static function from_string( string $amount, string $currency, int $scale = 2 ): self {
		return new self( $amount, $currency, $scale );
	}

	public static function zero( string $currency, int $scale = 2 ): self {
		return new self( '0', $currency, $scale );
	}

	public function amount(): string {
		return $this->amount;
	}

	public function currency(): string {
		return $this->currency;
	}

	public function scale(): int {
		return $this->scale;
	}

	public function formatted(): string {
		return $this->amount;
	}

	public function add( self $other ): self {
		$this->assert_same_currency( $other );
		return new self( Decimal::add( $this->amount, $other->amount, $this->scale ), $this->currency, $this->scale );
	}

	public function subtract( self $other ): self {
		$this->assert_same_currency( $other );
		return new self( Decimal::sub( $this->amount, $other->amount, $this->scale ), $this->currency, $this->scale );
	}

	public function percent( string $percent ): self {
		$factor = Decimal::div( $percent, '100', 8 );
		$raw    = Decimal::mul( $this->amount, $factor, $this->scale + 4 );
		return new self( Decimal::round( $raw, $this->scale ), $this->currency, $this->scale );
	}

	public function multiply( string $factor ): self {
		$raw = Decimal::mul( $this->amount, $factor, $this->scale + 4 );
		return new self( Decimal::round( $raw, $this->scale ), $this->currency, $this->scale );
	}

	public function compare( self $other ): int {
		$this->assert_same_currency( $other );
		return Decimal::cmp( $this->amount, $other->amount, $this->scale );
	}

	public function greater_than( self $other ): bool {
		return $this->compare( $other ) > 0;
	}

	public function greater_than_or_equal( self $other ): bool {
		return $this->compare( $other ) >= 0;
	}

	public function less_than( self $other ): bool {
		return $this->compare( $other ) < 0;
	}

	public function equals( self $other ): bool {
		return $this->compare( $other ) === 0;
	}

	public function is_zero(): bool {
		return Decimal::cmp( $this->amount, '0', $this->scale ) === 0;
	}

	public function is_negative(): bool {
		return Decimal::cmp( $this->amount, '0', $this->scale ) < 0;
	}

	public function max( self $other ): self {
		return $this->greater_than_or_equal( $other ) ? $this : $other;
	}

	/**
	 * @return array{amount:string,currency:string,formatted:string}
	 */
	public function to_rest(): array {
		return array(
			'amount'    => $this->amount,
			'currency'  => $this->currency,
			'formatted' => $this->amount . ' ' . $this->currency,
		);
	}

	private function assert_same_currency( self $other ): void {
		if ( $this->currency !== $other->currency ) {
			throw new InvalidArgumentException( 'Currency mismatch.' );
		}
	}

	public static function normalize( string $amount, int $scale ): string {
		$amount = trim( $amount );
		if ( '' === $amount || ! preg_match( '/^-?\d+(\.\d+)?$/', $amount ) ) {
			throw new InvalidArgumentException( 'Invalid monetary amount.' );
		}

		return Decimal::round( $amount, $scale );
	}
}
