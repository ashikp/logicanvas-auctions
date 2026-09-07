<?php
/**
 * Watchlist.
 *
 * Expects $wcap['bidder'].
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcap_bidder  = is_array( $wcap['bidder'] ?? null ) ? $wcap['bidder'] : array();
$wcap_watches = is_array( $wcap_bidder['watches'] ?? null ) ? $wcap_bidder['watches'] : array();
?>
<div class="dash-notice">
	<b><?php esc_html_e( 'Saved lots', 'logicanvas-auctions' ); ?></b>
	<span><?php esc_html_e( 'Watch an auction from its lot page to keep it here.', 'logicanvas-auctions' ); ?></span>
</div>
<?php
\LogicanvasAuctions\Frontend\TemplateLoader::include_partial(
	'partials/auction-table',
	array(
		'rows'  => $wcap_watches,
		'empty' => __( 'Your watchlist is empty.', 'logicanvas-auctions' ),
	)
);
