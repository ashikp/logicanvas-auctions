<?php
/**
 * WooCommerce My Account branding and auction endpoints.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\WooCommerce;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Frontend\Assets;
use LogicanvasAuctions\Frontend\Dashboards\AccountRouter;
use LogicanvasAuctions\Frontend\PluginPages;

final class MyAccount {

	public function register(): void {
		if ( ! get_option( 'wcap_wc_endpoints_v1' ) ) {
			update_option( 'wcap_flush_wc_endpoints', '1', false );
			update_option( 'wcap_wc_endpoints_v1', '1', false );
		}

		add_action( 'init', array( $this, 'endpoints' ) );
		add_action( 'init', array( $this, 'maybe_flush' ), 99 );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu_items' ), 40 );
		add_action( 'woocommerce_account_auction-dashboard_endpoint', array( $this, 'render_dashboard' ) );
		add_action( 'woocommerce_account_auction-bids_endpoint', array( $this, 'render_bids' ) );
		add_action( 'woocommerce_account_auction-wins_endpoint', array( $this, 'render_wins' ) );
		add_action( 'woocommerce_account_auction-listings_endpoint', array( $this, 'render_listings' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 30 );
		add_action( 'woocommerce_account_dashboard', array( $this, 'dashboard_intro' ), 5 );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'query_vars' ) );
	}

	public function endpoints(): void {
		add_rewrite_endpoint( 'auction-dashboard', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'auction-bids', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'auction-wins', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'auction-listings', EP_ROOT | EP_PAGES );
	}

	public function maybe_flush(): void {
		if ( '1' !== (string) get_option( 'wcap_flush_wc_endpoints', '' ) ) {
			return;
		}
		flush_rewrite_rules( false );
		delete_option( 'wcap_flush_wc_endpoints' );
	}

	/**
	 * @param array<string, string> $vars
	 * @return array<string, string>
	 */
	public function query_vars( array $vars ): array {
		$vars['auction-dashboard'] = 'auction-dashboard';
		$vars['auction-bids']      = 'auction-bids';
		$vars['auction-wins']      = 'auction-wins';
		$vars['auction-listings']  = 'auction-listings';
		return $vars;
	}

	/**
	 * @param array<string, string> $items
	 * @return array<string, string>
	 */
	public function menu_items( array $items ): array {
		$insert = array(
			'auction-dashboard' => __( 'Auction hub', 'logicanvas-auctions' ),
			'auction-bids'      => __( 'My bids', 'logicanvas-auctions' ),
			'auction-wins'      => __( 'Won auctions', 'logicanvas-auctions' ),
			'auction-listings'  => __( 'My listings', 'logicanvas-auctions' ),
		);

		$out = array();
		foreach ( $items as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'dashboard' === $key ) {
				foreach ( $insert as $slug => $title ) {
					$out[ $slug ] = $title;
				}
			}
		}

		foreach ( $insert as $slug => $title ) {
			if ( ! isset( $out[ $slug ] ) ) {
				$out[ $slug ] = $title;
			}
		}

		return $out;
	}

	public function dashboard_intro(): void {
		$user = wp_get_current_user();
		$arch = PluginPages::url( 'archive' );
		$hub  = AccountRouter::url( AccountRouter::OVERVIEW );
		?>
		<div class="wcap-wc-intro">
			<div class="wcap-wc-intro__copy">
				<p class="wcap-wc-intro__kicker"><?php echo esc_html( Config::PRODUCT_NAME ); ?></p>
				<h2>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: display name */
							__( 'Welcome back, %s', 'logicanvas-auctions' ),
							$user->display_name ? $user->display_name : __( 'bidder', 'logicanvas-auctions' )
						)
					);
					?>
				</h2>
				<p><?php esc_html_e( 'Track bids, pay for wins, and manage seller listings without leaving your account.', 'logicanvas-auctions' ); ?></p>
			</div>
			<div class="wcap-wc-intro__actions">
				<?php if ( $arch ) : ?>
					<a class="wcap-btn" href="<?php echo esc_url( $arch ); ?>"><?php esc_html_e( 'Browse auctions', 'logicanvas-auctions' ); ?></a>
				<?php endif; ?>
				<?php if ( $hub ) : ?>
					<a class="wcap-btn wcap-btn--secondary" href="<?php echo esc_url( $hub ); ?>"><?php esc_html_e( 'Open auction dashboard', 'logicanvas-auctions' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public function render_dashboard(): void {
		$this->render_panel(
			__( 'Auction hub', 'logicanvas-auctions' ),
			__( 'Jump into bidding, purchases, and seller tools.', 'logicanvas-auctions' ),
			array(
				array( __( 'My bids', 'logicanvas-auctions' ), AccountRouter::url( AccountRouter::BIDS ), __( 'Lots you are leading or have bid on.', 'logicanvas-auctions' ) ),
				array( __( 'Won auctions', 'logicanvas-auctions' ), AccountRouter::url( AccountRouter::PURCHASES ), __( 'Pay outstanding awards and view wins.', 'logicanvas-auctions' ) ),
				array( __( 'My listings', 'logicanvas-auctions' ), AccountRouter::url( AccountRouter::LISTINGS, AccountRouter::MODE_SELLER ), __( 'Create lots and accept current bids from your seller dashboard.', 'logicanvas-auctions' ) ),
				array( __( 'Browse catalog', 'logicanvas-auctions' ), PluginPages::url( 'archive' ), __( 'Find timed and live auctions.', 'logicanvas-auctions' ) ),
			)
		);
	}

	public function render_bids(): void {
		$url = AccountRouter::url( AccountRouter::BIDS );
		$this->redirect_or_panel( $url, __( 'My bids', 'logicanvas-auctions' ), __( 'Open your full bidder dashboard to manage active bids.', 'logicanvas-auctions' ) );
	}

	public function render_wins(): void {
		$url = AccountRouter::url( AccountRouter::PURCHASES );
		$this->redirect_or_panel( $url, __( 'Won auctions', 'logicanvas-auctions' ), __( 'Open purchases to pay for won lots.', 'logicanvas-auctions' ) );
	}

	public function render_listings(): void {
		$url = AccountRouter::url( AccountRouter::LISTINGS, AccountRouter::MODE_SELLER );
		$this->redirect_or_panel( $url, __( 'My listings', 'logicanvas-auctions' ), __( 'Open seller listings to accept bids and manage lots.', 'logicanvas-auctions' ) );
	}

	/**
	 * @param array<int, array{0:string,1:string,2:string}> $links
	 */
	private function render_panel( string $title, string $lede, array $links ): void {
		echo '<div class="wcap-wc-panel">';
		echo '<h2>' . esc_html( $title ) . '</h2>';
		echo '<p class="wcap-wc-panel__lede">' . esc_html( $lede ) . '</p>';
		echo '<div class="wcap-wc-cards">';
		foreach ( $links as $link ) {
			if ( empty( $link[1] ) ) {
				continue;
			}
			echo '<a class="wcap-wc-card" href="' . esc_url( (string) $link[1] ) . '">';
			echo '<strong>' . esc_html( (string) $link[0] ) . '</strong>';
			echo '<span>' . esc_html( (string) $link[2] ) . '</span>';
			echo '</a>';
		}
		echo '</div></div>';
	}

	private function redirect_or_panel( string $url, string $title, string $lede ): void {
		if ( $url ) {
			echo '<div class="wcap-wc-panel">';
			echo '<h2>' . esc_html( $title ) . '</h2>';
			echo '<p class="wcap-wc-panel__lede">' . esc_html( $lede ) . '</p>';
			echo '<p><a class="wcap-btn" href="' . esc_url( $url ) . '">' . esc_html__( 'Continue →', 'logicanvas-auctions' ) . '</a></p>';
			echo '</div>';
			return;
		}

		echo '<div class="wcap-wc-panel"><p>' . esc_html__( 'Auction account pages are not configured yet. Ask an administrator to run Auctions → Setup.', 'logicanvas-auctions' ) . '</p></div>';
	}

	/**
	 * @param string[] $classes
	 * @return string[]
	 */
	public function body_class( array $classes ): array {
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			$classes[] = 'wcap-wc-account';
			$classes[] = 'woocommerce-account';
		}
		return $classes;
	}

	public function assets(): void {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}
		Assets::enqueue_frontend();
		// Override theme float/clearfix rules that fight the account layout.
		$css = '
			body.woocommerce-account.wcap-wc-account .woocommerce::before,
			body.woocommerce-account.wcap-wc-account .woocommerce::after{content:none!important;display:none!important}
			body.woocommerce-account.wcap-wc-account .woocommerce-MyAccount-navigation,
			body.woocommerce-account.wcap-wc-account .woocommerce-MyAccount-content{float:none!important}
			@media (min-width:782px){
				body.woocommerce-account.wcap-wc-account .woocommerce{display:flex!important;flex-wrap:nowrap;gap:1.25rem;align-items:flex-start}
				body.woocommerce-account.wcap-wc-account .woocommerce-MyAccount-navigation{flex:0 0 240px;width:240px!important;max-width:240px;margin:0!important}
				body.woocommerce-account.wcap-wc-account .woocommerce-MyAccount-content{flex:1 1 auto;width:auto!important;min-width:0;margin:0!important}
			}
		';
		wp_add_inline_style( 'wcap-frontend', $css );
	}
}
