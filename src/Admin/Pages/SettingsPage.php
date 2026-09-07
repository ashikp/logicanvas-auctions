<?php
/**
 * Settings screen.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Admin\Pages;

use LogicanvasAuctions\Admin\Screen;
use LogicanvasAuctions\Admin\Settings;
use LogicanvasAuctions\Config;

final class SettingsPage {

	public function render(): void {
		if ( ! current_user_can( Config::CAP_MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'Forbidden.', 'logicanvas-auctions' ) );
		}

		$s = Settings::get();
		Screen::open(
			__( 'Settings', 'logicanvas-auctions' ),
			__( 'Bidding rules, checkout behavior, commissions, and privacy.', 'logicanvas-auctions' )
		);
		Screen::panel_open();
		echo '<form method="post" action="options.php">';
		settings_fields( 'wcap_settings_group' );
		echo '<table class="form-table" role="presentation">';
		$this->text( 'min_increment', __( 'Default increment', 'logicanvas-auctions' ), (string) $s['min_increment'] );
		$this->text( 'soft_close_window', __( 'Soft-close window (seconds)', 'logicanvas-auctions' ), (string) $s['soft_close_window'] );
		$this->text( 'soft_close_extend', __( 'Soft-close extension (seconds)', 'logicanvas-auctions' ), (string) $s['soft_close_extend'] );
		$this->text( 'soft_close_max_extensions', __( 'Max extensions', 'logicanvas-auctions' ), (string) $s['soft_close_max_extensions'] );
		$this->text( 'payment_deadline_hours', __( 'Payment deadline (hours)', 'logicanvas-auctions' ), (string) $s['payment_deadline_hours'] );
		$this->text( 'commission_fixed', __( 'Commission fixed fee', 'logicanvas-auctions' ), (string) $s['commission_fixed'] );
		$this->text( 'commission_percent', __( 'Commission percent', 'logicanvas-auctions' ), (string) $s['commission_percent'] );
		$this->text( 'commission_minimum', __( 'Minimum commission', 'logicanvas-auctions' ), (string) $s['commission_minimum'] );
		$this->check( 'proxy_bidding_enabled', __( 'Enable proxy bidding for timed auctions', 'logicanvas-auctions' ), ! empty( $s['proxy_bidding_enabled'] ) );
		$this->check( 'allow_coupons_on_auction', __( 'Allow coupons on auction checkout', 'logicanvas-auctions' ), ! empty( $s['allow_coupons_on_auction'] ) );
		$this->check( 'allow_mixed_cart', __( 'Allow mixing auction wins with other cart items', 'logicanvas-auctions' ), ! empty( $s['allow_mixed_cart'] ) );
		$this->check( 'disable_wc_catalog', __( 'Disable WooCommerce shop and single product pages (keep checkout, cart, and account)', 'logicanvas-auctions' ), ! empty( $s['disable_wc_catalog'] ) );
		$this->check( 'login_redirect_dashboard', __( 'Send holders and bidders to their dashboard after login', 'logicanvas-auctions' ), ! empty( $s['login_redirect_dashboard'] ) );
		$this->check( 'restrict_wp_admin', __( 'Block wp-admin for holders and bidders', 'logicanvas-auctions' ), ! empty( $s['restrict_wp_admin'] ) );
		$this->check( 'remove_data_on_uninstall', __( 'Remove all plugin data on uninstall', 'logicanvas-auctions' ), ! empty( $s['remove_data_on_uninstall'] ) );
		$this->check( 'hash_ip_addresses', __( 'Hash IP addresses', 'logicanvas-auctions' ), ! empty( $s['hash_ip_addresses'] ) );
		$this->select(
			'order_managed_by',
			__( 'Auction orders managed by', 'logicanvas-auctions' ),
			(string) ( $s['order_managed_by'] ?? 'both' ),
			array(
				'admin'  => __( 'Administrator only', 'logicanvas-auctions' ),
				'seller' => __( 'Seller / auction holder only', 'logicanvas-auctions' ),
				'both'   => __( 'Both admin and seller', 'logicanvas-auctions' ),
			),
			__( 'Controls who can update WooCommerce order status, payment notes, and shipping tracking for auction wins from the seller dashboard.', 'logicanvas-auctions' )
		);
		echo '</table>';
		submit_button( __( 'Save settings', 'logicanvas-auctions' ) );
		echo '</form>';
		Screen::panel_close();
		Screen::close();
	}

	private function text( string $key, string $label, string $value ): void {
		$name = Config::OPTION_SETTINGS . '[' . $key . ']';
		echo '<tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input class="regular-text" type="text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
		echo '</td></tr>';
	}

	/**
	 * @param array<string, string> $options
	 */
	private function select( string $key, string $label, string $value, array $options, string $help = '' ): void {
		$name = Config::OPTION_SETTINGS . '[' . $key . ']';
		echo '<tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<select id="' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $options as $opt => $text ) {
			echo '<option value="' . esc_attr( $opt ) . '" ' . selected( $value, $opt, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select>';
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	private function check( string $key, string $label, bool $on ): void {
		$name = Config::OPTION_SETTINGS . '[' . $key . ']';
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0" />';
		echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( $on, true, false ) . ' /> ' . esc_html( $label ) . '</label>';
		echo '</td></tr>';
	}
}
