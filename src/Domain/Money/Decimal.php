<?php
/**
 * String decimal arithmetic using bcmath when available.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Money;

final class Decimal {

	public static function add( string $a, string $b, int $scale ): string {
		if ( function_exists( 'bcadd' ) ) {
			return self::trim( bcadd( $a, $b, $scale + 2 ) );
		}

		return self::from_scaled( self::to_scaled( $a, $scale ) + self::to_scaled( $b, $scale ), $scale );
	}

	public static function sub( string $a, string $b, int $scale ): string {
		if ( function_exists( 'bcsub' ) ) {
			return self::trim( bcsub( $a, $b, $scale + 2 ) );
		}

		return self::from_scaled( self::to_scaled( $a, $scale ) - self::to_scaled( $b, $scale ), $scale );
	}

	public static function mul( string $a, string $b, int $scale ): string {
		if ( function_exists( 'bcmul' ) ) {
			return self::trim( bcmul( $a, $b, $scale + 4 ) );
		}

		$prod = self::to_scaled( $a, $scale ) * self::to_scaled( $b, $scale );
		return self::from_scaled( intdiv( $prod, 10 ** $scale ), $scale );
	}

	public static function div( string $a, string $b, int $scale ): string {
		if ( function_exists( 'bcdiv' ) ) {
			$out = bcdiv( $a, $b, $scale + 4 );
			if ( ! is_string( $out ) ) {
				return '0';
			}
			return self::trim( $out );
		}

		$den = self::to_scaled( $b, $scale );
		if ( 0 === $den ) {
			return '0';
		}

		$num = self::to_scaled( $a, $scale ) * ( 10 ** $scale );
		return self::from_scaled( intdiv( $num, $den ), $scale );
	}

	public static function cmp( string $a, string $b, int $scale ): int {
		if ( function_exists( 'bccomp' ) ) {
			return bccomp( $a, $b, $scale );
		}

		$left  = self::to_scaled( $a, $scale );
		$right = self::to_scaled( $b, $scale );
		return $left <=> $right;
	}

	public static function round( string $amount, int $scale ): string {
		if ( function_exists( 'bcadd' ) ) {
			$pad = bcpow( '10', (string) ( $scale + 1 ), 0 );
			$mul = bcmul( $amount, $pad, 0 );
			if ( ! str_starts_with( $amount, '-' ) ) {
				$mul = bcadd( $mul, '5', 0 );
			} else {
				$mul = bcsub( $mul, '5', 0 );
			}
			$div = bcdiv( $mul, $pad, $scale );
			return (string) $div;
		}

		$scaled = self::to_scaled( $amount, $scale + 1 );
		$sign   = $scaled < 0 ? -1 : 1;
		$scaled = abs( $scaled );
		$scaled = intdiv( $scaled + 5, 10 ) * $sign;
		return self::from_scaled( $scaled, $scale );
	}

	public static function max( string $a, string $b, int $scale ): string {
		return self::cmp( $a, $b, $scale ) >= 0 ? self::round( $a, $scale ) : self::round( $b, $scale );
	}

	private static function to_scaled( string $amount, int $scale ): int {
		$neg    = str_starts_with( $amount, '-' );
		$amount = ltrim( $amount, '+-' );
		$parts  = explode( '.', $amount, 2 );
		$int    = $parts[0] !== '' ? $parts[0] : '0';
		$frac   = isset( $parts[1] ) ? substr( str_pad( $parts[1], $scale, '0' ), 0, $scale ) : str_repeat( '0', $scale );
		$value  = (int) ( $int . $frac );

		return $neg ? -$value : $value;
	}

	private static function from_scaled( int $value, int $scale ): string {
		$neg   = $value < 0;
		$value = abs( $value );
		$pad   = str_pad( (string) $value, $scale + 1, '0', STR_PAD_LEFT );
		$int   = substr( $pad, 0, strlen( $pad ) - $scale );
		$frac  = substr( $pad, -$scale );
		$out   = $scale > 0 ? ( $int . '.' . $frac ) : $int;

		return ( $neg ? '-' : '' ) . $out;
	}

	private static function trim( string $value ): string {
		if ( str_contains( $value, '.' ) ) {
			$value = rtrim( rtrim( $value, '0' ), '.' );
		}

		return '' === $value || '-' === $value ? '0' : $value;
	}
}
