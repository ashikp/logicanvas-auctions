<?php
/**
 * Holder apply form.
 *
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() ) {
	$wcap_login = wp_login_url( get_permalink() ?: home_url( '/' ) );
	echo '<div class="wcap-login-gate"><p>' . esc_html__( 'Log in to apply.', 'logicanvas-auctions' ) . ' <a href="' . esc_url( $wcap_login ) . '">' . esc_html__( 'Log in', 'logicanvas-auctions' ) . '</a></p></div>';
	return;
}
?>
<div class="wcap-form-page">
	<section class="wcap-page-hero seller-hero">
		<div class="wcap-hero-inner">
			<p class="eyebrow"><span></span> <?php esc_html_e( 'Become a holder', 'logicanvas-auctions' ); ?></p>
			<h1><?php esc_html_e( 'Sell with this auction house.', 'logicanvas-auctions' ); ?></h1>
			<p><?php esc_html_e( 'Approved auction holders can submit lots, host live rooms, and view settlements. Applications are reviewed by an administrator.', 'logicanvas-auctions' ); ?></p>
		</div>
	</section>
	<section class="wcap-form-section" id="wcap-apply">
		<div class="form-layout">
			<div>
				<p class="eyebrow dark"><span></span> <?php esc_html_e( 'Application', 'logicanvas-auctions' ); ?></p>
				<h2><?php esc_html_e( 'Tell us about your offerings.', 'logicanvas-auctions' ); ?></h2>
				<p><?php esc_html_e( 'This is not an open public listing marketplace. Holders are approved, and lots are reviewed before they appear in the catalog.', 'logicanvas-auctions' ); ?></p>
				<ul class="checklist">
					<li><?php esc_html_e( 'Company or trading name', 'logicanvas-auctions' ); ?></li>
					<li><?php esc_html_e( 'Categories you intend to sell', 'logicanvas-auctions' ); ?></li>
					<li><?php esc_html_e( 'Location and logistics notes', 'logicanvas-auctions' ); ?></li>
				</ul>
			</div>
			<form class="wcap-form lead-form" data-wcap-apply>
				<label><?php esc_html_e( 'Company / trading name', 'logicanvas-auctions' ); ?>
					<input name="company" type="text" required />
				</label>
				<label class="full"><?php esc_html_e( 'Application notes', 'logicanvas-auctions' ); ?>
					<textarea name="notes" rows="5" placeholder="<?php esc_attr_e( 'Categories, location, typical lot size, and timeline', 'logicanvas-auctions' ); ?>"></textarea>
				</label>
				<button class="wcap-btn full" type="submit"><?php esc_html_e( 'Submit for review →', 'logicanvas-auctions' ); ?></button>
				<p class="wcap-form-status full" role="status"></p>
			</form>
		</div>
	</section>
</div>
