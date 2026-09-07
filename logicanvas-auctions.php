<?php
/**
 * Plugin Name: Logicanvas Auctions for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/logicanvas-auctions/
 * Description: Timed and live auctions for WooCommerce, with server-side bidding, winner checkout, and optional Elementor widgets.
 * Version: 1.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Author: Logicanvasio
 * Documentation: https://docs.logicanvas.io/logicanvas-auctions
 * Author URI: https://logicanvas.io
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: logicanvas-auctions
 * Domain Path: /languages
 * WC requires at least: 8.0
 * WC tested up to: 10.1
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WCAP_PLUGIN_FILE', __FILE__ );
define( 'WCAP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCAP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCAP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WCAP_PLUGIN_DIR . 'src/Autoloader.php';

LogicanvasAuctions\Autoloader::register();

add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WCAP_PLUGIN_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WCAP_PLUGIN_FILE, true );
	}
);

register_activation_hook( WCAP_PLUGIN_FILE, array( LogicanvasAuctions\Core\Activator::class, 'activate' ) );
register_deactivation_hook( WCAP_PLUGIN_FILE, array( LogicanvasAuctions\Core\Deactivator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		LogicanvasAuctions\Plugin::instance()->boot();
	},
	11
);
