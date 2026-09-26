<?php
/**
 * Admin dashboard metrics + first-run checklist.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Admin\Pages;

use LogicanvasAuctions\Admin\Screen;
use LogicanvasAuctions\Admin\Settings;
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
			__( 'Your auction overview. Finish setup once, then add lots and watch bids come in.', 'logicanvas-auctions' ),
			$action
		);

		$this->checklist();

		echo '<div class="wcap-admin__stats">';
		$this->card( __( 'Active', 'logicanvas-auctions' ), $repo->count( array( 'state' => array( 'active' ), 'include_unlisted' => true ) ) );
		$this->card( __( 'Live', 'logicanvas-auctions' ), $repo->count( array( 'state' => array( 'live', 'lobby', 'going_once', 'going_twice' ), 'include_unlisted' => true ) ) );
		$this->card( __( 'Pending review', 'logicanvas-auctions' ), $repo->count( array( 'state' => array( 'pending_review' ), 'include_unlisted' => true ) ) );
		$this->card( __( 'Payment pending', 'logicanvas-auctions' ), $repo->count( array( 'state' => array( 'payment_pending', 'sold_payment_pending' ), 'include_unlisted' => true ) ) );
		echo '</div>';
		Screen::close();
	}

	private function checklist(): void {
		$pages   = get_option( Config::OPTION_PAGES, array() );
		$ready   = is_array( $pages ) && ! empty( $pages['archive'] );
		$repo    = new WpdbAuctionRepository();
		$has_lot = $repo->count( array( 'include_unlisted' => true ) ) > 0;
		$settings = Settings::get();

		if ( $ready && $has_lot ) {
			return;
		}

		Screen::panel_open( '', __( 'Get started', 'logicanvas-auctions' ) );
		echo '<ol class="wcap-setup-steps wcap-dashboard-checklist">';

		echo '<li class="' . ( $ready ? 'is-done' : 'is-todo' ) . '">';
		echo '<strong>' . esc_html__( '1. Create auction pages', 'logicanvas-auctions' ) . '</strong>';
		if ( $ready ) {
			echo ' <span class="wcap-pill wcap-pill--ok">' . esc_html__( 'Done', 'logicanvas-auctions' ) . '</span>';
		} else {
			echo '<p>' . esc_html__( 'One click creates the catalog, dashboards, login, and payment pages. Your WordPress login and shop stay unchanged unless you opt in.', 'logicanvas-auctions' ) . '</p>';
			echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=wcap-setup' ) ) . '">' . esc_html__( 'Open setup', 'logicanvas-auctions' ) . '</a></p>';
		}
		echo '</li>';

		echo '<li class="' . ( $ready ? 'is-done' : 'is-todo' ) . '">';
		echo '<strong>' . esc_html__( '2. Review optional modes', 'logicanvas-auctions' ) . '</strong>';
		echo '<p>';
		echo esc_html__( 'Shop hide:', 'logicanvas-auctions' ) . ' <em>' . esc_html( ! empty( $settings['disable_wc_catalog'] ) ? __( 'On', 'logicanvas-auctions' ) : __( 'Off', 'logicanvas-auctions' ) ) . '</em>';
		echo ' · ' . esc_html__( 'Replace wp-login:', 'logicanvas-auctions' ) . ' <em>' . esc_html( ! empty( $settings['replace_wp_login'] ) ? __( 'On', 'logicanvas-auctions' ) : __( 'Off', 'logicanvas-auctions' ) ) . '</em>';
		echo '</p>';
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=wcap-settings' ) ) . '">' . esc_html__( 'Open settings', 'logicanvas-auctions' ) . '</a></p>';
		echo '</li>';

		echo '<li class="' . ( $has_lot ? 'is-done' : 'is-todo' ) . '">';
		echo '<strong>' . esc_html__( '3. Add your first auction', 'logicanvas-auctions' ) . '</strong>';
		if ( $has_lot ) {
			echo ' <span class="wcap-pill wcap-pill--ok">' . esc_html__( 'Done', 'logicanvas-auctions' ) . '</span>';
		} else {
			echo '<p>' . esc_html__( 'Create a timed lot with a starting price, photos, and an end time. Publish immediately as admin, or let sellers submit for review.', 'logicanvas-auctions' ) . '</p>';
			echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'post-new.php?post_type=' . Config::CPT ) ) . '">' . esc_html__( 'Add auction', 'logicanvas-auctions' ) . '</a></p>';
		}
		echo '</li>';

		echo '</ol>';
		Screen::panel_close();
	}

	private function card( string $label, int $value ): void {
		echo '<div class="wcap-admin__stat"><span>' . esc_html( $label ) . '</span><b>' . esc_html( (string) $value ) . '</b></div>';
	}
}
