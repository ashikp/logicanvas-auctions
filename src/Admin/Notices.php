<?php
/**
 * Admin notices (scoped; Guideline 11).
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Admin;

use LogicanvasAuctions\Config;

final class Notices {

	private const DISMISS_OPTION = 'wcap_dismiss_onboarding_notice';

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'maybe_onboarding' ) );
		add_action( 'admin_post_wcap_dismiss_onboarding', array( $this, 'dismiss_onboarding' ) );
	}

	public function add_woocommerce_notice(): void {
		add_action(
			'admin_notices',
			static function (): void {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				echo '<div class="notice notice-error"><p>';
				echo esc_html__( 'Logicanvas Auctions for WooCommerce requires WooCommerce to be installed and active.', 'logicanvas-auctions' );
				echo '</p></div>';
			}
		);
	}

	public function add_php_notice(): void {
		add_action(
			'admin_notices',
			static function (): void {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				echo '<div class="notice notice-error"><p>';
				echo esc_html(
					sprintf(
						/* translators: %s: PHP version */
						__( 'Logicanvas Auctions for WooCommerce requires PHP %s or higher.', 'logicanvas-auctions' ),
						Config::MIN_PHP
					)
				);
				echo '</p></div>';
			}
		);
	}

	public function maybe_onboarding(): void {
		if ( ! current_user_can( Config::CAP_MANAGE_SETTINGS ) ) {
			return;
		}

		if ( ! $this->is_auctions_admin_screen() ) {
			return;
		}

		if ( get_option( self::DISMISS_OPTION, '' ) === '1' ) {
			return;
		}

		$pages = get_option( Config::OPTION_PAGES, array() );
		if ( is_array( $pages ) && ! empty( $pages['archive'] ) ) {
			return;
		}

		$url      = admin_url( 'admin.php?page=wcap-setup' );
		$dismiss  = wp_nonce_url( admin_url( 'admin-post.php?action=wcap_dismiss_onboarding' ), 'wcap_dismiss_onboarding' );
		echo '<div class="notice notice-info is-dismissible"><p>';
		echo esc_html__( 'Logicanvas Auctions for WooCommerce is active. Open Auctions → Setup to create auction pages.', 'logicanvas-auctions' );
		echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open setup wizard', 'logicanvas-auctions' ) . '</a>';
		echo ' | <a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'logicanvas-auctions' ) . '</a>';
		echo '</p></div>';
	}

	public function dismiss_onboarding(): void {
		if ( ! current_user_can( Config::CAP_MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'Forbidden.', 'logicanvas-auctions' ) );
		}
		check_admin_referer( 'wcap_dismiss_onboarding' );
		update_option( self::DISMISS_OPTION, '1', false );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=wcap-dashboard' ) );
		exit;
	}

	private function is_auctions_admin_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		if ( Config::CPT === $screen->post_type ) {
			return true;
		}

		$id = (string) $screen->id;
		return str_starts_with( $id, 'toplevel_page_wcap-' )
			|| str_contains( $id, '_page_wcap-' );
	}
}
