<?php
/**
 * Deactivation. Business data is retained.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Core;

use LogicanvasAuctions\Config;

final class Deactivator {

	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( Config::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, Config::CRON_HOOK );
		}

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', array(), Config::SCHEDULER_GROUP );
		}

		flush_rewrite_rules();
	}
}
