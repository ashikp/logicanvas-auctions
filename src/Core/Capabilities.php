<?php
/**
 * Roles and capabilities. Idempotent install.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Core;

use LogicanvasAuctions\Config;
use WP_Role;

final class Capabilities {

	public function install(): void {
		$this->add_role(
			Config::HOLDER_ROLE,
			'Auction Holder',
			$this->caps_as_true( Config::holder_capabilities() )
		);

		$this->add_role(
			Config::BIDDER_ROLE,
			'Auction Bidder',
			$this->caps_as_true( Config::bidder_capabilities() )
		);

		$admin = get_role( 'administrator' );
		if ( $admin instanceof WP_Role ) {
			foreach ( Config::all_capabilities() as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		$shop_manager = get_role( 'shop_manager' );
		if ( $shop_manager instanceof WP_Role ) {
			foreach ( Config::all_capabilities() as $cap ) {
				$shop_manager->add_cap( $cap );
			}
		}

		$customer = get_role( 'customer' );
		if ( $customer instanceof WP_Role ) {
			foreach ( Config::bidder_capabilities() as $cap ) {
				$customer->add_cap( $cap );
			}
		}
	}

	public function user_can_bid( int $user_id ): bool {
		return user_can( $user_id, Config::CAP_BID );
	}

	public function user_can_create( int $user_id ): bool {
		return user_can( $user_id, Config::CAP_CREATE_AUCTIONS );
	}

	public function user_can_moderate( int $user_id ): bool {
		return user_can( $user_id, Config::CAP_MODERATE_AUCTIONS );
	}

	/**
	 * @param string[] $caps
	 * @return array<string, bool>
	 */
	private function caps_as_true( array $caps ): array {
		$out = array( 'read' => true );
		foreach ( $caps as $cap ) {
			$out[ $cap ] = true;
		}

		return $out;
	}

	/**
	 * @param array<string, bool> $caps
	 */
	private function add_role( string $slug, string $label, array $caps ): void {
		$existing = get_role( $slug );
		if ( ! $existing instanceof WP_Role ) {
			add_role( $slug, $label, $caps );
			return;
		}

		foreach ( $caps as $cap => $grant ) {
			if ( $grant ) {
				$existing->add_cap( $cap );
			}
		}
	}
}
