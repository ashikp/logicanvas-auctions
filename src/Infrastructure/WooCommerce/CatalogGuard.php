<?php
/**
 * Disable WooCommerce shop/catalog and single product pages.
 *
 * Checkout, cart, account, and winner payment remain on WooCommerce.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\WooCommerce;

use LogicanvasAuctions\Admin\Settings;
use LogicanvasAuctions\Frontend\PluginPages;

final class CatalogGuard {

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'redirect' ), 1 );
		add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 99 );
		add_filter( 'woocommerce_product_is_visible', array( $this, 'hide_from_catalog' ), 20, 2 );
		add_filter( 'wp_sitemaps_post_types', array( $this, 'exclude_product_sitemap' ) );
	}

	public function maybe_flush_rewrites(): void {
		if ( ! get_transient( 'wcap_flush_rewrites' ) ) {
			return;
		}

		delete_transient( 'wcap_flush_rewrites' );
		flush_rewrite_rules( false );
	}

	public function redirect(): void {
		if ( ! $this->enabled() || is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( ! function_exists( 'is_woocommerce' ) ) {
			return;
		}

		if ( $this->is_checkout_flow() ) {
			return;
		}

		$target = $this->redirect_target();
		if ( '' === $target ) {
			return;
		}

		$current = home_url( esc_url_raw( wp_unslash( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ) ) ) );
		if ( untrailingslashit( $target ) === untrailingslashit( $current ) ) {
			return;
		}

		wp_safe_redirect( $target, 302 );
		exit;
	}

	/**
	 * @param mixed $visible Visible flag.
	 * @param mixed $product_id Product ID.
	 */
	public function hide_from_catalog( $visible, $product_id ): bool {
		unset( $product_id );

		if ( $this->enabled() ) {
			return false;
		}

		return (bool) $visible;
	}

	/**
	 * @param mixed $post_types
	 * @return array<string, mixed>
	 */
	public function exclude_product_sitemap( $post_types ): array {
		if ( ! is_array( $post_types ) ) {
			return array();
		}

		if ( $this->enabled() ) {
			unset( $post_types['product'] );
		}

		return $post_types;
	}

	private function enabled(): bool {
		return ! empty( Settings::get()['disable_wc_catalog'] );
	}

	private function is_checkout_flow(): bool {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return true;
		}
		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return true;
		}
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url() ) {
			return true;
		}

		return false;
	}

	private function redirect_target(): string {
		if ( function_exists( 'is_product' ) && is_product() ) {
			return $this->product_url( (int) get_queried_object_id() );
		}

		if ( ( function_exists( 'is_shop' ) && is_shop() )
			|| ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() )
			|| is_post_type_archive( 'product' )
		) {
			return $this->archive_url();
		}

		if ( function_exists( 'wc_get_page_id' ) ) {
			$shop_id = (int) wc_get_page_id( 'shop' );
			if ( $shop_id > 0 && is_page( $shop_id ) ) {
				return $this->archive_url();
			}
		}

		return '';
	}

	private function product_url( int $product_id ): string {
		if ( $product_id > 0 ) {
			$auction_id = (int) get_post_meta( $product_id, '_wcap_auction_id', true );
			if ( $auction_id > 0 ) {
				$permalink = get_permalink( $auction_id );
				if ( is_string( $permalink ) && '' !== $permalink ) {
					return $permalink;
				}
			}
		}

		return $this->archive_url();
	}

	private function archive_url(): string {
		$archive = PluginPages::url( 'archive' );
		if ( '' !== $archive ) {
			return $archive;
		}

		return home_url( '/' );
	}
}
