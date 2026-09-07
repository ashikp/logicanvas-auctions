<?php
/**
 * Awards list.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Admin\Pages;

use LogicanvasAuctions\Admin\Screen;
use LogicanvasAuctions\Config;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;

final class AwardsPage {

	public function render(): void {
		if ( ! current_user_can( Config::CAP_MODERATE_AUCTIONS ) ) {
			wp_die( esc_html__( 'Forbidden.', 'logicanvas-auctions' ) );
		}

		global $wpdb;
		$table = Config::table( Config::TABLE_AWARDS );
		$rows  = QueryCache::remember(
			QueryCache::key( 'admin_awards' ),
			45,
			static function () use ( $wpdb, $table ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 100', $table ), ARRAY_A );
			}
		);
		Screen::open(
			__( 'Awards', 'logicanvas-auctions' ),
			__( 'Winning bids, payment deadlines, and linked WooCommerce orders.', 'logicanvas-auctions' )
		);
		Screen::panel_open();
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Auction', 'logicanvas-auctions' ) . '</th><th>' . esc_html__( 'Winner', 'logicanvas-auctions' ) . '</th><th>' . esc_html__( 'Amount', 'logicanvas-auctions' ) . '</th><th>' . esc_html__( 'Status', 'logicanvas-auctions' ) . '</th><th>' . esc_html__( 'Deadline', 'logicanvas-auctions' ) . '</th><th>' . esc_html__( 'Order', 'logicanvas-auctions' ) . '</th></tr></thead><tbody>';
		foreach ( (array) $rows as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $row['id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['auction_id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['winner_id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['amount'] . ' ' . $row['currency'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['status'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['payment_deadline_utc'] ) . '</td>';
			echo '<td>' . esc_html( (string) ( $row['order_id'] ?? '' ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		Screen::panel_close();
		Screen::close();
	}
}
