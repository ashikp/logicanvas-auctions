<?php
/**
 * Holder "accept current bid" action cards.
 *
 * Expects $wcap['rows'].
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcap_rows = is_array( $wcap['rows'] ?? null ) ? $wcap['rows'] : array();
if ( empty( $wcap_rows ) ) {
	return;
}
?>
<section class="wcap-accept-board" id="wcap-accept-bids">
	<div class="wcap-accept-board__head">
		<div>
			<p class="eyebrow dark"><span></span> <?php esc_html_e( 'Sell now', 'logicanvas-auctions' ); ?></p>
			<h2><?php esc_html_e( 'Accept current bids', 'logicanvas-auctions' ); ?></h2>
			<p class="wcap-meta"><?php esc_html_e( 'End a timed auction immediately and sell to the highest bidder at their current price. They will pay through WooCommerce.', 'logicanvas-auctions' ); ?></p>
		</div>
	</div>
	<div class="wcap-accept-board__grid">
		<?php foreach ( $wcap_rows as $wcap_row ) : ?>
			<article class="wcap-accept-card">
				<div class="wcap-accept-card__art"<?php echo ! empty( $wcap_row['image'] ) ? ' style="background-image:url(' . esc_url( (string) $wcap_row['image'] ) . ')"' : ''; ?>></div>
				<div class="wcap-accept-card__body">
					<small><?php echo esc_html( strtoupper( (string) ( $wcap_row['state'] ?? '' ) ) ); ?> · <?php echo esc_html( strtoupper( (string) ( $wcap_row['type'] ?? '' ) ) ); ?></small>
					<h3><?php echo esc_html( (string) ( $wcap_row['title'] ?? '' ) ); ?></h3>
					<p class="wcap-accept-card__price"><?php echo esc_html( (string) ( $wcap_row['price'] ?? '' ) ); ?></p>
					<p class="wcap-meta"><?php echo esc_html( sprintf( /* translators: %d bid count */ _n( '%d bid', '%d bids', (int) ( $wcap_row['bids'] ?? 0 ), 'logicanvas-auctions' ), (int) ( $wcap_row['bids'] ?? 0 ) ) ); ?></p>
					<div class="wcap-accept-card__actions">
						<button type="button" class="wcap-btn" data-wcap-accept-bid data-id="<?php echo esc_attr( (string) ( $wcap_row['id'] ?? '' ) ); ?>">
							<?php esc_html_e( 'Accept current bid', 'logicanvas-auctions' ); ?>
						</button>
						<?php if ( ! empty( $wcap_row['permalink'] ) ) : ?>
							<a class="wcap-btn wcap-btn--secondary" href="<?php echo esc_url( (string) $wcap_row['permalink'] ); ?>"><?php esc_html_e( 'View lot', 'logicanvas-auctions' ); ?></a>
						<?php endif; ?>
					</div>
					<p class="wcap-form-status" role="status"></p>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
</section>
