<?php
/**
 * Customizable auction + category permalink bases (Settings → Permalinks).
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Admin;

final class PermalinkSettings {

	public const OPTION_AUCTION_BASE   = 'wcap_cpt_rewrite_slug';
	public const OPTION_CATEGORY_BASE  = 'wcap_cat_rewrite_slug';
	public const DEFAULT_AUCTION_BASE  = 'auctions';
	public const DEFAULT_CATEGORY_BASE = 'auction-category';

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_fields' ) );
		add_action( 'admin_init', array( $this, 'maybe_save_from_permalinks_screen' ) );
	}

	public function register_fields(): void {
		add_settings_section(
			'wcap_permalinks',
			__( 'Logicanvas Auctions', 'logicanvas-auctions' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Customize the URL bases for auction listings and auction categories. Click “Save Changes” at the bottom of this page after editing.', 'logicanvas-auctions' ) . '</p>';
			},
			'permalink'
		);

		add_settings_field(
			self::OPTION_AUCTION_BASE,
			__( 'Auction base', 'logicanvas-auctions' ),
			array( $this, 'render_auction_field' ),
			'permalink',
			'wcap_permalinks'
		);

		add_settings_field(
			self::OPTION_CATEGORY_BASE,
			__( 'Auction category base', 'logicanvas-auctions' ),
			array( $this, 'render_category_field' ),
			'permalink',
			'wcap_permalinks'
		);
	}

	public function render_auction_field(): void {
		$slug = self::auction_base();
		echo '<code>' . esc_html( home_url( '/' ) ) . '</code>';
		echo '<input name="' . esc_attr( self::OPTION_AUCTION_BASE ) . '" type="text" value="' . esc_attr( $slug ) . '" class="regular-text code" />';
		echo '<code>/sample-auction/</code>';
		echo '<p class="description">' . esc_html__( 'Default: auctions → /auctions/my-lot/', 'logicanvas-auctions' ) . '</p>';
	}

	public function render_category_field(): void {
		$slug = self::category_base();
		echo '<code>' . esc_html( home_url( '/' ) ) . '</code>';
		echo '<input name="' . esc_attr( self::OPTION_CATEGORY_BASE ) . '" type="text" value="' . esc_attr( $slug ) . '" class="regular-text code" />';
		echo '<code>/vehicles/</code>';
		echo '<p class="description">' . esc_html__( 'Default: auction-category → /auction-category/vehicles/', 'logicanvas-auctions' ) . '</p>';
	}

	/**
	 * WordPress permalinks form does not always persist custom register_setting options.
	 */
	public function maybe_save_from_permalinks_screen(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::OPTION_AUCTION_BASE ] ) && ! isset( $_POST[ self::OPTION_CATEGORY_BASE ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ), 'update-permalink' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$prev_auction = self::auction_base();
		$prev_cat     = self::category_base();

		if ( isset( $_POST[ self::OPTION_AUCTION_BASE ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$auction_base = sanitize_text_field( wp_unslash( (string) $_POST[ self::OPTION_AUCTION_BASE ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_option(
				self::OPTION_AUCTION_BASE,
				self::sanitize_base( $auction_base, self::DEFAULT_AUCTION_BASE ),
				false
			);
		}
		if ( isset( $_POST[ self::OPTION_CATEGORY_BASE ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$category_base = sanitize_text_field( wp_unslash( (string) $_POST[ self::OPTION_CATEGORY_BASE ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_option(
				self::OPTION_CATEGORY_BASE,
				self::sanitize_base( $category_base, self::DEFAULT_CATEGORY_BASE ),
				false
			);
		}

		if ( $prev_auction !== self::auction_base() || $prev_cat !== self::category_base() ) {
			set_transient( 'wcap_flush_rewrites', '1', 10 * MINUTE_IN_SECONDS );
		}
	}

	public static function sanitize_base( string $value, string $fallback ): string {
		$value = strtolower( trim( $value ) );
		$value = trim( $value, '/' );
		$value = preg_replace( '#[^a-z0-9_\-/]+#', '-', $value ) ?? '';
		$value = preg_replace( '#/+#', '/', $value ) ?? '';
		$value = trim( (string) $value, '/-' );
		return '' !== $value ? $value : $fallback;
	}

	public static function auction_base(): string {
		$raw = (string) get_option( self::OPTION_AUCTION_BASE, self::DEFAULT_AUCTION_BASE );
		return self::sanitize_base( $raw, self::DEFAULT_AUCTION_BASE );
	}

	public static function category_base(): string {
		$raw = (string) get_option( self::OPTION_CATEGORY_BASE, self::DEFAULT_CATEGORY_BASE );
		return self::sanitize_base( $raw, self::DEFAULT_CATEGORY_BASE );
	}
}
