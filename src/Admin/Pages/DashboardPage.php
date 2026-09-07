<?php
/**
 * Admin dashboard metrics.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Admin\Pages;

use LogicanvasAuctions\Admin\Screen;
use LogicanvasAuctions\Config;
use LogicanvasAuctions\Frontend\PluginPages;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository;

final class DashboardPage {

	private static bool $rendered = false;

	public static function render(): void {
		if ( self::$rendered ) {
			return;
		}
		self::$rendered = true;

		$page = new self();
		$page->output();
	}

	private function output(): void {
		if ( ! current_user_can( Config::CAP_MODERATE_AUCTIONS ) ) {
			wp_die( esc_html__( 'Forbidden.', 'logicanvas-auctions' ) );
		}

		$repo   = new WpdbAuctionRepository();
		$create = PluginPages::create_url();
		$action = '' !== $create
			? Screen::add_link( __( 'Add auction', 'logicanvas-auctions' ), $create )
			: Screen::add_link( __( 'Add auction', 'logicanvas-auctions' ), admin_url( 'post-new.php?post_type=' . Config::CPT ) );

		Screen::open(
			__( 'Dashboard', 'logicanvas-auctions' ),
			__( 'A live snapshot of auctions, rooms, reviews, and unpaid awards.', 'logicanvas-auctions' ),
			$action
		);
		echo '<div class="wcap-admin__stats">';
		$this->card( __( 'Active', 'logicanvas-auctions' ), $repo->count( array( 'state' => array( 'active' ), 'include_unlisted' => true ) ) );
		$this->card( __( 'Live', 'logicanvas-auctions' ), $repo->count( array( 'state' => array( 'live', 'lobby', 'going_once', 'going_twice' ), 'include_unlisted' => true ) ) );
		$this->card( __( 'Pending review', 'logicanvas-auctions' ), $repo->count( array( 'state' => array( 'pending_review' ), 'include_unlisted' => true ) ) );
		$this->card( __( 'Payment pending', 'logicanvas-auctions' ), $repo->count( array( 'state' => array( 'payment_pending', 'sold_payment_pending' ), 'include_unlisted' => true ) ) );
		echo '</div>';
		Screen::close();
	}

	private function card( string $label, int $value ): void {
		echo '<div class="wcap-admin__stat"><span>' . esc_html( $label ) . '</span><b>' . esc_html( (string) $value ) . '</b></div>';
	}
}
