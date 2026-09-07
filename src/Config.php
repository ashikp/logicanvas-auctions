<?php
/**
 * Centralized product identifiers, paths, capabilities, tables, and defaults.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions;

final class Config {

	public const PRODUCT_NAME    = 'Logicanvas Auctions for WooCommerce';
	public const AUTHOR          = 'Logicanvas.io';
	public const AUTHOR_URI      = 'https://plugins.logicanvas.io';
	public const PLUGIN_URI      = 'https://wordpress.org/plugins/logicanvas-auctions/';
	public const DOCS_URI        = 'https://docs.logicanvas.io/logicanvas-auctions';
	public const REVIEW_URI      = 'https://wordpress.org/support/plugin/logicanvas-auctions/reviews/?filter=5#new-post';
	public const SLUG            = 'logicanvas-auctions';
	public const VERSION         = '1.1.0';
	public const DB_VERSION      = '1.0.2';
	public const TEXT_DOMAIN     = 'logicanvas-auctions';
	public const REST_NAMESPACE  = 'logicanvas-auctions/v1';
	public const PREFIX          = 'wcap_';
	public const LOGO_RELATIVE   = 'assets/img/logicanvas-auctions-logo.png';
	public const MIN_PHP         = '8.1';
	public const MIN_WP          = '6.4';
	public const MIN_WC          = '8.0';
	public const CPT             = 'wcap_auction';
	public const TAXONOMY_CAT    = 'wcap_auction_cat';
	public const HOLDER_ROLE     = 'wcap_auction_holder';
	public const BIDDER_ROLE     = 'wcap_auction_bidder';
	public const SCHEDULER_GROUP = 'logicanvas-auctions';
	public const OPTION_SETTINGS = 'wcap_settings';
	public const OPTION_DB_VER   = 'wcap_db_version';
	public const OPTION_PAGES    = 'wcap_pages';
	public const OPTION_UNINSTALL = 'wcap_remove_data_on_uninstall';
	public const CRON_HOOK       = 'wcap_process_overdue_auctions';
	public const TEMPLATE_DIR    = 'logicanvas-auctions';

	public const CAP_READ_AUCTIONS             = 'read_auctions';
	public const CAP_BID                       = 'bid_on_auctions';
	public const CAP_CREATE_AUCTIONS           = 'create_auctions';
	public const CAP_EDIT_OWN_AUCTIONS         = 'edit_own_auctions';
	public const CAP_SUBMIT_AUCTIONS           = 'submit_auctions';
	public const CAP_HOST_LIVE                 = 'host_live_auctions';
	public const CAP_VIEW_OWN_BIDS             = 'view_own_auction_bids';
	public const CAP_VIEW_OWN_SETTLEMENTS      = 'view_own_settlements';
	public const CAP_MODERATE_AUCTIONS         = 'moderate_auctions';
	public const CAP_MANAGE_HOLDERS            = 'manage_auction_holders';
	public const CAP_MANAGE_BIDS               = 'manage_auction_bids';
	public const CAP_MANAGE_SETTINGS           = 'manage_auction_settings';
	public const CAP_MANAGE_SETTLEMENTS        = 'manage_auction_settlements';

	public const TABLE_AUCTION_STATE      = 'wcap_auction_state';
	public const TABLE_BIDS               = 'wcap_bids';
	public const TABLE_EVENTS             = 'wcap_events';
	public const TABLE_INVITATIONS        = 'wcap_invitations';
	public const TABLE_PARTICIPANTS       = 'wcap_participants';
	public const TABLE_AWARDS             = 'wcap_awards';
	public const TABLE_SETTLEMENTS        = 'wcap_settlements';
	public const TABLE_PAYOUT_REQUESTS    = 'wcap_payout_requests';
	public const TABLE_AUDIT              = 'wcap_audit_log';
	public const TABLE_WATCHES            = 'wcap_watches';
	public const TABLE_HOLDERS            = 'wcap_holder_accounts';
	public const TABLE_NOTIFICATION_PREFS = 'wcap_notification_prefs';
	public const TABLE_IDEMPOTENCY        = 'wcap_idempotency';

	public const HOOK_CLOSE_AUCTION          = 'wcap_close_auction';
	public const HOOK_START_AUCTION          = 'wcap_start_auction';
	public const HOOK_PAYMENT_REMINDER       = 'wcap_payment_reminder';
	public const HOOK_PAYMENT_EXPIRE         = 'wcap_payment_expire';
	public const HOOK_NOTIFY                 = 'wcap_dispatch_notification';
	public const HOOK_LIVE_START             = 'wcap_live_start';

	/**
	 * Default plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'currency_lock'              => true,
			'timed_default_duration'     => 86400 * 7,
			'soft_close_window'          => 120,
			'soft_close_extend'          => 120,
			'soft_close_max_extensions'  => 20,
			'payment_deadline_hours'     => 48,
			'min_increment'              => '1.00',
			'proxy_bidding_enabled'      => false,
			'allow_coupons_on_auction'   => false,
			'allow_mixed_cart'           => false,
			'disable_wc_catalog'         => false,
			'restrict_wp_admin'          => true,
			'login_redirect_dashboard'   => true,
			'public_bid_history'         => true,
			'bid_rate_limit_user'        => 30,
			'bid_rate_limit_auction'     => 120,
			'bid_rate_limit_ip'          => 60,
			'commission_fixed'           => '0.00',
			'commission_percent'         => '10.00',
			'commission_minimum'         => '0.00',
			'reserve_display'            => 'met_only',
			'bidder_alias_mode'          => 'first_last_initial',
			'remove_data_on_uninstall'   => false,
			'hash_ip_addresses'          => true,
			'offer_runner_up_auto'       => false,
			'live_poll_interval_ms'      => 1000,
			'lobby_poll_interval_ms'     => 4000,
			'terms_version'              => '1.0',
			'min_age'                    => 0,
			'require_terms'              => true,
			'realtime_mode'              => 'polling',
			'order_managed_by'           => 'both',
		);
	}

	public static function plugin_file(): string {
		return defined( 'WCAP_PLUGIN_FILE' ) ? WCAP_PLUGIN_FILE : dirname( __DIR__ ) . '/logicanvas-auctions.php';
	}

	public static function plugin_dir(): string {
		return defined( 'WCAP_PLUGIN_DIR' ) ? WCAP_PLUGIN_DIR : dirname( __DIR__ ) . '/';
	}

	public static function plugin_url(): string {
		if ( defined( 'WCAP_PLUGIN_URL' ) && is_string( WCAP_PLUGIN_URL ) && '' !== WCAP_PLUGIN_URL ) {
			return trailingslashit( WCAP_PLUGIN_URL );
		}

		return plugin_dir_url( self::plugin_file() );
	}

	/**
	 * Absolute URL for a file under the plugin directory.
	 */
	public static function asset_url( string $relative ): string {
		$relative = ltrim( str_replace( '\\', '/', $relative ), '/' );
		return self::plugin_url() . $relative;
	}

	public static function logo_url(): string {
		$path = self::plugin_dir() . self::LOGO_RELATIVE;
		if ( ! is_readable( $path ) ) {
			return '';
		}

		return self::asset_url( self::LOGO_RELATIVE );
	}

	public static function table( string $logical_name ): string {
		global $wpdb;

		$prefix = isset( $wpdb->prefix ) ? $wpdb->prefix : 'wp_';

		return $prefix . $logical_name;
	}

	/**
	 * @return string[]
	 */
	public static function all_capabilities(): array {
		return array(
			self::CAP_READ_AUCTIONS,
			self::CAP_BID,
			self::CAP_CREATE_AUCTIONS,
			self::CAP_EDIT_OWN_AUCTIONS,
			self::CAP_SUBMIT_AUCTIONS,
			self::CAP_HOST_LIVE,
			self::CAP_VIEW_OWN_BIDS,
			self::CAP_VIEW_OWN_SETTLEMENTS,
			self::CAP_MODERATE_AUCTIONS,
			self::CAP_MANAGE_HOLDERS,
			self::CAP_MANAGE_BIDS,
			self::CAP_MANAGE_SETTINGS,
			self::CAP_MANAGE_SETTLEMENTS,
		);
	}

	/**
	 * @return string[]
	 */
	public static function holder_capabilities(): array {
		return array(
			self::CAP_READ_AUCTIONS,
			self::CAP_BID,
			self::CAP_CREATE_AUCTIONS,
			self::CAP_EDIT_OWN_AUCTIONS,
			self::CAP_SUBMIT_AUCTIONS,
			self::CAP_HOST_LIVE,
			self::CAP_VIEW_OWN_BIDS,
			self::CAP_VIEW_OWN_SETTLEMENTS,
			'upload_files',
		);
	}

	/**
	 * @return string[]
	 */
	public static function bidder_capabilities(): array {
		return array(
			self::CAP_READ_AUCTIONS,
			self::CAP_BID,
			self::CAP_VIEW_OWN_BIDS,
		);
	}
}
