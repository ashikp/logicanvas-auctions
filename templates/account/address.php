<?php
/**
 * Billing and shipping addresses.
 *
 * Expects $wcap['addresses'].
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcap_addresses = is_array( $wcap['addresses'] ?? null ) ? $wcap['addresses'] : array();
$wcap_billing   = (string) ( $wcap_addresses['billing'] ?? '' );
$wcap_shipping  = (string) ( $wcap_addresses['shipping'] ?? '' );
$wcap_edit      = (string) ( $wcap_addresses['edit_url'] ?? '' );
?>
<div class="dash-notice">
	<b><?php esc_html_e( 'Checkout addresses', 'logicanvas-auctions' ); ?></b>
	<span><?php esc_html_e( 'These WooCommerce addresses are used when you pay for a won auction.', 'logicanvas-auctions' ); ?></span>
</div>
<div class="dashboard-grid">
	<section class="seller-panel">
		<div class="dash-title"><h2><?php esc_html_e( 'Billing', 'logicanvas-auctions' ); ?></h2></div>
		<?php if ( $wcap_billing ) : ?>
			<div class="wcap-address"><?php echo wp_kses_post( $wcap_billing ); ?></div>
		<?php else : ?>
			<p class="wcap-empty"><?php esc_html_e( 'No billing address on file.', 'logicanvas-auctions' ); ?></p>
		<?php endif; ?>
	</section>
	<section class="seller-panel">
		<div class="dash-title"><h2><?php esc_html_e( 'Shipping', 'logicanvas-auctions' ); ?></h2></div>
		<?php if ( $wcap_shipping ) : ?>
			<div class="wcap-address"><?php echo wp_kses_post( $wcap_shipping ); ?></div>
		<?php else : ?>
			<p class="wcap-empty"><?php esc_html_e( 'No shipping address on file.', 'logicanvas-auctions' ); ?></p>
		<?php endif; ?>
	</section>
</div>
<?php if ( $wcap_edit ) : ?>
	<p><a class="wcap-btn" href="<?php echo esc_url( $wcap_edit ); ?>"><?php esc_html_e( 'Edit addresses in WooCommerce →', 'logicanvas-auctions' ); ?></a></p>
<?php endif; ?>
