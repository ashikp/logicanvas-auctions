<?php
/**
 * Activation routine. Idempotent.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Core;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Infrastructure\Database\Migrator;
use LogicanvasAuctions\Infrastructure\WooCommerce\MyAccount;

final class Activator {

	public static function activate(): void {
		if ( version_compare( PHP_VERSION, Config::MIN_PHP, '<' ) ) {
			deactivate_plugins( plugin_basename( Config::plugin_file() ) );
			wp_die(
				esc_html(
					sprintf(
						/* translators: %s: minimum PHP version */
						__( 'Logicanvas Auctions for WooCommerce requires PHP %s or higher.', 'logicanvas-auctions' ),
						Config::MIN_PHP
					)
				)
			);
		}

		$capabilities = new Capabilities();
		$capabilities->install();

		$migrator = new Migrator();
		$migrator->migrate();

		$post_types = new PostTypes();
		$post_types->register_types();

		( new MyAccount() )->endpoints();
		flush_rewrite_rules();

		if ( ! wp_next_scheduled( Config::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'wcap_every_minute', Config::CRON_HOOK );
		}

		update_option( Config::OPTION_DB_VER, Config::DB_VERSION, true );
		update_option( 'wcap_flush_wc_endpoints', '1', false );
	}
}
