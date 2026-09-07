<?php
/**
 * Bidder dashboard.
 *
 * Expects $wcap['dashboard'].
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() ) {
	$wcap_login = wp_login_url( get_permalink() ?: home_url( '/' ) );
	echo '<div class="wcap-login-gate"><p>' . esc_html__( 'Log in to view your bidder dashboard.', 'logicanvas-auctions' ) . ' <a href="' . esc_url( $wcap_login ) . '">' . esc_html__( 'Log in', 'logicanvas-auctions' ) . '</a></p></div>';
	return;
}

$wcap_dashboard = is_array( $wcap['dashboard'] ?? null ) ? $wcap['dashboard'] : array();
$wcap_profile   = is_array( $wcap_dashboard['profile'] ?? null ) ? $wcap_dashboard['profile'] : array();
$wcap_leading   = is_array( $wcap_dashboard['leading'] ?? null ) ? $wcap_dashboard['leading'] : array();
$wcap_awards    = is_array( $wcap_dashboard['awards'] ?? null ) ? $wcap_dashboard['awards'] : array();
$wcap_counts    = is_array( $wcap_dashboard['counts'] ?? null ) ? $wcap_dashboard['counts'] : array();
$wcap_featured  = $wcap_leading[0] ?? null;
$wcap_can_sell  = ! empty( $wcap_dashboard['can_create'] ) || ! empty( $wcap_dashboard['is_admin'] );
?>
<div class="dashboard-page wcap-dash" data-wcap-bidder-dash data-wcap-ssr="1">
	<div class="dashboard-wrap">
		<?php
		\LogicanvasAuctions\Frontend\TemplateLoader::include_partial(
			'partials/account-sidebar',
			array(
				'profile'     => $wcap_profile,
				'active'      => 'overview',
				'role_label'  => $wcap_can_sell ? __( 'Buyer · Seller approved', 'logicanvas-auctions' ) : __( 'Registered bidder', 'logicanvas-auctions' ),
				'show_seller' => $wcap_can_sell,
				'badges'      => array(
					'leading' => (int) ( $wcap_counts['leading'] ?? 0 ),
					'watch'   => (int) ( $wcap_dashboard['watch_count'] ?? 0 ),
					'due'     => (int) ( $wcap_counts['due'] ?? 0 ),
				),
			)
		);
		?>
		<section class="dashboard-main" id="wcap-overview">
			<header>
				<div>
					<small><?php echo esc_html( strtoupper( (string) ( $wcap_profile['site_name'] ?? '' ) ) ); ?> <?php esc_html_e( 'ACCOUNT', 'logicanvas-auctions' ); ?></small>
					<h1><?php echo esc_html( (string) ( $wcap_profile['greeting'] ?? __( 'Welcome back.', 'logicanvas-auctions' ) ) ); ?></h1>
				</div>
				<?php if ( ! empty( $wcap_profile['urls']['archive'] ) ) : ?>
					<a class="wcap-btn wcap-btn--secondary" href="<?php echo esc_url( (string) $wcap_profile['urls']['archive'] ); ?>"><?php esc_html_e( 'Browse auctions', 'logicanvas-auctions' ); ?></a>
				<?php endif; ?>
			</header>

			<div class="dash-stats">
				<article><small><?php esc_html_e( 'Active bids', 'logicanvas-auctions' ); ?></small><strong><?php echo esc_html( (string) ( $wcap_counts['leading'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Lots you are leading', 'logicanvas-auctions' ); ?></span></article>
				<article><small><?php esc_html_e( 'Watchlist', 'logicanvas-auctions' ); ?></small><strong><?php echo esc_html( (string) ( $wcap_dashboard['watch_count'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Saved lots', 'logicanvas-auctions' ); ?></span></article>
				<article><small><?php esc_html_e( 'Open invoices', 'logicanvas-auctions' ); ?></small><strong><?php echo esc_html( (string) ( $wcap_counts['due'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Payment required', 'logicanvas-auctions' ); ?></span></article>
				<article><small><?php esc_html_e( 'Wins', 'logicanvas-auctions' ); ?></small><strong><?php echo esc_html( (string) ( $wcap_counts['awards'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Awards on file', 'logicanvas-auctions' ); ?></span></article>
			</div>

			<div class="dashboard-grid">
				<section id="wcap-bids">
					<div class="dash-title">
						<h2><?php esc_html_e( 'Active bidding', 'logicanvas-auctions' ); ?></h2>
					</div>
					<div data-wcap-leading>
						<?php if ( $wcap_featured ) : ?>
							<article class="active-lot">
								<div class="active-lot-art"<?php echo ! empty( $wcap_featured['image'] ) ? ' style="background-image:url(' . esc_url( (string) $wcap_featured['image'] ) . ')"' : ''; ?>>
									<b><?php echo esc_html( strtoupper( (string) $wcap_featured['type'] ) ); ?></b>
								</div>
								<div>
									<small><?php esc_html_e( 'You are leading', 'logicanvas-auctions' ); ?></small>
									<h3><?php echo esc_html( (string) $wcap_featured['title'] ); ?></h3>
									<div class="lot-metrics">
										<span><?php esc_html_e( 'Current bid', 'logicanvas-auctions' ); ?> <b><?php echo esc_html( (string) $wcap_featured['price'] ); ?></b></span>
										<span><?php esc_html_e( 'Bids', 'logicanvas-auctions' ); ?> <b><?php echo esc_html( (string) $wcap_featured['bids'] ); ?></b></span>
										<span><?php esc_html_e( 'Type', 'logicanvas-auctions' ); ?> <b><?php echo esc_html( (string) ( $wcap_featured['type'] ?? '' ) ); ?></b></span>
									</div>
									<?php if ( ! empty( $wcap_featured['permalink'] ) ) : ?>
										<a class="wcap-btn" href="<?php echo esc_url( (string) $wcap_featured['permalink'] ); ?>"><?php esc_html_e( 'Increase bid →', 'logicanvas-auctions' ); ?></a>
									<?php endif; ?>
								</div>
							</article>
						<?php endif; ?>
						<?php if ( count( $wcap_leading ) > 1 ) : ?>
							<ul class="seller-panel" style="list-style:none;padding:0;margin:16px 0 0">
								<?php foreach ( array_slice( $wcap_leading, 1 ) as $wcap_row ) : ?>
									<li style="display:flex;justify-content:space-between;border-bottom:1px solid #ddd;padding:10px;font-size:12px">
										<a href="<?php echo esc_url( (string) $wcap_row['permalink'] ); ?>"><?php echo esc_html( (string) $wcap_row['title'] ); ?></a>
										<b><?php echo esc_html( (string) $wcap_row['price'] ); ?></b>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
						<?php if ( empty( $wcap_leading ) ) : ?>
							<p class="wcap-empty"><?php esc_html_e( 'You are not leading any lots. Browse the auction hall to place a bid.', 'logicanvas-auctions' ); ?></p>
						<?php endif; ?>
					</div>
				</section>
				<section class="seller-panel" id="wcap-wins">
					<div class="dash-title"><h2><?php esc_html_e( 'Wins / payment required', 'logicanvas-auctions' ); ?></h2></div>
					<div data-wcap-awards>
						<?php if ( empty( $wcap_awards ) ) : ?>
							<p class="wcap-empty"><?php esc_html_e( 'No awards yet.', 'logicanvas-auctions' ); ?></p>
						<?php else : ?>
							<ul>
								<?php foreach ( $wcap_awards as $wcap_row ) : ?>
									<li>
										<span><?php echo esc_html( (string) $wcap_row['title'] ); ?> — <?php echo esc_html( (string) $wcap_row['amount'] ); ?></span>
										<b>
											<?php echo esc_html( (string) $wcap_row['status'] ); ?>
											<?php if ( 'pending' === $wcap_row['status'] && ! empty( $wcap_row['pay_url'] ) ) : ?>
												<a href="<?php echo esc_url( (string) $wcap_row['pay_url'] ); ?>"><?php esc_html_e( 'Pay', 'logicanvas-auctions' ); ?></a>
											<?php endif; ?>
										</b>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</section>
			</div>
		</section>
	</div>
</div>
