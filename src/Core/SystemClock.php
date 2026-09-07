<?php
/**
 * System clock using UTC.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Core;

use DateTimeImmutable;
use DateTimeZone;

final class SystemClock implements ClockInterface {

	public function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}

	public function timestamp(): int {
		return $this->now()->getTimestamp();
	}

	public function utc_mysql(): string {
		return $this->now()->format( 'Y-m-d H:i:s' );
	}
}
