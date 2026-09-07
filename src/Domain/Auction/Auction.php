<?php
/**
 * Auction state snapshot (canonical row + content identifiers).
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Auction;

use LogicanvasAuctions\Domain\Money\Money;

final class Auction {

	/**
	 * @param array<string, mixed> $row
	 */
	public function __construct( private array $row ) {}

	public function id(): int {
		return (int) $this->row['auction_id'];
	}

	public function holder_id(): int {
		return (int) $this->row['holder_id'];
	}

	public function product_id(): int {
		return (int) $this->row['product_id'];
	}

	public function type(): string {
		return (string) $this->row['type'];
	}

	public function state(): string {
		return (string) $this->row['state'];
	}

	public function visibility(): string {
		return (string) $this->row['visibility'];
	}

	public function currency(): string {
		return (string) $this->row['currency'];
	}

	public function current_amount(): Money {
		return Money::from_string( (string) $this->row['current_amount'], $this->currency() );
	}

	public function starting_amount(): Money {
		return Money::from_string( (string) $this->row['starting_amount'], $this->currency() );
	}

	public function min_increment(): Money {
		return Money::from_string( (string) $this->row['min_increment'], $this->currency() );
	}

	public function reserve_amount(): ?Money {
		if ( null === $this->row['reserve_amount'] || '' === $this->row['reserve_amount'] ) {
			return null;
		}

		return Money::from_string( (string) $this->row['reserve_amount'], $this->currency() );
	}

	public function buy_now_amount(): ?Money {
		if ( null === $this->row['buy_now_amount'] || '' === $this->row['buy_now_amount'] ) {
			return null;
		}

		return Money::from_string( (string) $this->row['buy_now_amount'], $this->currency() );
	}

	public function current_leader_id(): ?int {
		$id = (int) ( $this->row['current_leader_id'] ?? 0 );
		return $id > 0 ? $id : null;
	}

	public function bid_count(): int {
		return (int) $this->row['bid_count'];
	}

	public function sequence(): int {
		return (int) $this->row['sequence'];
	}

	public function start_at_utc(): string {
		return (string) $this->row['start_at_utc'];
	}

	public function end_at_utc(): ?string {
		$end = $this->row['end_at_utc'] ?? null;
		return $end ? (string) $end : null;
	}

	public function extension_count(): int {
		return (int) ( $this->row['extension_count'] ?? 0 );
	}

	public function payment_deadline_hours(): int {
		return (int) $this->row['payment_deadline_hours'];
	}

	public function increment_strategy(): string {
		return (string) ( $this->row['increment_strategy'] ?? 'fixed' );
	}

	public function proxy_enabled(): bool {
		return ! empty( $this->row['proxy_enabled'] );
	}

	public function award_id(): ?int {
		$id = (int) ( $this->row['award_id'] ?? 0 );
		return $id > 0 ? $id : null;
	}

	public function order_id(): ?int {
		$id = (int) ( $this->row['order_id'] ?? 0 );
		return $id > 0 ? $id : null;
	}

	public function settlement_id(): ?int {
		$id = (int) ( $this->row['settlement_id'] ?? 0 );
		return $id > 0 ? $id : null;
	}

	public function accepts_bids(): bool {
		return AuctionState::accepts_bids( $this->state(), $this->type() );
	}

	public function is_timed(): bool {
		return AuctionType::TIMED === $this->type();
	}

	public function is_live(): bool {
		return AuctionType::LIVE === $this->type();
	}

	public function is_holder( int $user_id ): bool {
		return $this->holder_id() === $user_id;
	}

	public function reserve_met(): bool {
		$reserve = $this->reserve_amount();
		if ( null === $reserve ) {
			return $this->bid_count() > 0;
		}

		if ( $this->bid_count() < 1 ) {
			return false;
		}

		return $this->current_amount()->greater_than_or_equal( $reserve );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return $this->row;
	}

	/**
	 * @param array<string, mixed> $patch
	 */
	public function with( array $patch ): self {
		return new self( array_merge( $this->row, $patch ) );
	}
}
