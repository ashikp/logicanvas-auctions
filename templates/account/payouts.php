<?php
/**
 * Seller payouts — payment method + request form.
 *
 * Expects $wcap['holder'].
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcap_holder         = is_array( $wcap['holder'] ?? null ) ? $wcap['holder'] : array();
$wcap_settlements    = is_array( $wcap_holder['settlements'] ?? null ) ? $wcap_holder['settlements'] : array();
$wcap_requests       = is_array( $wcap_holder['payout_requests'] ?? null ) ? $wcap_holder['payout_requests'] : array();
$wcap_balance        = is_array( $wcap_holder['available_balance'] ?? null ) ? $wcap_holder['available_balance'] : array();
$wcap_method         = is_array( $wcap_holder['payment_method'] ?? null ) ? $wcap_holder['payment_method'] : array();
$wcap_method_ok      = ! empty( $wcap_holder['payment_method_ok'] );
$wcap_labels         = is_array( $wcap_holder['payment_methods'] ?? null ) ? $wcap_holder['payment_methods'] : array();
$wcap_available      = (string) ( $wcap_balance['amount'] ?? '0.00' );
$wcap_currency       = (string) ( $wcap_balance['currency'] ?? '' );
$wcap_pending_label  = (string) ( $wcap_holder['pending_payout'] ?? ( $wcap_available . ( $wcap_currency ? ' ' . $wcap_currency : '' ) ) );
$wcap_current_method = (string) ( $wcap_method['method'] ?? '' );
$wcap_available_zero = ( (float) $wcap_available ) <= 0;
?>
<div class="dash-stats">
	<article>
		<small><?php esc_html_e( 'Available to request', 'logicanvas-auctions' ); ?></small>
		<strong><?php echo esc_html( $wcap_pending_label ); ?></strong>
		<span><?php esc_html_e( 'Pending settlements minus open requests', 'logicanvas-auctions' ); ?></span>
	</article>
	<article>
		<small><?php esc_html_e( 'Open requests', 'logicanvas-auctions' ); ?></small>
		<strong><?php echo esc_html( (string) ( $wcap_balance['reserved'] ?? '0.00' ) ); ?><?php echo $wcap_currency ? ' ' . esc_html( $wcap_currency ) : ''; ?></strong>
		<span><?php esc_html_e( 'Awaiting admin review', 'logicanvas-auctions' ); ?></span>
	</article>
	<article>
		<small><?php esc_html_e( 'Settlements', 'logicanvas-auctions' ); ?></small>
		<strong><?php echo esc_html( (string) count( $wcap_settlements ) ); ?></strong>
		<span><?php esc_html_e( 'Ledger rows', 'logicanvas-auctions' ); ?></span>
	</article>
</div>

<section class="wcap-payout-panel" data-wcap-payouts>
	<h3><?php esc_html_e( 'Payout payment method', 'logicanvas-auctions' ); ?></h3>
	<p class="wcap-meta"><?php esc_html_e( 'Save how you want to receive funds before requesting a payout.', 'logicanvas-auctions' ); ?></p>
	<form class="wcap-form lead-form" data-wcap-payout-method>
		<p>
			<label for="wcap-payout-method"><?php esc_html_e( 'Method', 'logicanvas-auctions' ); ?></label>
			<select id="wcap-payout-method" name="method" required data-wcap-method-select>
				<option value=""><?php esc_html_e( 'Select…', 'logicanvas-auctions' ); ?></option>
				<?php foreach ( $wcap_labels as $wcap_key => $wcap_label ) : ?>
					<option value="<?php echo esc_attr( (string) $wcap_key ); ?>" <?php selected( $wcap_current_method, (string) $wcap_key ); ?>><?php echo esc_html( (string) $wcap_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<div data-wcap-method-fields="bank_transfer" <?php echo 'bank_transfer' === $wcap_current_method ? '' : 'hidden'; ?>>
			<p>
				<label for="wcap-account-name"><?php esc_html_e( 'Account name', 'logicanvas-auctions' ); ?></label>
				<input id="wcap-account-name" type="text" name="account_name" value="<?php echo esc_attr( (string) ( $wcap_method['account_name'] ?? '' ) ); ?>" />
			</p>
			<p>
				<label for="wcap-bank-name"><?php esc_html_e( 'Bank name', 'logicanvas-auctions' ); ?></label>
				<input id="wcap-bank-name" type="text" name="bank_name" value="<?php echo esc_attr( (string) ( $wcap_method['bank_name'] ?? '' ) ); ?>" />
			</p>
			<p>
				<label for="wcap-account-number"><?php esc_html_e( 'Account / IBAN', 'logicanvas-auctions' ); ?></label>
				<input id="wcap-account-number" type="text" name="account_number" value="<?php echo esc_attr( (string) ( $wcap_method['account_number'] ?? '' ) ); ?>" autocomplete="off" />
			</p>
			<p>
				<label for="wcap-routing"><?php esc_html_e( 'Routing / BSB / Sort code', 'logicanvas-auctions' ); ?></label>
				<input id="wcap-routing" type="text" name="routing" value="<?php echo esc_attr( (string) ( $wcap_method['routing'] ?? '' ) ); ?>" autocomplete="off" />
			</p>
		</div>
		<div data-wcap-method-fields="paypal" <?php echo 'paypal' === $wcap_current_method ? '' : 'hidden'; ?>>
			<p>
				<label for="wcap-paypal-email"><?php esc_html_e( 'PayPal email', 'logicanvas-auctions' ); ?></label>
				<input id="wcap-paypal-email" type="email" name="paypal_email" value="<?php echo esc_attr( (string) ( $wcap_method['paypal_email'] ?? '' ) ); ?>" />
			</p>
		</div>
		<div data-wcap-method-fields="other" <?php echo 'other' === $wcap_current_method ? '' : 'hidden'; ?>>
			<p>
				<label for="wcap-other-details"><?php esc_html_e( 'Payment details', 'logicanvas-auctions' ); ?></label>
				<textarea id="wcap-other-details" name="other_details" rows="3"><?php echo esc_textarea( (string) ( $wcap_method['other_details'] ?? '' ) ); ?></textarea>
			</p>
		</div>
		<p>
			<button type="submit" class="wcap-btn"><?php esc_html_e( 'Save payment method', 'logicanvas-auctions' ); ?></button>
		</p>
		<p class="wcap-form-status" role="status"></p>
	</form>
</section>

<section class="wcap-payout-panel">
	<h3><?php esc_html_e( 'Request a payout', 'logicanvas-auctions' ); ?></h3>
	<?php if ( ! $wcap_method_ok ) : ?>
		<div class="dash-notice">
			<b><?php esc_html_e( 'Payment method required', 'logicanvas-auctions' ); ?></b>
			<span><?php esc_html_e( 'Add and save your payout details above before requesting funds.', 'logicanvas-auctions' ); ?></span>
		</div>
	<?php endif; ?>
	<form class="wcap-form lead-form" data-wcap-payout-request <?php echo $wcap_method_ok ? '' : 'aria-disabled="true"'; ?>>
		<p>
			<label for="wcap-payout-amount"><?php esc_html_e( 'Amount', 'logicanvas-auctions' ); ?></label>
			<input
				id="wcap-payout-amount"
				type="number"
				name="amount"
				step="0.01"
				min="0.01"
				max="<?php echo esc_attr( $wcap_available ); ?>"
				value="<?php echo esc_attr( $wcap_available ); ?>"
				required
				<?php disabled( ! $wcap_method_ok || $wcap_available_zero ); ?>
			/>
			<span class="wcap-meta">
				<?php
				printf(
					/* translators: %s: available balance */
					esc_html__( 'Maximum available: %s', 'logicanvas-auctions' ),
					esc_html( $wcap_pending_label )
				);
				?>
			</span>
		</p>
		<p>
			<label for="wcap-payout-note"><?php esc_html_e( 'Note (optional)', 'logicanvas-auctions' ); ?></label>
			<textarea id="wcap-payout-note" name="note" rows="2" <?php disabled( ! $wcap_method_ok ); ?>></textarea>
		</p>
		<p>
			<button type="submit" class="wcap-btn" <?php disabled( ! $wcap_method_ok || $wcap_available_zero ); ?>>
				<?php esc_html_e( 'Request payout', 'logicanvas-auctions' ); ?>
			</button>
		</p>
		<p class="wcap-form-status" role="status"></p>
	</form>
</section>

<section class="wcap-payout-panel">
	<h3><?php esc_html_e( 'Your payout requests', 'logicanvas-auctions' ); ?></h3>
	<?php if ( empty( $wcap_requests ) ) : ?>
		<p class="wcap-empty"><?php esc_html_e( 'No payout requests yet.', 'logicanvas-auctions' ); ?></p>
	<?php else : ?>
		<div class="wcap-table-wrap">
			<table class="wcap-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'logicanvas-auctions' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'logicanvas-auctions' ); ?></th>
						<th><?php esc_html_e( 'Method', 'logicanvas-auctions' ); ?></th>
						<th><?php esc_html_e( 'Status', 'logicanvas-auctions' ); ?></th>
						<th><?php esc_html_e( 'Requested', 'logicanvas-auctions' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $wcap_requests as $wcap_row ) : ?>
						<?php
						$wcap_status = (string) ( $wcap_row['status'] ?? '' );
						$wcap_mid    = (string) ( $wcap_row['payment_method'] ?? '' );
						$wcap_mlabel = $wcap_labels[ $wcap_mid ] ?? $wcap_mid;
						$wcap_proof  = ( new \LogicanvasAuctions\Domain\Settlement\PayoutRequestService() )->proof_summary( (int) $wcap_row['id'] );
						$wcap_amount = number_format( (float) ( $wcap_row['amount'] ?? 0 ), 2, '.', ',' );
						?>
						<tr>
							<td>#<?php echo esc_html( (string) $wcap_row['id'] ); ?></td>
							<td><?php echo esc_html( $wcap_amount . ' ' . (string) ( $wcap_row['currency'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) $wcap_mlabel ); ?></td>
							<td><?php echo esc_html( $wcap_status ); ?></td>
							<td><?php echo esc_html( (string) ( $wcap_row['created_at_utc'] ?? '' ) ); ?></td>
							<td>
								<?php if ( 'pending' === $wcap_status ) : ?>
									<button type="button" class="wcap-link-btn" data-wcap-payout-cancel data-id="<?php echo esc_attr( (string) $wcap_row['id'] ); ?>">
										<?php esc_html_e( 'Cancel', 'logicanvas-auctions' ); ?>
									</button>
								<?php endif; ?>
							</td>
						</tr>
						<?php if ( is_array( $wcap_proof ) && ! empty( $wcap_proof['has_proof'] ) ) : ?>
							<tr class="wcap-payout-proof-row">
								<td colspan="6">
									<div class="wcap-seller-proof">
										<strong><?php esc_html_e( 'Payout proof from site', 'logicanvas-auctions' ); ?></strong>
										<?php if ( ! empty( $wcap_proof['transaction_reference'] ) ) : ?>
											<p><span><?php esc_html_e( 'Reference', 'logicanvas-auctions' ); ?>:</span> <?php echo esc_html( (string) $wcap_proof['transaction_reference'] ); ?></p>
										<?php endif; ?>
										<?php if ( ! empty( $wcap_proof['transaction_details'] ) ) : ?>
											<p><?php echo esc_html( (string) $wcap_proof['transaction_details'] ); ?></p>
										<?php endif; ?>
										<?php if ( ! empty( $wcap_proof['download_url'] ) ) : ?>
											<p>
												<a class="wcap-btn wcap-btn--secondary" href="<?php echo esc_url( (string) $wcap_proof['download_url'] ); ?>" target="_blank" rel="noopener noreferrer">
													<?php
													printf(
														/* translators: %s: file name */
														esc_html__( 'View proof document (%s)', 'logicanvas-auctions' ),
														esc_html( (string) $wcap_proof['proof_file_name'] )
													);
													?>
												</a>
											</p>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endif; ?>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>

<section class="wcap-payout-panel">
	<h3><?php esc_html_e( 'Settlement ledger', 'logicanvas-auctions' ); ?></h3>
	<?php if ( empty( $wcap_settlements ) ) : ?>
		<p class="wcap-empty"><?php esc_html_e( 'No payout records yet.', 'logicanvas-auctions' ); ?></p>
	<?php else : ?>
		<div class="wcap-table-wrap">
			<table class="wcap-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Auction', 'logicanvas-auctions' ); ?></th>
						<th><?php esc_html_e( 'Net', 'logicanvas-auctions' ); ?></th>
						<th><?php esc_html_e( 'Status', 'logicanvas-auctions' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $wcap_settlements as $wcap_row ) : ?>
						<tr>
							<td>#<?php echo esc_html( (string) $wcap_row['auction_id'] ); ?></td>
							<td><?php echo esc_html( (string) $wcap_row['net_amount'] . ' ' . $wcap_row['currency'] ); ?></td>
							<td><?php echo esc_html( (string) $wcap_row['payout_status'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>
