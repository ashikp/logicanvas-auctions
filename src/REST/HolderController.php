<?php
/**
 * Holder application REST.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\REST;

use LogicanvasAuctions\Domain\Holder\HolderService;
use LogicanvasAuctions\Infrastructure\Database\RateLimiter;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class HolderController {

	public function apply( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();
		if ( ! ( new RateLimiter() )->hit( 'holder_apply_' . $user_id, 8, HOUR_IN_SECONDS ) ) {
			return new WP_Error( 'rate_limited', __( 'Too many applications. Try again later.', 'logicanvas-auctions' ), array( 'status' => 429 ) );
		}

		$params = $request->get_json_params() ?: $request->get_params();
		$id     = ( new HolderService() )->apply(
			$user_id,
			array(
				'company' => (string) ( $params['company'] ?? '' ),
				'notes'   => (string) ( $params['notes'] ?? '' ),
			)
		);

		return new WP_REST_Response( array( 'id' => $id, 'status' => HolderService::PENDING ) );
	}
}
