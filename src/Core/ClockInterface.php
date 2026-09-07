<?php
/**
 * Clock abstraction for testable domain time.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Core;

interface ClockInterface {

	public function now(): \DateTimeImmutable;

	public function timestamp(): int;

	public function utc_mysql(): string;
}
