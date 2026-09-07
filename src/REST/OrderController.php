<?php
/**
 * Seller/admin auction order REST updates.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\REST;

use LogicanvasAuctions\Domain\Order\HolderOrderService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class OrderController {

	public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$service = new HolderOrderService();
		$result  = $service->update(
			(int) $request['id'],
			get_current_user_id(),
			array(
				'status'            => (string) $request->get_param( 'status' ),
				'tracking_number'   => (string) $request->get_param( 'tracking_number' ),
				'tracking_carrier'  => (string) $request->get_param( 'tracking_carrier' ),
				'tracking_url'      => (string) $request->get_param( 'tracking_url' ),
				'fulfilment_note'   => (string) $request->get_param( 'fulfilment_note' ),
				'customer_note'     => (string) $request->get_param( 'customer_note' ),
				'mark_paid_offline' => (bool) $request->get_param( 'mark_paid_offline' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'ok' => true ) );
	}
}
