<?php
/**
 * Auctions moderation list.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Admin\Pages;

use LogicanvasAuctions\Admin\ActionUI;
use LogicanvasAuctions\Admin\Screen;
use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Auction\AuctionState;
use LogicanvasAuctions\Frontend\PluginPages;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository;

final class AuctionsPage {

	public function render(): void {
		if ( ! current_user_can( Config::CAP_MODERATE_AUCTIONS ) ) {
			wp_die( esc_html__( 'Forbidden.', 'logicanvas-auctions' ) );
		}

		$repo     = new WpdbAuctionRepository();
		$auctions = $repo->query(
			array(
				'include_unlisted' => true,
				'limit'            => 50,
				'orderby'          => 'updated_at_utc',
				'order'            => 'DESC',
				'visibility'       => '',
			)
		);

		$create = PluginPages::create_url();
		$action = '' !== $create
			? Screen::add_link( __( 'Add auction', 'logicanvas-auctions' ), $create )
			: Screen::add_link( __( 'Add auction', 'logicanvas-auctions' ), admin_url( 'post-new.php?post_type=' . Config::CPT ) );

		Screen::open(
			__( 'Auctions', 'logicanvas-auctions' ),
			__( 'Review listings, approve submissions, and close or cancel auctions.', 'logicanvas-auctions' ),
			$action
		);
		Screen::panel_open();
		echo '<table class="widefat striped wcap-admin-table"><thead><tr>';
		echo '<th>' . esc_html__( 'ID', 'logicanvas-auctions' ) . '</th><th>' . esc_html__( 'Title', 'logicanvas-auctions' ) . '</th><th>' . esc_html__( 'Type', 'logicanvas-auctions' ) . '</th><th>' . esc_html__( 'State', 'logicanvas-auctions' ) . '</th><th>' . esc_html__( 'Price', 'logicanvas-auctions' ) . '</th><th>' . esc_html__( 'Actions', 'logicanvas-auctions' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $auctions as $auction ) {
			$post  = get_post( $auction->id() );
			$state = $auction->state();
			echo '<tr>';
			echo '<td>' . esc_html( (string) $auction->id() ) . '</td>';
			echo '<td><a href="' . esc_url( get_edit_post_link( $auction->id() ) ?: '#' ) . '">' . esc_html( $post ? $post->post_title : '' ) . '</a></td>';
			echo '<td>' . esc_html( $auction->type() ) . '</td>';
			echo '<td><span class="wcap-state-pill">' . esc_html( $state ) . '</span></td>';
			echo '<td>' . esc_html( $auction->current_amount()->formatted() . ' ' . $auction->currency() ) . '</td>';
			echo '<td><div class="wcap-actions">';
			if ( in_array( $state, array( AuctionState::DRAFT, AuctionState::PENDING_REVIEW, AuctionState::REJECTED ), true ) ) {
				ActionUI::button(
					array(
						'action'  => 'approve_auction',
						'label'   => __( 'Approve', 'logicanvas-auctions' ),
						'fields'  => array( 'auction_id' => $auction->id() ),
						'confirm' => __( 'Approve this auction and move it to scheduled?', 'logicanvas-auctions' ),
						'title'   => __( 'Approve auction', 'logicanvas-auctions' ),
					)
				);
			}
			if ( AuctionState::PENDING_REVIEW === $state ) {
				ActionUI::button(
					array(
						'action'       => 'reject_auction',
						'label'        => __( 'Reject', 'logicanvas-auctions' ),
						'fields'       => array( 'auction_id' => $auction->id() ),
						'need_reason'  => true,
						'reason_label' => __( 'Reason for rejection', 'logicanvas-auctions' ),
						'confirm'      => __( 'Reject this auction submission?', 'logicanvas-auctions' ),
						'title'        => __( 'Reject auction', 'logicanvas-auctions' ),
					)
				);
			}
			if ( ! AuctionState::is_terminal( $state ) && AuctionState::CANCELLED !== $state ) {
				ActionUI::button(
					array(
						'action'       => 'force_close',
						'label'        => __( 'Force close', 'logicanvas-auctions' ),
						'fields'       => array( 'auction_id' => $auction->id() ),
						'need_reason'  => true,
						'reason_label' => __( 'Reason for force close', 'logicanvas-auctions' ),
						'confirm'      => __( 'Force-close this auction now? This cannot be undone.', 'logicanvas-auctions' ),
						'title'        => __( 'Force close auction', 'logicanvas-auctions' ),
					)
				);
				ActionUI::button(
					array(
						'action'       => 'cancel_auction',
						'label'        => __( 'Cancel', 'logicanvas-auctions' ),
						'fields'       => array( 'auction_id' => $auction->id() ),
						'need_reason'  => true,
						'reason_label' => __( 'Reason for cancellation', 'logicanvas-auctions' ),
						'confirm'      => __( 'Cancel this auction?', 'logicanvas-auctions' ),
						'title'        => __( 'Cancel auction', 'logicanvas-auctions' ),
					)
				);
			}
			echo '</div></td></tr>';
		}

		echo '</tbody></table>';
		Screen::panel_close();
		Screen::close();
		ActionUI::ensure_modal();
	}
}
