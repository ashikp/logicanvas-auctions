<?php
/**
 * Auction holder dashboard.
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
	echo '<div class="wcap-login-gate"><p>' . esc_html__( 'Log in to view your auction holder dashboard.', 'logicanvas-auctions' ) . ' <a href="' . esc_url( $wcap_login ) . '">' . esc_html__( 'Log in', 'logicanvas-auctions' ) . '</a></p></div>';
	return;
}

$wcap_dashboard    = is_array( $wcap['dashboard'] ?? null ) ? $wcap['dashboard'] : array();
$wcap_status       = (string) ( $wcap_dashboard['status'] ?? '' );
$wcap_can_create   = ! empty( $wcap_dashboard['can_create'] );
$wcap_is_admin     = ! empty( $wcap_dashboard['is_admin'] );
$wcap_counts       = is_array( $wcap_dashboard['counts'] ?? null ) ? $wcap_dashboard['counts'] : array();
$wcap_groups       = is_array( $wcap_dashboard['groups'] ?? null ) ? $wcap_dashboard['groups'] : array();
$wcap_settlements  = is_array( $wcap_dashboard['settlements'] ?? null ) ? $wcap_dashboard['settlements'] : array();
$wcap_urls         = is_array( $wcap_dashboard['urls'] ?? null ) ? $wcap_dashboard['urls'] : array();
$wcap_profile      = is_array( $wcap_dashboard['profile'] ?? null ) ? $wcap_dashboard['profile'] : array();
$wcap_featured     = is_array( $wcap_dashboard['featured'] ?? null ) ? $wcap_dashboard['featured'] : null;
$wcap_accept_ready = is_array( $wcap_dashboard['accept_ready'] ?? null ) ? $wcap_dashboard['accept_ready'] : array();
$wcap_submit       = (string) ( $wcap_urls['submit'] ?? '' );
$wcap_apply        = (string) ( $wcap_urls['apply'] ?? '' );

$wcap_status_labels = array(
	'pending'   => __( 'Application pending review', 'logicanvas-auctions' ),
	'approved'  => __( 'Approved auction holder', 'logicanvas-auctions' ),
	'rejected'  => __( 'Application rejected', 'logicanvas-auctions' ),
	'suspended' => __( 'Account suspended', 'logicanvas-auctions' ),
	''          => __( 'Not yet an auction holder', 'logicanvas-auctions' ),
);

$wcap_standing = $wcap_status_labels[ $wcap_status ] ?? $wcap_status_labels[''];
if ( $wcap_is_admin ) {
	$wcap_standing = __( 'Administrator · seller approved', 'logicanvas-auctions' );
}

$wcap_role_label = $wcap_is_admin ? __( 'Buyer · Seller approved', 'logicanvas-auctions' ) : ( $wcap_can_create ? __( 'Seller approved', 'logicanvas-auctions' ) : $wcap_standing );
?>
<div class="dashboard-page wcap-holder-dash">
	<div class="dashboard-wrap">
		<?php
		\LogicanvasAuctions\Frontend\TemplateLoader::include_partial(
			'partials/account-sidebar',
			array(
				'profile'     => $wcap_profile,
				'active'      => 'listings',
				'role_label'  => $wcap_role_label,
				'show_seller' => $wcap_can_create || $wcap_is_admin,
				'badges'      => array(
					'listings' => (int) ( $wcap_counts['total'] ?? 0 ),
					'watch'    => (int) ( $wcap_dashboard['watch_count'] ?? 0 ),
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
				<?php if ( $wcap_can_create ) : ?>
					<a class="wcap-btn" href="#wcap-create"><?php esc_html_e( 'Create listing', 'logicanvas-auctions' ); ?></a>
				<?php elseif ( $wcap_apply ) : ?>
					<a class="wcap-btn" href="<?php echo esc_url( $wcap_apply ); ?>"><?php esc_html_e( 'Apply to sell', 'logicanvas-auctions' ); ?></a>
				<?php endif; ?>
			</header>

			<div class="dash-notice">
				<b><?php echo esc_html( $wcap_standing ); ?></b>
				<span>
					<?php if ( 'pending' === $wcap_status ) : ?>
						<?php esc_html_e( 'Your holder application is waiting for administrator approval. You will be able to publish auctions after you are approved.', 'logicanvas-auctions' ); ?>
					<?php elseif ( 'rejected' === $wcap_status ) : ?>
						<?php esc_html_e( 'Your application was not approved. You may submit a new application.', 'logicanvas-auctions' ); ?>
					<?php elseif ( 'suspended' === $wcap_status ) : ?>
						<?php esc_html_e( 'Your holder account is suspended. You cannot publish or host auctions.', 'logicanvas-auctions' ); ?>
					<?php elseif ( $wcap_is_admin ) : ?>
						<?php esc_html_e( 'You are a site administrator, so you can create and host auctions for the store.', 'logicanvas-auctions' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'List a lot with photos and details. Administrators can publish immediately; other holders submit for review.', 'logicanvas-auctions' ); ?>
					<?php endif; ?>
				</span>
			</div>

			<div class="dash-stats">
				<article><small><?php esc_html_e( 'My auctions', 'logicanvas-auctions' ); ?></small><strong><?php echo esc_html( (string) ( $wcap_counts['total'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'All listings', 'logicanvas-auctions' ); ?></span></article>
				<article><small><?php esc_html_e( 'Live / active', 'logicanvas-auctions' ); ?></small><strong><?php echo esc_html( (string) ( $wcap_counts['live'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Accepting bids', 'logicanvas-auctions' ); ?></span></article>
				<article><small><?php esc_html_e( 'Ready to sell', 'logicanvas-auctions' ); ?></small><strong><?php echo esc_html( (string) ( $wcap_counts['accept_ready'] ?? count( $wcap_accept_ready ) ) ); ?></strong><span><?php esc_html_e( 'Accept current bid', 'logicanvas-auctions' ); ?></span></article>
				<article><small><?php esc_html_e( 'Seller balance', 'logicanvas-auctions' ); ?></small><strong><?php echo esc_html( (string) ( $wcap_dashboard['pending_payout'] ?? '0.00' ) ); ?></strong><span><?php esc_html_e( 'Pending payout', 'logicanvas-auctions' ); ?></span></article>
			</div>

			<?php
			\LogicanvasAuctions\Frontend\TemplateLoader::include_partial(
				'partials/accept-ready',
				array( 'rows' => $wcap_accept_ready )
			);
			?>

			<div class="dashboard-grid">
				<section>
					<div class="dash-title">
						<h2><?php esc_html_e( 'Active listing', 'logicanvas-auctions' ); ?></h2>
						<?php if ( ! empty( $wcap_profile['urls']['archive'] ) ) : ?>
							<a href="<?php echo esc_url( (string) $wcap_profile['urls']['archive'] ); ?>"><?php esc_html_e( 'Browse all auctions →', 'logicanvas-auctions' ); ?></a>
						<?php endif; ?>
					</div>
					<?php if ( $wcap_featured ) : ?>
						<article class="active-lot">
							<div class="active-lot-art"<?php echo ! empty( $wcap_featured['image'] ) ? ' style="background-image:url(' . esc_url( (string) $wcap_featured['image'] ) . ')"' : ''; ?>>
								<b><?php echo esc_html( strtoupper( (string) $wcap_featured['type'] ) ); ?></b>
							</div>
							<div>
								<small><?php echo esc_html( strtoupper( (string) $wcap_featured['state'] ) ); ?></small>
								<h3><?php echo esc_html( (string) $wcap_featured['title'] ); ?></h3>
								<div class="lot-metrics">
									<span><?php esc_html_e( 'Current bid', 'logicanvas-auctions' ); ?> <b><?php echo esc_html( (string) $wcap_featured['price'] ); ?></b></span>
									<span><?php esc_html_e( 'Bids', 'logicanvas-auctions' ); ?> <b><?php echo esc_html( (string) $wcap_featured['bids'] ); ?></b></span>
									<span><?php esc_html_e( 'Type', 'logicanvas-auctions' ); ?> <b><?php echo esc_html( (string) ( $wcap_featured['type'] ?? '' ) ); ?></b></span>
								</div>
								<div class="wcap-accept-card__actions">
									<?php if ( ! empty( $wcap_featured['can_accept_bid'] ) ) : ?>
										<button type="button" class="wcap-btn" data-wcap-accept-bid data-id="<?php echo esc_attr( (string) $wcap_featured['id'] ); ?>">
											<?php
											echo esc_html(
												sprintf(
													/* translators: %s: current bid */
													__( 'Accept current bid (%s)', 'logicanvas-auctions' ),
													(string) $wcap_featured['price']
												)
											);
											?>
										</button>
									<?php endif; ?>
									<?php if ( ! empty( $wcap_featured['permalink'] ) ) : ?>
										<a class="wcap-btn wcap-btn--secondary" href="<?php echo esc_url( (string) $wcap_featured['permalink'] ); ?>"><?php esc_html_e( 'View lot →', 'logicanvas-auctions' ); ?></a>
									<?php endif; ?>
								</div>
								<p class="wcap-form-status" role="status"></p>
							</div>
						</article>
					<?php else : ?>
						<p class="wcap-empty"><?php esc_html_e( 'You have not created any auctions yet. Use the form below to add a lot with photos and details.', 'logicanvas-auctions' ); ?></p>
					<?php endif; ?>
				</section>
				<section class="seller-panel">
					<div class="dash-title">
						<h2><?php esc_html_e( 'Seller center', 'logicanvas-auctions' ); ?></h2>
						<a href="#wcap-listings"><?php esc_html_e( 'View listings →', 'logicanvas-auctions' ); ?></a>
					</div>
					<div class="seller-score">
						<strong><?php echo esc_html( $wcap_can_create || $wcap_is_admin ? __( 'Approved', 'logicanvas-auctions' ) : $wcap_standing ); ?></strong>
						<span><?php esc_html_e( 'Account standing', 'logicanvas-auctions' ); ?></span>
					</div>
					<ul>
						<li><span><?php esc_html_e( 'Draft listings', 'logicanvas-auctions' ); ?></span><b><?php echo esc_html( (string) ( $wcap_counts['drafts'] ?? 0 ) ); ?></b></li>
						<li><span><?php esc_html_e( 'Pending review', 'logicanvas-auctions' ); ?></span><b><?php echo esc_html( (string) ( $wcap_counts['pending'] ?? 0 ) ); ?></b></li>
						<li><span><?php esc_html_e( 'Upcoming', 'logicanvas-auctions' ); ?></span><b><?php echo esc_html( (string) ( $wcap_counts['upcoming'] ?? 0 ) ); ?></b></li>
						<li><span><?php esc_html_e( 'Completed', 'logicanvas-auctions' ); ?></span><b><?php echo esc_html( (string) ( $wcap_counts['completed'] ?? 0 ) ); ?></b></li>
					</ul>
					<?php if ( $wcap_can_create ) : ?>
						<a class="wcap-btn" href="#wcap-create"><?php esc_html_e( 'Create draft listing →', 'logicanvas-auctions' ); ?></a>
						<small><?php esc_html_e( 'Every draft requires review before publication unless you are an administrator.', 'logicanvas-auctions' ); ?></small>
					<?php endif; ?>
					<?php if ( $wcap_submit ) : ?>
						<p><a href="<?php echo esc_url( $wcap_submit ); ?>"><?php esc_html_e( 'Open full submit page', 'logicanvas-auctions' ); ?></a></p>
					<?php endif; ?>
				</section>
			</div>

			<?php if ( $wcap_can_create ) : ?>
				<section class="wcap-dash-section" id="wcap-create">
					<div class="dash-title"><h2><?php esc_html_e( 'Create auction', 'logicanvas-auctions' ); ?></h2></div>
					<p><?php esc_html_e( 'Add a title, photos, and details so bidders can see the lot.', 'logicanvas-auctions' ); ?></p>
					<?php
					\LogicanvasAuctions\Frontend\TemplateLoader::include_partial(
						'holder/form-submit',
						array(
							'can_publish' => $wcap_is_admin || current_user_can( \LogicanvasAuctions\Config::CAP_MODERATE_AUCTIONS ),
						)
					);
					?>
				</section>
			<?php endif; ?>

			<div id="wcap-listings">
			<?php
			$wcap_section_titles = array(
				'drafts'    => __( 'Drafts', 'logicanvas-auctions' ),
				'pending'   => __( 'Pending review', 'logicanvas-auctions' ),
				'upcoming'  => __( 'Upcoming', 'logicanvas-auctions' ),
				'live'      => __( 'Live and active', 'logicanvas-auctions' ),
				'payment'   => __( 'Sold — payment pending', 'logicanvas-auctions' ),
				'completed' => __( 'Completed, unsold, and cancelled', 'logicanvas-auctions' ),
			);
			foreach ( $wcap_section_titles as $wcap_key => $wcap_title ) :
				$wcap_rows = $wcap_groups[ $wcap_key ] ?? array();
				if ( empty( $wcap_rows ) ) {
					continue;
				}
				?>
				<section class="wcap-dash-section">
					<h2><?php echo esc_html( $wcap_title ); ?></h2>
					<div class="wcap-table-wrap">
					<table class="wcap-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Auction', 'logicanvas-auctions' ); ?></th>
								<th><?php esc_html_e( 'Type', 'logicanvas-auctions' ); ?></th>
								<th><?php esc_html_e( 'Status', 'logicanvas-auctions' ); ?></th>
								<th><?php esc_html_e( 'Price', 'logicanvas-auctions' ); ?></th>
								<th><?php esc_html_e( 'Bids', 'logicanvas-auctions' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'logicanvas-auctions' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $wcap_rows as $wcap_row ) : ?>
								<tr>
									<td>
										<?php if ( ! empty( $wcap_row['permalink'] ) ) : ?>
											<a href="<?php echo esc_url( (string) $wcap_row['permalink'] ); ?>"><?php echo esc_html( (string) $wcap_row['title'] ); ?></a>
										<?php else : ?>
											<?php echo esc_html( (string) $wcap_row['title'] ); ?>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( (string) $wcap_row['type'] ); ?></td>
									<td><span class="wcap-badge"><?php echo esc_html( (string) $wcap_row['state'] ); ?></span></td>
									<td><?php echo esc_html( (string) $wcap_row['price'] ); ?></td>
									<td><?php echo esc_html( (string) $wcap_row['bids'] ); ?></td>
									<td class="wcap-table__actions">
										<?php if ( ! empty( $wcap_row['can_accept_bid'] ) ) : ?>
											<button type="button" class="wcap-btn" data-wcap-accept-bid data-id="<?php echo esc_attr( (string) $wcap_row['id'] ); ?>"><?php esc_html_e( 'Accept current bid', 'logicanvas-auctions' ); ?></button>
										<?php endif; ?>
										<?php if ( ! empty( $wcap_row['host'] ) && in_array( $wcap_row['state'], array( 'lobby', 'scheduled', 'live', 'paused', 'going_once', 'going_twice' ), true ) ) : ?>
											<a href="<?php echo esc_url( (string) $wcap_row['host'] ); ?>"><?php esc_html_e( 'Host console', 'logicanvas-auctions' ); ?></a>
										<?php endif; ?>
										<?php if ( 'draft' === $wcap_row['state'] || 'rejected' === $wcap_row['state'] ) : ?>
											<button type="button" class="wcap-link-btn" data-wcap-auction-action="submit" data-id="<?php echo esc_attr( (string) $wcap_row['id'] ); ?>"><?php esc_html_e( 'Submit for review', 'logicanvas-auctions' ); ?></button>
										<?php endif; ?>
										<?php if ( $wcap_is_admin && 'pending_review' === $wcap_row['state'] ) : ?>
											<button type="button" class="wcap-link-btn" data-wcap-auction-action="approve" data-id="<?php echo esc_attr( (string) $wcap_row['id'] ); ?>"><?php esc_html_e( 'Approve / publish', 'logicanvas-auctions' ); ?></button>
										<?php endif; ?>
										<?php if ( ! empty( $wcap_row['edit'] ) && current_user_can( 'edit_post', (int) $wcap_row['id'] ) ) : ?>
											<a href="<?php echo esc_url( (string) $wcap_row['edit'] ); ?>"><?php esc_html_e( 'Edit', 'logicanvas-auctions' ); ?></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					</div>
				</section>
				<?php
			endforeach;
			?>
			</div>

			<section class="wcap-dash-section" id="wcap-settlements">
				<h2><?php esc_html_e( 'Settlements', 'logicanvas-auctions' ); ?></h2>
				<?php if ( empty( $wcap_settlements ) ) : ?>
					<p class="wcap-empty"><?php esc_html_e( 'No settlements yet. They appear after an auction sells.', 'logicanvas-auctions' ); ?></p>
				<?php else : ?>
					<div class="wcap-table-wrap">
					<table class="wcap-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Auction', 'logicanvas-auctions' ); ?></th>
								<th><?php esc_html_e( 'Gross', 'logicanvas-auctions' ); ?></th>
								<th><?php esc_html_e( 'Commission', 'logicanvas-auctions' ); ?></th>
								<th><?php esc_html_e( 'Net', 'logicanvas-auctions' ); ?></th>
								<th><?php esc_html_e( 'Payout', 'logicanvas-auctions' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $wcap_settlements as $wcap_row ) : ?>
								<tr>
									<td>#<?php echo esc_html( (string) $wcap_row['auction_id'] ); ?></td>
									<td><?php echo esc_html( (string) $wcap_row['gross_amount'] . ' ' . $wcap_row['currency'] ); ?></td>
									<td><?php echo esc_html( (string) $wcap_row['commission_amount'] ); ?></td>
									<td><?php echo esc_html( (string) $wcap_row['net_amount'] ); ?></td>
									<td><?php echo esc_html( (string) $wcap_row['payout_status'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					</div>
				<?php endif; ?>
			</section>
		</section>
	</div>
</div>
