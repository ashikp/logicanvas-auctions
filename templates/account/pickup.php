<?php
/**
 * Pickup and shipping for won lots.
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
$wcap_awards = is_array( $wcap_bidder['awards'] ?? null ) ? $wcap_bidder['awards'] : array();
?>
<div class="dash-notice">
	<b><?php esc_html_e( 'Fulfilment for lots you won', 'logicanvas-auctions' ); ?></b>
	<span><?php esc_html_e( 'Shipping, pickup, and digital delivery notes are copied from each auction.', 'logicanvas-auctions' ); ?></span>
</div>
<?php if ( empty( $wcap_awards ) ) : ?>
	<p class="wcap-empty"><?php esc_html_e( 'No won lots to collect yet.', 'logicanvas-auctions' ); ?></p>
<?php else : ?>
	<div class="wcap-table-wrap">
		<table class="wcap-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Auction', 'logicanvas-auctions' ); ?></th>
					<th><?php esc_html_e( 'Payment', 'logicanvas-auctions' ); ?></th>
					<th><?php esc_html_e( 'Fulfilment', 'logicanvas-auctions' ); ?></th>
					<th><?php esc_html_e( 'Notes', 'logicanvas-auctions' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $wcap_awards as $wcap_row ) : ?>
					<?php
					$wcap_auction_id = (int) ( $wcap_row['auction_id'] ?? 0 );
					$wcap_auction    = $wcap_auction_id ? ( new \LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository() )->find( $wcap_auction_id ) : null;
					$wcap_type       = $wcap_auction ? (string) ( $wcap_auction->to_array()['fulfilment_type'] ?? '' ) : '';
					$wcap_notes      = $wcap_auction_id ? (string) get_post_meta( $wcap_auction_id, '_wcap_fulfilment_notes', true ) : '';
					if ( '' === $wcap_type ) {
						$wcap_type = __( 'See auction details', 'logicanvas-auctions' );
					}
					?>
					<tr>
						<td><?php echo esc_html( (string) $wcap_row['title'] ); ?></td>
						<td><?php echo esc_html( (string) $wcap_row['status'] ); ?></td>
						<td><?php echo esc_html( $wcap_type ); ?></td>
						<td><?php echo $wcap_notes ? esc_html( $wcap_notes ) : '—'; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
