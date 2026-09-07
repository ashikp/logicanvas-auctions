<?php
/**
 * Seller payout request REST endpoints.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\REST;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Settlement\PayoutRequestService;
use LogicanvasAuctions\Infrastructure\Database\RateLimiter;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class PayoutController {

	public function balance( WP_REST_Request $request ): WP_REST_Response|WP_Error { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$service = new PayoutRequestService();
		$user_id = get_current_user_id();

		return new WP_REST_Response(
			array(
				'balance'         => $service->available_balance( $user_id ),
				'payment_method'  => $service->get_payment_method( $user_id ),
				'method_complete' => $service->payment_method_is_complete( $user_id ),
				'method_labels'   => PayoutRequestService::method_labels(),
				'requests'        => $service->for_holder( $user_id ),
			)
		);
	}

	public function save_method( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params() ?: $request->get_params();
		$result = ( new PayoutRequestService() )->save_payment_method( get_current_user_id(), is_array( $params ) ? $params : array() );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}

		return new WP_REST_Response(
			array(
				'ok'             => true,
				'payment_method' => ( new PayoutRequestService() )->get_payment_method( get_current_user_id() ),
			)
		);
	}

	public function request_payout( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();
		if ( ! ( new RateLimiter() )->hit( 'payout_request_' . $user_id, 20, HOUR_IN_SECONDS ) ) {
			return new WP_Error( 'rate_limited', __( 'Too many payout requests. Try again later.', 'logicanvas-auctions' ), array( 'status' => 429 ) );
		}

		$params = $request->get_json_params() ?: $request->get_params();
		$id     = ( new PayoutRequestService() )->request( $user_id, is_array( $params ) ? $params : array() );
		if ( is_wp_error( $id ) ) {
			$id->add_data( array( 'status' => 400 ) );
			return $id;
		}

		$service = new PayoutRequestService();

		return new WP_REST_Response(
			array(
				'id'      => $id,
				'balance' => $service->available_balance( $user_id ),
				'request' => $service->find( $id ),
			),
			201
		);
	}

	public function cancel( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id     = (int) $request['id'];
		$result = ( new PayoutRequestService() )->cancel( $id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}

		return new WP_REST_Response( array( 'ok' => true, 'id' => $id ) );
	}

	public static function can_request_payout(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		return current_user_can( Config::CAP_VIEW_OWN_SETTLEMENTS )
			|| current_user_can( Config::CAP_CREATE_AUCTIONS )
			|| current_user_can( 'manage_options' );
	}
}
