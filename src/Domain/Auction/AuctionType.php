<?php
/**
 * Auction type.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Auction;

final class AuctionType {

	public const TIMED = 'timed';
	public const LIVE  = 'live';

	public static function is_valid( string $type ): bool {
		return in_array( $type, array( self::TIMED, self::LIVE ), true );
	}
}
