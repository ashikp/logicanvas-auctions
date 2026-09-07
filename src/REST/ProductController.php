<?php
/**
 * Product search for auction creation.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\REST;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Infrastructure\WooCommerce\ProductSync;
use WP_REST_Request;
use WP_REST_Response;

final class ProductController {

	public function search( WP_REST_Request $request ): WP_REST_Response {
		$term = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$list = ( new ProductSync() )->search( $term, get_current_user_id(), 20 );

		return new WP_REST_Response( $list );
	}

	public function get( WP_REST_Request $request ): WP_REST_Response {
		$id      = (int) $request['id'];
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
		if ( ! $product ) {
			return new WP_REST_Response( array(), 404 );
		}

		$sync = new ProductSync();
		if ( ! $sync->user_can_use_product( get_current_user_id(), $product ) ) {
			return new WP_REST_Response( array(), 403 );
		}

		return new WP_REST_Response(
			array(
				'id'          => $product->get_id(),
				'name'        => $product->get_name(),
				'sku'         => (string) $product->get_sku(),
				'price'       => (string) $product->get_regular_price(),
				'description' => $product->get_description(),
				'short'       => $product->get_short_description(),
				'image'       => (string) get_the_post_thumbnail_url( $product->get_id(), 'medium' ),
				'tax_class'   => $product->get_tax_class(),
			)
		);
	}
}
