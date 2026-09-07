<?php
/**
 * Bid rejection reasons.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Bidding;

use RuntimeException;

final class BidRejectedException extends RuntimeException {

	public function __construct(
		string $message,
		private string $code_key = 'bid_rejected'
	) {
		parent::__construct( $message );
	}

	public function error_code(): string {
		return $this->code_key;
	}

	public static function not_found(): self {
		return new self( 'Auction not found.', 'auction_not_found' );
	}

	public static function not_accepting(): self {
		return new self( 'Auction is not accepting bids.', 'not_accepting' );
	}

	public static function outside_window(): self {
		return new self( 'Bid is outside the allowed time window.', 'outside_window' );
	}

	public static function unauthenticated(): self {
		return new self( 'Authentication is required to bid.', 'unauthenticated' );
	}

	public static function ineligible(): self {
		return new self( 'You are not eligible to bid on this auction.', 'ineligible' );
	}

	public static function self_bid(): self {
		return new self( 'You cannot bid on your own auction.', 'self_bid' );
	}

	public static function increment(): self {
		return new self( 'Bid does not meet the minimum increment.', 'increment' );
	}

	public static function currency(): self {
		return new self( 'Bid currency is invalid.', 'currency' );
	}

	public static function rate_limited(): self {
		return new self( 'Too many bid attempts. Please wait.', 'rate_limited' );
	}

	public static function access(): self {
		return new self( 'You do not have access to this auction.', 'access_denied' );
	}
}
