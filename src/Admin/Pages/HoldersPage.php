<?php
/**
 * Holder applications.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Admin\Pages;

use LogicanvasAuctions\Admin\ActionUI;
use LogicanvasAuctions\Admin\Screen;
use LogicanvasAuctions\Config;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;

final class HoldersPage {

	public function render(): void {
		if ( ! current_user_can( Config::CAP_MANAGE_HOLDERS ) ) {
			wp_die( esc_html__( 'Forbidden.', 'logicanvas-auctions' ) );
		}

		global $wpdb;
		$table = Config::table( Config::TABLE_HOLDERS );
		$rows  = QueryCache::remember(
			QueryCache::key( 'admin_holders' ),
			45,
			static function () use ( $wpdb, $table ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 100', $table ), ARRAY_A );
			}
		);

		Screen::open(
			__( 'Auction holders', 'logicanvas-auctions' ),
			__( 'Approve third-party sellers before they can publish or host auctions.', 'logicanvas-auctions' )
		);
		Screen::panel_open();
		echo '<table class="widefat striped wcap-admin-table"><thead><tr><th>ID</th><th>' . esc_html__( 'User', 'logicanvas-auctions' ) . '</th><th>' . esc_html__( 'Status', 'logicanvas-auctions' ) . '</th><th>' . esc_html__( 'Company', 'logicanvas-auctions' ) . '</th><th>' . esc_html__( 'Actions', 'logicanvas-auctions' ) . '</th></tr></thead><tbody>';
		foreach ( (array) $rows as $row ) {
			$user = get_user_by( 'id', (int) $row['user_id'] );
			echo '<tr>';
			echo '<td>' . esc_html( (string) $row['id'] ) . '</td>';
			echo '<td>' . esc_html( $user ? $user->user_login : (string) $row['user_id'] ) . '</td>';
			echo '<td><span class="wcap-state-pill">' . esc_html( (string) $row['status'] ) . '</span></td>';
			echo '<td>' . esc_html( (string) $row['company'] ) . '</td>';
			echo '<td><div class="wcap-actions">';
			ActionUI::button(
				array(
					'action'  => 'approve_holder',
					'label'   => __( 'Approve', 'logicanvas-auctions' ),
					'fields'  => array( 'holder_user_id' => (int) $row['user_id'] ),
					'confirm' => __( 'Approve this auction holder application?', 'logicanvas-auctions' ),
					'title'   => __( 'Approve holder', 'logicanvas-auctions' ),
				)
			);
			ActionUI::button(
				array(
					'action'       => 'reject_holder',
					'label'        => __( 'Reject', 'logicanvas-auctions' ),
					'fields'       => array( 'holder_user_id' => (int) $row['user_id'] ),
					'need_reason'  => true,
					'reason_label' => __( 'Reason for rejection', 'logicanvas-auctions' ),
					'confirm'      => __( 'Reject this holder application?', 'logicanvas-auctions' ),
					'title'        => __( 'Reject holder', 'logicanvas-auctions' ),
				)
			);
			echo '</div></td></tr>';
		}
		echo '</tbody></table>';
		Screen::panel_close();
		Screen::close();
		ActionUI::ensure_modal();
	}
}
