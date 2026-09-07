<?php
/**
 * Plugin settings.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Admin;

use LogicanvasAuctions\Config;

final class Settings {

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings(): void {
		register_setting(
			'wcap_settings_group',
			Config::OPTION_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => Config::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Sanitize every registered settings key before persistence.
	 *
	 * @param mixed $input Raw settings from the options form or update_option.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		$defaults = Config::defaults();
		if ( ! is_array( $input ) ) {
			return $defaults;
		}

		$previous = self::get();
		$merged   = array();
		foreach ( array_keys( $defaults ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$merged[ $key ] = $input[ $key ];
			} elseif ( array_key_exists( $key, $previous ) ) {
				$merged[ $key ] = $previous[ $key ];
			} else {
				$merged[ $key ] = $defaults[ $key ];
			}
		}

		$out = array(
			'currency_lock'             => $this->to_bool( $merged['currency_lock'] ),
			'timed_default_duration'    => max( 60, (int) $merged['timed_default_duration'] ),
			'soft_close_window'         => max( 0, (int) $merged['soft_close_window'] ),
			'soft_close_extend'         => max( 0, (int) $merged['soft_close_extend'] ),
			'soft_close_max_extensions' => max( 0, (int) $merged['soft_close_max_extensions'] ),
			'payment_deadline_hours'    => max( 1, (int) $merged['payment_deadline_hours'] ),
			'min_increment'             => $this->sanitize_money( (string) $merged['min_increment'], '1.00' ),
			'proxy_bidding_enabled'     => $this->to_bool( $merged['proxy_bidding_enabled'] ),
			'allow_coupons_on_auction'  => $this->to_bool( $merged['allow_coupons_on_auction'] ),
			'allow_mixed_cart'          => $this->to_bool( $merged['allow_mixed_cart'] ),
			'disable_wc_catalog'        => $this->to_bool( $merged['disable_wc_catalog'] ),
			'restrict_wp_admin'         => $this->to_bool( $merged['restrict_wp_admin'] ),
			'login_redirect_dashboard'  => $this->to_bool( $merged['login_redirect_dashboard'] ),
			'public_bid_history'        => $this->to_bool( $merged['public_bid_history'] ),
			'bid_rate_limit_user'       => max( 1, (int) $merged['bid_rate_limit_user'] ),
			'bid_rate_limit_auction'    => max( 1, (int) $merged['bid_rate_limit_auction'] ),
			'bid_rate_limit_ip'         => max( 1, (int) $merged['bid_rate_limit_ip'] ),
			'commission_fixed'          => $this->sanitize_money( (string) $merged['commission_fixed'], '0.00' ),
			'commission_percent'        => $this->sanitize_money( (string) $merged['commission_percent'], '10.00' ),
			'commission_minimum'        => $this->sanitize_money( (string) $merged['commission_minimum'], '0.00' ),
			'reserve_display'           => $this->sanitize_enum(
				(string) $merged['reserve_display'],
				array( 'met_only', 'always', 'never' ),
				'met_only'
			),
			'bidder_alias_mode'         => $this->sanitize_enum(
				(string) $merged['bidder_alias_mode'],
				array( 'first_last_initial', 'initials', 'masked', 'full' ),
				'first_last_initial'
			),
			'remove_data_on_uninstall'  => $this->to_bool( $merged['remove_data_on_uninstall'] ),
			'hash_ip_addresses'         => $this->to_bool( $merged['hash_ip_addresses'] ),
			'offer_runner_up_auto'      => $this->to_bool( $merged['offer_runner_up_auto'] ),
			'live_poll_interval_ms'     => max( 250, (int) $merged['live_poll_interval_ms'] ),
			'lobby_poll_interval_ms'    => max( 1000, (int) $merged['lobby_poll_interval_ms'] ),
			'terms_version'             => sanitize_text_field( (string) $merged['terms_version'] ),
			'min_age'                   => max( 0, (int) $merged['min_age'] ),
			'require_terms'             => $this->to_bool( $merged['require_terms'] ),
			'realtime_mode'             => $this->sanitize_enum(
				(string) $merged['realtime_mode'],
				array( 'polling', 'push' ),
				'polling'
			),
			'order_managed_by'          => $this->sanitize_enum(
				(string) $merged['order_managed_by'],
				array( 'admin', 'seller', 'both' ),
				'both'
			),
		);

		if ( '' === $out['terms_version'] ) {
			$out['terms_version'] = '1.0';
		}

		update_option( Config::OPTION_UNINSTALL, $out['remove_data_on_uninstall'] ? '1' : '0', false );

		if ( ( ! empty( $previous['disable_wc_catalog'] ) ) !== $out['disable_wc_catalog'] ) {
			set_transient( 'wcap_flush_rewrites', '1', 10 * MINUTE_IN_SECONDS );
		}

		return $out;
	}

	/**
	 * @param mixed $value Raw checkbox / truthy value.
	 */
	private function to_bool( $value ): bool {
		return ! empty( $value ) && '0' !== (string) $value && 'false' !== strtolower( (string) $value );
	}

	/**
	 * @param string   $value   Candidate value.
	 * @param string[] $allowed Allowed values.
	 * @param string   $default Fallback.
	 */
	private function sanitize_enum( string $value, array $allowed, string $default ): string {
		$value = sanitize_key( $value );
		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	private function sanitize_money( string $value, string $default ): string {
		$value = sanitize_text_field( $value );
		$value = str_replace( ',', '', $value );
		if ( '' === $value || ! is_numeric( $value ) ) {
			return $default;
		}

		return number_format( (float) $value, 2, '.', '' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$stored = get_option( Config::OPTION_SETTINGS, array() );
		return self::with_defaults( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * @param array<string, mixed> $stored
	 * @return array<string, mixed>
	 */
	public static function with_defaults( array $stored ): array {
		return array_merge( Config::defaults(), $stored );
	}
}
