<?php
/**
 * Account overview.
 *
 * Expects $wcap keys: holder, bidder, profile, show_seller.
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcap_holder       = is_array( $wcap['holder'] ?? null ) ? $wcap['holder'] : array();
$wcap_bidder       = is_array( $wcap['bidder'] ?? null ) ? $wcap['bidder'] : array();
$wcap_profile      = is_array( $wcap['profile'] ?? null ) ? $wcap['profile'] : array();
$wcap_counts       = is_array( $wcap_holder['counts'] ?? null ) ? $wcap_holder['counts'] : array();
$wcap_bcounts      = is_array( $wcap_bidder['counts'] ?? null ) ? $wcap_bidder['counts'] : array();
$wcap_show_seller  = ! empty( $wcap['show_seller'] );
$wcap_accept_ready = $wcap_show_seller && is_array( $wcap_holder['accept_ready'] ?? null ) ? $wcap_holder['accept_ready'] : array();
$wcap_featured     = $wcap_show_seller
	? ( is_array( $wcap_holder['featured'] ?? null ) ? $wcap_holder['featured'] : null )
	: ( is_array( $wcap_bidder['leading'][0] ?? null ) ? $wcap_bidder['leading'][0] : null );
$wcap_status       = (string) ( $wcap_holder['status'] ?? '' );
$wcap_is_admin     = ! empty( $wcap_holder['is_admin'] );

$wcap_status_labels = array(
	'pending'   => __( 'Application pending review', 'logicanvas-auctions' ),
	'approved'  => __( 'Approved auction holder', 'logicanvas-auctions' ),
	'rejected'  => __( 'Application rejected', 'logicanvas-auctions' ),
	'suspended' => __( 'Account suspended', 'logicanvas-auctions' ),
	''          => __( 'Registered bidder', 'logicanvas-auctions' ),
);
if ( $wcap_show_seller ) {
	$wcap_standing = $wcap_is_admin ? __( 'Administrator · seller approved', 'logicanvas-auctions' ) : ( $wcap_status_labels[ $wcap_status ] ?? $wcap_status_labels[''] );
	$wcap_notice   = __( 'Use the menu to manage listings, orders, and payouts. Open the bidder dashboard to bid and pay.', 'logicanvas-auctions' );
} else {
	$wcap_standing = __( 'Registered bidder', 'logicanvas-auctions' );
	$wcap_notice   = __( 'Track your bids, watchlist, purchases, and pickup from this account. Seller tools stay on the seller dashboard.', 'logicanvas-auctions' );
}
?>
<div class="dash-notice">
	<b><?php echo esc_html( $wcap_standing ); ?></b>
	<span><?php echo esc_html( $wcap_notice ); ?></span>
</div>
<?php
if ( $wcap_show_seller && $wcap_accept_ready ) {
	\LogicanvasAuctions\Frontend\TemplateLoader::include_partial(
		'partials/accept-ready',
		array( 'rows' => $wcap_accept_ready )
	);
}
?>
<div class="dash-stats">
	<article><small><?php esc_html_e( 'Active bids', 'logicanvas-auctions' ); ?></small><strong><?php echo esc_html( (string) ( $wcap_bcounts['leading'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Lots you are leading', 'logicanvas-auctions' ); ?></span></article>
	<article><small><?php esc_html_e( 'Watchlist', 'logicanvas-auctions' ); ?></small><strong><?php echo esc_html( (string) ( $wcap_holder['watch_count'] ?? $wcap_bidder['watch_count'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Saved lots', 'logicanvas-auctions' ); ?></span></article>
	<article><small><?php esc_html_e( 'Open invoices', 'logicanvas-auctions' ); ?></small><strong><?php echo esc_html( (string) ( $wcap_bcounts['due'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Payment required', 'logicanvas-auctions' ); ?></span></article>
	<article><small><?php echo esc_html( $wcap_show_seller ? __( 'Seller balance', 'logicanvas-auctions' ) : __( 'Wins', 'logicanvas-auctions' ) ); ?></small><strong><?php echo esc_html( $wcap_show_seller ? (string) ( $wcap_holder['pending_payout'] ?? '0.00' ) : (string) ( $wcap_bcounts['awards'] ?? 0 ) ); ?></strong><span><?php echo esc_html( $wcap_show_seller ? __( 'Pending payout', 'logicanvas-auctions' ) : __( 'Awards on file', 'logicanvas-auctions' ) ); ?></span></article>
</div>
<div class="dashboard-grid">
	<section>
		<div class="dash-title">
			<h2><?php echo esc_html( $wcap_show_seller ? __( 'Active listing', 'logicanvas-auctions' ) : __( 'Active bidding', 'logicanvas-auctions' ) ); ?></h2>
			<?php if ( ! empty( $wcap_profile['urls']['archive'] ) ) : ?>
				<a href="<?php echo esc_url( (string) $wcap_profile['urls']['archive'] ); ?>"><?php esc_html_e( 'Browse all auctions →', 'logicanvas-auctions' ); ?></a>
			<?php endif; ?>
		</div>
		<?php if ( is_array( $wcap_featured ) ) : ?>
			<article class="active-lot">
				<div class="active-lot-art"<?php echo ! empty( $wcap_featured['image'] ) ? ' style="background-image:url(' . esc_url( (string) $wcap_featured['image'] ) . ')"' : ''; ?>>
					<b><?php echo esc_html( strtoupper( (string) ( $wcap_featured['type'] ?? '' ) ) ); ?></b>
				</div>
				<div>
					<small><?php echo esc_html( strtoupper( (string) ( $wcap_featured['state'] ?? '' ) ) ); ?></small>
					<h3><?php echo esc_html( (string) $wcap_featured['title'] ); ?></h3>
					<div class="lot-metrics">
						<span><?php esc_html_e( 'Current bid', 'logicanvas-auctions' ); ?> <b><?php echo esc_html( (string) $wcap_featured['price'] ); ?></b></span>
						<span><?php esc_html_e( 'Bids', 'logicanvas-auctions' ); ?> <b><?php echo esc_html( (string) $wcap_featured['bids'] ); ?></b></span>
						<span><?php esc_html_e( 'Type', 'logicanvas-auctions' ); ?> <b><?php echo esc_html( (string) ( $wcap_featured['type'] ?? '' ) ); ?></b></span>
					</div>
					<?php if ( ! empty( $wcap_featured['permalink'] ) ) : ?>
						<a class="wcap-btn" href="<?php echo esc_url( (string) $wcap_featured['permalink'] ); ?>"><?php esc_html_e( 'View lot →', 'logicanvas-auctions' ); ?></a>
					<?php endif; ?>
				</div>
			</article>
		<?php else : ?>
			<p class="wcap-empty"><?php echo esc_html( $wcap_show_seller ? __( 'You have not created any auctions yet.', 'logicanvas-auctions' ) : __( 'You are not leading any lots.', 'logicanvas-auctions' ) ); ?></p>
		<?php endif; ?>
	</section>
	<section class="seller-panel">
		<div class="dash-title">
			<h2><?php echo esc_html( $wcap_show_seller ? __( 'Seller center', 'logicanvas-auctions' ) : __( 'Quick links', 'logicanvas-auctions' ) ); ?></h2>
		</div>
		<?php if ( $wcap_show_seller ) : ?>
			<ul>
				<li><span><?php esc_html_e( 'Draft listings', 'logicanvas-auctions' ); ?></span><b><?php echo esc_html( (string) ( $wcap_counts['drafts'] ?? 0 ) ); ?></b></li>
				<li><span><?php esc_html_e( 'Pending review', 'logicanvas-auctions' ); ?></span><b><?php echo esc_html( (string) ( $wcap_counts['pending'] ?? 0 ) ); ?></b></li>
				<li><span><?php esc_html_e( 'Live / active', 'logicanvas-auctions' ); ?></span><b><?php echo esc_html( (string) ( $wcap_counts['live'] ?? 0 ) ); ?></b></li>
				<li><span><?php esc_html_e( 'All listings', 'logicanvas-auctions' ); ?></span><b><a href="<?php echo esc_url( \LogicanvasAuctions\Frontend\Dashboards\AccountRouter::url( 'listings' ) ); ?>"><?php echo esc_html( (string) ( $wcap_counts['total'] ?? 0 ) ); ?></a></b></li>
			</ul>
			<a class="wcap-btn" href="<?php echo esc_url( \LogicanvasAuctions\Frontend\Dashboards\AccountRouter::url( 'create' ) ); ?>"><?php esc_html_e( 'Create listing →', 'logicanvas-auctions' ); ?></a>
		<?php else : ?>
			<ul>
				<li><a href="<?php echo esc_url( \LogicanvasAuctions\Frontend\Dashboards\AccountRouter::url( 'bids' ) ); ?>"><?php esc_html_e( 'View my bids →', 'logicanvas-auctions' ); ?></a></li>
				<li><a href="<?php echo esc_url( \LogicanvasAuctions\Frontend\Dashboards\AccountRouter::url( 'watchlist' ) ); ?>"><?php esc_html_e( 'Open watchlist →', 'logicanvas-auctions' ); ?></a></li>
				<li><a href="<?php echo esc_url( \LogicanvasAuctions\Frontend\Dashboards\AccountRouter::url( 'purchases' ) ); ?>"><?php esc_html_e( 'View purchases →', 'logicanvas-auctions' ); ?></a></li>
			</ul>
			<?php if ( ! empty( $wcap_profile['urls']['archive'] ) ) : ?>
				<a class="wcap-btn" href="<?php echo esc_url( (string) $wcap_profile['urls']['archive'] ); ?>"><?php esc_html_e( 'Browse auctions →', 'logicanvas-auctions' ); ?></a>
			<?php endif; ?>
		<?php endif; ?>
	</section>
</div>
