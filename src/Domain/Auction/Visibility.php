<?php
/**
 * Visibility modes.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Auction;

final class Visibility {

	public const PUBLIC_LISTED = 'public';
	public const UNLISTED      = 'unlisted';
	public const PRIVATE_INVITE = 'private';
	public const ROLE_RESTRICTED = 'role_restricted';

	public static function is_valid( string $visibility ): bool {
		return in_array(
			$visibility,
			array( self::PUBLIC_LISTED, self::UNLISTED, self::PRIVATE_INVITE, self::ROLE_RESTRICTED ),
			true
		);
	}

	public static function is_publicly_listed( string $visibility ): bool {
		return self::PUBLIC_LISTED === $visibility;
	}
}
