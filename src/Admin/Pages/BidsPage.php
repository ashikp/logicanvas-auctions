<?php
/**
 * Bid audit.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Admin\Pages;

use LogicanvasAuctions\Admin\ActionUI;
use LogicanvasAuctions\Admin\Screen;
use LogicanvasAuctions\Config;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;

final class BidsPage {

	public function render(): void {
		if ( ! current_user_can( Config::CAP_MANAGE_BIDS ) ) {
			wp_die( esc_html__( 'Forbidden.', 'logicanvas-auctions' ) );
		}

		global $wpdb;
		$table      = Config::table( Config::TABLE_BIDS );
		$raw_id     = filter_input( INPUT_GET, 'auction_id' );
		$auction_id = ( is_string( $raw_id ) || is_int( $raw_id ) ) ? absint( $raw_id ) : 0;
		$cache_key  = QueryCache::key( 'admin_bids', $auction_id );
		$rows       = wp_cache_get( $cache_key, QueryCache::GROUP );

		if ( false === $rows || ! is_array( $rows ) ) {
			if ( $auction_id ) {
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE auction_id = %d ORDER BY id DESC LIMIT 100',
						$table,
						$auction_id
					),
					ARRAY_A
				);
			} else {
				$rows = $wpdb->get_results(
					$wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 100', $table ),
					ARRAY_A
				);
			}
			$rows = is_array( $rows ) ? $rows : array();
			wp_cache_set( $cache_key, $rows, QueryCache::GROUP, 30 );
		}

		Screen::open(
			__( 'Bid audit', 'logicanvas-auctions' ),
			__( 'Inspect accepted bids and void a bid only when you record a reason.', 'logicanvas-auctions' )
		);
		ActionUI::ensure_modal();
		Screen::panel_open();
		echo '<table class="widefat striped wcap-admin-table"><thead><tr><th>ID</th><th>' . esc_html__( 'Auction', 'logicanvas-auctions' ) . '</th><th>' . esc_html__( 'Bidder', 'logicanvas-auctions' ) . '</th><th>' . esc_html__( 'Amount', 'logicanvas-auctions' ) . '</th><th>' . esc_html__( 'Status', 'logicanvas-auctions' ) . '</th><th>' . esc_html__( 'Time', 'logicanvas-auctions' ) . '</th><th>' . esc_html__( 'Actions', 'logicanvas-auctions' ) . '</th></tr></thead><tbody>';
		foreach ( (array) $rows as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $row['id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['auction_id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['bidder_id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['amount'] . ' ' . $row['currency'] ) . '</td>';
			echo '<td><span class="wcap-state-pill">' . esc_html( (string) $row['status'] ) . '</span></td>';
			echo '<td>' . esc_html( (string) $row['created_at_utc'] ) . '</td>';
			echo '<td><div class="wcap-actions">';
			if ( 'accepted' === $row['status'] ) {
				ActionUI::button(
					array(
						'action'       => 'void_bid',
						'label'        => __( 'Void', 'logicanvas-auctions' ),
						'fields'       => array( 'bid_id' => (int) $row['id'] ),
						'need_reason'  => true,
						'reason_label' => __( 'Reason for voiding this bid', 'logicanvas-auctions' ),
						'confirm'      => __( 'Void this accepted bid? The auction state will be recalculated.', 'logicanvas-auctions' ),
						'title'        => __( 'Void bid', 'logicanvas-auctions' ),
					)
				);
			} else {
				echo '<span class="wcap-actions__empty">—</span>';
			}
			echo '</div></td></tr>';
		}
		echo '</tbody></table>';
		Screen::panel_close();
		Screen::close();
	}
}
