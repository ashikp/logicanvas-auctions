<?php
/**
 * Winner payment shortcode.
 *
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() ) {
	$wcap_login = wp_login_url( get_permalink() ?: home_url( '/' ) );
	echo '<div class="wcap-login-gate"><p>' . esc_html__( 'Please log in to pay.', 'logicanvas-auctions' ) . ' <a href="' . esc_url( $wcap_login ) . '">' . esc_html__( 'Log in', 'logicanvas-auctions' ) . '</a></p></div>';
	return;
}

$wcap_award_id = isset( $_GET['award_id'] ) ? absint( $_GET['award_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$wcap_award    = $wcap_award_id ? ( new \LogicanvasAuctions\Domain\Award\AwardService() )->find( $wcap_award_id ) : null;

if ( ! $wcap_award || (int) $wcap_award['winner_id'] !== get_current_user_id() ) {
	echo '<div class="wcap-empty-auction"><h2>' . esc_html__( 'This payment link is not valid for your account.', 'logicanvas-auctions' ) . '</h2></div>';
	return;
}

if ( function_exists( 'wc_print_notices' ) ) {
	echo '<div class="wcap-wc-notices">';
	wc_print_notices();
	echo '</div>';
}
?>
<div class="wcap-form-section">
	<div class="form-layout">
		<div>
			<p class="eyebrow dark"><span></span> <?php esc_html_e( 'Winner checkout', 'logicanvas-auctions' ); ?></p>
			<h2><?php esc_html_e( 'Pay for won auction', 'logicanvas-auctions' ); ?></h2>
			<p><?php echo esc_html( sprintf( /* translators: 1: amount, 2: currency */ __( 'Awarded amount: %1$s %2$s. Checkout uses the store’s WooCommerce payment gateways. Quantity and price are locked.', 'logicanvas-auctions' ), (string) $wcap_award['amount'], (string) $wcap_award['currency'] ) ); ?></p>
		</div>
		<div class="wcap-pay lead-form" data-award-id="<?php echo esc_attr( (string) $wcap_award_id ); ?>">
			<p class="full"><strong><?php echo esc_html( (string) $wcap_award['status'] ); ?></strong></p>
			<?php if ( 'pending' === (string) $wcap_award['status'] ) : ?>
				<form method="post" action="" class="wcap-checkout-start-form">
					<?php wp_nonce_field( 'wcap_start_checkout' ); ?>
					<input type="hidden" name="wcap_start_checkout" value="1" />
					<input type="hidden" name="award_id" value="<?php echo esc_attr( (string) $wcap_award_id ); ?>" />
					<button type="submit" class="wcap-btn full"><?php esc_html_e( 'Continue to checkout →', 'logicanvas-auctions' ); ?></button>
				</form>
			<?php else : ?>
				<p class="wcap-meta"><?php esc_html_e( 'This award is no longer awaiting payment.', 'logicanvas-auctions' ); ?></p>
			<?php endif; ?>
			<p class="wcap-form-status full" role="status"></p>
		</div>
	</div>
</div>
