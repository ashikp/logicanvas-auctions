<?php
/**
 * WooCommerce compatibility helpers and currency lock warnings.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\WooCommerce;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository;

final class Compatibility {

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'currency_warning' ) );
		add_filter( 'woocommerce_product_is_visible', array( $this, 'hide_auction_products' ), 10, 2 );
		add_filter( 'woocommerce_is_purchasable', array( $this, 'purchasable' ), 10, 2 );
		( new CatalogGuard() )->register();
	}

	public function currency_warning(): void {
		if ( ! current_user_can( Config::CAP_MANAGE_SETTINGS ) || ! function_exists( 'get_woocommerce_currency' ) ) {
			return;
		}

		$store = get_woocommerce_currency();
		$repo  = new WpdbAuctionRepository();
		$open  = $repo->query(
			array(
				'state'            => array( 'scheduled', 'active', 'live', 'lobby', 'payment_pending', 'sold_payment_pending' ),
				'include_unlisted' => true,
				'limit'            => 20,
			)
		);

		foreach ( $open as $auction ) {
			if ( $auction->currency() !== $store ) {
				echo '<div class="notice notice-warning"><p>';
				echo esc_html(
					sprintf(
						/* translators: 1: auction id, 2: auction currency, 3: store currency */
						__( 'Auction #%1$d is locked to %2$s but the store currency is now %3$s. Settlement is blocked until currencies match or the auction is cancelled.', 'logicanvas-auctions' ),
						$auction->id(),
						$auction->currency(),
						$store
					)
				);
				echo '</p></div>';
				break;
			}
		}
	}

	/**
	 * @param mixed $visible Visible flag.
	 * @param mixed $product_id Product ID.
	 */
	public function hide_auction_products( $visible, $product_id ): bool {
		if ( 'yes' === get_post_meta( (int) $product_id, '_wcap_is_auction_product', true ) ) {
			return false;
		}

		return (bool) $visible;
	}

	/**
	 * @param mixed $purchasable Purchasable flag.
	 * @param mixed $product Product.
	 */
	public function purchasable( $purchasable, $product ): bool {
		if ( ! $product instanceof \WC_Product ) {
			return (bool) $purchasable;
		}

		if ( 'yes' !== $product->get_meta( '_wcap_is_auction_product' ) ) {
			return (bool) $purchasable;
		}

		return ( new ProductSync() )->is_winner_checkout_product( $product );
	}

	public static function currencies_compatible( string $auction_currency ): bool {
		if ( ! function_exists( 'get_woocommerce_currency' ) ) {
			return true;
		}

		return $auction_currency === get_woocommerce_currency();
	}
}
