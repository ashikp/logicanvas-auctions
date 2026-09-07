<?php
/**
 * V1 manual payout provider — records status only.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Settlement;

final class ManualPayoutProvider implements PayoutProviderInterface {

	public function id(): string {
		return 'manual';
	}

	/**
	 * @param array<string, mixed> $settlement
	 * @return array{ok:bool,reference?:string,message?:string}
	 */
	public function payout( array $settlement ): array {
		return array(
			'ok'        => true,
			'reference' => 'manual-' . (int) $settlement['id'],
			'message'   => 'Recorded as manually paid. No funds were transferred.',
		);
	}
}
