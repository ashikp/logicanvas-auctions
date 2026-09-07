<?php
/**
 * Bid record.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Bidding;

use LogicanvasAuctions\Domain\Money\Money;

final class Bid {

	public const STATUS_ACCEPTED = 'accepted';
	public const STATUS_VOIDED   = 'voided';
	public const TYPE_REGULAR    = 'regular';
	public const TYPE_QUICK      = 'quick';
	public const TYPE_PROXY      = 'proxy';
	public const TYPE_PROXY_FILL = 'proxy_fill';

	/**
	 * @param array<string, mixed> $row
	 */
	public function __construct( private array $row ) {}

	public function id(): int {
		return (int) $this->row['id'];
	}

	public function auction_id(): int {
		return (int) $this->row['auction_id'];
	}

	public function bidder_id(): int {
		return (int) $this->row['bidder_id'];
	}

	public function amount(): Money {
		return Money::from_string( (string) $this->row['amount'], (string) $this->row['currency'] );
	}

	public function status(): string {
		return (string) $this->row['status'];
	}

	public function type(): string {
		return (string) $this->row['type'];
	}

	public function idempotency_key(): string {
		return (string) $this->row['idempotency_key'];
	}

	public function is_accepted(): bool {
		return self::STATUS_ACCEPTED === $this->status();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return $this->row;
	}

	/**
	 * Public representation without proxy maxima or identity details.
	 *
	 * @return array<string, mixed>
	 */
	public function to_public( string $display_name ): array {
		return array(
			'id'         => $this->id(),
			'auction_id' => $this->auction_id(),
			'amount'     => $this->amount()->to_rest(),
			'type'       => $this->type(),
			'status'     => $this->status(),
			'bidder'     => $display_name,
			// Opaque key for leaderboard grouping without exposing the WordPress user ID.
			'bidder_key' => \LogicanvasAuctions\Application\AuctionPresenter::bidder_key( $this->bidder_id() ),
			'created_at' => (string) ( $this->row['created_at_utc'] ?? '' ),
		);
	}
}
