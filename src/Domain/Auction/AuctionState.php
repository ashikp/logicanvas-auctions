<?php
/**
 * Canonical auction states.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Auction;

final class AuctionState {

	public const DRAFT                 = 'draft';
	public const PENDING_REVIEW        = 'pending_review';
	public const REJECTED              = 'rejected';
	public const SCHEDULED             = 'scheduled';
	public const ACTIVE                = 'active';
	public const PAUSED_ADMIN          = 'paused_admin';
	public const LOBBY                 = 'lobby';
	public const LIVE                  = 'live';
	public const PAUSED                = 'paused';
	public const GOING_ONCE            = 'going_once';
	public const GOING_TWICE           = 'going_twice';
	public const CLOSING               = 'closing';
	public const ENDED                 = 'ended';
	public const RESERVE_NOT_MET       = 'reserve_not_met';
	public const PAYMENT_PENDING       = 'payment_pending';
	public const SOLD_PAYMENT_PENDING  = 'sold_payment_pending';
	public const PAID                  = 'paid';
	public const COMPLETED             = 'completed';
	public const UNSOLD                = 'unsold';
	public const CANCELLED             = 'cancelled';
	public const PAYMENT_DEFAULTED     = 'payment_defaulted';

	/**
	 * @return string[]
	 */
	public static function timed(): array {
		return array(
			self::DRAFT,
			self::PENDING_REVIEW,
			self::REJECTED,
			self::SCHEDULED,
			self::ACTIVE,
			self::PAUSED_ADMIN,
			self::CLOSING,
			self::ENDED,
			self::RESERVE_NOT_MET,
			self::PAYMENT_PENDING,
			self::PAID,
			self::COMPLETED,
			self::UNSOLD,
			self::CANCELLED,
			self::PAYMENT_DEFAULTED,
		);
	}

	/**
	 * @return string[]
	 */
	public static function live(): array {
		return array(
			self::DRAFT,
			self::PENDING_REVIEW,
			self::REJECTED,
			self::SCHEDULED,
			self::LOBBY,
			self::LIVE,
			self::PAUSED,
			self::GOING_ONCE,
			self::GOING_TWICE,
			self::CLOSING,
			self::SOLD_PAYMENT_PENDING,
			self::PAID,
			self::COMPLETED,
			self::UNSOLD,
			self::CANCELLED,
			self::PAYMENT_DEFAULTED,
		);
	}

	public static function accepts_bids( string $state, string $type ): bool {
		if ( AuctionType::TIMED === $type ) {
			return self::ACTIVE === $state;
		}

		return in_array( $state, array( self::LIVE, self::GOING_ONCE, self::GOING_TWICE ), true );
	}

	public static function is_terminal( string $state ): bool {
		return in_array(
			$state,
			array( self::COMPLETED, self::UNSOLD, self::CANCELLED, self::PAYMENT_DEFAULTED, self::RESERVE_NOT_MET ),
			true
		);
	}

	public static function is_payment_pending( string $state ): bool {
		return in_array( $state, array( self::PAYMENT_PENDING, self::SOLD_PAYMENT_PENDING ), true );
	}

	/**
	 * @return string[]
	 */
	public static function all(): array {
		return array_values( array_unique( array_merge( self::timed(), self::live() ) ) );
	}

	public static function is_valid( string $state ): bool {
		return in_array( $state, self::all(), true );
	}
}
