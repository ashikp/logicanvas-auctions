<?php
/**
 * Seller orders: payment status, fulfilment, tracking.
 *
 * Expects $wcap['holder'].
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcap_holder   = is_array( $wcap['holder'] ?? null ) ? $wcap['holder'] : array();
$wcap_orders   = is_array( $wcap_holder['orders'] ?? null ) ? $wcap_holder['orders'] : array();
$wcap_mode     = (string) ( $wcap_holder['order_managed_by'] ?? 'both' );
$wcap_mode_msg = array(
	'admin'  => __( 'Order status and tracking are managed by store administrators. You can still review payment status here.', 'logicanvas-auctions' ),
	'seller' => __( 'You manage auction order status, offline payment confirmation, and shipping tracking from this screen.', 'logicanvas-auctions' ),
	'both'   => __( 'You and store administrators can update auction order status and tracking.', 'logicanvas-auctions' ),
);
?>
<div class="dash-notice">
	<b><?php esc_html_e( 'Seller orders', 'logicanvas-auctions' ); ?></b>
	<span><?php echo esc_html( $wcap_mode_msg[ $wcap_mode ] ?? $wcap_mode_msg['both'] ); ?></span>
</div>
<?php if ( empty( $wcap_orders ) ) : ?>
	<p class="wcap-empty"><?php esc_html_e( 'No sold lots yet. Orders appear after a winner is awarded and checkout begins.', 'logicanvas-auctions' ); ?></p>
<?php else : ?>
	<div class="wcap-order-board">
		<?php foreach ( $wcap_orders as $wcap_row ) : ?>
			<?php
			$wcap_payment = is_array( $wcap_row['payment'] ?? null ) ? $wcap_row['payment'] : array();
			$wcap_paid    = ! empty( $wcap_payment['paid'] );
			?>
			<article class="wcap-order-card" data-wcap-order-card data-order-id="<?php echo esc_attr( (string) ( $wcap_row['order_id'] ?? 0 ) ); ?>">
				<header class="wcap-order-card__head">
					<div>
						<small><?php echo esc_html( $wcap_row['order_number'] ? sprintf( /* translators: %s order number */ __( 'Order #%s', 'logicanvas-auctions' ), (string) $wcap_row['order_number'] ) : __( 'Awaiting order', 'logicanvas-auctions' ) ); ?></small>
						<h3>
							<?php if ( ! empty( $wcap_row['permalink'] ) ) : ?>
								<a href="<?php echo esc_url( (string) $wcap_row['permalink'] ); ?>"><?php echo esc_html( (string) $wcap_row['title'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( (string) $wcap_row['title'] ); ?>
							<?php endif; ?>
						</h3>
						<p class="wcap-meta">
							<?php
							if ( ! empty( $wcap_row['winner_name'] ) ) {
								echo esc_html( sprintf( /* translators: %s winner name */ __( 'Winner: %s', 'logicanvas-auctions' ), (string) $wcap_row['winner_name'] ) );
							}
							?>
						</p>
					</div>
					<div class="wcap-order-card__badges">
						<span class="wcap-badge <?php echo $wcap_paid ? 'wcap-badge--ok' : 'wcap-badge--warn'; ?>">
							<?php echo esc_html( (string) ( $wcap_payment['label'] ?? '' ) ); ?>
						</span>
						<span class="wcap-badge"><?php echo esc_html( (string) ( $wcap_row['order_status_label'] ?? '' ) ); ?></span>
					</div>
				</header>

				<div class="wcap-order-card__meta">
					<span><?php esc_html_e( 'Gross', 'logicanvas-auctions' ); ?> <b><?php echo esc_html( (string) ( $wcap_row['gross'] ?? '' ) ); ?></b></span>
					<?php if ( '' !== (string) ( $wcap_row['net'] ?? '' ) ) : ?>
						<span><?php esc_html_e( 'Net', 'logicanvas-auctions' ); ?> <b><?php echo esc_html( (string) $wcap_row['net'] ); ?></b></span>
					<?php endif; ?>
					<?php if ( ! empty( $wcap_row['payout_status'] ) ) : ?>
						<span><?php esc_html_e( 'Payout', 'logicanvas-auctions' ); ?> <b><?php echo esc_html( (string) $wcap_row['payout_status'] ); ?></b></span>
					<?php endif; ?>
					<?php if ( ! empty( $wcap_payment['method'] ) ) : ?>
						<span><?php esc_html_e( 'Method', 'logicanvas-auctions' ); ?> <b><?php echo esc_html( (string) $wcap_payment['method'] ); ?></b></span>
					<?php endif; ?>
					<?php if ( ! empty( $wcap_payment['date'] ) ) : ?>
						<span><?php esc_html_e( 'Paid on', 'logicanvas-auctions' ); ?> <b><?php echo esc_html( (string) $wcap_payment['date'] ); ?></b></span>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( $wcap_row['tracking_number'] ) || ! empty( $wcap_row['fulfilment_note'] ) ) : ?>
					<div class="wcap-order-card__track-view">
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
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $wcap_row['can_manage'] ) && ! empty( $wcap_row['order_id'] ) ) : ?>
					<form class="wcap-order-form lead-form" data-wcap-order-form>
						<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $wcap_row['order_id'] ); ?>" />
						<label>
							<span><?php esc_html_e( 'Order status', 'logicanvas-auctions' ); ?></span>
							<select name="status">
								<?php
								$wcap_labels  = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
								$wcap_allowed = is_array( $wcap_row['allowed_statuses'] ?? null ) ? $wcap_row['allowed_statuses'] : array();
								foreach ( $wcap_allowed as $wcap_status ) :
									$wcap_key   = 'wc-' . $wcap_status;
									$wcap_label = $wcap_labels[ $wcap_key ] ?? ucfirst( str_replace( '-', ' ', (string) $wcap_status ) );
									?>
									<option value="<?php echo esc_attr( (string) $wcap_status ); ?>" <?php selected( (string) ( $wcap_row['order_status'] ?? '' ), (string) $wcap_status ); ?>><?php echo esc_html( (string) $wcap_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label>
							<span><?php esc_html_e( 'Tracking number', 'logicanvas-auctions' ); ?></span>
							<input type="text" name="tracking_number" value="<?php echo esc_attr( (string) ( $wcap_row['tracking_number'] ?? '' ) ); ?>" />
						</label>
						<label>
							<span><?php esc_html_e( 'Carrier', 'logicanvas-auctions' ); ?></span>
							<input type="text" name="tracking_carrier" value="<?php echo esc_attr( (string) ( $wcap_row['tracking_carrier'] ?? '' ) ); ?>" />
						</label>
						<label>
							<span><?php esc_html_e( 'Tracking URL', 'logicanvas-auctions' ); ?></span>
							<input type="url" name="tracking_url" value="<?php echo esc_attr( (string) ( $wcap_row['tracking_url'] ?? '' ) ); ?>" />
						</label>
						<label class="full">
							<span><?php esc_html_e( 'Fulfilment note', 'logicanvas-auctions' ); ?></span>
							<textarea name="fulfilment_note" rows="2"><?php echo esc_textarea( (string) ( $wcap_row['fulfilment_note'] ?? '' ) ); ?></textarea>
						</label>
						<label class="full">
							<span><?php esc_html_e( 'Note to buyer (optional)', 'logicanvas-auctions' ); ?></span>
							<textarea name="customer_note" rows="2" placeholder="<?php esc_attr_e( 'Visible to the buyer on the order', 'logicanvas-auctions' ); ?>"></textarea>
						</label>
						<label class="full wcap-check">
							<input type="checkbox" name="mark_paid_offline" value="1" <?php disabled( $wcap_paid ); ?> />
							<span><?php esc_html_e( 'Mark as paid offline (cash / bank transfer received)', 'logicanvas-auctions' ); ?></span>
						</label>
						<div class="wcap-accept-card__actions full">
							<button type="submit" class="wcap-btn"><?php esc_html_e( 'Update order', 'logicanvas-auctions' ); ?></button>
							<?php if ( ! empty( $wcap_row['view_order_url'] ) ) : ?>
								<a class="wcap-btn wcap-btn--secondary" href="<?php echo esc_url( (string) $wcap_row['view_order_url'] ); ?>"><?php esc_html_e( 'View order', 'logicanvas-auctions' ); ?></a>
							<?php endif; ?>
						</div>
						<p class="wcap-form-status full" role="status"></p>
					</form>
				<?php elseif ( ! empty( $wcap_row['view_order_url'] ) ) : ?>
					<p><a class="wcap-btn wcap-btn--secondary" href="<?php echo esc_url( (string) $wcap_row['view_order_url'] ); ?>"><?php esc_html_e( 'View order', 'logicanvas-auctions' ); ?></a></p>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
