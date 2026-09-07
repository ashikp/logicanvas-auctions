<?php
/**
 * Timed auction state machine.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Auction;

final class TimedStateMachine {

	/**
	 * @var array<string, string[]>
	 */
	private const TRANSITIONS = array(
		AuctionState::DRAFT             => array( AuctionState::PENDING_REVIEW, AuctionState::CANCELLED ),
		AuctionState::PENDING_REVIEW    => array( AuctionState::SCHEDULED, AuctionState::REJECTED, AuctionState::CANCELLED ),
		AuctionState::REJECTED          => array( AuctionState::DRAFT, AuctionState::PENDING_REVIEW, AuctionState::CANCELLED ),
		AuctionState::SCHEDULED         => array( AuctionState::ACTIVE, AuctionState::CANCELLED ),
		AuctionState::ACTIVE            => array( AuctionState::PAUSED_ADMIN, AuctionState::CLOSING, AuctionState::CANCELLED ),
		AuctionState::PAUSED_ADMIN      => array( AuctionState::ACTIVE, AuctionState::CLOSING, AuctionState::CANCELLED ),
		AuctionState::CLOSING           => array( AuctionState::ENDED ),
		AuctionState::ENDED             => array( AuctionState::PAYMENT_PENDING, AuctionState::RESERVE_NOT_MET, AuctionState::UNSOLD ),
		AuctionState::RESERVE_NOT_MET   => array( AuctionState::UNSOLD, AuctionState::CANCELLED ),
		AuctionState::PAYMENT_PENDING   => array( AuctionState::PAID, AuctionState::PAYMENT_DEFAULTED, AuctionState::CANCELLED ),
		AuctionState::PAID              => array( AuctionState::COMPLETED ),
		AuctionState::PAYMENT_DEFAULTED => array( AuctionState::UNSOLD, AuctionState::CANCELLED ),
		AuctionState::UNSOLD            => array(),
		AuctionState::COMPLETED         => array(),
		AuctionState::CANCELLED         => array(),
	);

	public function can_transition( string $from, string $to ): bool {
		return in_array( $to, self::TRANSITIONS[ $from ] ?? array(), true );
	}

	public function assert( string $from, string $to ): void {
		if ( ! $this->can_transition( $from, $to ) ) {
			throw InvalidTransitionException::create( esc_html( $from ), esc_html( $to ), esc_html( AuctionType::TIMED ) );
		}
	}

	/**
	 * @return array<string, string[]>
	 */
	public function map(): array {
		return self::TRANSITIONS;
	}
}
