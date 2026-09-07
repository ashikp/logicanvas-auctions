<?php
/**
 * Account dashboard shell.
 *
 * Expects $wcap keys: view, heading, eyebrow, action, holder, bidder, profile,
 * can_sell, show_seller, is_admin, addresses, seller_url, buyer_url.
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcap_holder      = is_array( $wcap['holder'] ?? null ) ? $wcap['holder'] : array();
$wcap_bidder      = is_array( $wcap['bidder'] ?? null ) ? $wcap['bidder'] : array();
$wcap_profile     = is_array( $wcap['profile'] ?? null ) ? $wcap['profile'] : array();
$wcap_addresses   = is_array( $wcap['addresses'] ?? null ) ? $wcap['addresses'] : array();
$wcap_counts      = is_array( $wcap_holder['counts'] ?? null ) ? $wcap_holder['counts'] : array();
$wcap_bcounts     = is_array( $wcap_bidder['counts'] ?? null ) ? $wcap_bidder['counts'] : array();
$wcap_show_seller = ! empty( $wcap['show_seller'] );
$wcap_can_sell    = ! empty( $wcap['can_sell'] );
$wcap_is_admin    = ! empty( $wcap['is_admin'] );
$wcap_view        = (string) ( $wcap['view'] ?? 'overview' );
$wcap_heading     = (string) ( $wcap['heading'] ?? '' );
$wcap_eyebrow     = (string) ( $wcap['eyebrow'] ?? '' );
$wcap_action      = is_array( $wcap['action'] ?? null ) ? $wcap['action'] : null;
$wcap_site        = (string) ( $wcap_profile['site_name'] ?? get_bloginfo( 'name' ) );
$wcap_seller_url  = (string) ( $wcap['seller_url'] ?? '' );
$wcap_buyer_url   = (string) ( $wcap['buyer_url'] ?? '' );
?>
<div class="dashboard-page wcap-account-dash" data-wcap-ssr="1" data-wcap-view="<?php echo esc_attr( $wcap_view ); ?>">
	<div class="dashboard-wrap">
		<?php
		\LogicanvasAuctions\Frontend\TemplateLoader::include_partial(
			'partials/account-sidebar',
			array(
				'profile'     => $wcap_profile,
				'active'      => $wcap_view,
				'role_label'  => $wcap_show_seller
					? ( $wcap_is_admin ? __( 'Buyer · Seller approved', 'logicanvas-auctions' ) : __( 'Seller approved', 'logicanvas-auctions' ) )
					: __( 'Registered bidder', 'logicanvas-auctions' ),
				'show_seller' => $wcap_show_seller,
				'seller_url'  => $wcap_seller_url,
				'buyer_url'   => $wcap_buyer_url,
				'badges'      => array(
					'listings' => (int) ( $wcap_counts['total'] ?? 0 ),
					'leading'  => (int) ( $wcap_bcounts['leading'] ?? 0 ),
					'watch'    => (int) ( $wcap_holder['watch_count'] ?? $wcap_bidder['watch_count'] ?? 0 ),
					'due'      => (int) ( $wcap_bcounts['due'] ?? 0 ),
				),
			)
		);
		?>
		<section class="dashboard-main">
			<header>
				<div>
					<small><?php echo esc_html( strtoupper( $wcap_site ) ); ?> <?php echo esc_html( strtoupper( (string) $wcap_eyebrow ) ); ?></small>
					<h1><?php echo esc_html( (string) $wcap_heading ); ?></h1>
				</div>
				<?php if ( is_array( $wcap_action ) && ! empty( $wcap_action['url'] ) ) : ?>
					<a class="wcap-btn" href="<?php echo esc_url( (string) $wcap_action['url'] ); ?>"><?php echo esc_html( (string) $wcap_action['label'] ); ?></a>
				<?php endif; ?>
			</header>
			<?php
			\LogicanvasAuctions\Frontend\TemplateLoader::include_partial(
				'account/' . $wcap_view,
				array(
					'holder'      => $wcap_holder,
					'bidder'      => $wcap_bidder,
					'profile'     => $wcap_profile,
					'can_sell'    => $wcap_can_sell,
					'show_seller' => $wcap_show_seller,
					'is_admin'    => $wcap_is_admin,
					'addresses'   => $wcap_addresses,
					'edit'        => is_array( $wcap['edit'] ?? null ) ? $wcap['edit'] : null,
					'auction_id'  => (int) ( $wcap['auction_id'] ?? 0 ),
				)
			);
			?>
		</section>
	</div>
</div>
