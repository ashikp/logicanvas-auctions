<?php
/**
 * Incremental event polling.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\REST;

use LogicanvasAuctions\Application\AuctionPresenter;
use LogicanvasAuctions\Infrastructure\Database\WpdbEventRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class EventController {

	/**
	 * @var string[]
	 */
	private const PAYLOAD_KEYS = array(
		'bid_id',
		'amount',
		'extended',
		'end_at_utc',
		'seconds',
		'from',
		'to',
	);

	public function index( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$auction = RestAccess::viewable_auction( $request );
		if ( is_wp_error( $auction ) ) {
			return $auction;
		}

		$after  = max( 0, (int) $request->get_param( 'after' ) );
		$events = ( new WpdbEventRepository() )->since( $auction->id(), $after, 50 );
		$state  = ( new AuctionPresenter() )->public_state( $auction, get_current_user_id(), RestAccess::invite_token( $request ) );

		$public = array();
		foreach ( $events as $event ) {
			$public[] = $this->public_event( $event );
		}

		return new WP_REST_Response(
			array(
				'events'          => $public,
				'state'           => $state,
				'server_time_utc' => gmdate( 'Y-m-d H:i:s' ),
				'server_ts'       => time(),
			)
		);
	}

	/**
	 * @param array<string, mixed> $event Raw event row.
	 * @return array<string, mixed>
	 */
	private function public_event( array $event ): array {
		$payload = is_array( $event['payload'] ?? null ) ? $event['payload'] : array();
		$safe    = array();
		foreach ( self::PAYLOAD_KEYS as $key ) {
			if ( array_key_exists( $key, $payload ) ) {
				$safe[ $key ] = $payload[ $key ];
			}
		}

		return array(
			'sequence'       => (int) ( $event['sequence'] ?? 0 ),
			'event_type'     => sanitize_key( (string) ( $event['event_type'] ?? '' ) ),
			'payload'        => $safe,
			'created_at_utc' => sanitize_text_field( (string) ( $event['created_at_utc'] ?? '' ) ),
		);
	}
}
