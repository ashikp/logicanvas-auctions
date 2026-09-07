<?php
/**
 * Elementor widget category.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Elementor;

final class Category {

	public const SLUG = 'logicanvas-auctions';

	public function register( $elements_manager ): void {
		$elements_manager->add_category(
			self::SLUG,
			array(
				'title' => __( 'Logicanvas Auctions for WooCommerce', 'logicanvas-auctions' ),
				'icon'  => 'fa fa-gavel',
			)
		);
	}
}
