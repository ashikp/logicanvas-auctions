<?php
/**
 * Frontend edit listing page (admin-like Classic Editor experience).
 *
 * Expects $wcap['can_sell'], $wcap['is_admin'], $wcap['edit'], $wcap['auction_id'].
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $wcap['can_sell'] ) ) {
	echo '<p class="wcap-empty">' . esc_html__( 'Your seller account must be approved before you can edit a listing.', 'logicanvas-auctions' ) . '</p>';
	return;
}

$wcap_edit = is_array( $wcap['edit'] ?? null ) ? $wcap['edit'] : null;
if ( ! $wcap_edit ) {
	echo '<p class="wcap-empty">' . esc_html__( 'That listing could not be loaded for editing. It may be locked after bidding starts, or you may not have permission.', 'logicanvas-auctions' ) . '</p>';
	echo '<p><a class="wcap-btn wcap-btn--secondary" href="' . esc_url( \LogicanvasAuctions\Frontend\Dashboards\AccountRouter::url( \LogicanvasAuctions\Frontend\Dashboards\AccountRouter::LISTINGS ) ) . '">' . esc_html__( 'Back to listings', 'logicanvas-auctions' ) . '</a></p>';
	return;
}
?>
<div class="dash-notice">
	<b><?php esc_html_e( 'Edit listing', 'logicanvas-auctions' ); ?></b>
	<span><?php esc_html_e( 'Update this auction like the admin editor: Classic Editor for the description, photos, prices, schedule, and visibility.', 'logicanvas-auctions' ); ?></span>
</div>
<?php
\LogicanvasAuctions\Frontend\TemplateLoader::include_partial(
	'holder/form-edit',
	array(
		'can_publish' => ! empty( $wcap['is_admin'] ) || current_user_can( \LogicanvasAuctions\Config::CAP_MODERATE_AUCTIONS ),
		'edit'        => $wcap_edit,
		'auction_id'  => (int) ( $wcap['auction_id'] ?? $wcap_edit['id'] ?? 0 ),
	)
);
