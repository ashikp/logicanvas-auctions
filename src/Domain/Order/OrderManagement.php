<?php
/**
 * Who may manage auction WooCommerce orders after a win.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Order;

use LogicanvasAuctions\Admin\Settings;
use LogicanvasAuctions\Config;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository;
use WC_Order;

final class OrderManagement {

	public const MODE_ADMIN  = 'admin';
	public const MODE_SELLER = 'seller';
	public const MODE_BOTH   = 'both';

	public static function mode(): string {
		$mode = (string) ( Settings::get()['order_managed_by'] ?? self::MODE_BOTH );
		return in_array( $mode, array( self::MODE_ADMIN, self::MODE_SELLER, self::MODE_BOTH ), true )
			? $mode
			: self::MODE_BOTH;
	}

	public static function seller_can_manage(): bool {
		return in_array( self::mode(), array( self::MODE_SELLER, self::MODE_BOTH ), true );
	}

	public static function admin_can_manage(): bool {
		return in_array( self::mode(), array( self::MODE_ADMIN, self::MODE_BOTH ), true );
	}

	/**
	 * Whether this user may update status/tracking for an auction order.
	 */
	public function user_can_manage_order( int $user_id, WC_Order $order ): bool {
		if ( $user_id < 1 ) {
			return false;
		}

		$is_shop_admin = user_can( $user_id, 'manage_woocommerce' ) || user_can( $user_id, 'manage_options' );
		$is_holder     = $this->is_order_holder( $user_id, $order );
		$mode          = self::mode();

		if ( self::MODE_ADMIN === $mode ) {
			return $is_shop_admin;
		}

		if ( self::MODE_SELLER === $mode ) {
			return $is_holder;
		}

		return $is_shop_admin || $is_holder;
	}

	public function is_order_holder( int $user_id, WC_Order $order ): bool {
		if ( ! user_can( $user_id, Config::CAP_CREATE_AUCTIONS ) ) {
			return false;
		}

		$auction_id = (int) $order->get_meta( '_wcap_auction_id' );
		if ( $auction_id < 1 ) {
			return false;
		}

		$auction = ( new WpdbAuctionRepository() )->find( $auction_id );
		return $auction && $auction->holder_id() === $user_id;
	}

	/**
	 * Statuses a seller may set (admins use full WooCommerce statuses).
	 *
	 * @return string[]
	 */
	public function allowed_seller_statuses(): array {
		$statuses = array( 'pending', 'on-hold', 'processing', 'completed', 'cancelled' );

		/**
		 * Filter seller-allowed WooCommerce order statuses for auction orders.
		 *
		 * @param string[] $statuses Status slugs without wc- prefix.
		 */
		return array_values( array_unique( (array) apply_filters( 'wcap_seller_allowed_order_statuses', $statuses ) ) );
	}

	/**
	 * @return array<string, string>
	 */
	public function status_labels(): array {
		if ( function_exists( 'wc_get_order_statuses' ) ) {
			$raw = wc_get_order_statuses();
			$out = array();
			foreach ( $raw as $key => $label ) {
				$slug         = str_replace( 'wc-', '', (string) $key );
				$out[ $slug ] = (string) $label;
			}
			return $out;
		}

		return array(
			'pending'    => __( 'Pending payment', 'logicanvas-auctions' ),
			'on-hold'    => __( 'On hold', 'logicanvas-auctions' ),
			'processing' => __( 'Processing', 'logicanvas-auctions' ),
			'completed'  => __( 'Completed', 'logicanvas-auctions' ),
			'cancelled'  => __( 'Cancelled', 'logicanvas-auctions' ),
		);
	}
}
