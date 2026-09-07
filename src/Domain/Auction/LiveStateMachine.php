<?php
/**
 * Live auction state machine.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Auction;

final class LiveStateMachine {

	/**
	 * @var array<string, string[]>
	 */
	private const TRANSITIONS = array(
		AuctionState::DRAFT                => array( AuctionState::PENDING_REVIEW, AuctionState::CANCELLED ),
		AuctionState::PENDING_REVIEW       => array( AuctionState::SCHEDULED, AuctionState::REJECTED, AuctionState::CANCELLED ),
		AuctionState::REJECTED             => array( AuctionState::DRAFT, AuctionState::PENDING_REVIEW, AuctionState::CANCELLED ),
		AuctionState::SCHEDULED            => array( AuctionState::LOBBY, AuctionState::CANCELLED ),
		AuctionState::LOBBY                => array( AuctionState::LIVE, AuctionState::CANCELLED ),
		AuctionState::LIVE                 => array( AuctionState::PAUSED, AuctionState::GOING_ONCE, AuctionState::CLOSING, AuctionState::UNSOLD, AuctionState::CANCELLED ),
		AuctionState::PAUSED               => array( AuctionState::LIVE, AuctionState::CANCELLED, AuctionState::UNSOLD ),
		AuctionState::GOING_ONCE           => array( AuctionState::LIVE, AuctionState::GOING_TWICE, AuctionState::CANCELLED ),
		AuctionState::GOING_TWICE          => array( AuctionState::LIVE, AuctionState::CLOSING, AuctionState::CANCELLED ),
		AuctionState::CLOSING              => array( AuctionState::SOLD_PAYMENT_PENDING, AuctionState::UNSOLD, AuctionState::CANCELLED ),
		AuctionState::SOLD_PAYMENT_PENDING => array( AuctionState::PAID, AuctionState::PAYMENT_DEFAULTED, AuctionState::CANCELLED ),
		AuctionState::PAID                 => array( AuctionState::COMPLETED ),
		AuctionState::PAYMENT_DEFAULTED    => array( AuctionState::UNSOLD, AuctionState::CANCELLED ),
		AuctionState::UNSOLD               => array(),
		AuctionState::COMPLETED            => array(),
		AuctionState::CANCELLED            => array(),
	);

	public function can_transition( string $from, string $to ): bool {
		return in_array( $to, self::TRANSITIONS[ $from ] ?? array(), true );
	}

	public function assert( string $from, string $to ): void {
		if ( ! $this->can_transition( $from, $to ) ) {
			throw InvalidTransitionException::create( esc_html( $from ), esc_html( $to ), esc_html( AuctionType::LIVE ) );
		}
	}

	/**
	 * @return array<string, string[]>
	 */
	public function map(): array {
		return self::TRANSITIONS;
	}
}
