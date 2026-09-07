<?php
/**
 * Invalid state transition.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Auction;

use RuntimeException;

final class InvalidTransitionException extends RuntimeException {

	public static function create( string $from, string $to, string $type ): self {
		return new self(
			sprintf(
				/* translators: 1: auction type, 2: previous state, 3: new state */
				'Invalid %1$s auction transition from "%2$s" to "%3$s".',
				esc_html( $type ),
				esc_html( $from ),
				esc_html( $to )
			)
		);
	}
}
