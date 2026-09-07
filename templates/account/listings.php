<?php
/**
 * Seller listings.
 *
 * Expects $wcap['holder'], $wcap['is_admin'].
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcap_holder       = is_array( $wcap['holder'] ?? null ) ? $wcap['holder'] : array();
$wcap_groups       = is_array( $wcap_holder['groups'] ?? null ) ? $wcap_holder['groups'] : array();
$wcap_counts       = is_array( $wcap_holder['counts'] ?? null ) ? $wcap_holder['counts'] : array();
$wcap_accept_ready = is_array( $wcap_holder['accept_ready'] ?? null ) ? $wcap_holder['accept_ready'] : array();
$wcap_is_admin     = ! empty( $wcap['is_admin'] );

$wcap_section_titles = array(
	'drafts'    => __( 'Drafts', 'logicanvas-auctions' ),
	'pending'   => __( 'Pending review', 'logicanvas-auctions' ),
	'upcoming'  => __( 'Upcoming', 'logicanvas-auctions' ),
	'live'      => __( 'Live and active', 'logicanvas-auctions' ),
	'payment'   => __( 'Sold — payment pending', 'logicanvas-auctions' ),
	'completed' => __( 'Completed, unsold, and cancelled', 'logicanvas-auctions' ),
);
?>
<div class="dash-stats">
	<article><small><?php esc_html_e( 'All listings', 'logicanvas-auctions' ); ?></small><strong><?php echo esc_html( (string) ( $wcap_counts['total'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Your auctions', 'logicanvas-auctions' ); ?></span></article>
	<article><small><?php esc_html_e( 'Live / active', 'logicanvas-auctions' ); ?></small><strong><?php echo esc_html( (string) ( $wcap_counts['live'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Accepting bids', 'logicanvas-auctions' ); ?></span></article>
	<article><small><?php esc_html_e( 'Ready to sell', 'logicanvas-auctions' ); ?></small><strong><?php echo esc_html( (string) ( $wcap_counts['accept_ready'] ?? count( $wcap_accept_ready ) ) ); ?></strong><span><?php esc_html_e( 'Accept current bid', 'logicanvas-auctions' ); ?></span></article>
	<article><small><?php esc_html_e( 'Pending review', 'logicanvas-auctions' ); ?></small><strong><?php echo esc_html( (string) ( $wcap_counts['pending'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Awaiting approval', 'logicanvas-auctions' ); ?></span></article>
</div>
<?php
\LogicanvasAuctions\Frontend\TemplateLoader::include_partial(
	'partials/accept-ready',
	array( 'rows' => $wcap_accept_ready )
);
?>
<?php
$wcap_shown = false;
foreach ( $wcap_section_titles as $wcap_key => $wcap_title ) :
	$wcap_rows = $wcap_groups[ $wcap_key ] ?? array();
	if ( empty( $wcap_rows ) ) {
		continue;
	}
	$wcap_shown = true;
	?>
	<section class="wcap-dash-section">
		<h2><?php echo esc_html( $wcap_title ); ?></h2>
		<?php
		\LogicanvasAuctions\Frontend\TemplateLoader::include_partial(
			'partials/auction-table',
			array(
				'rows'     => $wcap_rows,
				'is_admin' => $wcap_is_admin,
			)
		);
		?>
	</section>
	<?php
endforeach;

if ( ! $wcap_shown ) :
	?>
	<p class="wcap-empty"><?php esc_html_e( 'You have not created any listings yet.', 'logicanvas-auctions' ); ?> <a href="<?php echo esc_url( \LogicanvasAuctions\Frontend\Dashboards\AccountRouter::url( 'create' ) ); ?>"><?php esc_html_e( 'Create a listing', 'logicanvas-auctions' ); ?></a></p>
	<?php
endif;
