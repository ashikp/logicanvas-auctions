<?php
/**
 * Frozen clock for tests.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Core;

use DateTimeImmutable;
use DateTimeZone;

final class FrozenClock implements ClockInterface {

	private DateTimeImmutable $now;

	public function __construct( ?DateTimeImmutable $now = null ) {
		$this->now = $now ?? new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}

	public function set( DateTimeImmutable $now ): void {
		$this->now = $now;
	}

	public function advance( int $seconds ): void {
		$this->now = $this->now->modify( '+' . $seconds . ' seconds' );
	}

	public function now(): DateTimeImmutable {
		return $this->now;
	}

	public function timestamp(): int {
		return $this->now->getTimestamp();
	}

	public function utc_mysql(): string {
		return $this->now->format( 'Y-m-d H:i:s' );
	}
}
