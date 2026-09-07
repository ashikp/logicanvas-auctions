<?php
/**
 * My bids.
 *
 * Expects $wcap['bidder'].
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcap_bidder = is_array( $wcap['bidder'] ?? null ) ? $wcap['bidder'] : array();
$wcap_bids   = is_array( $wcap_bidder['bids'] ?? null ) ? $wcap_bidder['bids'] : array();
?>
<div class="dash-notice">
	<b><?php esc_html_e( 'Lots you have bid on', 'logicanvas-auctions' ); ?></b>
	<span><?php esc_html_e( 'Leading means your last accepted bid is still the current high bid.', 'logicanvas-auctions' ); ?></span>
</div>
<?php
\LogicanvasAuctions\Frontend\TemplateLoader::include_partial(
	'partials/auction-table',
	array(
		'rows'  => $wcap_bids,
		'empty' => __( 'You have not placed any bids yet.', 'logicanvas-auctions' ),
	)
);
