<?php
/**
 * Account sidebar with one URL per view.
 *
 * Expects $wcap keys: profile, active, role_label, badges, show_seller, seller_url, buyer_url.
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcap_profile     = is_array( $wcap['profile'] ?? null ) ? $wcap['profile'] : array();
$wcap_urls        = is_array( $wcap_profile['urls'] ?? null ) ? $wcap_profile['urls'] : array();
$wcap_active      = (string) ( $wcap['active'] ?? \LogicanvasAuctions\Frontend\Dashboards\AccountRouter::OVERVIEW );
$wcap_role_label  = (string) ( $wcap['role_label'] ?? '' );
$wcap_badges      = is_array( $wcap['badges'] ?? null ) ? $wcap['badges'] : array();
$wcap_show_seller = ! empty( $wcap['show_seller'] );
$wcap_seller_url  = (string) ( $wcap['seller_url'] ?? '' );
$wcap_buyer_url   = (string) ( $wcap['buyer_url'] ?? '' );
$wcap_site        = (string) ( $wcap_profile['site_name'] ?? get_bloginfo( 'name' ) );
$wcap_router      = \LogicanvasAuctions\Frontend\Dashboards\AccountRouter::class;
// Edit listing uses its own view but should highlight Listings in the nav.
$wcap_nav_active  = \LogicanvasAuctions\Frontend\Dashboards\AccountRouter::EDIT === $wcap_active
	? \LogicanvasAuctions\Frontend\Dashboards\AccountRouter::LISTINGS
	: $wcap_active;

$wcap_item = static function ( string $wcap_key, string $wcap_label, string $wcap_active, int $wcap_count = 0, string $wcap_class = '' ) use ( $wcap_router ): void {
	$wcap_cls = trim( ( $wcap_key === $wcap_active ? 'active ' : '' ) . $wcap_class );
	echo '<a class="' . esc_attr( $wcap_cls ) . '" href="' . esc_url( $wcap_router::url( $wcap_key ) ) . '">' . esc_html( $wcap_label );
	if ( $wcap_count > 0 ) {
		echo ' <em>' . esc_html( (string) $wcap_count ) . '</em>';
	}
	echo '</a>';
};
?>
<aside class="dashboard-side">
	<p class="dashboard-brand"><?php echo esc_html( $wcap_site ); ?><small><?php esc_html_e( 'Account', 'logicanvas-auctions' ); ?></small></p>
	<div class="account-person">
		<span><?php echo esc_html( (string) ( $wcap_profile['initials'] ?? 'A' ) ); ?></span>
		<div>
			<b><?php echo esc_html( (string) ( $wcap_profile['name'] ?? '' ) ); ?></b>
			<small><?php echo esc_html( $wcap_role_label ); ?></small>
		</div>
	</div>
	<nav>
		<?php
		$wcap_item( $wcap_router::OVERVIEW, __( 'Overview', 'logicanvas-auctions' ), $wcap_nav_active );
		$wcap_item( $wcap_router::BIDS, __( 'My bids', 'logicanvas-auctions' ), $wcap_nav_active, (int) ( $wcap_badges['leading'] ?? 0 ) );
		$wcap_item( $wcap_router::WATCHLIST, __( 'Watchlist', 'logicanvas-auctions' ), $wcap_nav_active, (int) ( $wcap_badges['watch'] ?? 0 ) );
		$wcap_item( $wcap_router::PURCHASES, __( 'Purchases', 'logicanvas-auctions' ), $wcap_nav_active, (int) ( $wcap_badges['due'] ?? 0 ) );
		$wcap_item( $wcap_router::PICKUP, __( 'Pickup & shipping', 'logicanvas-auctions' ), $wcap_nav_active );
		if ( $wcap_show_seller ) :
			echo '<span>' . esc_html__( 'Seller tools', 'logicanvas-auctions' ) . '</span>';
			$wcap_item( $wcap_router::LISTINGS, __( 'Listings', 'logicanvas-auctions' ), $wcap_nav_active, (int) ( $wcap_badges['listings'] ?? 0 ) );
			$wcap_item( $wcap_router::ORDERS, __( 'Orders', 'logicanvas-auctions' ), $wcap_nav_active );
			$wcap_item( $wcap_router::PAYOUTS, __( 'Payouts', 'logicanvas-auctions' ), $wcap_nav_active );
			$wcap_item( $wcap_router::CREATE, __( 'Create listing', 'logicanvas-auctions' ), $wcap_nav_active, 0, 'wcap-side-create' );
		endif;
		echo '<span>' . esc_html__( 'My account', 'logicanvas-auctions' ) . '</span>';
		$wcap_item( $wcap_router::ADDRESS, __( 'Address', 'logicanvas-auctions' ), $wcap_nav_active );
		if ( $wcap_seller_url ) :
			echo '<a class="wcap-side-switch" href="' . esc_url( $wcap_seller_url ) . '">' . esc_html__( 'Seller dashboard →', 'logicanvas-auctions' ) . '</a>';
		endif;
		if ( $wcap_buyer_url ) :
			echo '<a class="wcap-side-switch" href="' . esc_url( $wcap_buyer_url ) . '">' . esc_html__( 'Bidder dashboard →', 'logicanvas-auctions' ) . '</a>';
		endif;
		?>
	</nav>
	<?php if ( ! empty( $wcap_urls['archive'] ) ) : ?>
		<a class="wcap-side-return" href="<?php echo esc_url( (string) $wcap_urls['archive'] ); ?>"><?php esc_html_e( '← Return to auction hall', 'logicanvas-auctions' ); ?></a>
	<?php endif; ?>
	<a class="wcap-side-logout" href="<?php echo esc_url( wp_logout_url( \LogicanvasAuctions\Frontend\PluginPages::url( 'login' ) ?: home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Log out', 'logicanvas-auctions' ); ?></a>
</aside>
