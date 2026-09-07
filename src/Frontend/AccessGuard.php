<?php
/**
 * Frontend login destinations and wp-admin restriction for auction users.
 *
 * Administrators, shop managers, and other content editors keep wp-admin.
 * Holders and bidders use the frontend dashboards. Checkout, AJAX, and
 * media uploads from the listing form remain available.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Frontend;

use LogicanvasAuctions\Admin\Settings;
use LogicanvasAuctions\Config;
use LogicanvasAuctions\Frontend\Dashboards\AccountRouter;
use WP_User;

final class AccessGuard {

	public function register(): void {
		add_filter( 'login_redirect', array( $this, 'login_redirect' ), 99, 3 );
		add_filter( 'woocommerce_login_redirect', array( $this, 'wc_login_redirect' ), 20, 2 );
		add_filter( 'woocommerce_registration_redirect', array( $this, 'wc_registration_redirect' ), 20, 1 );
		add_action( 'admin_init', array( $this, 'block_admin' ), 1 );
		add_filter( 'show_admin_bar', array( $this, 'admin_bar' ) );
		add_filter( 'woocommerce_prevent_admin_access', array( $this, 'wc_prevent_admin' ), 20 );
	}

	/**
	 * @param mixed $redirect_to Default destination.
	 * @param mixed $requested Requested destination.
	 * @param mixed $user Authenticated user or error.
	 */
	public function login_redirect( $redirect_to, $requested, $user ) {
		if ( ! $this->redirect_enabled() ) {
			return $redirect_to;
		}

		if ( ! $user instanceof WP_User || $user->ID < 1 ) {
			return $redirect_to;
		}

		$requested   = is_string( $requested ) ? $requested : '';
		$redirect_to = is_string( $redirect_to ) ? $redirect_to : '';
		$candidate   = '' !== $requested ? $requested : $redirect_to;

		if ( $this->can_access_admin( $user->ID ) ) {
			if ( $this->should_keep_checkout_flow( $candidate ) ) {
				return $candidate;
			}

			return admin_url();
		}

		// Bidders/holders always land on their dashboard after login,
		// unless they are mid checkout / paying for a win.
		if ( $this->should_keep_checkout_flow( $candidate ) ) {
			return $candidate;
		}

		$dashboard = AccountRouter::dashboard_for_user( $user->ID );

		return (string) apply_filters( 'wcap_login_redirect', $dashboard, $user, $candidate );
	}

	/**
	 * @param mixed $redirect WooCommerce destination.
	 * @param mixed $user User.
	 */
	public function wc_login_redirect( $redirect, $user ) {
		$redirect = is_string( $redirect ) ? $redirect : '';

		return $this->login_redirect( $redirect, $redirect, $user );
	}

	/**
	 * @param mixed $redirect Registration destination.
	 */
	public function wc_registration_redirect( $redirect ) {
		if ( ! $this->redirect_enabled() || ! is_user_logged_in() ) {
			return $redirect;
		}

		$user_id = get_current_user_id();
		if ( $this->can_access_admin( $user_id ) ) {
			return $redirect;
		}

		$redirect = is_string( $redirect ) ? $redirect : '';
		if ( $this->should_keep_checkout_flow( $redirect ) ) {
			return $redirect;
		}

		return AccountRouter::dashboard_for_user( $user_id );
	}

	public function block_admin(): void {
		if ( ! $this->restrict_enabled() || ! is_user_logged_in() ) {
			return;
		}

		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		if ( $this->is_allowed_admin_script() ) {
			return;
		}

		if ( $this->can_access_admin( get_current_user_id() ) ) {
			return;
		}

		wp_safe_redirect( AccountRouter::dashboard_for_user( get_current_user_id() ) );
		exit;
	}

	/**
	 * @param mixed $show Current admin-bar visibility.
	 */
	public function admin_bar( $show ): bool {
		if ( ! $this->restrict_enabled() || ! is_user_logged_in() ) {
			return (bool) $show;
		}

		if ( $this->can_access_admin( get_current_user_id() ) ) {
			return (bool) $show;
		}

		return false;
	}

	/**
	 * @param mixed $prevent WooCommerce prevent-admin flag.
	 */
	public function wc_prevent_admin( $prevent ): bool {
		if ( ! $this->restrict_enabled() || ! is_user_logged_in() ) {
			return (bool) $prevent;
		}

		if ( $this->can_access_admin( get_current_user_id() ) ) {
			return false;
		}

		return true;
	}

	public function can_access_admin( int $user_id ): bool {
		$allowed = user_can( $user_id, 'manage_options' )
			|| user_can( $user_id, 'manage_woocommerce' )
			|| user_can( $user_id, Config::CAP_MODERATE_AUCTIONS )
			|| user_can( $user_id, 'edit_posts' );

		return (bool) apply_filters( 'wcap_user_can_access_wp_admin', $allowed, $user_id );
	}

	/**
	 * Keep only checkout / cart / pay-for-win destinations after login.
	 * Auction pages and generic redirects go to the dashboard instead.
	 */
	private function should_keep_checkout_flow( string $url ): bool {
		$url = trim( $url );
		if ( '' === $url ) {
			return false;
		}

		$safe = wp_validate_redirect( $url, '' );
		if ( '' === $safe ) {
			return false;
		}

		$path = strtolower( (string) ( wp_parse_url( $safe, PHP_URL_PATH ) ?? '' ) );
		if ( '' === $path ) {
			return false;
		}

		if ( str_contains( $path, '/wp-admin' ) || str_contains( $path, 'wp-login.php' ) ) {
			return false;
		}

		if ( PluginPages::is_login_url( $safe ) ) {
			return false;
		}

		$pay_id = PluginPages::id( 'pay' );
		if ( $pay_id > 0 ) {
			$pay_path = untrailingslashit( (string) ( wp_parse_url( (string) get_permalink( $pay_id ), PHP_URL_PATH ) ?? '' ) );
			if ( '' !== $pay_path && untrailingslashit( $path ) === $pay_path ) {
				return true;
			}
		}

		if ( function_exists( 'wc_get_checkout_url' ) ) {
			$checkout = untrailingslashit( (string) ( wp_parse_url( (string) wc_get_checkout_url(), PHP_URL_PATH ) ?? '' ) );
			if ( '' !== $checkout && ( untrailingslashit( $path ) === $checkout || str_contains( $path, 'order-pay' ) ) ) {
				return true;
			}
		}

		if ( function_exists( 'wc_get_cart_url' ) ) {
			$cart = untrailingslashit( (string) ( wp_parse_url( (string) wc_get_cart_url(), PHP_URL_PATH ) ?? '' ) );
			if ( '' !== $cart && untrailingslashit( $path ) === $cart ) {
				return true;
			}
		}

		return false;
	}

	private function is_allowed_admin_script(): bool {
		global $pagenow;

		$page = is_string( $pagenow ) ? $pagenow : '';

		return in_array( $page, array( 'admin-ajax.php', 'admin-post.php', 'async-upload.php', 'media-upload.php' ), true );
	}

	private function restrict_enabled(): bool {
		return ! empty( Settings::get()['restrict_wp_admin'] );
	}

	private function redirect_enabled(): bool {
		return ! empty( Settings::get()['login_redirect_dashboard'] );
	}
}
