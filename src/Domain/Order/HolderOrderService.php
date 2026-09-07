<?php
/**
 * Seller-facing auction order listing and updates.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Order;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuditRepository;
use WC_Order;

final class HolderOrderService {

	public const META_TRACKING_NUMBER  = '_wcap_tracking_number';
	public const META_TRACKING_CARRIER = '_wcap_tracking_carrier';
	public const META_TRACKING_URL     = '_wcap_tracking_url';
	public const META_FULFILMENT_NOTE  = '_wcap_fulfilment_note';

	public function __construct(
		private OrderManagement $management = new OrderManagement()
	) {}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_for_holder( int $holder_id ): array {
		global $wpdb;

		$this->reconcile_for_holder( $holder_id );

		$settle_t = Config::table( Config::TABLE_SETTLEMENTS );
		$award_t  = Config::table( Config::TABLE_AWARDS );
		$state_t  = Config::table( Config::TABLE_AUCTION_STATE );
		$rows     = QueryCache::remember(
			QueryCache::key( 'holder_orders', $holder_id ),
			45,
			static function () use ( $wpdb, $settle_t, $award_t, $state_t, $holder_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT s.id AS settlement_id, s.auction_id, s.award_id, s.gross_amount, s.commission_amount, s.net_amount, s.currency, s.payout_status,
							a.order_id AS award_order_id, a.winner_id, a.status AS award_status, a.amount AS award_amount,
							st.order_id AS state_order_id
						FROM %i s
						LEFT JOIN %i a ON a.id = s.award_id
						LEFT JOIN %i st ON st.auction_id = s.auction_id
						WHERE s.holder_id = %d
						ORDER BY s.id DESC
						LIMIT 50',
						$settle_t,
						$award_t,
						$state_t,
						$holder_id
					),
					ARRAY_A
				);
			}
		);

		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			$order_id = (int) ( $row['award_order_id'] ?: $row['state_order_id'] ?: 0 );
			$order    = $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
			$post     = get_post( (int) $row['auction_id'] );
			$winner   = ! empty( $row['winner_id'] ) ? get_userdata( (int) $row['winner_id'] ) : false;

			$payment = $this->payment_snapshot( $order instanceof WC_Order ? $order : null, (string) ( $row['award_status'] ?? '' ) );
			$track   = $this->tracking_from_order( $order instanceof WC_Order ? $order : null );

			$item = array(
				'settlement_id'      => (int) $row['settlement_id'],
				'auction_id'         => (int) $row['auction_id'],
				'award_id'           => (int) $row['award_id'],
				'title'              => $post ? $post->post_title : '#' . $row['auction_id'],
				'permalink'          => get_permalink( (int) $row['auction_id'] ),
				'gross'              => (string) $row['gross_amount'] . ' ' . (string) $row['currency'],
				'commission'         => (string) $row['commission_amount'],
				'net'                => (string) $row['net_amount'] . ' ' . (string) $row['currency'],
				'payout_status'      => (string) $row['payout_status'],
				'award_status'       => (string) ( $row['award_status'] ?? '' ),
				'winner_name'        => $winner ? $winner->display_name : '',
				'order_id'           => $order_id,
				'order_number'       => $order instanceof WC_Order ? $order->get_order_number() : '',
				'order_status'       => $order instanceof WC_Order ? $order->get_status() : '',
				'order_status_label' => $order instanceof WC_Order ? wc_get_order_status_name( $order->get_status() ) : __( 'No order yet', 'logicanvas-auctions' ),
				'payment'            => $payment,
				'tracking_number'    => $track['number'],
				'tracking_carrier'   => $track['carrier'],
				'tracking_url'       => $track['url'],
				'fulfilment_note'    => $track['note'],
				'can_manage'         => $order instanceof WC_Order && $this->management->user_can_manage_order( $holder_id, $order ),
				'allowed_statuses'   => $this->management->allowed_seller_statuses(),
				'view_order_url'     => $order instanceof WC_Order ? $order->get_view_order_url() : '',
			);

			$out[] = $item;
		}

		// Also include awards with orders that may not have settlements yet.
		$extra = QueryCache::remember(
			QueryCache::key( 'holder_extra_orders', $holder_id ),
			45,
			static function () use ( $wpdb, $award_t, $state_t, $holder_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT a.id AS award_id, a.auction_id, a.order_id, a.winner_id, a.status AS award_status, a.amount, a.currency
						FROM %i a
						INNER JOIN %i st ON st.auction_id = a.auction_id
						WHERE st.holder_id = %d AND a.order_id > 0
						ORDER BY a.id DESC
						LIMIT 50',
						$award_t,
						$state_t,
						$holder_id
					),
					ARRAY_A
				);
			}
		);

		$seen = array();
		foreach ( $out as $item ) {
			if ( ! empty( $item['order_id'] ) ) {
				$seen[ (int) $item['order_id'] ] = true;
			}
		}

		if ( is_array( $extra ) ) {
			foreach ( $extra as $row ) {
				$order_id = (int) $row['order_id'];
				if ( $order_id < 1 || isset( $seen[ $order_id ] ) ) {
					continue;
				}
				$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
				if ( ! $order instanceof WC_Order ) {
					continue;
				}
				$post   = get_post( (int) $row['auction_id'] );
				$winner = ! empty( $row['winner_id'] ) ? get_userdata( (int) $row['winner_id'] ) : false;
				$track  = $this->tracking_from_order( $order );
				$out[]  = array(
					'settlement_id'      => 0,
					'auction_id'         => (int) $row['auction_id'],
					'award_id'           => (int) $row['award_id'],
					'title'              => $post ? $post->post_title : '#' . $row['auction_id'],
					'permalink'          => get_permalink( (int) $row['auction_id'] ),
					'gross'              => (string) $row['amount'] . ' ' . (string) $row['currency'],
					'commission'         => '',
					'net'                => '',
					'payout_status'      => '',
					'award_status'       => (string) $row['award_status'],
					'winner_name'        => $winner ? $winner->display_name : '',
					'order_id'           => $order_id,
					'order_number'       => $order->get_order_number(),
					'order_status'       => $order->get_status(),
					'order_status_label' => wc_get_order_status_name( $order->get_status() ),
					'payment'            => $this->payment_snapshot( $order, (string) $row['award_status'] ),
					'tracking_number'    => $track['number'],
					'tracking_carrier'   => $track['carrier'],
					'tracking_url'       => $track['url'],
					'fulfilment_note'    => $track['note'],
					'can_manage'         => $this->management->user_can_manage_order( $holder_id, $order ),
					'allowed_statuses'   => $this->management->allowed_seller_statuses(),
					'view_order_url'     => $order->get_view_order_url(),
				);
			}
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return true|\WP_Error
	 */
	public function update( int $order_id, int $user_id, array $input ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return new \WP_Error( 'woocommerce', __( 'WooCommerce is required.', 'logicanvas-auctions' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return new \WP_Error( 'not_found', __( 'Order not found.', 'logicanvas-auctions' ), array( 'status' => 404 ) );
		}

		if ( ! $this->management->user_can_manage_order( $user_id, $order ) ) {
			return new \WP_Error( 'forbidden', __( 'You cannot manage this order.', 'logicanvas-auctions' ), array( 'status' => 403 ) );
		}

		$is_admin = user_can( $user_id, 'manage_woocommerce' ) || user_can( $user_id, 'manage_options' );
		$changed  = array();

		if ( array_key_exists( 'status', $input ) && '' !== (string) $input['status'] ) {
			$status = sanitize_key( (string) $input['status'] );
			$status = str_replace( 'wc-', '', $status );
			$allowed = $is_admin
				? array_keys( $this->management->status_labels() )
				: $this->management->allowed_seller_statuses();

			if ( ! in_array( $status, $allowed, true ) ) {
				return new \WP_Error( 'invalid_status', __( 'That order status is not allowed.', 'logicanvas-auctions' ), array( 'status' => 400 ) );
			}

			if ( $status !== $order->get_status() ) {
				$order->update_status( $status, sprintf(
					/* translators: %d: user id */
					__( 'Status updated by auction holder/admin (user #%d) via Logicanvas Auctions for WooCommerce.', 'logicanvas-auctions' ),
					$user_id
				), true );
				$changed[] = 'status:' . $status;
			}
		}

		if ( array_key_exists( 'tracking_number', $input ) ) {
			$order->update_meta_data( self::META_TRACKING_NUMBER, sanitize_text_field( (string) $input['tracking_number'] ) );
			$changed[] = 'tracking_number';
		}
		if ( array_key_exists( 'tracking_carrier', $input ) ) {
			$order->update_meta_data( self::META_TRACKING_CARRIER, sanitize_text_field( (string) $input['tracking_carrier'] ) );
			$changed[] = 'tracking_carrier';
		}
		if ( array_key_exists( 'tracking_url', $input ) ) {
			$order->update_meta_data( self::META_TRACKING_URL, esc_url_raw( (string) $input['tracking_url'] ) );
			$changed[] = 'tracking_url';
		}
		if ( array_key_exists( 'fulfilment_note', $input ) ) {
			$order->update_meta_data( self::META_FULFILMENT_NOTE, sanitize_textarea_field( (string) $input['fulfilment_note'] ) );
			$changed[] = 'fulfilment_note';
		}

		if ( ! empty( $input['customer_note'] ) ) {
			$order->add_order_note( sanitize_textarea_field( (string) $input['customer_note'] ), true, true );
			$changed[] = 'customer_note';
		}

		if ( ! empty( $input['mark_paid_offline'] ) && ! $order->is_paid() ) {
			$order->payment_complete();
			$order->add_order_note(
				sprintf(
					/* translators: %d: user id */
					__( 'Marked paid offline by auction holder/admin (user #%d).', 'logicanvas-auctions' ),
					$user_id
				),
				false,
				true
			);
			$changed[] = 'paid_offline';
		}

		$order->save();

		if ( in_array( $order->get_status(), array( 'processing', 'completed' ), true ) || $order->is_paid() ) {
			( new \LogicanvasAuctions\Infrastructure\WooCommerce\OrderObserver() )->sync_paid_order( $order );
		}

		( new WpdbAuditRepository() )->write(
			(int) $order->get_meta( '_wcap_auction_id' ),
			'order_updated',
			$user_id,
			implode( ',', $changed ),
			array( 'order_id' => $order_id ),
			gmdate( 'Y-m-d H:i:s' ),
			'user',
			'order-' . $order_id
		);

		do_action( 'wcap_auction_order_updated', $order_id, $user_id, $changed );

		return true;
	}

	/**
	 * @return array{label:string,paid:bool,method:string,date:string,needs_payment:bool}
	 */
	public function payment_snapshot( ?WC_Order $order, string $award_status = '' ): array {
		if ( ! $order ) {
			$pending = 'pending' === $award_status;
			return array(
				'label'         => $pending ? __( 'Awaiting winner payment', 'logicanvas-auctions' ) : __( 'No WooCommerce order', 'logicanvas-auctions' ),
				'paid'          => false,
				'method'        => '',
				'date'          => '',
				'needs_payment' => $pending,
			);
		}

		$status_paid = $order->has_status( array( 'processing', 'completed' ) );
		$paid        = $status_paid || $order->is_paid();
		$date        = $order->get_date_paid();

		return array(
			'label'         => $paid
				? __( 'Paid', 'logicanvas-auctions' )
				: ( $order->needs_payment() ? __( 'Unpaid / awaiting payment', 'logicanvas-auctions' ) : wc_get_order_status_name( $order->get_status() ) ),
			'paid'          => $paid,
			'method'        => (string) $order->get_payment_method_title(),
			'date'          => $date ? $date->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '',
			'needs_payment' => ! $paid && $order->needs_payment(),
		);
	}

	/**
	 * Heal auctions still stuck in payment_pending when WooCommerce already shows paid.
	 */
	public function reconcile_for_holder( int $holder_id ): void {
		global $wpdb;

		$state_t = Config::table( Config::TABLE_AUCTION_STATE );
		$award_t = Config::table( Config::TABLE_AWARDS );
		$rows    = QueryCache::remember(
			QueryCache::key( 'holder_reconcile', $holder_id ),
			30,
			static function () use ( $wpdb, $state_t, $award_t, $holder_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT a.id AS award_id, a.order_id, st.auction_id, st.state, st.order_id AS state_order_id
						FROM %i st
						INNER JOIN %i a ON a.auction_id = st.auction_id
						WHERE st.holder_id = %d
							AND st.state IN (%s, %s)
						ORDER BY st.auction_id DESC
						LIMIT 40',
						$state_t,
						$award_t,
						$holder_id,
						'payment_pending',
						'sold_payment_pending'
					),
					ARRAY_A
				);
			}
		);

		if ( ! is_array( $rows ) || ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$observer = new \LogicanvasAuctions\Infrastructure\WooCommerce\OrderObserver();
		foreach ( $rows as $row ) {
			$order_id = (int) ( $row['order_id'] ?: $row['state_order_id'] ?: 0 );
			if ( $order_id < 1 ) {
				$order_id = $this->find_order_id_for_auction( (int) $row['auction_id'] );
			}
			if ( $order_id < 1 ) {
				continue;
			}
			$order = wc_get_order( $order_id );
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			if ( $order->has_status( array( 'processing', 'completed' ) ) || $order->is_paid() ) {
				$observer->sync_paid_order( $order );
			}
		}
	}

	private function find_order_id_for_auction( int $auction_id ): int {
		if ( $auction_id < 1 ) {
			return 0;
		}

		global $wpdb;

		$award_t  = Config::table( Config::TABLE_AWARDS );
		$order_id = (int) QueryCache::remember(
			QueryCache::key( 'award_order', $auction_id ),
			60,
			static function () use ( $wpdb, $award_t, $auction_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_var(
					$wpdb->prepare(
						'SELECT order_id FROM %i WHERE auction_id = %d AND order_id > 0 ORDER BY id DESC LIMIT 1',
						$award_t,
						$auction_id
					)
				);
			}
		);
		if ( $order_id > 0 ) {
			return $order_id;
		}

		$state_t  = Config::table( Config::TABLE_AUCTION_STATE );
		$order_id = (int) QueryCache::remember(
			QueryCache::key( 'state_order', $auction_id ),
			60,
			static function () use ( $wpdb, $state_t, $auction_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_var(
					$wpdb->prepare(
						'SELECT order_id FROM %i WHERE auction_id = %d AND order_id > 0 LIMIT 1',
						$state_t,
						$auction_id
					)
				);
			}
		);
		if ( $order_id > 0 ) {
			return $order_id;
		}

		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}

		$product_id = (int) get_post_meta( $auction_id, 'wcap_product_id', true );
		if ( $product_id < 1 ) {
			return 0;
		}

		$orders = wc_get_orders(
			array(
				'limit'   => 10,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'objects',
			)
		);
		if ( ! is_array( $orders ) ) {
			return 0;
		}
		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			foreach ( $order->get_items() as $item ) {
				if ( (int) $item->get_product_id() === $product_id ) {
					return $order->get_id();
				}
			}
		}

		return 0;
	}

	/**
	 * @return array{number:string,carrier:string,url:string,note:string}
	 */
	public function tracking_from_order( ?WC_Order $order ): array {
		if ( ! $order ) {
			return array(
				'number'  => '',
				'carrier' => '',
				'url'     => '',
				'note'    => '',
			);
		}

		return array(
			'number'  => (string) $order->get_meta( self::META_TRACKING_NUMBER ),
			'carrier' => (string) $order->get_meta( self::META_TRACKING_CARRIER ),
			'url'     => (string) $order->get_meta( self::META_TRACKING_URL ),
			'note'    => (string) $order->get_meta( self::META_FULFILMENT_NOTE ),
		);
	}

	/**
	 * Buyer-facing award enrichment.
	 *
	 * @return array<string, mixed>
	 */
	public function award_order_fields( int $order_id ): array {
		$order = $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		$track = $this->tracking_from_order( $order instanceof WC_Order ? $order : null );
		$pay   = $this->payment_snapshot( $order instanceof WC_Order ? $order : null );

		return array(
			'order_id'           => $order_id,
			'order_number'       => $order instanceof WC_Order ? $order->get_order_number() : '',
			'order_status'       => $order instanceof WC_Order ? $order->get_status() : '',
			'order_status_label' => $order instanceof WC_Order ? wc_get_order_status_name( $order->get_status() ) : '',
			'payment'            => $pay,
			'tracking_number'    => $track['number'],
			'tracking_carrier'   => $track['carrier'],
			'tracking_url'       => $track['url'],
			'fulfilment_note'    => $track['note'],
			'view_order_url'     => $order instanceof WC_Order ? $order->get_view_order_url() : '',
		);
	}
}
