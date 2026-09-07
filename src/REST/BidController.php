<?php
/**
 * Bid REST endpoints.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\REST;

use LogicanvasAuctions\Admin\Settings;
use LogicanvasAuctions\Application\AuctionPresenter;
use LogicanvasAuctions\Domain\Bidding\BidRejectedException;
use LogicanvasAuctions\Domain\Bidding\BidService;
use LogicanvasAuctions\Infrastructure\Database\WpdbBidRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class BidController {

	public function index( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$auction = RestAccess::viewable_auction( $request );
		if ( is_wp_error( $auction ) ) {
			return $auction;
		}

		$settings = Settings::get();
		if ( empty( $settings['public_bid_history'] ) && ! is_user_logged_in() ) {
			return new WP_REST_Response( array() );
		}

		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$per_page = min( 50, max( 1, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );
		$bids     = ( new WpdbBidRepository() )->for_auction( $auction->id(), $per_page, ( $page - 1 ) * $per_page );
		$present  = new AuctionPresenter();

		$data = array_map(
			static fn( $bid ) => $bid->to_public( $present->bidder_display_name( $bid->bidder_id() ) ),
			$bids
		);

		return new WP_REST_Response( $data );
	}

	public function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params() ?: $request->get_params();
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ua     = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		try {
			$result = ( new BidService() )->place(
				array(
					'auction_id'      => (int) $request['id'],
					'user_id'         => get_current_user_id(),
					'amount'          => sanitize_text_field( (string) ( $params['amount'] ?? '' ) ),
					'idempotency_key' => sanitize_text_field( (string) ( $params['idempotency_key'] ?? '' ) ),
					'type'            => sanitize_key( (string) ( $params['type'] ?? 'regular' ) ),
					'max_amount'      => isset( $params['max_amount'] ) ? sanitize_text_field( (string) $params['max_amount'] ) : null,
					'ip'              => $ip,
					'user_agent'      => $ua,
					'invite_token'    => sanitize_text_field( (string) ( $params['invite_token'] ?? RestAccess::invite_token( $request ) ) ),
				)
			);
		} catch ( BidRejectedException $e ) {
			return new WP_Error( $e->error_code(), $e->getMessage(), array( 'status' => 400 ) );
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'invalid_amount', $e->getMessage(), array( 'status' => 400 ) );
		}

		$present = new AuctionPresenter();
		return new WP_REST_Response(
			array(
				'bid'      => $result['bid']->to_public( $present->bidder_display_name( $result['bid']->bidder_id() ) ),
				'auction'  => $present->public_state( $result['auction'], get_current_user_id() ),
				'replay'   => $result['replay'],
				'extended' => $result['extended'],
			)
		);
	}
}
