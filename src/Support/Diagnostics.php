<?php
/**
 * Diagnostics report.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Support;

use LogicanvasAuctions\Admin\Settings;
use LogicanvasAuctions\Config;
use LogicanvasAuctions\Core\Dependencies;
use LogicanvasAuctions\Infrastructure\Database\Migrator;

final class Diagnostics {

	/**
	 * @return array<string, mixed>
	 */
	public function report(): array {
		$deps = new Dependencies();

		return array(
			'plugin_version'      => Config::VERSION,
			'db_version'          => get_option( Config::OPTION_DB_VER, '' ),
			'php'                 => PHP_VERSION,
			'woocommerce'         => defined( 'WC_VERSION' ) ? WC_VERSION : 'missing',
			'elementor'           => $deps->elementor_active() ? 'active' : 'absent',
			'action_scheduler'    => $deps->action_scheduler_available(),
			'tables'              => ( new Migrator() )->tables_exist(),
			'realtime_mode'       => Settings::get()['realtime_mode'],
			'store_currency'      => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'cron_scheduled'      => (bool) wp_next_scheduled( Config::CRON_HOOK ),
			'hpos'                => class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(),
		);
	}
}
