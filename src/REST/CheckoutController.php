<?php
/**
 * Winner checkout REST.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\REST;

use LogicanvasAuctions\Infrastructure\WooCommerce\CheckoutLock;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class CheckoutController {

	public function start( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = ( new CheckoutLock() )->start_for_winner( (int) $request['id'], get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}

		return new WP_REST_Response( array( 'checkout_url' => $result ) );
	}
}
