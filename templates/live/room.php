<?php
/**
 * Live room.
 *
 * Expects $wcap['auction'], $wcap['presenter'].
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $wcap['auction'] ) ) {
	echo '<div class="wcap-empty-auction"><h2>' . esc_html__( 'Select a live auction.', 'logicanvas-auctions' ) . '</h2></div>';
	return;
}

$wcap_auction   = $wcap['auction'];
$wcap_presenter = $wcap['presenter'];
$wcap_state     = $wcap_presenter->public_state( $wcap_auction, get_current_user_id() );
if ( empty( $wcap_state['accessible'] ) ) {
	echo '<div class="wcap-empty-auction"><h2>' . esc_html__( 'This auction is private.', 'logicanvas-auctions' ) . '</h2></div>';
	return;
}
?>
<div class="wcap-live" data-wcap-live="<?php echo esc_attr( (string) $wcap_auction->id() ); ?>" data-sequence="<?php echo esc_attr( (string) $wcap_state['sequence'] ); ?>"<?php echo ! empty( $wcap_state['winner_key'] ) ? ' data-winner-key="' . esc_attr( (string) $wcap_state['winner_key'] ) . '"' : ''; ?>>
	<p class="wcap-connection" data-wcap-connection><?php esc_html_e( 'Connecting…', 'logicanvas-auctions' ); ?></p>
	<?php if ( ! empty( $wcap_state['featured_image'] ) ) : ?>
		<p><img src="<?php echo esc_url( $wcap_state['featured_image'] ); ?>" alt="<?php echo esc_attr( $wcap_state['title'] ); ?>" /></p>
	<?php endif; ?>
	<p class="wcap-online-pill"><i></i><?php esc_html_e( 'Live room', 'logicanvas-auctions' ); ?></p>
	<h1><?php echo esc_html( $wcap_state['title'] ); ?></h1>
	<p class="wcap-badge" data-wcap-state><?php echo esc_html( $wcap_state['state'] ); ?></p>
	<p class="wcap-price" data-wcap-price><?php echo esc_html( $wcap_state['current_price']['formatted'] ); ?></p>
	<p data-wcap-lead aria-live="polite"></p>
	<div class="wcap-countdown" data-end="<?php echo esc_attr( (string) $wcap_state['end_at_utc'] ); ?>" data-server-ts="<?php echo esc_attr( (string) $wcap_state['server_ts'] ); ?>"></div>
	<?php if ( is_user_logged_in() && $wcap_state['can_bid'] ) : ?>
		<form class="wcap-bid-form" data-wcap-bid-form>
			<label for="wcap-live-amount"><?php esc_html_e( 'Your bid', 'logicanvas-auctions' ); ?></label>
			<input id="wcap-live-amount" name="amount" type="text" required value="<?php echo esc_attr( $wcap_state['next_min_bid']['amount'] ); ?>" />
			<button class="wcap-btn" type="submit"><?php esc_html_e( 'Bid', 'logicanvas-auctions' ); ?></button>
			<button type="button" class="wcap-btn wcap-btn--secondary" data-wcap-quick><?php esc_html_e( 'Quick bid', 'logicanvas-auctions' ); ?></button>
			<p class="wcap-form-status" role="status"></p>
		</form>
	<?php elseif ( ! is_user_logged_in() ) : ?>
		<p><a class="wcap-btn" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Log in to join bidding', 'logicanvas-auctions' ); ?></a></p>
	<?php endif; ?>
	<ol data-wcap-activity aria-live="polite"></ol>
</div>
