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
use LogicanvasAuctions\Frontend\Design;

final class SettingsPage {

	public function render(): void {
		if ( ! current_user_can( Config::CAP_MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'Forbidden.', 'logicanvas-auctions' ) );
		}

		$s = Settings::get();
		Screen::open(
			__( 'Settings', 'logicanvas-auctions' ),
			__( 'Bidding rules, appearance, checkout behavior, commissions, and privacy.', 'logicanvas-auctions' )
		);

		echo '<form method="post" action="options.php">';
		settings_fields( 'wcap_settings_group' );

		Screen::panel_open( '', __( 'Appearance', 'logicanvas-auctions' ) );
		echo '<p class="description">' . esc_html__( 'Choose a design for auction pages, live rooms, and dashboards. Customizations apply on top of the selected theme.', 'logicanvas-auctions' ) . '</p>';
		$this->theme_picker( (string) ( $s['design_theme'] ?? Design::THEME_CLASSIC ) );
		echo '<table class="form-table" role="presentation">';
		$this->text(
			'design_accent',
			__( 'Accent color override', 'logicanvas-auctions' ),
			(string) ( $s['design_accent'] ?? '' ),
			__( 'Optional hex like #0d9488. Leave blank to use the theme accent.', 'logicanvas-auctions' )
		);
		$this->select(
			'design_radius',
			__( 'Corner radius', 'logicanvas-auctions' ),
			(string) ( $s['design_radius'] ?? 'medium' ),
			array(
				'soft'   => __( 'Soft (rounded)', 'logicanvas-auctions' ),
				'medium' => __( 'Medium', 'logicanvas-auctions' ),
				'sharp'  => __( 'Sharp', 'logicanvas-auctions' ),
			)
		);
		$this->select(
			'design_density',
			__( 'Spacing density', 'logicanvas-auctions' ),
			(string) ( $s['design_density'] ?? 'comfortable' ),
			array(
				'comfortable' => __( 'Comfortable', 'logicanvas-auctions' ),
				'compact'     => __( 'Compact', 'logicanvas-auctions' ),
			)
		);
		$this->select(
			'design_card_style',
			__( 'Card style', 'logicanvas-auctions' ),
			(string) ( $s['design_card_style'] ?? 'elevated' ),
			array(
				'elevated' => __( 'Elevated (shadow)', 'logicanvas-auctions' ),
				'flat'     => __( 'Flat', 'logicanvas-auctions' ),
				'outlined' => __( 'Outlined', 'logicanvas-auctions' ),
			)
		);
		$this->textarea(
			'design_custom_css',
			__( 'Custom CSS', 'logicanvas-auctions' ),
			(string) ( $s['design_custom_css'] ?? '' ),
			__( 'Advanced. Scoped rules for .wcap-root only. Do not wrap rules in style tags.', 'logicanvas-auctions' )
		);
		echo '</table>';
		Screen::panel_close();

		Screen::panel_open( '', __( 'Bidding & commerce', 'logicanvas-auctions' ) );
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
		Screen::panel_close();

		Screen::panel_open( '', __( 'Store modes & privacy', 'logicanvas-auctions' ) );
		echo '<table class="form-table" role="presentation">';
		$this->check( 'disable_wc_catalog', __( 'Hide WooCommerce shop and single product pages', 'logicanvas-auctions' ), ! empty( $s['disable_wc_catalog'] ), __( 'Optional. Cart, checkout, and My Account stay available for winners. Leave off if you also sell regular products.', 'logicanvas-auctions' ) );
		$this->check( 'replace_wp_login', __( 'Replace WordPress login (wp-login.php) with the auction Log in page', 'logicanvas-auctions' ), ! empty( $s['replace_wp_login'] ), __( 'Optional. Keep off to use the normal WordPress / WooCommerce login. Escape hatch: wp-login.php?wcap_core=1', 'logicanvas-auctions' ) );
		$this->check( 'login_redirect_dashboard', __( 'Send holders and bidders to their dashboard after login', 'logicanvas-auctions' ), ! empty( $s['login_redirect_dashboard'] ), __( 'Administrators still land in wp-admin.', 'logicanvas-auctions' ) );
		$this->check( 'restrict_wp_admin', __( 'Block wp-admin for holders and bidders', 'logicanvas-auctions' ), ! empty( $s['restrict_wp_admin'] ), __( 'They use frontend dashboards instead. Administrators are never blocked.', 'logicanvas-auctions' ) );
		$this->check( 'remove_data_on_uninstall', __( 'Remove all plugin data on uninstall', 'logicanvas-auctions' ), ! empty( $s['remove_data_on_uninstall'] ) );
		$this->check( 'hash_ip_addresses', __( 'Hash IP addresses', 'logicanvas-auctions' ), ! empty( $s['hash_ip_addresses'] ) );
		echo '</table>';
		submit_button( __( 'Save settings', 'logicanvas-auctions' ) );
		Screen::panel_close();

		echo '</form>';
		Screen::close();
	}

	private function theme_picker( string $current ): void {
		$name   = Config::OPTION_SETTINGS . '[design_theme]';
		$themes = Design::themes();
		echo '<div class="wcap-theme-picker" role="radiogroup" aria-label="' . esc_attr__( 'Design theme', 'logicanvas-auctions' ) . '">';
		foreach ( $themes as $slug => $theme ) {
			$id     = 'wcap_design_theme_' . $slug;
			$tokens = $theme['tokens'];
			$accent = $tokens['--wcap-accent'] ?? '#4f46e5';
			$bg     = $tokens['--wcap-bg'] ?? '#f8fafc';
			$card   = $tokens['--wcap-card'] ?? '#fff';
			$from   = $tokens['--wcap-hero-from'] ?? '#0f172a';
			$to     = $tokens['--wcap-hero-to'] ?? '#312e81';
			echo '<label class="wcap-theme-card' . ( $current === $slug ? ' is-selected' : '' ) . '" for="' . esc_attr( $id ) . '">';
			echo '<input type="radio" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $slug ) . '" ' . checked( $current, $slug, false ) . ' />';
			echo '<span class="wcap-theme-card__swatch" style="--swatch-accent:' . esc_attr( $accent ) . ';--swatch-bg:' . esc_attr( $bg ) . ';--swatch-card:' . esc_attr( $card ) . ';--swatch-from:' . esc_attr( $from ) . ';--swatch-to:' . esc_attr( $to ) . '" aria-hidden="true"></span>';
			echo '<span class="wcap-theme-card__body">';
			echo '<strong>' . esc_html( $theme['label'] ) . '</strong>';
			echo '<span class="description">' . esc_html( $theme['description'] ) . '</span>';
			echo '</span>';
			echo '</label>';
		}
		echo '</div>';
	}

	private function text( string $key, string $label, string $value, string $help = '' ): void {
		$name = Config::OPTION_SETTINGS . '[' . $key . ']';
		echo '<tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input class="regular-text" type="text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	private function textarea( string $key, string $label, string $value, string $help = '' ): void {
		$name = Config::OPTION_SETTINGS . '[' . $key . ']';
		echo '<tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<textarea class="large-text code" rows="6" id="' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $value ) . '</textarea>';
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
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

	private function check( string $key, string $label, bool $on, string $help = '' ): void {
		$name = Config::OPTION_SETTINGS . '[' . $key . ']';
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0" />';
		echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( $on, true, false ) . ' /> ' . esc_html( $label ) . '</label>';
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}
}
