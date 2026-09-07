<?php
/**
 * Create auction form.
 *
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() ) {
	$wcap_login = wp_login_url( get_permalink() ?: home_url( '/' ) );
	echo '<div class="wcap-login-gate"><p>' . esc_html__( 'Log in to create an auction.', 'logicanvas-auctions' ) . ' <a href="' . esc_url( $wcap_login ) . '">' . esc_html__( 'Log in', 'logicanvas-auctions' ) . '</a></p></div>';
	return;
}

if ( ! current_user_can( \LogicanvasAuctions\Config::CAP_CREATE_AUCTIONS ) ) {
	echo '<div class="wcap-login-gate"><p>' . esc_html__( 'You do not have permission to create auctions.', 'logicanvas-auctions' ) . '</p></div>';
	return;
}
?>
<div class="wcap-form-page">
	<section class="wcap-page-hero seller-hero">
		<div class="wcap-hero-inner">
			<p class="eyebrow"><span></span> <?php esc_html_e( 'Consignments', 'logicanvas-auctions' ); ?></p>
			<h1><?php esc_html_e( 'Quality items.', 'logicanvas-auctions' ); ?><br /><?php esc_html_e( 'Professionally sold.', 'logicanvas-auctions' ); ?></h1>
			<p><?php esc_html_e( 'Submit equipment, vehicles, collections, and specialty assets for review. Accepted lots are cataloged and offered to registered bidders.', 'logicanvas-auctions' ); ?></p>
			<div class="wcap-hero-actions">
				<a class="wcap-btn" href="#wcap-submit"><?php esc_html_e( 'Submit items →', 'logicanvas-auctions' ); ?></a>
			</div>
		</div>
	</section>
	<section class="wcap-form-section">
		<div class="wcap-system-grid">
			<article><b>01</b><h3><?php esc_html_e( 'Submit details', 'logicanvas-auctions' ); ?></h3><p><?php esc_html_e( 'Provide title, condition, photographs, starting price, and your preferred timeline.', 'logicanvas-auctions' ); ?></p></article>
			<article><b>02</b><h3><?php esc_html_e( 'Asset review', 'logicanvas-auctions' ); ?></h3><p><?php esc_html_e( 'Administrators evaluate suitability, likely bidder demand, logistics, and the auction format.', 'logicanvas-auctions' ); ?></p></article>
			<article><b>03</b><h3><?php esc_html_e( 'Written terms', 'logicanvas-auctions' ); ?></h3><p><?php esc_html_e( 'Accepted items receive documented commission, reserve, marketing, and settlement terms.', 'logicanvas-auctions' ); ?></p></article>
			<article><b>04</b><h3><?php esc_html_e( 'Sold & settled', 'logicanvas-auctions' ); ?></h3><p><?php esc_html_e( 'The site catalogs the lot, conducts bidding, collects funds through WooCommerce, and records settlement.', 'logicanvas-auctions' ); ?></p></article>
		</div>
	</section>
	<section class="wcap-form-section" id="wcap-submit">
		<div class="form-layout">
			<div>
				<p class="eyebrow dark"><span></span> <?php esc_html_e( 'Listing review', 'logicanvas-auctions' ); ?></p>
				<h2><?php esc_html_e( 'What would you like to sell?', 'logicanvas-auctions' ); ?></h2>
				<p><?php esc_html_e( 'Use this form to create a timed or live auction lot. Upload photos and set starting price, reserve, and fulfilment notes.', 'logicanvas-auctions' ); ?></p>
				<ul class="checklist">
					<li><?php esc_html_e( 'Clear photographs of the actual item', 'logicanvas-auctions' ); ?></li>
					<li><?php esc_html_e( 'Honest condition notes', 'logicanvas-auctions' ); ?></li>
					<li><?php esc_html_e( 'Starting price and increment', 'logicanvas-auctions' ); ?></li>
					<li><?php esc_html_e( 'Pickup or shipping instructions', 'logicanvas-auctions' ); ?></li>
				</ul>
			</div>
			<?php
			\LogicanvasAuctions\Frontend\TemplateLoader::include_partial(
				'holder/form-submit',
				array(
					'can_publish' => current_user_can( \LogicanvasAuctions\Config::CAP_MODERATE_AUCTIONS ),
				)
			);
			?>
		</div>
	</section>
</div>
