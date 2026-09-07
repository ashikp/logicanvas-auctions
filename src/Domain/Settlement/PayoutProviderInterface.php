<?php
/**
 * Manual payout tracking. Stripe Connect can implement this later.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Settlement;

interface PayoutProviderInterface {

	public function id(): string;

	/**
	 * @param array<string, mixed> $settlement
	 * @return array{ok:bool,reference?:string,message?:string}
	 */
	public function payout( array $settlement ): array;
}
