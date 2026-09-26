<?php
/**
 * Post-activation installer (pages are created by wizard, not here).
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Core;

use LogicanvasAuctions\Admin\Settings;
use LogicanvasAuctions\Config;

final class Installer {

	public function maybe_install(): void {
		$caps = new Capabilities();
		$caps->install();

		if ( false === get_option( Config::OPTION_SETTINGS, false ) ) {
			update_option( Config::OPTION_SETTINGS, Settings::with_defaults( array() ), true );
		}

		$this->maybe_migrate_clarity_120();

		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'init', array( $this, 'schedule_cron' ), 5 );
	}

	/**
	 * Preserve login redirect for sites that already had a plugin login page before 1.2.0.
	 */
	private function maybe_migrate_clarity_120(): void {
		if ( get_option( 'wcap_clarity_migrated_120', '' ) === '1' ) {
			return;
		}

		$pages    = get_option( Config::OPTION_PAGES, array() );
		$settings = Settings::get();

		if ( is_array( $pages ) && ! empty( $pages['login'] ) && empty( $settings['replace_wp_login'] ) ) {
			$settings['replace_wp_login'] = true;
			update_option( Config::OPTION_SETTINGS, $settings, true );
		}

		update_option( 'wcap_clarity_migrated_120', '1', false );
	}

	/**
	 * @param mixed $schedules Existing schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public function cron_schedules( $schedules ): array {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}

		if ( ! isset( $schedules['wcap_every_minute'] ) ) {
			$schedules['wcap_every_minute'] = array(
				'interval' => 60,
				'display'  => 'Every Minute (Logicanvas Auctions for WooCommerce)',
			);
		}

		return $schedules;
	}

	public function schedule_cron(): void {
		if ( ! wp_next_scheduled( Config::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'wcap_every_minute', Config::CRON_HOOK );
		}
	}
}
