<?php
/**
 * Create listing view.
 *
 * Expects $wcap['can_sell'], $wcap['is_admin'].
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $wcap['can_sell'] ) ) {
	echo '<p class="wcap-empty">' . esc_html__( 'Your seller account must be approved before you can create a listing.', 'logicanvas-auctions' ) . '</p>';
	return;
}
?>
<div class="dash-notice">
	<b><?php esc_html_e( 'New auction lot', 'logicanvas-auctions' ); ?></b>
	<span><?php esc_html_e( 'Add a title, photos, and details. Administrators can publish immediately; other holders submit for review.', 'logicanvas-auctions' ); ?></span>
</div>
<?php
\LogicanvasAuctions\Frontend\TemplateLoader::include_partial(
	'holder/form-submit',
	array(
		'can_publish' => ! empty( $wcap['is_admin'] ) || current_user_can( \LogicanvasAuctions\Config::CAP_MODERATE_AUCTIONS ),
	)
);
