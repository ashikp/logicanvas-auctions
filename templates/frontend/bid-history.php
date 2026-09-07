<?php
/**
 * Bid history / leaderboard; populated via REST.
 *
 * Expects $wcap['auction'].
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$wcap_auction = $wcap['auction'] ?? null;
$wcap_id = $wcap_auction ? $wcap_auction->id() : 0;
$wcap_winner_key = '';
if ( $wcap_auction ) {
	$wcap_state = ( new \LogicanvasAuctions\Application\AuctionPresenter() )->public_state( $wcap_auction, get_current_user_id() );
	$wcap_winner_key = (string) ( $wcap_state['winner_key'] ?? '' );
}
?>
<section class="wcap-leaderboard wcap-history" data-wcap-history data-auction-id="<?php echo esc_attr( (string) $wcap_id ); ?>"<?php echo $wcap_winner_key ? ' data-winner-key="' . esc_attr( $wcap_winner_key ) . '"' : ''; ?> aria-live="polite">
	<div class="wcap-leaderboard__head">
		<div>
			<p class="eyebrow dark"><span></span> <?php esc_html_e( 'Live board', 'logicanvas-auctions' ); ?></p>
			<h2><?php esc_html_e( 'Bidder leaderboard', 'logicanvas-auctions' ); ?></h2>
		</div>
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
