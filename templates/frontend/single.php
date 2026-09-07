<?php
/**
 * Single auction.
 *
 * Expects $wcap['auction'], $wcap['presenter'].
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcap_auction   = $wcap['auction'] ?? null;
$wcap_presenter = $wcap['presenter'] ?? null;
if ( ! $wcap_auction ) {
	echo '<div class="wcap-empty-auction"><span>' . esc_html__( 'Lot', 'logicanvas-auctions' ) . '</span><h2>' . esc_html__( 'Auction not found.', 'logicanvas-auctions' ) . '</h2></div>';
	return;
}

$wcap_state = $wcap_presenter->public_state( $wcap_auction, get_current_user_id() );
if ( empty( $wcap_state['accessible'] ) ) {
	echo '<div class="wcap-empty-auction"><span>' . esc_html__( 'Private', 'logicanvas-auctions' ) . '</span><h2>' . esc_html__( 'This auction is private.', 'logicanvas-auctions' ) . '</h2></div>';
	return;
}

$wcap_currency = (string) ( $wcap_state['currency'] ?? '' );
$wcap_gallery  = is_array( $wcap_state['gallery_items'] ?? null ) ? $wcap_state['gallery_items'] : array();
$wcap_featured = (string) ( $wcap_state['featured_image'] ?? '' );
if ( '' === $wcap_featured && ! empty( $wcap_gallery[0]['full'] ) ) {
	$wcap_featured = (string) $wcap_gallery[0]['full'];
}
$wcap_featured_alt = (string) ( $wcap_state['title'] ?? '' );

$wcap_qr            = is_array( $wcap_state['qr'] ?? null ) ? $wcap_state['qr'] : array();
$wcap_qr_share      = (string) ( $wcap_qr['share_url'] ?? $wcap_state['permalink'] ?? '' );
$wcap_qr_text       = (string) ( $wcap_qr['details'] ?? '' );
$wcap_has_share_tab = ( '' !== $wcap_qr_share || '' !== $wcap_qr_text );
?>
<div class="wcap-single" data-wcap-auction="<?php echo esc_attr( (string) $wcap_auction->id() ); ?>" data-sequence="<?php echo esc_attr( (string) $wcap_state['sequence'] ); ?>"<?php echo ! empty( $wcap_state['winner_key'] ) ? ' data-winner-key="' . esc_attr( (string) $wcap_state['winner_key'] ) . '"' : ''; ?>>
	<div class="wcap-single__media" data-wcap-gallery>
		<?php if ( $wcap_featured ) : ?>
			<button type="button" class="wcap-single__hero" data-wcap-gallery-hero data-full="<?php echo esc_url( $wcap_featured ); ?>" aria-label="<?php esc_attr_e( 'Open photo preview', 'logicanvas-auctions' ); ?>">
				<img src="<?php echo esc_url( $wcap_featured ); ?>" alt="<?php echo esc_attr( $wcap_featured_alt ); ?>" data-wcap-gallery-main />
			</button>
		<?php else : ?>
			<div class="lot-art"><span><?php esc_html_e( 'No photo yet', 'logicanvas-auctions' ); ?></span></div>
		<?php endif; ?>
		<?php if ( count( $wcap_gallery ) > 1 ) : ?>
			<div class="wcap-gallery" role="list">
				<?php foreach ( $wcap_gallery as $wcap_i => $wcap_item ) : ?>
					<?php
					$wcap_thumb = (string) ( $wcap_item['thumb'] ?? $wcap_item['full'] ?? '' );
					$wcap_full  = (string) ( $wcap_item['full'] ?? $wcap_thumb );
					$wcap_alt   = (string) ( $wcap_item['alt'] ?? $wcap_featured_alt );
					if ( '' === $wcap_full ) {
						continue;
					}
					?>
					<button
						type="button"
						class="wcap-gallery__thumb<?php echo 0 === (int) $wcap_i ? ' is-active' : ''; ?>"
						role="listitem"
						data-wcap-gallery-thumb
						data-full="<?php echo esc_url( $wcap_full ); ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %d: image number */ __( 'View photo %d', 'logicanvas-auctions' ), (int) $wcap_i + 1 ) ); ?>"
						aria-pressed="<?php echo 0 === (int) $wcap_i ? 'true' : 'false'; ?>"
					>
						<img src="<?php echo esc_url( $wcap_thumb ); ?>" alt="<?php echo esc_attr( $wcap_alt ); ?>" loading="lazy" />
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="wcap-single__main">
		<div class="wcap-lot-head">
			<p class="lot-tag"><i></i><?php echo esc_html( strtoupper( (string) $wcap_state['type'] ) ); ?></p>
			<h1><?php echo esc_html( $wcap_state['title'] ); ?></h1>
			<p class="wcap-meta">
				<?php echo esc_html( $wcap_state['holder'] ); ?>
				<?php if ( ! empty( $wcap_state['category']['name'] ) ) : ?>
					· <?php echo esc_html( (string) $wcap_state['category']['name'] ); ?>
				<?php endif; ?>
				<?php echo ! empty( $wcap_state['condition'] ) ? ' · ' . esc_html( (string) $wcap_state['condition'] ) : ''; ?>
			</p>
			<?php if ( ! empty( $wcap_state['unique_id'] ) ) : ?>
				<p class="wcap-meta wcap-lot-id"><?php echo esc_html( (string) $wcap_state['unique_id'] ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $wcap_state['can_edit'] ) && ! empty( $wcap_state['edit_url'] ) ) : ?>
				<p class="wcap-edit-listing">
					<a class="wcap-btn wcap-btn--secondary" href="<?php echo esc_url( (string) $wcap_state['edit_url'] ); ?>">
						<?php esc_html_e( 'Edit listing', 'logicanvas-auctions' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>

		<div class="wcap-bid-panel">
			<div class="wcap-bid-panel__stats">
				<div>
					<span class="wcap-bid-panel__label"><?php esc_html_e( 'Current bid', 'logicanvas-auctions' ); ?></span>
					<strong class="wcap-price" data-wcap-price><?php echo esc_html( $wcap_state['current_price']['formatted'] ); ?></strong>
				</div>
				<div>
					<span class="wcap-bid-panel__label"><?php esc_html_e( 'Time left', 'logicanvas-auctions' ); ?></span>
					<strong class="wcap-countdown" data-end="<?php echo esc_attr( (string) $wcap_state['end_at_utc'] ); ?>" data-server-ts="<?php echo esc_attr( (string) $wcap_state['server_ts'] ); ?>" aria-live="polite"></strong>
				</div>
				<div>
					<span class="wcap-bid-panel__label"><?php esc_html_e( 'Status', 'logicanvas-auctions' ); ?></span>
					<span class="wcap-badge" data-wcap-state><?php echo esc_html( $wcap_state['state'] ); ?></span>
				</div>
			</div>

			<div class="wcap-bid-panel__meta">
				<p><?php esc_html_e( 'Next minimum', 'logicanvas-auctions' ); ?> <strong data-wcap-next><?php echo esc_html( $wcap_state['next_min_bid']['formatted'] ); ?></strong></p>
				<p data-wcap-reserve><?php echo $wcap_state['reserve_met'] ? esc_html__( 'Reserve met', 'logicanvas-auctions' ) : esc_html__( 'Reserve not yet met', 'logicanvas-auctions' ); ?></p>
				<p class="wcap-status" data-wcap-lead aria-live="polite">
					<?php echo ! empty( $wcap_state['is_leading'] ) ? esc_html__( 'You are leading', 'logicanvas-auctions' ) : ''; ?>
				</p>
			</div>

			<?php if ( ! is_user_logged_in() ) : ?>
				<p><a class="wcap-btn" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Log in to bid', 'logicanvas-auctions' ); ?></a></p>
			<?php elseif ( $wcap_state['can_bid'] ) : ?>
				<form class="wcap-bid-form" data-wcap-bid-form>
					<label for="wcap-amount"><?php esc_html_e( 'Your bid amount', 'logicanvas-auctions' ); ?></label>
					<div class="wcap-bid-form__row">
						<div class="wcap-bid-input">
							<span class="wcap-bid-input__prefix" data-wcap-currency-symbol aria-hidden="true"><?php echo esc_html( (string) ( $wcap_state['next_min_bid']['symbol'] ?? $wcap_currency ) ); ?></span>
							<input id="wcap-amount" name="amount" type="text" inputmode="decimal" required value="<?php echo esc_attr( $wcap_state['next_min_bid']['amount'] ); ?>" />
						</div>
						<button type="button" class="wcap-btn wcap-btn--secondary" data-wcap-quick><?php esc_html_e( 'Quick bid', 'logicanvas-auctions' ); ?></button>
						<button type="submit" class="wcap-btn"><?php esc_html_e( 'Place bid', 'logicanvas-auctions' ); ?></button>
					</div>
					<p class="wcap-form-status" role="status"></p>
				</form>
			<?php elseif ( $wcap_auction->is_holder( get_current_user_id() ) ) : ?>
				<p class="wcap-meta"><?php esc_html_e( 'You are the auction holder, so bidding is disabled on this lot.', 'logicanvas-auctions' ); ?></p>
				<?php if ( ! empty( $wcap_state['can_accept_bid'] ) ) : ?>
					<div class="wcap-holder-sell">
						<p class="wcap-meta"><?php esc_html_e( 'You can end this timed auction now and sell to the current highest bidder at their bid price. The winner will be asked to pay through WooCommerce.', 'logicanvas-auctions' ); ?></p>
						<button type="button" class="wcap-btn" data-wcap-accept-bid data-id="<?php echo esc_attr( (string) $wcap_auction->id() ); ?>">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: current bid amount */
									__( 'Accept current bid (%s)', 'logicanvas-auctions' ),
									(string) $wcap_state['current_price']['formatted']
								)
							);
							?>
						</button>
						<p class="wcap-form-status" data-wcap-accept-status role="status"></p>
					</div>
				<?php endif; ?>
			<?php elseif ( ! empty( $wcap_state['accepts_bids'] ) ) : ?>
				<p class="wcap-meta"><?php esc_html_e( 'Bidding is open, but your account is not eligible to bid on this auction.', 'logicanvas-auctions' ); ?></p>
			<?php elseif ( in_array( (string) $wcap_state['state'], array( 'scheduled', 'lobby' ), true ) ) : ?>
				<p class="wcap-meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: start time UTC */
							__( 'Bidding has not opened yet. Scheduled start: %s UTC.', 'logicanvas-auctions' ),
							(string) $wcap_state['start_at_utc']
						)
					);
					?>
				</p>
			<?php else : ?>
				<p class="wcap-meta"><?php esc_html_e( 'This auction is not currently accepting bids.', 'logicanvas-auctions' ); ?></p>
			<?php endif; ?>

			<div class="wcap-bid-panel__actions">
				<?php if ( ! empty( $wcap_state['award'] ) && 'pending' === $wcap_state['award']['status'] ) : ?>
					<?php
					$wcap_pay_href = ! empty( $wcap_state['award']['pay_url'] )
						? (string) $wcap_state['award']['pay_url']
						: add_query_arg( 'award_id', (int) $wcap_state['award']['id'], get_permalink( (int) ( get_option( \LogicanvasAuctions\Config::OPTION_PAGES )['pay'] ?? 0 ) ) );
					?>
					<a class="wcap-btn" href="<?php echo esc_url( $wcap_pay_href ); ?>"><?php esc_html_e( 'Pay for won auction', 'logicanvas-auctions' ); ?></a>
				<?php endif; ?>
				<button type="button" class="wcap-btn wcap-btn--secondary" data-wcap-watch><?php esc_html_e( 'Watch', 'logicanvas-auctions' ); ?></button>
			</div>
		</div>
	</div>

	<section class="wcap-lot-tabs" data-wcap-lot-tabs>
		<div class="wcap-lot-tabs__nav" role="tablist" aria-label="<?php esc_attr_e( 'Lot information', 'logicanvas-auctions' ); ?>">
			<button type="button" class="wcap-lot-tabs__tab is-active" role="tab" id="wcap-tab-details" aria-selected="true" aria-controls="wcap-panel-details" data-wcap-tab="details">
				<?php esc_html_e( 'Details', 'logicanvas-auctions' ); ?>
			</button>
			<?php if ( $wcap_has_share_tab ) : ?>
				<button type="button" class="wcap-lot-tabs__tab" role="tab" id="wcap-tab-share" aria-selected="false" aria-controls="wcap-panel-share" data-wcap-tab="share">
					<?php esc_html_e( 'Share', 'logicanvas-auctions' ); ?>
				</button>
			<?php endif; ?>
		</div>

		<div class="wcap-lot-tabs__panel is-active" role="tabpanel" id="wcap-panel-details" aria-labelledby="wcap-tab-details" data-wcap-tab-panel="details">
			<?php if ( ! empty( $wcap_state['excerpt'] ) ) : ?>
				<div class="wcap-excerpt"><?php echo $wcap_state['excerpt']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- formatted listing HTML ?></div>
			<?php endif; ?>
			<div class="wcap-content"><?php echo $wcap_state['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- formatted listing HTML ?></div>
			<?php if ( ! empty( $wcap_state['fulfilment_notes'] ) || ! empty( $wcap_state['fulfilment'] ) ) : ?>
				<p class="wcap-meta wcap-fulfilment"><strong><?php esc_html_e( 'Fulfilment', 'logicanvas-auctions' ); ?>:</strong> <?php echo esc_html( (string) $wcap_state['fulfilment'] ); ?>
					<?php if ( ! empty( $wcap_state['fulfilment_notes'] ) ) : ?>
						— <?php echo esc_html( (string) $wcap_state['fulfilment_notes'] ); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>

		<?php if ( $wcap_has_share_tab ) : ?>
			<div class="wcap-lot-tabs__panel" role="tabpanel" id="wcap-panel-share" aria-labelledby="wcap-tab-share" data-wcap-tab-panel="share" hidden>
				<div
					class="wcap-qr-panel"
					data-wcap-qr
					data-share-url="<?php echo esc_url( $wcap_qr_share ); ?>"
					data-details="<?php echo esc_attr( $wcap_qr_text ); ?>"
				>
					<?php if ( $wcap_qr_share ) : ?>
						<p class="wcap-qr-panel__link">
							<label class="wcap-qr-panel__link-label" for="wcap-share-url"><?php esc_html_e( 'Share link', 'logicanvas-auctions' ); ?></label>
							<span class="wcap-qr-panel__link-row">
								<input id="wcap-share-url" class="wcap-qr-panel__link-input" type="text" readonly value="<?php echo esc_url( $wcap_qr_share ); ?>" />
								<button type="button" class="wcap-btn wcap-btn--secondary" data-wcap-copy-share><?php esc_html_e( 'Copy', 'logicanvas-auctions' ); ?></button>
							</span>
						</p>
					<?php endif; ?>
					<?php
					\LogicanvasAuctions\Frontend\TemplateLoader::include_partial(
						'partials/share-social',
						array(
							'share_url'   => $wcap_qr_share,
							'share_title' => (string) $wcap_state['title'],
						)
					);
					?>
					<div class="wcap-qr-panel__grid">
						<?php if ( $wcap_qr_share ) : ?>
							<div class="wcap-qr-card">
								<div class="wcap-qr-card__code" data-wcap-qr-share role="img" aria-label="<?php esc_attr_e( 'QR code for auction page URL', 'logicanvas-auctions' ); ?>"></div>
								<p class="wcap-qr-card__label"><?php esc_html_e( 'Page link', 'logicanvas-auctions' ); ?></p>
							</div>
						<?php endif; ?>
						<?php if ( $wcap_qr_text ) : ?>
							<div class="wcap-qr-card">
								<div class="wcap-qr-card__code" data-wcap-qr-details role="img" aria-label="<?php esc_attr_e( 'QR code for lot details', 'logicanvas-auctions' ); ?>"></div>
								<p class="wcap-qr-card__label"><?php esc_html_e( 'Lot details', 'logicanvas-auctions' ); ?></p>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</section>

	<section class="wcap-leaderboard" data-wcap-history aria-live="polite">
		<div class="wcap-leaderboard__head">
			<div>
				<p class="eyebrow dark"><span></span> <?php esc_html_e( 'Live board', 'logicanvas-auctions' ); ?></p>
				<h2><?php esc_html_e( 'Bidder leaderboard', 'logicanvas-auctions' ); ?></h2>
			</div>
			<p class="wcap-meta" data-wcap-bid-count>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: bid count */
						_n( '%d bid placed', '%d bids placed', (int) $wcap_state['bid_count'], 'logicanvas-auctions' ),
						(int) $wcap_state['bid_count']
					)
				);
				?>
			</p>
		</div>
		<div class="wcap-leaderboard__table-wrap">
			<table class="wcap-leaderboard__table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Rank', 'logicanvas-auctions' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Bidder', 'logicanvas-auctions' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Highest bid', 'logicanvas-auctions' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Bids', 'logicanvas-auctions' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Last bid', 'logicanvas-auctions' ); ?></th>
					</tr>
				</thead>
				<tbody data-wcap-leaderboard-body>
					<tr class="wcap-leaderboard__empty">
						<td colspan="5"><?php esc_html_e( 'Loading bidder standings…', 'logicanvas-auctions' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<details class="wcap-recent-bids">
			<summary><?php esc_html_e( 'Recent bid activity', 'logicanvas-auctions' ); ?></summary>
			<ol data-wcap-recent-bids></ol>
		</details>
	</section>
</div>
