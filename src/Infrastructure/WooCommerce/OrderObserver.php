<?php
/**
 * WooCommerce order status observer.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\WooCommerce;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Award\AwardService;
use LogicanvasAuctions\Domain\Settlement\SettlementService;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;
use WC_Order;

final class OrderObserver {

	public function register(): void {
		$lock = new CheckoutLock();
		$lock->register();

		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status' ), 10, 4 );
		add_action( 'woocommerce_payment_complete', array( $this, 'on_payment_complete' ), 20, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'on_paid_status' ), 20, 2 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_paid_status' ), 20, 2 );
		add_action( 'woocommerce_order_refunded', array( $this, 'on_refund' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'admin_notice' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'frontend_tracking' ) );
	}

	/**
	 * @param mixed  $order_id Order ID.
	 * @param mixed  $from Previous status.
	 * @param mixed  $to New status.
	 * @param mixed  $order Order object.
	 */
	public function on_status( $order_id, $from, $to, $order ): void {
		unset( $from );
		$order = $this->resolve_order( $order_id, $order );
		if ( ! $order ) {
			return;
		}

		$to = sanitize_key( (string) $to );
		if ( in_array( $to, array( 'processing', 'completed' ), true ) ) {
			$this->sync_paid_order( $order );
			return;
		}

		if ( in_array( $to, array( 'cancelled', 'failed' ), true ) ) {
			$ids = $this->resolve_auction_award( $order );
			if ( $ids['auction_id'] > 0 ) {
				do_action( 'wcap_payment_failed', $ids['auction_id'], $order->get_id(), $to );
			}
		}
	}

	/**
	 * @param mixed $order_id Order ID.
	 */
	public function on_payment_complete( $order_id ): void {
		$order = $this->resolve_order( $order_id, null );
		if ( $order ) {
			$this->sync_paid_order( $order );
		}
	}

	/**
	 * @param mixed $order_id Order ID.
	 * @param mixed $order Order.
	 */
	public function on_paid_status( $order_id, $order = null ): void {
		$order = $this->resolve_order( $order_id, $order );
		if ( $order ) {
			$this->sync_paid_order( $order );
		}
	}

	/**
	 * Keep auction/award state aligned with a paid WooCommerce order.
	 */
	public function sync_paid_order( WC_Order $order ): void {
		$ids = $this->resolve_auction_award( $order );
		$auction_id = $ids['auction_id'];
		$award_id   = $ids['award_id'];

		if ( $auction_id < 1 && $award_id < 1 ) {
			return;
		}

		if ( $award_id < 1 && $auction_id > 0 ) {
			$award = ( new AwardService() )->for_auction( $auction_id );
			$award_id = $award ? (int) $award['id'] : 0;
		}

		if ( $award_id < 1 ) {
			return;
		}

		if ( $auction_id < 1 ) {
			$award = ( new AwardService() )->find( $award_id );
			$auction_id = $award ? (int) $award['auction_id'] : 0;
		}

		if ( $auction_id < 1 ) {
			return;
		}

		// Persist meta so later hooks and admin screens stay linked.
		if ( (int) $order->get_meta( '_wcap_auction_id' ) !== $auction_id ) {
			$order->update_meta_data( '_wcap_auction_id', $auction_id );
		}
		if ( (int) $order->get_meta( '_wcap_award_id' ) !== $award_id ) {
			$order->update_meta_data( '_wcap_award_id', $award_id );
		}
		$order->save();

		$key = 'order-paid-' . $order->get_id() . '-' . $award_id;
		if ( ! $this->once( $key ) ) {
			// Still re-run mark_paid if auction is stuck in payment_pending (heal).
			$award = ( new AwardService() )->find( $award_id );
			if ( $award && AwardService::PAID === (string) $award['status'] ) {
				$state = ( new \LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository() )->find( $auction_id );
				if ( $state && ! in_array( $state->state(), array( 'payment_pending', 'sold_payment_pending' ), true ) ) {
					return;
				}
			}
		}

		( new AwardService() )->mark_paid( $award_id, $order->get_id() );
		do_action( 'wcap_order_linked', $auction_id, $order->get_id(), $award_id );
		do_action( 'wcap_payment_completed', $auction_id, $order->get_id() );
	}

	/**
	 * @param mixed $order_id Order ID.
	 * @param mixed $refund_id Refund ID.
	 */
	public function on_refund( $order_id, $refund_id ): void {
		$order_id  = (int) $order_id;
		$refund_id = (int) $refund_id;
		$order     = $this->resolve_order( $order_id, null );
		if ( ! $order ) {
			return;
		}

		$ids = $this->resolve_auction_award( $order );
		if ( $ids['auction_id'] < 1 ) {
			return;
		}

		$refund = wc_get_order( $refund_id );
		$amount = $refund ? (string) $refund->get_amount() : (string) $order->get_total();
		( new SettlementService() )->apply_refund( $ids['auction_id'], $amount );
	}

	/**
	 * @param mixed $order_id Order ID.
	 * @param mixed $order Order candidate.
	 */
	private function resolve_order( $order_id, $order ): ?WC_Order {
		if ( $order instanceof WC_Order ) {
			return $order;
		}

		$id = (int) $order_id;
		if ( $id < 1 && is_numeric( $order ) ) {
			$id = (int) $order;
		}

		if ( $id < 1 || ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		$loaded = wc_get_order( $id );
		return $loaded instanceof WC_Order ? $loaded : null;
	}

	/**
	 * @return array{auction_id:int,award_id:int}
	 */
	private function resolve_auction_award( WC_Order $order ): array {
		$auction_id = (int) $order->get_meta( '_wcap_auction_id' );
		$award_id   = (int) $order->get_meta( '_wcap_award_id' );

		if ( $auction_id > 0 && $award_id > 0 ) {
			return compact( 'auction_id', 'award_id' );
		}

		foreach ( $order->get_items() as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta' ) ) {
				continue;
			}
			if ( $auction_id < 1 ) {
				$auction_id = (int) $item->get_meta( '_wcap_auction_id' );
			}
			if ( $award_id < 1 ) {
				$award_id = (int) $item->get_meta( '_wcap_award_id' );
			}
			if ( $auction_id < 1 && method_exists( $item, 'get_product_id' ) ) {
				$auction_id = (int) get_post_meta( (int) $item->get_product_id(), '_wcap_auction_id', true );
			}
		}

		if ( ( $auction_id < 1 || $award_id < 1 ) && function_exists( 'wc_get_order_item_meta' ) ) {
			global $wpdb;
			$table = Config::table( Config::TABLE_SETTLEMENTS );
			$oid   = $order->get_id();
			$row   = QueryCache::remember(
				QueryCache::key( 'settlement_order', $oid ),
				60,
				static function () use ( $wpdb, $table, $oid ) {
					wp_cache_get( 'wcap_db', QueryCache::GROUP );
					return $wpdb->get_row(
						$wpdb->prepare(
							'SELECT auction_id, award_id FROM %i WHERE order_id = %d LIMIT 1',
							$table,
							$oid
						),
						ARRAY_A
					);
				}
			);
			if ( is_array( $row ) ) {
				if ( $auction_id < 1 ) {
					$auction_id = (int) $row['auction_id'];
				}
				if ( $award_id < 1 ) {
					$award_id = (int) $row['award_id'];
				}
			}
		}

		if ( $award_id < 1 && $auction_id > 0 ) {
			$award = ( new AwardService() )->for_auction( $auction_id );
			$award_id = $award ? (int) $award['id'] : 0;
		}

		return array(
			'auction_id' => $auction_id,
			'award_id'   => $award_id,
		);
	}

	private function once( string $key ): bool {
		// add_option() is atomic: only the first INSERT wins.
		return \LogicanvasAuctions\Infrastructure\Database\OptionLock::claim_once( 'wcap_idemp_' . md5( $key ) );
	}

	/**
	 * @param mixed $order Order.
	 */
	public function admin_notice( $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$ids = $this->resolve_auction_award( $order );
		if ( $ids['auction_id'] < 1 ) {
			return;
		}

		$mode = \LogicanvasAuctions\Domain\Order\OrderManagement::mode();
		$labels = array(
			'admin'  => __( 'Administrators only', 'logicanvas-auctions' ),
			'seller' => __( 'Seller / auction holder only', 'logicanvas-auctions' ),
			'both'   => __( 'Admin and seller', 'logicanvas-auctions' ),
		);

		$track = ( new \LogicanvasAuctions\Domain\Order\HolderOrderService() )->tracking_from_order( $order );

		echo '<p class="form-field form-field-wide"><strong>' . esc_html__( 'Logicanvas Auctions for WooCommerce', 'logicanvas-auctions' ) . '</strong><br />';
		echo esc_html(
			sprintf(
				/* translators: 1: auction id, 2: who manages */
				__( 'Auction #%1$d · Orders managed by: %2$s', 'logicanvas-auctions' ),
				$ids['auction_id'],
				$labels[ $mode ] ?? $mode
			)
		);
		if ( $track['number'] ) {
			echo '<br />' . esc_html__( 'Tracking:', 'logicanvas-auctions' ) . ' ' . esc_html( $track['number'] );
			if ( $track['carrier'] ) {
				echo ' (' . esc_html( $track['carrier'] ) . ')';
			}
		}
		echo '</p>';
	}

	/**
	 * @param mixed $order Order.
	 */
	public function frontend_tracking( $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$ids = $this->resolve_auction_award( $order );
		if ( $ids['auction_id'] < 1 ) {
			return;
		}

		$track = ( new \LogicanvasAuctions\Domain\Order\HolderOrderService() )->tracking_from_order( $order );
		if ( '' === $track['number'] && '' === $track['note'] ) {
			return;
		}

		echo '<section class="wcap-order-tracking"><h2>' . esc_html__( 'Shipping & tracking', 'logicanvas-auctions' ) . '</h2>';
		if ( $track['number'] ) {
			echo '<p><strong>' . esc_html( $track['number'] ) . '</strong>';
			if ( $track['carrier'] ) {
				echo ' · ' . esc_html( $track['carrier'] );
			}
			if ( $track['url'] ) {
				echo ' · <a href="' . esc_url( $track['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Track shipment', 'logicanvas-auctions' ) . '</a>';
			}
			echo '</p>';
		}
		if ( $track['note'] ) {
			echo '<p>' . esc_html( $track['note'] ) . '</p>';
		}
		echo '</section>';
	}
}
