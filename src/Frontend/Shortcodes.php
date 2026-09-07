<?php
/**
 * Shortcodes.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Frontend;

use LogicanvasAuctions\Application\AuctionPresenter;
use LogicanvasAuctions\Config;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository;

final class Shortcodes {

	public function register(): void {
		( new Dashboards\AccountRouter() )->register();

		add_shortcode( 'wcap_auction_grid', array( $this, 'grid' ) );
		add_shortcode( 'wcap_single_auction', array( $this, 'single' ) );
		add_shortcode( 'wcap_live_room', array( $this, 'live' ) );
		add_shortcode( 'wcap_live_host', array( $this, 'host' ) );
		add_shortcode( 'wcap_submit_auction', array( $this, 'submit' ) );
		add_shortcode( 'wcap_holder_dashboard', array( $this, 'holder' ) );
		add_shortcode( 'wcap_bidder_dashboard', array( $this, 'bidder' ) );
		add_shortcode( 'wcap_my_bids', array( $this, 'my_bids' ) );
		add_shortcode( 'wcap_my_wins', array( $this, 'my_wins' ) );
		add_shortcode( 'wcap_pay_award', array( $this, 'pay' ) );
		add_shortcode( 'wcap_holder_apply', array( $this, 'apply' ) );
		add_shortcode( 'wcap_countdown', array( $this, 'countdown' ) );
		add_shortcode( 'wcap_bid_panel', array( $this, 'bid_panel' ) );
		add_shortcode( 'wcap_bid_history', array( $this, 'bid_history' ) );
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public function grid( $atts ): string {
		Assets::enqueue_frontend();
		$atts = shortcode_atts(
			array(
				'type'     => '',
				'state'    => 'active,scheduled,live,lobby',
				'per_page' => '12',
			),
			is_array( $atts ) ? $atts : array(),
			'wcap_auction_grid'
		);

		$type = (string) $atts['type'];
		if ( '' === $type && isset( $_GET['wcap_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$requested = sanitize_key( wp_unslash( (string) $_GET['wcap_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( in_array( $requested, array( 'timed', 'live' ), true ) ) {
				$type = $requested;
			}
		}

		$repo   = new WpdbAuctionRepository();
		$items  = $repo->query(
			array(
				'type'   => $type,
				'state'  => array_filter( explode( ',', $atts['state'] ) ),
				'limit'  => (int) $atts['per_page'],
			)
		);

		ob_start();
		TemplateLoader::render(
			'frontend/grid',
			array(
				'auctions'  => $items,
				'presenter' => new AuctionPresenter(),
				'filter'    => $type,
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public function single( $atts ): string {
		Assets::enqueue_frontend();
		$atts = shortcode_atts( array( 'id' => '0' ), is_array( $atts ) ? $atts : array(), 'wcap_single_auction' );
		$id   = (int) $atts['id'];
		if ( $id < 1 && is_singular( Config::CPT ) ) {
			$id = get_the_ID();
		}
		if ( $id < 1 ) {
			$from_get = filter_input( INPUT_GET, 'auction_id', FILTER_SANITIZE_NUMBER_INT );
			$id       = absint( is_string( $from_get ) || is_int( $from_get ) ? $from_get : 0 );
		}

		$auction = $id ? ( new WpdbAuctionRepository() )->find( $id ) : null;
		ob_start();
		TemplateLoader::render(
			'frontend/single',
			array(
				'auction'   => $auction,
				'presenter' => new AuctionPresenter(),
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public function live( $atts ): string {
		Assets::enqueue_frontend();
		$atts = shortcode_atts( array( 'id' => '0' ), is_array( $atts ) ? $atts : array(), 'wcap_live_room' );
		$id   = (int) $atts['id'];
		if ( $id < 1 ) {
			$from_get = filter_input( INPUT_GET, 'auction_id', FILTER_SANITIZE_NUMBER_INT );
			$id       = absint( is_string( $from_get ) || is_int( $from_get ) ? $from_get : 0 );
		}
		$auction = $id ? ( new WpdbAuctionRepository() )->find( $id ) : null;
		ob_start();
		TemplateLoader::render( 'live/room', array( 'auction' => $auction, 'presenter' => new AuctionPresenter() ) );
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public function host( $atts ): string {
		Assets::enqueue_frontend();
		$atts = shortcode_atts( array( 'id' => '0' ), is_array( $atts ) ? $atts : array(), 'wcap_live_host' );
		$id   = (int) $atts['id'];
		if ( $id < 1 ) {
			$from_get = filter_input( INPUT_GET, 'auction_id', FILTER_SANITIZE_NUMBER_INT );
			$id       = absint( is_string( $from_get ) || is_int( $from_get ) ? $from_get : 0 );
		}
		$auction = $id ? ( new WpdbAuctionRepository() )->find( $id ) : null;
		ob_start();
		TemplateLoader::render( 'live/host', array( 'auction' => $auction, 'presenter' => new AuctionPresenter() ) );
		return (string) ob_get_clean();
	}

	public function submit(): string {
		return $this->safe_render(
			static function (): void {
				Assets::enqueue_media_library();
				TemplateLoader::render( 'holder/submit', array() );
			}
		);
	}

	public function holder(): string {
		return $this->account_view( Dashboards\AccountRouter::OVERVIEW, Dashboards\AccountRouter::MODE_SELLER );
	}

	public function bidder(): string {
		return $this->account_view( Dashboards\AccountRouter::OVERVIEW, Dashboards\AccountRouter::MODE_BUYER );
	}

	public function my_bids(): string {
		return $this->account_view( Dashboards\AccountRouter::BIDS, Dashboards\AccountRouter::MODE_BUYER );
	}

	public function my_wins(): string {
		return $this->account_view( Dashboards\AccountRouter::PURCHASES, Dashboards\AccountRouter::MODE_BUYER );
	}

	private function account_view( string $default, string $mode ): string {
		return $this->safe_render(
			static function () use ( $default, $mode ): void {
				( new Dashboards\AccountPage() )->render( $default, $mode );
			}
		);
	}

	/**
	 * Render a frontend view without letting a throwable blank the whole page.
	 */
	private function safe_render( callable $callback ): string {
		Assets::enqueue_frontend();

		try {
			ob_start();
			$callback();
			return (string) ob_get_clean();
		} catch ( \Throwable $e ) {
			if ( ob_get_level() > 0 ) {
				ob_end_clean();
			}

			$detail = current_user_can( 'manage_options' )
				? $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')'
				: __( 'This page could not be loaded. Please try again or contact the site administrator.', 'logicanvas-auctions' );

			return '<div class="wcap-root wcap-login-gate"><p>' . esc_html( $detail ) . '</p></div>';
		}
	}

	public function pay(): string {
		Assets::enqueue_frontend();
		ob_start();
		TemplateLoader::render( 'frontend/pay', array() );
		return (string) ob_get_clean();
	}

	public function apply(): string {
		Assets::enqueue_frontend();
		ob_start();
		TemplateLoader::render( 'holder/apply', array() );
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public function countdown( $atts ): string {
		Assets::enqueue_frontend();
		$atts = shortcode_atts( array( 'id' => '0' ), is_array( $atts ) ? $atts : array(), 'wcap_countdown' );
		$id   = (int) $atts['id'] ?: ( is_singular( Config::CPT ) ? (int) get_the_ID() : 0 );
		return '<div class="wcap-countdown" data-auction-id="' . esc_attr( (string) $id ) . '"></div>';
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public function bid_panel( $atts ): string {
		return $this->single( $atts );
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public function bid_history( $atts ): string {
		Assets::enqueue_frontend();
		$atts    = shortcode_atts( array( 'id' => '0' ), is_array( $atts ) ? $atts : array(), 'wcap_bid_history' );
		$id      = (int) $atts['id'] ?: ( is_singular( Config::CPT ) ? (int) get_the_ID() : 0 );
		$auction = $id ? ( new WpdbAuctionRepository() )->find( $id ) : null;
		ob_start();
		TemplateLoader::render( 'frontend/bid-history', array( 'auction' => $auction ) );
		return (string) ob_get_clean();
	}
}
