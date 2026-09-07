<?php
/**
 * Live room REST.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\REST;

use LogicanvasAuctions\Domain\Auction\LiveAuctionService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class LiveController {

	public function host( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params() ?: $request->get_params();
		$result = ( new LiveAuctionService() )->host_action(
			(int) $request['id'],
			get_current_user_id(),
			sanitize_key( (string) ( $params['action'] ?? '' ) ),
			sanitize_textarea_field( (string) ( $params['reason'] ?? '' ) ),
			array(
				'seconds' => (int) ( $params['seconds'] ?? 30 ),
			)
		);

		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}

		return new WP_REST_Response( array( 'ok' => true ) );
	}

	public function join( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$auction = RestAccess::viewable_auction( $request );
		if ( is_wp_error( $auction ) ) {
			return $auction;
		}

		$service = new LiveAuctionService();
		$service->heartbeat( $auction->id(), get_current_user_id() );

		return new WP_REST_Response(
			array(
				'ok'           => true,
				'participants' => $service->participant_count( $auction->id() ),
			)
		);
	}
}
