<?php
/**
 * Locked winner checkout through WooCommerce.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\WooCommerce;

use LogicanvasAuctions\Admin\Settings;
use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Award\AwardService;
use LogicanvasAuctions\Domain\Settlement\SettlementService;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository;
use WC_Cart;

final class CheckoutLock {

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'handle_form_start' ), 5 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_item_meta' ), 10, 4 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'add_order_meta' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'add_order_meta_from_order' ), 10, 1 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add' ), 10, 3 );
		add_filter( 'woocommerce_update_cart_validation', array( $this, 'block_qty' ), 10, 4 );
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'maybe_block_coupons' ), 10, 1 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'lock_cart_prices' ), 999 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'restore_cart_item' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'keep_cart_item_data' ), 10, 1 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'link_award_after_checkout' ), 20, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'link_award_after_checkout_order' ), 20, 1 );
	}

	/**
	 * Frontend form POST (reliable cart cookies). Prefer this over REST for checkout start.
	 */
	public function handle_form_start(): void {
		if ( empty( $_POST['wcap_start_checkout'] ) ) {
			return;
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ), 'wcap_start_checkout' ) ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Security check failed. Please try again.', 'logicanvas-auctions' ), 'error' );
			}
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$award_id = isset( $_POST['award_id'] ) ? absint( $_POST['award_id'] ) : 0;
		$result   = $this->start_for_winner( $award_id, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( $result->get_error_message(), 'error' );
			}
			return;
		}

		wp_safe_redirect( (string) $result );
		exit;
	}

	/**
	 * @return string|\WP_Error
	 */
	public function start_for_winner( int $award_id, int $user_id ) {
		try {
			if ( ! function_exists( 'WC' ) || ! class_exists( 'WooCommerce' ) ) {
				return new \WP_Error( 'woocommerce', __( 'WooCommerce is required for checkout.', 'logicanvas-auctions' ) );
			}

			$this->ensure_cart();

			$awards = new AwardService();
			$award  = $awards->find( $award_id );
			if ( ! $award ) {
				return new \WP_Error( 'not_found', __( 'Award not found.', 'logicanvas-auctions' ) );
			}

			if ( (int) $award['winner_id'] !== $user_id ) {
				return new \WP_Error( 'forbidden', __( 'Only the winner can pay for this auction.', 'logicanvas-auctions' ) );
			}

			if ( AwardService::PENDING !== $award['status'] ) {
				return new \WP_Error( 'invalid', __( 'This award cannot be paid.', 'logicanvas-auctions' ) );
			}

			if ( ! empty( $award['payment_deadline_utc'] ) && strtotime( $award['payment_deadline_utc'] . ' UTC' ) < time() ) {
				return new \WP_Error( 'expired', __( 'The payment deadline has passed.', 'logicanvas-auctions' ) );
			}

			$auction = ( new WpdbAuctionRepository() )->find( (int) $award['auction_id'] );
			if ( ! $auction ) {
				return new \WP_Error( 'not_found', __( 'Auction not found.', 'logicanvas-auctions' ) );
			}

			if ( ! Compatibility::currencies_compatible( $auction->currency() ) ) {
				return new \WP_Error( 'currency', __( 'Store currency does not match this auction.', 'logicanvas-auctions' ) );
			}

			if ( ! empty( $award['order_id'] ) ) {
				$order = wc_get_order( (int) $award['order_id'] );
				if ( $order && (int) $order->get_user_id() === $user_id ) {
					return $order->get_checkout_payment_url();
				}
			}

			$product_id = $this->ensure_product( $auction, (string) $award['amount'] );
			if ( $product_id < 1 ) {
				return new \WP_Error( 'product', __( 'No WooCommerce product is linked to this auction. Ask an administrator to re-sync the listing.', 'logicanvas-auctions' ) );
			}

			$cart = WC()->cart;
			if ( ! $cart ) {
				return new \WP_Error( 'cart', __( 'Unable to start checkout. Cart could not be loaded.', 'logicanvas-auctions' ) );
			}

			$settings = Settings::get();
			if ( empty( $settings['allow_mixed_cart'] ) ) {
				$cart->empty_cart();
			}

			( new ProductSync() )->lock_price( $product_id, (string) $award['amount'] );
			$this->restock_for_checkout( $product_id );

			$added = $cart->add_to_cart(
				$product_id,
				1,
				0,
				array(),
				array(
					'wcap_award_id'   => $award_id,
					'wcap_auction_id' => $auction->id(),
					'wcap_locked_amt' => (string) $award['amount'],
				)
			);

			if ( ! $added ) {
				$notices = function_exists( 'wc_get_notices' ) ? wc_get_notices( 'error' ) : array();
				$detail  = '';
				if ( is_array( $notices ) && ! empty( $notices[0]['notice'] ) ) {
					$detail = ' ' . wp_strip_all_tags( (string) $notices[0]['notice'] );
				}

				return new \WP_Error( 'cart', __( 'Unable to start checkout.', 'logicanvas-auctions' ) . $detail );
			}

			$url = wc_get_checkout_url();
			$key = 'checkout-' . $award_id . '-' . $user_id;
			global $wpdb;
			$wpdb->replace(
				Config::table( Config::TABLE_IDEMPOTENCY ),
				array(
					'scope'           => 'checkout',
					'idempotency_key' => $key,
					'payload'         => wp_json_encode( array( 'url' => $url ) ),
					'created_at_utc'  => gmdate( 'Y-m-d H:i:s' ),
				)
			);

			do_action( 'wcap_winner_checkout_initialized', $auction->id(), $award_id, $user_id );

			return $url;
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'checkout_fatal',
				sprintf(
					/* translators: %s: error message */
					__( 'Checkout could not start: %s', 'logicanvas-auctions' ),
					$e->getMessage()
				)
			);
		}
	}

	private function ensure_cart(): void {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		if ( WC()->session && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
	}

	/**
	 * @param \LogicanvasAuctions\Domain\Auction\Auction $auction Auction.
	 */
	private function ensure_product( $auction, string $amount ): int {
		$product_id = (int) $auction->product_id();
		if ( $product_id > 0 && function_exists( 'wc_get_product' ) && wc_get_product( $product_id ) ) {
			return $product_id;
		}

		$meta_id = (int) get_post_meta( $auction->id(), 'wcap_product_id', true );
		if ( $meta_id > 0 && function_exists( 'wc_get_product' ) && wc_get_product( $meta_id ) ) {
			( new WpdbAuctionRepository() )->update_state(
				$auction->id(),
				array(
					'product_id'     => $meta_id,
					'updated_at_utc' => gmdate( 'Y-m-d H:i:s' ),
				)
			);
			return $meta_id;
		}

		$post = get_post( $auction->id() );
		$id   = ( new ProductSync() )->create_or_update_product(
			$auction->id(),
			array(
				'title'       => $post ? $post->post_title : 'Auction #' . $auction->id(),
				'description' => $post ? $post->post_content : '',
				'short'       => $post ? $post->post_excerpt : '',
				'price'       => $amount,
				'fulfilment'  => (string) ( $auction->to_array()['fulfilment_type'] ?? 'shipping' ),
			)
		);

		if ( $id > 0 ) {
			( new WpdbAuctionRepository() )->update_state(
				$auction->id(),
				array(
					'product_id'     => $id,
					'updated_at_utc' => gmdate( 'Y-m-d H:i:s' ),
				)
			);
		}

		return $id;
	}

	private function restock_for_checkout( int $product_id ): void {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product ) {
			return;
		}

		$product->set_catalog_visibility( 'hidden' );
		$product->set_sold_individually( true );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 1 );
		$product->set_stock_status( 'instock' );
		$product->update_meta_data( '_wcap_is_auction_product', 'yes' );
		$product->save();
	}

	/**
	 * @param mixed $passed Whether add-to-cart may continue.
	 * @param mixed $product_id Product ID.
	 * @param mixed $qty Quantity.
	 */
	public function validate_add( $passed, $product_id, $qty ): bool {
		$passed     = (bool) $passed;
		$product_id = (int) $product_id;
		$qty        = (int) $qty;

		if ( 'yes' !== get_post_meta( $product_id, '_wcap_is_auction_product', true ) ) {
			return $passed;
		}

		$auction_id = (int) get_post_meta( $product_id, '_wcap_auction_id', true );
		if ( $auction_id < 1 ) {
			wc_add_notice( __( 'This auction product is missing its auction link.', 'logicanvas-auctions' ), 'error' );
			return false;
		}

		$award   = ( new AwardService() )->for_auction( $auction_id );
		$user_id = get_current_user_id();

		if ( ! $award || (int) $award['winner_id'] !== $user_id ) {
			wc_add_notice( __( 'This product can only be purchased by the auction winner.', 'logicanvas-auctions' ), 'error' );
			return false;
		}

		if ( AwardService::PENDING !== (string) ( $award['status'] ?? '' ) ) {
			wc_add_notice( __( 'This award cannot be paid.', 'logicanvas-auctions' ), 'error' );
			return false;
		}

		if ( $qty !== 1 ) {
			wc_add_notice( __( 'Auction quantity is locked to one.', 'logicanvas-auctions' ), 'error' );
			return false;
		}

		return $passed;
	}

	/**
	 * @param mixed                $passed Whether quantity change may continue.
	 * @param mixed                $cart_item_key Cart item key.
	 * @param mixed                $values Cart item.
	 * @param mixed                $quantity Quantity.
	 */
	public function block_qty( $passed, $cart_item_key, $values, $quantity ): bool {
		unset( $cart_item_key );
		$passed   = (bool) $passed;
		$quantity = (int) $quantity;
		$values   = is_array( $values ) ? $values : array();

		if ( ! empty( $values['wcap_award_id'] ) && 1 !== $quantity ) {
			wc_add_notice( __( 'Auction quantity cannot be changed.', 'logicanvas-auctions' ), 'error' );
			return false;
		}

		return $passed;
	}

	/**
	 * @param mixed $valid Coupon valid flag.
	 */
	public function maybe_block_coupons( $valid ): bool {
		$valid    = (bool) $valid;
		$settings = Settings::get();
		if ( ! empty( $settings['allow_coupons_on_auction'] ) ) {
			return $valid;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $valid;
		}

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item['wcap_award_id'] ) ) {
				return false;
			}
		}

		return $valid;
	}

	/**
	 * @param mixed $cart Cart.
	 */
	public function lock_cart_prices( $cart ): void {
		if ( ! $cart instanceof WC_Cart ) {
			return;
		}

		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item['wcap_locked_amt'] ) || empty( $item['data'] ) ) {
				continue;
			}
			$item['data']->set_price( (string) $item['wcap_locked_amt'] );
		}
	}

	/**
	 * @param mixed                $item Line item.
	 * @param mixed                $cart_item_key Cart key.
	 * @param array<string, mixed> $values Cart values.
	 * @param mixed                $order Order.
	 */
	public function add_item_meta( $item, $cart_item_key, $values, $order ): void {
		unset( $cart_item_key, $order );
		$values = is_array( $values ) ? $values : array();
		if ( ! is_object( $item ) || ! method_exists( $item, 'add_meta_data' ) ) {
			return;
		}

		$award_id   = (int) ( $values['wcap_award_id'] ?? 0 );
		$auction_id = (int) ( $values['wcap_auction_id'] ?? 0 );

		if ( $auction_id < 1 && method_exists( $item, 'get_product_id' ) ) {
			$auction_id = (int) get_post_meta( (int) $item->get_product_id(), '_wcap_auction_id', true );
		}
		if ( $award_id < 1 && $auction_id > 0 ) {
			$award    = ( new AwardService() )->for_auction( $auction_id );
			$award_id = $award ? (int) $award['id'] : 0;
		}

		if ( $award_id < 1 && $auction_id < 1 ) {
			return;
		}

		if ( $award_id > 0 ) {
			$item->add_meta_data( '_wcap_award_id', $award_id, true );
		}
		if ( $auction_id > 0 ) {
			$item->add_meta_data( '_wcap_auction_id', $auction_id, true );
			$item->add_meta_data( __( 'Auction', 'logicanvas-auctions' ), '#' . $auction_id );
		}
	}

	/**
	 * @param mixed $order Order.
	 * @param mixed $data Checkout data.
	 */
	public function add_order_meta( $order, $data ): void {
		unset( $data );
		$this->add_order_meta_from_order( $order );
	}

	/**
	 * @param mixed $order Order.
	 */
	public function add_order_meta_from_order( $order ): void {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
			return;
		}

		$auction_id = 0;
		$award_id   = 0;

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

		if ( $award_id < 1 && $auction_id > 0 ) {
			$award    = ( new AwardService() )->for_auction( $auction_id );
			$award_id = $award ? (int) $award['id'] : 0;
		}

		if ( $auction_id > 0 && method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( '_wcap_auction_id', $auction_id );
		}
		if ( $award_id > 0 && method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( '_wcap_award_id', $award_id );
		}
	}

	/**
	 * Keep auction cart extras when the cart is rebuilt from the session.
	 *
	 * @param array<string, mixed> $cart_item Cart item.
	 * @param array<string, mixed> $values Session values.
	 * @return array<string, mixed>
	 */
	public function restore_cart_item( $cart_item, $values ) {
		$cart_item = is_array( $cart_item ) ? $cart_item : array();
		$values    = is_array( $values ) ? $values : array();

		foreach ( array( 'wcap_award_id', 'wcap_auction_id', 'wcap_locked_amt' ) as $key ) {
			if ( isset( $values[ $key ] ) ) {
				$cart_item[ $key ] = $values[ $key ];
			}
		}

		return $cart_item;
	}

	/**
	 * @param array<string, mixed> $cart_item_data Cart item data.
	 * @return array<string, mixed>
	 */
	public function keep_cart_item_data( $cart_item_data ) {
		return is_array( $cart_item_data ) ? $cart_item_data : array();
	}

	/**
	 * @param mixed                $order_id Order ID.
	 * @param array<string, mixed> $posted Posted data.
	 * @param mixed                $order Order.
	 */
	public function link_award_after_checkout( $order_id, $posted, $order ): void {
		unset( $posted );
		$order = $order instanceof \WC_Order ? $order : ( function_exists( 'wc_get_order' ) ? wc_get_order( (int) $order_id ) : null );
		if ( $order instanceof \WC_Order ) {
			$this->link_award_order( $order );
		}
	}

	/**
	 * @param mixed $order Order.
	 */
	public function link_award_after_checkout_order( $order ): void {
		if ( $order instanceof \WC_Order ) {
			$this->link_award_order( $order );
		}
	}

	private function link_award_order( \WC_Order $order ): void {
		$this->add_order_meta_from_order( $order );
		$order->save();

		$award_id   = (int) $order->get_meta( '_wcap_award_id' );
		$auction_id = (int) $order->get_meta( '_wcap_auction_id' );
		if ( $award_id < 1 && $auction_id > 0 ) {
			$award    = ( new AwardService() )->for_auction( $auction_id );
			$award_id = $award ? (int) $award['id'] : 0;
			if ( $award_id > 0 ) {
				$order->update_meta_data( '_wcap_award_id', $award_id );
				$order->save();
			}
		}

		if ( $award_id < 1 ) {
			return;
		}

		global $wpdb;
		$wpdb->update(
			Config::table( Config::TABLE_AWARDS ),
			array(
				'order_id'       => $order->get_id(),
				'updated_at_utc' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $award_id )
		);

		if ( $auction_id > 0 ) {
			QueryCache::bust_auction( $auction_id );
		} else {
			QueryCache::flush_group();
		}

		if ( $auction_id > 0 ) {
			( new \LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository() )->update_state(
				$auction_id,
				array(
					'order_id'       => $order->get_id(),
					'updated_at_utc' => gmdate( 'Y-m-d H:i:s' ),
				)
			);
			( new SettlementService() )->mark_order_paid( $auction_id, $order->get_id() );
			// mark_order_paid only sets order_id on settlement — rename usage is OK for linking.
		}

		if ( $order->has_status( array( 'processing', 'completed' ) ) || $order->is_paid() ) {
			( new OrderObserver() )->sync_paid_order( $order );
		}
	}
}
