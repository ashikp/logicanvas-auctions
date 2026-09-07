<?php
/**
 * Reusable auction/lot table.
 *
 * Expects $wcap['rows'], optional $wcap['is_admin'], $wcap['empty'].
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcap_rows     = is_array( $wcap['rows'] ?? null ) ? $wcap['rows'] : array();
$wcap_is_admin = ! empty( $wcap['is_admin'] );
$wcap_empty    = (string) ( $wcap['empty'] ?? __( 'Nothing to show yet.', 'logicanvas-auctions' ) );

if ( empty( $wcap_rows ) ) {
	echo '<p class="wcap-empty">' . esc_html( $wcap_empty ) . '</p>';
	return;
}
?>
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
					<td><?php echo esc_html( (string) ( $wcap_row['type'] ?? '' ) ); ?></td>
					<td><span class="wcap-badge"><?php echo esc_html( (string) ( $wcap_row['state'] ?? $wcap_row['status'] ?? '' ) ); ?></span></td>
					<td><?php echo esc_html( (string) ( $wcap_row['price'] ?? $wcap_row['your_bid'] ?? $wcap_row['amount'] ?? '' ) ); ?></td>
					<td><?php echo esc_html( (string) ( $wcap_row['bids'] ?? '' ) ); ?></td>
					<td class="wcap-table__actions">
						<?php if ( ! empty( $wcap_row['host'] ) && in_array( (string) ( $wcap_row['state'] ?? '' ), array( 'lobby', 'scheduled', 'live', 'paused', 'going_once', 'going_twice' ), true ) ) : ?>
							<a href="<?php echo esc_url( (string) $wcap_row['host'] ); ?>"><?php esc_html_e( 'Host console', 'logicanvas-auctions' ); ?></a>
						<?php endif; ?>
						<?php if ( ! empty( $wcap_row['can_accept_bid'] ) ) : ?>
							<button type="button" class="wcap-btn" data-wcap-accept-bid data-id="<?php echo esc_attr( (string) $wcap_row['id'] ); ?>"><?php esc_html_e( 'Accept current bid', 'logicanvas-auctions' ); ?></button>
						<?php endif; ?>
						<?php if ( in_array( (string) ( $wcap_row['state'] ?? '' ), array( 'draft', 'rejected' ), true ) ) : ?>
							<button type="button" class="wcap-link-btn" data-wcap-auction-action="submit" data-id="<?php echo esc_attr( (string) $wcap_row['id'] ); ?>"><?php esc_html_e( 'Submit for review', 'logicanvas-auctions' ); ?></button>
						<?php endif; ?>
						<?php if ( $wcap_is_admin && 'pending_review' === ( $wcap_row['state'] ?? '' ) ) : ?>
							<button type="button" class="wcap-link-btn" data-wcap-auction-action="approve" data-id="<?php echo esc_attr( (string) $wcap_row['id'] ); ?>"><?php esc_html_e( 'Approve / publish', 'logicanvas-auctions' ); ?></button>
						<?php endif; ?>
						<?php if ( ! empty( $wcap_row['edit'] ) ) : ?>
							<a href="<?php echo esc_url( (string) $wcap_row['edit'] ); ?>"><?php esc_html_e( 'Edit', 'logicanvas-auctions' ); ?></a>
						<?php endif; ?>
						<?php if ( ! empty( $wcap_row['pay_url'] ) && 'pending' === ( $wcap_row['status'] ?? '' ) ) : ?>
							<a href="<?php echo esc_url( (string) $wcap_row['pay_url'] ); ?>"><?php esc_html_e( 'Pay now', 'logicanvas-auctions' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
