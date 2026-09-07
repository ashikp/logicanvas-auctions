<?php

declare(strict_types=1);

namespace LogicanvasAuctions\Tests\Unit;

use LogicanvasAuctions\Domain\Auction\AuctionState;
use LogicanvasAuctions\Domain\Auction\InvalidTransitionException;
use LogicanvasAuctions\Domain\Auction\LiveStateMachine;
use LogicanvasAuctions\Domain\Auction\TimedStateMachine;
use PHPUnit\Framework\TestCase;

final class StateMachineTest extends TestCase {

	public function test_timed_happy_path(): void {
		$sm = new TimedStateMachine();
		$this->assertTrue( $sm->can_transition( AuctionState::DRAFT, AuctionState::PENDING_REVIEW ) );
		$this->assertTrue( $sm->can_transition( AuctionState::PENDING_REVIEW, AuctionState::SCHEDULED ) );
		$this->assertTrue( $sm->can_transition( AuctionState::SCHEDULED, AuctionState::ACTIVE ) );
		$this->assertTrue( $sm->can_transition( AuctionState::ACTIVE, AuctionState::CLOSING ) );
		$this->assertTrue( $sm->can_transition( AuctionState::CLOSING, AuctionState::ENDED ) );
		$this->assertTrue( $sm->can_transition( AuctionState::ENDED, AuctionState::PAYMENT_PENDING ) );
		$this->assertTrue( $sm->can_transition( AuctionState::PAYMENT_PENDING, AuctionState::PAID ) );
		$this->assertTrue( $sm->can_transition( AuctionState::PAID, AuctionState::COMPLETED ) );
		$this->assertFalse( $sm->can_transition( AuctionState::COMPLETED, AuctionState::ACTIVE ) );
	}

	public function test_timed_invalid_throws(): void {
		$this->expectException( InvalidTransitionException::class );
		( new TimedStateMachine() )->assert( AuctionState::DRAFT, AuctionState::ACTIVE );
	}

	public function test_live_going_once_returns_to_live(): void {
		$sm = new LiveStateMachine();
		$this->assertTrue( $sm->can_transition( AuctionState::LIVE, AuctionState::GOING_ONCE ) );
		$this->assertTrue( $sm->can_transition( AuctionState::GOING_ONCE, AuctionState::LIVE ) );
		$this->assertTrue( $sm->can_transition( AuctionState::GOING_TWICE, AuctionState::CLOSING ) );
		$this->assertTrue( $sm->can_transition( AuctionState::CLOSING, AuctionState::SOLD_PAYMENT_PENDING ) );
	}

	public function test_accepts_bids(): void {
		$this->assertTrue( AuctionState::accepts_bids( AuctionState::ACTIVE, 'timed' ) );
		$this->assertFalse( AuctionState::accepts_bids( AuctionState::SCHEDULED, 'timed' ) );
		$this->assertTrue( AuctionState::accepts_bids( AuctionState::GOING_ONCE, 'live' ) );
		$this->assertFalse( AuctionState::accepts_bids( AuctionState::LOBBY, 'live' ) );
	}

	public function test_every_timed_state_is_listed(): void {
		foreach ( array_keys( ( new TimedStateMachine() )->map() ) as $state ) {
			$this->assertContains( $state, AuctionState::timed() );
		}
	}

	public function test_every_live_state_is_listed(): void {
		foreach ( array_keys( ( new LiveStateMachine() )->map() ) as $state ) {
			$this->assertContains( $state, AuctionState::live() );
		}
	}
}
