<?php
/**
 * Account dashboard views, URLs, and document titles.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Frontend\Dashboards;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Frontend\PluginPages;

final class AccountRouter {

	public const QUERY_VAR = 'wcap_view';

	public const OVERVIEW   = 'overview';
	public const BIDS       = 'bids';
	public const WATCHLIST  = 'watchlist';
	public const PURCHASES  = 'purchases';
	public const PICKUP     = 'pickup';
	public const LISTINGS   = 'listings';
	public const ORDERS     = 'orders';
	public const PAYOUTS    = 'payouts';
	public const CREATE     = 'create';
	public const EDIT       = 'edit';
	public const ADDRESS    = 'address';

	public const MODE_BUYER  = 'buyer';
	public const MODE_SELLER = 'seller';

	private static string $context = self::MODE_SELLER;

	/**
	 * @var list<string>
	 */
	private const SELLER_VIEWS = array( self::LISTINGS, self::ORDERS, self::PAYOUTS, self::CREATE, self::EDIT );

	public function register(): void {
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'document_title_parts', array( $this, 'document_title' ) );
	}

	/**
	 * @param mixed $vars Query vars.
	 * @return string[]
	 */
	public function query_vars( $vars ): array {
		if ( ! is_array( $vars ) ) {
			$vars = array();
		}

		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * @param mixed $parts Title parts.
	 * @return array<string, string>
	 */
	public function document_title( $parts ): array {
		if ( ! is_array( $parts ) ) {
			$parts = array();
		}

		if ( ! $this->is_account_request() ) {
			return $parts;
		}

		$parts['title'] = $this->page_title( $this->current() );
		return $parts;
	}

	/**
	 * @return array<string, array{title:string,eyebrow:string,seller?:bool}>
	 */
	public static function catalog(): array {
		return array(
			self::OVERVIEW  => array(
				'title'   => __( 'Overview', 'logicanvas-auctions' ),
				'eyebrow' => __( 'Account overview', 'logicanvas-auctions' ),
			),
			self::BIDS      => array(
				'title'   => __( 'My bids', 'logicanvas-auctions' ),
				'eyebrow' => __( 'Bidding activity', 'logicanvas-auctions' ),
			),
			self::WATCHLIST => array(
				'title'   => __( 'Watchlist', 'logicanvas-auctions' ),
				'eyebrow' => __( 'Saved lots', 'logicanvas-auctions' ),
			),
			self::PURCHASES => array(
				'title'   => __( 'Purchases', 'logicanvas-auctions' ),
				'eyebrow' => __( 'Wins and invoices', 'logicanvas-auctions' ),
			),
			self::PICKUP    => array(
				'title'   => __( 'Pickup & shipping', 'logicanvas-auctions' ),
				'eyebrow' => __( 'Fulfilment', 'logicanvas-auctions' ),
			),
			self::LISTINGS  => array(
				'title'   => __( 'Listings', 'logicanvas-auctions' ),
				'eyebrow' => __( 'Seller listings', 'logicanvas-auctions' ),
				'seller'  => true,
			),
			self::ORDERS    => array(
				'title'   => __( 'Orders', 'logicanvas-auctions' ),
				'eyebrow' => __( 'Settlements', 'logicanvas-auctions' ),
				'seller'  => true,
			),
			self::PAYOUTS   => array(
				'title'   => __( 'Payouts', 'logicanvas-auctions' ),
				'eyebrow' => __( 'Seller payouts', 'logicanvas-auctions' ),
				'seller'  => true,
			),
			self::CREATE    => array(
				'title'   => __( 'Create listing', 'logicanvas-auctions' ),
				'eyebrow' => __( 'New auction', 'logicanvas-auctions' ),
				'seller'  => true,
			),
			self::EDIT      => array(
				'title'   => __( 'Edit listing', 'logicanvas-auctions' ),
				'eyebrow' => __( 'Update auction', 'logicanvas-auctions' ),
				'seller'  => true,
			),
			self::ADDRESS   => array(
				'title'   => __( 'Address', 'logicanvas-auctions' ),
				'eyebrow' => __( 'My account', 'logicanvas-auctions' ),
			),
		);
	}

	public static function set_context( string $mode ): void {
		self::$context = self::MODE_BUYER === $mode ? self::MODE_BUYER : self::MODE_SELLER;
	}

	public static function context(): string {
		return self::$context;
	}

	public static function is_buyer(): bool {
		return self::MODE_BUYER === self::$context;
	}

	public static function hub( ?string $mode = null ): string {
		$mode = $mode ?: self::$context;
		$keys = self::MODE_BUYER === $mode
			? array( 'bidder', 'my_bids', 'my_wins' )
			: array( 'holder', 'submit' );

		foreach ( $keys as $key ) {
			$url = PluginPages::url( $key );
			if ( $url ) {
				return $url;
			}
		}

		$permalink = get_permalink();
		return $permalink ? (string) $permalink : home_url( '/' );
	}

	/**
	 * Frontend dashboard for a user after login or when wp-admin is blocked.
	 * Pure bidders land on the bidder dashboard; approved holders land on the seller hub.
	 */
	public static function dashboard_for_user( int $user_id ): string {
		$is_holder = user_can( $user_id, Config::CAP_CREATE_AUCTIONS );
		$mode      = $is_holder ? self::MODE_SELLER : self::MODE_BUYER;
		$url       = self::hub( $mode );

		if ( ! $is_holder ) {
			$bidder = PluginPages::url( 'bidder' );
			if ( $bidder ) {
				$url = $bidder;
			}
		}

		$url = is_string( $url ) && '' !== $url ? $url : home_url( '/' );

		return (string) apply_filters( 'wcap_dashboard_url_for_user', $url, $user_id, $mode );
	}

	/**
	 * @param array<string, scalar> $args Extra query args (e.g. auction_id).
	 */
	public static function url( string $view, ?string $mode = null, array $args = array() ): string {
		$mode = $mode ?: self::$context;
		$can  = self::MODE_SELLER === $mode;
		$view = self::normalize( $view, self::OVERVIEW, $can );
		$hub  = self::hub( $mode );

		if ( self::OVERVIEW === $view ) {
			$url = remove_query_arg( self::QUERY_VAR, $hub );
		} else {
			$url = add_query_arg( self::QUERY_VAR, $view, $hub );
		}

		if ( $args ) {
			$url = add_query_arg( $args, $url );
		}

		return $url;
	}

	public function current( string $default = self::OVERVIEW, bool $can_sell = true ): string {
		$requested = get_query_var( self::QUERY_VAR, '' );
		if ( ! is_string( $requested ) || '' === $requested ) {
			$requested = isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::QUERY_VAR ] ) ) : $default; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} else {
			$requested = sanitize_key( $requested );
		}

		return self::normalize( $requested, $default, $can_sell );
	}

	public static function page_title( string $view ): string {
		$catalog = self::catalog();
		return $catalog[ $view ]['title'] ?? __( 'Account', 'logicanvas-auctions' );
	}

	public static function eyebrow( string $view ): string {
		$catalog = self::catalog();
		return $catalog[ $view ]['eyebrow'] ?? __( 'Account', 'logicanvas-auctions' );
	}

	public static function heading( string $view, array $profile ): string {
		if ( self::OVERVIEW === $view ) {
			return (string) ( $profile['greeting'] ?? __( 'Welcome back.', 'logicanvas-auctions' ) );
		}

		return self::page_title( $view );
	}

	public static function normalize( string $view, string $default = self::OVERVIEW, bool $can_sell = true ): string {
		$catalog = self::catalog();
		if ( ! isset( $catalog[ $view ] ) ) {
			$view = $default;
		}

		if ( ! $can_sell && in_array( $view, self::SELLER_VIEWS, true ) ) {
			return self::OVERVIEW;
		}

		return $view;
	}

	private function is_account_request(): bool {
		if ( is_admin() || ! is_singular( 'page' ) ) {
			return false;
		}

		$page_id = (int) get_queried_object_id();
		$pages   = get_option( Config::OPTION_PAGES, array() );
		if ( ! is_array( $pages ) ) {
			return false;
		}

		$account_keys = array( 'holder', 'bidder', 'my_bids', 'my_wins', 'submit', 'pay' );
		foreach ( $account_keys as $key ) {
			if ( $page_id === (int) ( $pages[ $key ] ?? 0 ) ) {
				return true;
			}
		}

		return false;
	}
}
