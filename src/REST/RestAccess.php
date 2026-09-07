<?php
/**
 * Shared REST auction access checks.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\REST;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Auction\Auction;
use LogicanvasAuctions\Domain\Invitation\AccessService;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository;
use WP_Error;
use WP_REST_Request;

final class RestAccess {

	public static function public_read(): bool {
		return true;
	}

	public static function logged_in(): bool {
		return is_user_logged_in();
	}

	public static function can_create(): bool {
		return is_user_logged_in() && current_user_can( Config::CAP_CREATE_AUCTIONS );
	}

	public static function can_bid(): bool {
		return is_user_logged_in() && current_user_can( Config::CAP_BID );
	}

	public static function can_moderate(): bool {
		return is_user_logged_in() && current_user_can( Config::CAP_MODERATE_AUCTIONS );
	}

	public static function can_host(): bool {
		return is_user_logged_in() && current_user_can( Config::CAP_HOST_LIVE );
	}

	public static function can_manage_store(): bool {
		return is_user_logged_in() && current_user_can( 'manage_options' );
	}

	public static function invite_token( WP_REST_Request $request ): string {
		$token = (string) ( $request->get_param( 'invite' ) ?: $request->get_param( 'invite_token' ) ?: '' );

		return sanitize_text_field( $token );
	}

	/**
	 * @return Auction|WP_Error
	 */
	public static function viewable_auction( WP_REST_Request $request ) {
		$auction = ( new WpdbAuctionRepository() )->find( (int) $request['id'] );
		if ( ! $auction ) {
			return new WP_Error( 'not_found', __( 'Auction not found.', 'logicanvas-auctions' ), array( 'status' => 404 ) );
		}

		if ( ! ( new AccessService() )->can_view( $auction, get_current_user_id(), self::invite_token( $request ) ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot view this auction.', 'logicanvas-auctions' ), array( 'status' => 403 ) );
		}

		return $auction;
	}
}
