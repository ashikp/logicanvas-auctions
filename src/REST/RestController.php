<?php
/**
 * REST API registration.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\REST;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Auction\AuctionState;

final class RestController {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		$ns = Config::REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/auctions',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( new AuctionController(), 'index' ),
					'permission_callback' => array( RestAccess::class, 'public_read' ),
					'args'                => array(
						'page'     => array(
							'type'              => 'integer',
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'type'              => 'integer',
							'default'           => 12,
							'sanitize_callback' => 'absint',
						),
						'type'     => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
						'state'    => array(
							'type'              => 'string',
							'sanitize_callback' => array( $this, 'sanitize_state_list' ),
						),
						'search'   => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( new AuctionController(), 'create' ),
					'permission_callback' => array( RestAccess::class, 'can_create' ),
					'args'                => $this->auction_write_args(),
				),
			)
		);

		register_rest_route(
			$ns,
			'/media',
			array(
				'methods'             => 'POST',
				'callback'            => array( new MediaController(), 'upload' ),
				'permission_callback' => array( RestAccess::class, 'can_create' ),
			)
		);

		$id_arg = array(
			'id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
		);

		$invite_arg = array(
			'invite'       => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'invite_token' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);

		register_rest_route(
			$ns,
			'/auctions/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( new AuctionController(), 'show' ),
					'permission_callback' => array( RestAccess::class, 'public_read' ),
					'args'                => $id_arg + $invite_arg,
				),
				array(
					'methods'             => array( 'PUT', 'PATCH' ),
					'callback'            => array( new AuctionController(), 'update' ),
					'permission_callback' => array( RestAccess::class, 'can_create' ),
					'args'                => $id_arg + $this->auction_write_args(),
				),
			)
		);

		register_rest_route(
			$ns,
			'/auctions/(?P<id>\d+)/state',
			array(
				'methods'             => 'GET',
				'callback'            => array( new AuctionController(), 'state' ),
				'permission_callback' => array( RestAccess::class, 'public_read' ),
				'args'                => $id_arg + $invite_arg,
			)
		);

		register_rest_route(
			$ns,
			'/auctions/(?P<id>\d+)/bids',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( new BidController(), 'index' ),
					'permission_callback' => array( RestAccess::class, 'public_read' ),
					'args'                => $id_arg + $invite_arg + array(
						'page'     => array(
							'type'              => 'integer',
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'type'              => 'integer',
							'default'           => 20,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( new BidController(), 'create' ),
					'permission_callback' => array( RestAccess::class, 'can_bid' ),
					'args'                => $id_arg,
				),
			)
		);

		register_rest_route(
			$ns,
			'/auctions/(?P<id>\d+)/events',
			array(
				'methods'             => 'GET',
				'callback'            => array( new EventController(), 'index' ),
				'permission_callback' => array( RestAccess::class, 'public_read' ),
				'args'                => $id_arg + $invite_arg + array(
					'after' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/auctions/(?P<id>\d+)/watch',
			array(
				'methods'             => 'POST',
				'callback'            => array( new WatchController(), 'toggle' ),
				'permission_callback' => array( RestAccess::class, 'logged_in' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			$ns,
			'/auctions/(?P<id>\d+)/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( new AuctionController(), 'submit' ),
				'permission_callback' => array( RestAccess::class, 'logged_in' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			$ns,
			'/auctions/(?P<id>\d+)/approve',
			array(
				'methods'             => 'POST',
				'callback'            => array( new AuctionController(), 'approve' ),
				'permission_callback' => array( RestAccess::class, 'can_moderate' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			$ns,
			'/auctions/(?P<id>\d+)/accept-bid',
			array(
				'methods'             => 'POST',
				'callback'            => array( new AuctionController(), 'accept_bid' ),
				'permission_callback' => array( RestAccess::class, 'logged_in' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			$ns,
			'/live/(?P<id>\d+)/host',
			array(
				'methods'             => 'POST',
				'callback'            => array( new LiveController(), 'host' ),
				'permission_callback' => array( RestAccess::class, 'can_host' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			$ns,
			'/live/(?P<id>\d+)/join',
			array(
				'methods'             => 'POST',
				'callback'            => array( new LiveController(), 'join' ),
				'permission_callback' => array( RestAccess::class, 'logged_in' ),
				'args'                => $id_arg + $invite_arg,
			)
		);

		register_rest_route(
			$ns,
			'/awards/(?P<id>\d+)/checkout',
			array(
				'methods'             => 'POST',
				'callback'            => array( new CheckoutController(), 'start' ),
				'permission_callback' => array( RestAccess::class, 'logged_in' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			$ns,
			'/orders/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( new OrderController(), 'update' ),
				'permission_callback' => array( RestAccess::class, 'logged_in' ),
				'args'                => $id_arg + array(
					'status'            => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'tracking_number'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'tracking_carrier'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'tracking_url'      => array(
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
					'fulfilment_note'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'customer_note'     => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'mark_paid_offline' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/products',
			array(
				'methods'             => 'GET',
				'callback'            => array( new ProductController(), 'search' ),
				'permission_callback' => array( RestAccess::class, 'can_manage_store' ),
				'args'                => array(
					'search' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/products/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( new ProductController(), 'get' ),
				'permission_callback' => array( RestAccess::class, 'can_manage_store' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			$ns,
			'/holder/apply',
			array(
				'methods'             => 'POST',
				'callback'            => array( new HolderController(), 'apply' ),
				'permission_callback' => array( RestAccess::class, 'logged_in' ),
			)
		);

		register_rest_route(
			$ns,
			'/payouts',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( new PayoutController(), 'balance' ),
					'permission_callback' => array( PayoutController::class, 'can_request_payout' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( new PayoutController(), 'request_payout' ),
					'permission_callback' => array( PayoutController::class, 'can_request_payout' ),
					'args'                => array(
						'amount' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'note'   => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/payouts/method',
			array(
				'methods'             => 'POST',
				'callback'            => array( new PayoutController(), 'save_method' ),
				'permission_callback' => array( PayoutController::class, 'can_request_payout' ),
			)
		);

		register_rest_route(
			$ns,
			'/payouts/(?P<id>\d+)/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( new PayoutController(), 'cancel' ),
				'permission_callback' => array( PayoutController::class, 'can_request_payout' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			$ns,
			'/me/dashboard',
			array(
				'methods'             => 'GET',
				'callback'            => array( new MeController(), 'dashboard' ),
				'permission_callback' => array( RestAccess::class, 'logged_in' ),
			)
		);
	}

	/**
	 * REST arg schema for auction create/update (types + sanitize callbacks).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function auction_write_args(): array {
		$text = static function ( $value ) {
			return \LogicanvasAuctions\Domain\Auction\AuctionInputSanitizer::plain_text( $value, 200 );
		};
		$html = static function ( $value ) {
			return \LogicanvasAuctions\Domain\Auction\AuctionInputSanitizer::safe_html( $value );
		};
		$area = static function ( $value ) {
			return \LogicanvasAuctions\Domain\Auction\AuctionInputSanitizer::plain_textarea( $value, 2000 );
		};
		$money = static function ( $value ) {
			$result = \LogicanvasAuctions\Domain\Auction\AuctionInputSanitizer::money_string( $value );
			return is_wp_error( $result ) ? '' : $result;
		};
		$ids = static function ( $value ) {
			return \LogicanvasAuctions\Domain\Auction\AuctionInputSanitizer::int_list( $value, 12 );
		};

		return array(
			'title'                   => array(
				'type'              => 'string',
				'sanitize_callback' => $text,
			),
			'description'             => array(
				'type'              => 'string',
				'sanitize_callback' => $html,
			),
			'short_description'       => array(
				'type'              => 'string',
				'sanitize_callback' => $area,
			),
			'type'                    => array(
				'type'              => 'string',
				'enum'              => array( 'timed', 'live' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'visibility'              => array(
				'type'              => 'string',
				'enum'              => array( 'public', 'unlisted', 'private', 'role_restricted' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'product_source'          => array(
				'type'              => 'string',
				'enum'              => array( 'new', 'existing' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'product_id'              => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'featured_image_id'       => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'gallery_ids'             => array(
				'sanitize_callback' => $ids,
			),
			'starting_price'          => array(
				'type'              => 'string',
				'sanitize_callback' => $money,
			),
			'reserve_price'           => array(
				'type'              => 'string',
				'sanitize_callback' => $money,
			),
			'min_increment'           => array(
				'type'              => 'string',
				'sanitize_callback' => $money,
			),
			'buy_now'                 => array(
				'type'              => 'string',
				'sanitize_callback' => $money,
			),
			'start_at'                => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'end_at'                  => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'fulfilment_type'         => array(
				'type'              => 'string',
				'enum'              => array( 'shipping', 'pickup', 'digital', 'holder_defined' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'fulfilment'              => array(
				'type'              => 'string',
				'enum'              => array( 'shipping', 'pickup', 'digital', 'holder_defined' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'condition'               => array(
				'type'              => 'string',
				'sanitize_callback' => $text,
			),
			'sku'                     => array(
				'type'              => 'string',
				'sanitize_callback' => $text,
			),
			'fulfilment_notes'        => array(
				'type'              => 'string',
				'sanitize_callback' => $area,
			),
			'payment_deadline_hours'  => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'proxy_enabled'           => array(
				'type' => 'boolean',
			),
		);
	}

	/**
	 * @param mixed $value Raw state list.
	 */
	public function sanitize_state_list( $value ): string {
		$parts = array_filter( array_map( 'sanitize_key', explode( ',', (string) $value ) ) );
		$valid = array_values( array_filter( $parts, static fn( string $state ) => AuctionState::is_valid( $state ) ) );

		return implode( ',', $valid );
	}
}
