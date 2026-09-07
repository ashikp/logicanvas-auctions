<?php
/**
 * Frontend / admin assets. Loaded only on relevant screens.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Frontend;

use LogicanvasAuctions\Admin\Settings;
use LogicanvasAuctions\Config;

final class Assets {

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
		add_action( 'wp_footer', array( $this, 'print_late_style' ), 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin' ) );
	}

	/**
	 * Print CSS enqueued from a shortcode after wp_head has already run.
	 */
	public function print_late_style(): void {
		if ( wp_style_is( 'wcap-frontend', 'enqueued' ) && ! wp_style_is( 'wcap-frontend', 'done' ) ) {
			wp_print_styles( 'wcap-frontend' );
		}
	}

	public function maybe_enqueue(): void {
		if ( ! $this->needs_assets() ) {
			return;
		}

		self::register_qrcode();
		self::attach_qrcode_dependency();
		self::enqueue_frontend();
	}

	/**
	 * Register handles so Elementor widgets can declare style/script dependencies.
	 */
	public static function register_frontend(): void {
		if ( ! wp_style_is( 'wcap-frontend', 'registered' ) ) {
			$deps = array();
			if ( wp_style_is( 'elementor-frontend', 'registered' ) ) {
				$deps[] = 'elementor-frontend';
			}

			$css = Config::plugin_dir() . 'assets/dist/css/frontend.css';
			wp_register_style(
				'wcap-frontend',
				plugins_url( 'assets/dist/css/frontend.css', Config::plugin_file() ),
				$deps,
				is_readable( $css ) ? (string) filemtime( $css ) : Config::VERSION
			);
		}

		if ( ! wp_script_is( 'wcap-frontend', 'registered' ) ) {
			$js_deps = array();
			if ( wp_script_is( 'jquery', 'registered' ) ) {
				$js_deps[] = 'jquery';
			}

			$js = Config::plugin_dir() . 'assets/dist/js/frontend.js';
			wp_register_script(
				'wcap-frontend',
				plugins_url( 'assets/dist/js/frontend.js', Config::plugin_file() ),
				$js_deps,
				is_readable( $js ) ? (string) filemtime( $js ) : Config::VERSION,
				true
			);
		}
	}

	/**
	 * Enqueue frontend CSS/JS. Safe to call from shortcodes after wp_head
	 * (scripts print in the footer).
	 */
	public static function enqueue_frontend(): void {
		self::register_frontend();

		if ( ! wp_style_is( 'wcap-frontend', 'enqueued' ) && ! wp_style_is( 'wcap-frontend', 'done' ) ) {
			wp_enqueue_style( 'wcap-frontend' );
		}

		if ( ! wp_script_is( 'wcap-frontend', 'enqueued' ) && ! wp_script_is( 'wcap-frontend', 'done' ) ) {
			self::enqueue_script();
		}
	}

	/**
	 * Load Classic Editor (TinyMCE) for frontend listing forms.
	 */
	public static function enqueue_classic_editor(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! function_exists( 'wp_enqueue_editor' ) ) {
			return;
		}

		wp_enqueue_editor();
		wp_enqueue_media();
		self::enqueue_media_library();
	}

	/**
	 * Load wp.media only on listing forms. Calling it on every frontend page
	 * can fatal when get_current_screen() is null (Elementor/PHP 8.4).
	 */
	public static function enqueue_media_library(): void {
		if ( ! is_user_logged_in() || ! current_user_can( 'upload_files' ) ) {
			return;
		}

		try {
			wp_enqueue_media();
		} catch ( \Throwable $e ) {
			unset( $e );
			return;
		}

		self::register_frontend();
		self::attach_media_dependency();
	}

	/**
	 * Register QR code library used on single auction Share tabs.
	 */
	public static function register_qrcode(): void {
		if ( wp_script_is( 'wcap-qrcode', 'registered' ) ) {
			return;
		}

		$js = Config::plugin_dir() . 'assets/dist/js/qrcode.min.js';
		wp_register_script(
			'wcap-qrcode',
			plugins_url( 'assets/dist/js/qrcode.min.js', Config::plugin_file() ),
			array(),
			is_readable( $js ) ? (string) filemtime( $js ) : Config::VERSION,
			true
		);
	}

	private static function attach_qrcode_dependency(): void {
		self::register_frontend();
		self::register_qrcode();

		$scripts = wp_scripts();
		if ( ! isset( $scripts->registered['wcap-frontend'] ) ) {
			return;
		}

		$deps = $scripts->registered['wcap-frontend']->deps;
		if ( ! in_array( 'wcap-qrcode', $deps, true ) ) {
			$scripts->registered['wcap-frontend']->deps[] = 'wcap-qrcode';
		}
	}

	/**
	 * Ensure the frontend script waits for wp.media when the library is loaded.
	 */
	private static function attach_media_dependency(): void {
		if ( ! wp_script_is( 'media-editor', 'registered' ) || ! wp_script_is( 'wcap-frontend', 'registered' ) ) {
			return;
		}

		$scripts = wp_scripts();
		if ( ! isset( $scripts->registered['wcap-frontend'] ) ) {
			return;
		}

		$deps = $scripts->registered['wcap-frontend']->deps;
		if ( ! in_array( 'media-editor', $deps, true ) ) {
			$scripts->registered['wcap-frontend']->deps[] = 'media-editor';
		}
		if ( wp_script_is( 'jquery', 'registered' ) && ! in_array( 'jquery', $deps, true ) ) {
			$scripts->registered['wcap-frontend']->deps[] = 'jquery';
		}
	}

	private static function enqueue_script(): void {
		self::register_frontend();
		self::register_qrcode();
		self::attach_qrcode_dependency();
		wp_enqueue_script( 'wcap-frontend' );

		if ( wp_scripts()->get_data( 'wcap-frontend', 'data' ) ) {
			return;
		}

		$settings = Settings::get();
		wp_localize_script(
			'wcap-frontend',
			'wcapSettings',
			array(
				'restUrl'   => esc_url_raw( rest_url( Config::REST_NAMESPACE . '/' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'userId'    => get_current_user_id(),
				'pollLive'  => (int) $settings['live_poll_interval_ms'],
				'pollLobby' => (int) $settings['lobby_poll_interval_ms'],
				'canUpload' => current_user_can( 'upload_files' ),
				'i18n'      => array(
					'confirmBid'     => __( 'Place this bid?', 'logicanvas-auctions' ),
					'leading'        => __( 'You are leading', 'logicanvas-auctions' ),
					'outbid'         => __( 'You have been outbid', 'logicanvas-auctions' ),
					'error'          => __( 'Something went wrong.', 'logicanvas-auctions' ),
					'login'          => __( 'Log in to bid.', 'logicanvas-auctions' ),
					'selectFeatured' => __( 'Select featured image', 'logicanvas-auctions' ),
					'selectGallery'  => __( 'Select gallery images', 'logicanvas-auctions' ),
					'useImage'       => __( 'Use this image', 'logicanvas-auctions' ),
					'useImages'      => __( 'Use these images', 'logicanvas-auctions' ),
					'mediaMissing'   => __( 'WordPress media library could not be loaded.', 'logicanvas-auctions' ),
					'noBidsYet'      => __( 'No bids yet. Be the first on the board.', 'logicanvas-auctions' ),
					'leader'         => __( 'Leader', 'logicanvas-auctions' ),
					'winner'         => __( 'Win', 'logicanvas-auctions' ),
					/* translators: %d: number of bids */
					'bidSingular'    => __( '%d bid placed', 'logicanvas-auctions' ),
					/* translators: %d: number of bids */
					'bidPlural'      => __( '%d bids placed', 'logicanvas-auctions' ),
					'acceptBid'      => __( 'Sell to the current highest bidder at this price? The auction will end now and they will be asked to pay.', 'logicanvas-auctions' ),
					'acceptOk'       => __( 'Highest bid accepted. The winner can now pay.', 'logicanvas-auctions' ),
					'orderUpdated'   => __( 'Order updated.', 'logicanvas-auctions' ),
					'methodSaved'    => __( 'Payment method saved.', 'logicanvas-auctions' ),
					'payoutRequested'=> __( 'Payout requested.', 'logicanvas-auctions' ),
					'payoutCancelled'=> __( 'Payout request cancelled.', 'logicanvas-auctions' ),
					'copy'           => __( 'Copy', 'logicanvas-auctions' ),
					'copied'         => __( 'Copied', 'logicanvas-auctions' ),
				),
			)
		);
	}

	public function admin( string $hook ): void {
		if ( ! str_contains( $hook, 'wcap' ) ) {
			return;
		}

		$css = Config::plugin_dir() . 'assets/dist/css/admin.css';
		wp_enqueue_style(
			'wcap-admin',
			plugins_url( 'assets/dist/css/admin.css', Config::plugin_file() ),
			array(),
			is_readable( $css ) ? (string) filemtime( $css ) : Config::VERSION
		);
	}

	private function needs_assets(): bool {
		if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		if ( is_singular( Config::CPT ) ) {
			return true;
		}

		$page_id = (int) get_queried_object_id();
		if ( $page_id > 0 ) {
			$pages = get_option( Config::OPTION_PAGES, array() );
			if ( is_array( $pages ) ) {
				foreach ( $pages as $assigned_id ) {
					if ( (int) $assigned_id === $page_id ) {
						return true;
					}
				}
			}
		}

		global $post;
		if ( $post instanceof \WP_Post ) {
			if ( isset( $post->post_content ) && preg_match( '/\[wcap_/', $post->post_content ) ) {
				return true;
			}

			$elementor = get_post_meta( $post->ID, '_elementor_data', true );
			if ( is_string( $elementor ) && $elementor !== '' && ( str_contains( $elementor, 'wcap-' ) || str_contains( $elementor, 'wcap_' ) ) ) {
				return true;
			}
		}

		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return true;
		}

		return (bool) apply_filters( 'wcap_enqueue_frontend_assets', false );
	}
}
