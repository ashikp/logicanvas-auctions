<?php
/**
 * Purchases / invoices.
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
	<b><?php esc_html_e( 'Won auctions', 'logicanvas-auctions' ); ?></b>
	<span><?php esc_html_e( 'Pay pending awards before the deadline. After payment you can follow order status and shipping tracking here.', 'logicanvas-auctions' ); ?></span>
</div>
<?php if ( empty( $wcap_awards ) ) : ?>
	<p class="wcap-empty"><?php esc_html_e( 'You have not won any auctions yet.', 'logicanvas-auctions' ); ?></p>
<?php else : ?>
	<div class="wcap-order-board">
		<?php foreach ( $wcap_awards as $wcap_row ) : ?>
			<?php
			$wcap_payment = is_array( $wcap_row['payment'] ?? null ) ? $wcap_row['payment'] : array();
			$wcap_paid    = ! empty( $wcap_payment['paid'] ) || 'paid' === ( $wcap_row['status'] ?? '' );
			?>
			<article class="wcap-order-card">
				<header class="wcap-order-card__head">
					<div>
						<small><?php echo esc_html( ! empty( $wcap_row['order_number'] ) ? sprintf( /* translators: %s order number */ __( 'Order #%s', 'logicanvas-auctions' ), (string) $wcap_row['order_number'] ) : __( 'Award', 'logicanvas-auctions' ) ); ?></small>
						<h3><?php echo esc_html( (string) $wcap_row['title'] ); ?></h3>
					</div>
					<div class="wcap-order-card__badges">
						<span class="wcap-badge <?php echo $wcap_paid ? 'wcap-badge--ok' : 'wcap-badge--warn'; ?>">
							<?php echo esc_html( (string) ( $wcap_payment['label'] ?? $wcap_row['status'] ?? '' ) ); ?>
						</span>
						<?php if ( ! empty( $wcap_row['order_status_label'] ) ) : ?>
							<span class="wcap-badge"><?php echo esc_html( (string) $wcap_row['order_status_label'] ); ?></span>
						<?php endif; ?>
					</div>
				</header>
				<div class="wcap-order-card__meta">
					<span><?php esc_html_e( 'Amount', 'logicanvas-auctions' ); ?> <b><?php echo esc_html( (string) $wcap_row['amount'] ); ?></b></span>
					<?php if ( ! empty( $wcap_row['deadline'] ) && 'pending' === ( $wcap_row['status'] ?? '' ) ) : ?>
						<span><?php esc_html_e( 'Pay by', 'logicanvas-auctions' ); ?> <b><?php echo esc_html( (string) $wcap_row['deadline'] ); ?></b></span>
					<?php endif; ?>
					<?php if ( ! empty( $wcap_payment['method'] ) ) : ?>
						<span><?php esc_html_e( 'Method', 'logicanvas-auctions' ); ?> <b><?php echo esc_html( (string) $wcap_payment['method'] ); ?></b></span>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $wcap_row['tracking_number'] ) ) : ?>
					<p>
						<?php esc_html_e( 'Tracking', 'logicanvas-auctions' ); ?>:
						<strong><?php echo esc_html( (string) $wcap_row['tracking_number'] ); ?></strong>
						<?php if ( ! empty( $wcap_row['tracking_carrier'] ) ) : ?>
							(<?php echo esc_html( (string) $wcap_row['tracking_carrier'] ); ?>)
						<?php endif; ?>
						<?php if ( ! empty( $wcap_row['tracking_url'] ) ) : ?>
							— <a href="<?php echo esc_url( (string) $wcap_row['tracking_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Track shipment', 'logicanvas-auctions' ); ?></a>
						<?php endif; ?>
					</p>
				<?php endif; ?>
				<?php if ( ! empty( $wcap_row['fulfilment_note'] ) ) : ?>
					<p class="wcap-meta"><?php echo esc_html( (string) $wcap_row['fulfilment_note'] ); ?></p>
				<?php endif; ?>
				<div class="wcap-accept-card__actions">
					<?php if ( 'pending' === ( $wcap_row['status'] ?? '' ) && ! empty( $wcap_row['pay_url'] ) ) : ?>
						<a class="wcap-btn" href="<?php echo esc_url( (string) $wcap_row['pay_url'] ); ?>"><?php esc_html_e( 'Pay now', 'logicanvas-auctions' ); ?></a>
					<?php endif; ?>
					<?php if ( ! empty( $wcap_row['view_order_url'] ) ) : ?>
						<a class="wcap-btn wcap-btn--secondary" href="<?php echo esc_url( (string) $wcap_row['view_order_url'] ); ?>"><?php esc_html_e( 'View order', 'logicanvas-auctions' ); ?></a>
					<?php endif; ?>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
