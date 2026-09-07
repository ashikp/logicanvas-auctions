<?php
/**
 * WooCommerce product mirror and existing-product attachment for an auction lot.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\WooCommerce;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Award\AwardService;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;
use WP_Error;

final class ProductSync {

	/**
	 * @param array<string, mixed> $data
	 */
	public function create_or_update_product( int $auction_id, array $data ): int {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return 0;
		}

		try {
			$existing = (int) get_post_meta( $auction_id, 'wcap_product_id', true );
			$product  = $existing && function_exists( 'wc_get_product' ) ? wc_get_product( $existing ) : null;

			if ( ! $product ) {
				$product = new \WC_Product_Simple();
				$product->set_status( 'publish' );
			}

			$price = wc_format_decimal( (string) ( $data['price'] ?? '0' ) );
			$price = is_numeric( $price ) ? (string) $price : '0';

			$product->set_name( (string) $data['title'] );
			$product->set_description( wp_kses_post( (string) ( $data['description'] ?? '' ) ) );
			$product->set_short_description( wp_kses_post( (string) ( $data['short'] ?? '' ) ) );
			$product->set_regular_price( $price );
			$product->set_price( $price );
			$product->set_catalog_visibility( 'hidden' );
			$product->set_sold_individually( true );
			$product->set_manage_stock( true );
			$product->set_stock_quantity( 1 );
			$product->set_stock_status( 'instock' );
			$product->set_virtual( 'digital' === ( $data['fulfilment'] ?? '' ) );
			$product->update_meta_data( '_wcap_auction_id', $auction_id );
			$product->update_meta_data( '_wcap_is_auction_product', 'yes' );
			$product->update_meta_data( '_wcap_created_for_auction', 'yes' );

			if ( ! empty( $data['sku'] ) ) {
				try {
					$product->set_sku( (string) $data['sku'] );
				} catch ( \Throwable $e ) {
					unset( $e );
				}
			}
			if ( ! empty( $data['tax_class'] ) ) {
				$product->set_tax_class( (string) $data['tax_class'] );
			}
			if ( ! empty( $data['shipping_class'] ) ) {
				$term = get_term_by( 'slug', (string) $data['shipping_class'], 'product_shipping_class' );
				if ( $term ) {
					$product->set_shipping_class_id( (int) $term->term_id );
				}
			}

			$id = $product->save();
			update_post_meta( $auction_id, 'wcap_product_id', $id );
			update_post_meta( $auction_id, '_wcap_product_source', 'new' );

			return (int) $id;
		} catch ( \Throwable $e ) {
			return 0;
		}
	}

	/**
	 * @param int[] $gallery
	 */
	public function sync_images( int $product_id, int $featured_id, array $gallery ): void {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product ) {
			return;
		}

		if ( $featured_id > 0 ) {
			$product->set_image_id( $featured_id );
		}
		if ( $gallery ) {
			$product->set_gallery_image_ids( array_values( array_map( 'intval', $gallery ) ) );
		}
		$product->save();
	}

	/**
	 * @return int|WP_Error
	 */
	public function attach_existing( int $auction_id, int $product_id, int $user_id ) {
		if ( ! user_can( $user_id, 'manage_options' ) ) {
			return new WP_Error( 'forbidden', __( 'Only administrators can auction an existing WooCommerce product.', 'logicanvas-auctions' ) );
		}

		if ( $product_id < 1 ) {
			return new WP_Error( 'product_required', __( 'Select an existing WooCommerce product.', 'logicanvas-auctions' ) );
		}

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product instanceof \WC_Product ) {
			return new WP_Error( 'product_not_found', __( 'That WooCommerce product was not found.', 'logicanvas-auctions' ) );
		}

		if ( ! $this->user_can_use_product( $user_id, $product ) ) {
			return new WP_Error( 'product_forbidden', __( 'You cannot auction that product.', 'logicanvas-auctions' ) );
		}

		$busy = $this->open_auction_id_for_product( $product_id );
		if ( $busy && $busy !== $auction_id ) {
			return new WP_Error(
				'product_in_use',
				sprintf(
					/* translators: %d: auction ID */
					__( 'That product is already used by auction #%d.', 'logicanvas-auctions' ),
					$busy
				)
			);
		}

		if ( ! $product->get_meta( '_wcap_original_catalog_visibility' ) ) {
			$product->update_meta_data( '_wcap_original_catalog_visibility', $product->get_catalog_visibility() );
		}

		$product->set_catalog_visibility( 'hidden' );
		$product->set_sold_individually( true );
		$product->update_meta_data( '_wcap_auction_id', $auction_id );
		$product->update_meta_data( '_wcap_is_auction_product', 'yes' );
		$product->update_meta_data( '_wcap_existing_catalog_product', 'yes' );
		$product->save();

		update_post_meta( $auction_id, 'wcap_product_id', $product_id );
		update_post_meta( $auction_id, '_wcap_product_source', 'existing' );

		$thumb = get_post_thumbnail_id( $product_id );
		if ( $thumb && ! get_post_thumbnail_id( $auction_id ) ) {
			set_post_thumbnail( $auction_id, $thumb );
		}

		return $product_id;
	}

	public function user_can_use_product( int $user_id, object $product ): bool {
		unset( $product );

		return user_can( $user_id, 'manage_options' );
	}

	public function open_auction_id_for_product( int $product_id ): int {
		global $wpdb;

		$states = array(
			'draft',
			'pending_review',
			'scheduled',
			'active',
			'paused_admin',
			'lobby',
			'live',
			'paused',
			'going_once',
			'going_twice',
			'closing',
			'payment_pending',
			'sold_payment_pending',
		);
		$table = Config::table( Config::TABLE_AUCTION_STATE );

		return (int) QueryCache::remember(
			QueryCache::key( 'open_auction_product', $product_id ),
			45,
			static function () use ( $wpdb, $table, $product_id, $states ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_var(
					$wpdb->prepare(
						'SELECT auction_id FROM %i WHERE product_id = %d AND state IN (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s) LIMIT 1',
						$table,
						$product_id,
						...$states
					)
				);
			}
		);
	}

	/**
	 * @return array<int, array{id:int,name:string,sku:string,price:string,image:string,permalink:string}>
	 */
	public function search( string $term, int $user_id, int $limit = 20 ): array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$args = array(
			'status'  => array( 'publish', 'private' ),
			'limit'   => $limit,
			'orderby' => 'title',
			'order'   => 'ASC',
			'return'  => 'objects',
		);

		$term = trim( $term );
		if ( '' !== $term ) {
			$args['s'] = $term;
		}

		if ( ! user_can( $user_id, 'manage_woocommerce' ) && ! user_can( $user_id, 'manage_options' ) ) {
			$args['author'] = $user_id;
		}

		$products = wc_get_products( $args );
		$out      = array();

		foreach ( $products as $product ) {
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}
			if ( ! $this->user_can_use_product( $user_id, $product ) ) {
				continue;
			}

			$busy = $this->open_auction_id_for_product( $product->get_id() );
			$out[] = array(
				'id'        => $product->get_id(),
				'name'      => $product->get_name(),
				'sku'       => (string) $product->get_sku(),
				'price'     => (string) $product->get_regular_price(),
				'image'     => (string) get_the_post_thumbnail_url( $product->get_id(), 'thumbnail' ),
				'permalink' => (string) get_edit_post_link( $product->get_id(), 'raw' ),
				'in_use'    => $busy > 0,
			);
		}

		return $out;
	}

	public function lock_price( int $product_id, string $amount ): void {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		$product->set_regular_price( $amount );
		$product->set_sale_price( '' );
		$product->set_price( $amount );
		$product->save();
	}

	public function is_winner_checkout_product( \WC_Product $product ): bool {
		if ( 'yes' !== $product->get_meta( '_wcap_is_auction_product' ) ) {
			return true;
		}

		$auction_id = (int) $product->get_meta( '_wcap_auction_id' );
		if ( $auction_id < 1 ) {
			return false;
		}

		$award = ( new AwardService() )->for_auction( $auction_id );
		if ( ! $award || AwardService::PENDING !== $award['status'] ) {
			return false;
		}

		return (int) $award['winner_id'] === get_current_user_id();
	}
}
