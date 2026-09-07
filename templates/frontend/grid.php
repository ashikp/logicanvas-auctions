<?php
/**
 * Auction listing.
 *
 * Expects $wcap['auctions'], $wcap['presenter'], $wcap['filter'].
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcap_auctions  = is_array( $wcap['auctions'] ?? null ) ? $wcap['auctions'] : array();
$wcap_presenter = $wcap['presenter'] ?? null;
$wcap_filter    = (string) ( $wcap['filter'] ?? '' );
$wcap_base      = remove_query_arg( 'wcap_type' );
$wcap_filters   = array(
	''      => __( 'All', 'logicanvas-auctions' ),
	'timed' => __( 'Timed', 'logicanvas-auctions' ),
	'live'  => __( 'Live', 'logicanvas-auctions' ),
);

$wcap_label_for = static function ( string $wcap_state, string $wcap_type ): string {
	if ( in_array( $wcap_state, array( 'live', 'going_once', 'going_twice', 'active' ), true ) ) {
		return __( 'Bidding open', 'logicanvas-auctions' );
	}
	if ( 'lobby' === $wcap_state ) {
		return __( 'Lobby open', 'logicanvas-auctions' );
	}
	if ( 'scheduled' === $wcap_state ) {
		return __( 'Coming soon', 'logicanvas-auctions' );
	}
	return strtoupper( $wcap_state ?: $wcap_type );
};
?>
<div class="wcap-market" data-wcap-grid>
	<section class="wcap-page-hero">
		<div class="wcap-hero-inner">
			<div class="wcap-online-pill"><i></i><?php esc_html_e( 'Online auction hall', 'logicanvas-auctions' ); ?></div>
			<h1><?php esc_html_e( 'Bid anytime.', 'logicanvas-auctions' ); ?><br /><em><?php esc_html_e( 'From anywhere.', 'logicanvas-auctions' ); ?></em></h1>
			<p><?php esc_html_e( 'Register once, browse live catalogs, place bids, and receive closing alerts on your phone or computer.', 'logicanvas-auctions' ); ?></p>
			<div class="wcap-hero-actions">
				<a class="wcap-btn" href="#wcap-catalogs"><?php esc_html_e( 'Browse auctions ↓', 'logicanvas-auctions' ); ?></a>
				<?php
				$wcap_terms = \LogicanvasAuctions\Frontend\PluginPages::url( 'terms' );
				if ( $wcap_terms ) :
					?>
					<a class="wcap-btn wcap-btn--ghost" href="<?php echo esc_url( $wcap_terms ); ?>"><?php esc_html_e( 'How online bidding works', 'logicanvas-auctions' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<section class="wcap-bid-steps">
		<div class="wcap-bid-steps-inner">
			<div><b>01</b><span><?php esc_html_e( 'Register', 'logicanvas-auctions' ); ?></span></div>
			<div><b>02</b><span><?php esc_html_e( 'Review terms', 'logicanvas-auctions' ); ?></span></div>
			<div><b>03</b><span><?php esc_html_e( 'Place bids', 'logicanvas-auctions' ); ?></span></div>
			<div><b>04</b><span><?php esc_html_e( 'Pay & pick up', 'logicanvas-auctions' ); ?></span></div>
		</div>
	</section>
	<section class="wcap-catalog" id="wcap-catalogs">
		<div class="wcap-catalog-head">
			<div>
				<p class="eyebrow dark"><span></span> <?php esc_html_e( 'Live catalog', 'logicanvas-auctions' ); ?></p>
				<h2><?php esc_html_e( 'Current & upcoming', 'logicanvas-auctions' ); ?></h2>
			</div>
			<div class="wcap-catalog-filters">
				<?php foreach ( $wcap_filters as $wcap_key => $wcap_label ) : ?>
					<a class="<?php echo $wcap_filter === $wcap_key ? 'active' : ''; ?>" href="<?php echo esc_url( $wcap_key ? add_query_arg( 'wcap_type', $wcap_key, $wcap_base ) : $wcap_base ); ?>"><?php echo esc_html( $wcap_label ); ?></a>
				<?php endforeach; ?>
			</div>
		</div>

		<?php if ( empty( $wcap_auctions ) ) : ?>
			<div class="wcap-empty-auction">
				<span><?php esc_html_e( 'Catalog', 'logicanvas-auctions' ); ?></span>
				<h2><?php esc_html_e( 'No auctions found.', 'logicanvas-auctions' ); ?></h2>
				<p><?php esc_html_e( 'Check back soon for timed and live lots, or ask an approved holder to submit an offering.', 'logicanvas-auctions' ); ?></p>
			</div>
		<?php else : ?>
			<div class="wcap-event-list">
				<?php foreach ( $wcap_auctions as $wcap_auction ) : ?>
					<?php $wcap_state = $wcap_presenter->public_state( $wcap_auction, get_current_user_id() ); ?>
					<article class="wcap-event">
						<div class="wcap-event-pic"<?php echo ! empty( $wcap_state['featured_image'] ) ? ' style="background-image:url(' . esc_url( (string) $wcap_state['featured_image'] ) . ')"' : ''; ?>>
							<span><?php echo esc_html( sprintf( /* translators: %d bid count */ _n( '%d bid', '%d bids', (int) $wcap_state['bid_count'], 'logicanvas-auctions' ), (int) $wcap_state['bid_count'] ) ); ?></span>
						</div>
						<div class="wcap-event-info">
							<div class="wcap-status"><i></i><?php echo esc_html( $wcap_label_for( (string) $wcap_state['state'], (string) $wcap_state['type'] ) ); ?></div>
							<small><?php echo esc_html( strtoupper( (string) $wcap_state['type'] ) ); ?><?php echo ! empty( $wcap_state['condition'] ) ? ' · ' . esc_html( (string) $wcap_state['condition'] ) : ''; ?></small>
							<h3><a href="<?php echo esc_url( (string) $wcap_state['permalink'] ); ?>"><?php echo esc_html( (string) $wcap_state['title'] ); ?></a></h3>
							<p>
								<?php echo esc_html( (string) $wcap_state['current_price']['formatted'] ); ?>
								<?php if ( ! empty( $wcap_state['end_at_utc'] ) ) : ?>
									· <?php echo esc_html( sprintf( /* translators: %s UTC timestamp */ __( 'Closes %s UTC', 'logicanvas-auctions' ), (string) $wcap_state['end_at_utc'] ) ); ?>
								<?php endif; ?>
							</p>
						</div>
						<div class="wcap-event-actions">
							<a class="wcap-btn wcap-btn--secondary" href="<?php echo esc_url( (string) $wcap_state['permalink'] ); ?>"><?php esc_html_e( 'View catalog', 'logicanvas-auctions' ); ?></a>
							<a class="wcap-btn" href="<?php echo esc_url( (string) $wcap_state['permalink'] ); ?>"><?php esc_html_e( 'Register & bid →', 'logicanvas-auctions' ); ?></a>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
</div>
