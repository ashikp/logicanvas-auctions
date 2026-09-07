<?php
/**
 * Runtime dependency checks.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Core;

use LogicanvasAuctions\Config;

final class Dependencies {

	public function meets_php(): bool {
		return version_compare( PHP_VERSION, Config::MIN_PHP, '>=' );
	}

	public function woocommerce_active(): bool {
		if ( class_exists( 'WooCommerce' ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			$plugin_php = ABSPATH . 'wp-admin/includes/plugin.php';
			if ( is_readable( $plugin_php ) ) {
				require_once $plugin_php;
			}
		}

		return function_exists( 'is_plugin_active' ) && is_plugin_active( 'woocommerce/woocommerce.php' );
	}

	public function woocommerce_version_ok(): bool {
		if ( ! defined( 'WC_VERSION' ) ) {
			return false;
		}

		return version_compare( WC_VERSION, Config::MIN_WC, '>=' );
	}

	public function elementor_active(): bool {
		return did_action( 'elementor/loaded' ) > 0 || class_exists( '\Elementor\Plugin' );
	}

	public function action_scheduler_available(): bool {
		return function_exists( 'as_schedule_single_action' );
	}
}
