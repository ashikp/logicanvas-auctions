<?php
/**
 * Live host console.
 *
 * Expects $wcap['auction'], $wcap['presenter'].
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() ) {
	echo '<div class="wcap-login-gate"><p>' . esc_html__( 'Please log in.', 'logicanvas-auctions' ) . '</p></div>';
	return;
}

if ( empty( $wcap['auction'] ) ) {
	echo '<div class="wcap-empty-auction"><h2>' . esc_html__( 'Provide auction_id.', 'logicanvas-auctions' ) . '</h2></div>';
	return;
}

$wcap_auction   = $wcap['auction'];
$wcap_presenter = $wcap['presenter'];
$wcap_user_id   = get_current_user_id();
if ( $wcap_auction->holder_id() !== $wcap_user_id && ! current_user_can( \LogicanvasAuctions\Config::CAP_MODERATE_AUCTIONS ) ) {
	echo '<div class="wcap-login-gate"><p>' . esc_html__( 'You cannot host this auction.', 'logicanvas-auctions' ) . '</p></div>';
	return;
}

$wcap_state = $wcap_presenter->public_state( $wcap_auction, $wcap_user_id );
?>
<div class="wcap-host" data-wcap-host="<?php echo esc_attr( (string) $wcap_auction->id() ); ?>">
	<p class="wcap-online-pill"><i></i><?php esc_html_e( 'Host console', 'logicanvas-auctions' ); ?></p>
	<h1><?php echo esc_html( $wcap_state['title'] ); ?></h1>
	<p data-wcap-state><?php echo esc_html( $wcap_state['state'] ); ?></p>
	<p class="wcap-price" data-wcap-price><?php echo esc_html( $wcap_state['current_price']['formatted'] ); ?></p>
	<div class="wcap-host-actions">
		<?php
		$wcap_actions = array(
			'open_lobby'  => __( 'Open lobby', 'logicanvas-auctions' ),
			'start'       => __( 'Start', 'logicanvas-auctions' ),
			'pause'       => __( 'Pause', 'logicanvas-auctions' ),
			'resume'      => __( 'Resume', 'logicanvas-auctions' ),
			'going_once'  => __( 'Going once', 'logicanvas-auctions' ),
			'going_twice' => __( 'Going twice', 'logicanvas-auctions' ),
			'sell'        => __( 'Sell', 'logicanvas-auctions' ),
			'unsold'      => __( 'Unsold', 'logicanvas-auctions' ),
			'extend'      => __( 'Extend 30s', 'logicanvas-auctions' ),
		);
		foreach ( $wcap_actions as $wcap_key => $wcap_label ) :
			?>
			<button type="button" class="wcap-btn" data-host-action="<?php echo esc_attr( $wcap_key ); ?>"><?php echo esc_html( $wcap_label ); ?></button>
		<?php endforeach; ?>
		<label><?php esc_html_e( 'Cancel reason', 'logicanvas-auctions' ); ?>
			<input type="text" data-host-reason />
		</label>
		<button type="button" class="wcap-btn wcap-btn--danger" data-host-action="cancel"><?php esc_html_e( 'Cancel session', 'logicanvas-auctions' ); ?></button>
	</div>
	<p class="wcap-form-status" role="status"></p>
</div>
