<?php
/**
 * Onboarding wizard. Creates ordinary WordPress pages with shortcodes.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Admin\Pages;

use LogicanvasAuctions\Admin\Screen;
use LogicanvasAuctions\Config;

final class SetupWizard {

	public function render(): void {
		if ( ! current_user_can( Config::CAP_MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'Forbidden.', 'logicanvas-auctions' ) );
		}

		if ( isset( $_POST['wcap_setup'] ) ) {
			check_admin_referer( 'wcap_setup' );
			$this->create_pages();
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Pages created. You can edit them in Pages or Elementor.', 'logicanvas-auctions' ) . '</p></div>';
		}

		$pages = get_option( Config::OPTION_PAGES, array() );
		Screen::open(
			__( 'Setup', 'logicanvas-auctions' ),
			__( 'Create editable WordPress pages for the auction archive, dashboards, login, and live room. Pages are not recreated if you delete them later.', 'logicanvas-auctions' )
		);
		Screen::panel_open();
		echo '<form method="post">';
		wp_nonce_field( 'wcap_setup' );
		echo '<p><button class="button button-primary" name="wcap_setup" value="1">' . esc_html__( 'Create auction pages', 'logicanvas-auctions' ) . '</button></p>';
		echo '</form>';
		if ( is_array( $pages ) && $pages ) {
			echo '<h2>' . esc_html__( 'Assigned pages', 'logicanvas-auctions' ) . '</h2>';
			echo '<ul class="wcap-admin__pages">';
			foreach ( $pages as $key => $id ) {
				echo '<li>' . esc_html( (string) $key ) . ': <a href="' . esc_url( get_edit_post_link( (int) $id ) ?: '#' ) . '">' . esc_html( (string) $id ) . '</a></li>';
			}
			echo '</ul>';
		}
		Screen::panel_close();
		Screen::close();
	}

	private function create_pages(): void {
		$defs = array(
			'archive' => array( __( 'Auctions', 'logicanvas-auctions' ), '[wcap_auction_grid]' ),
			'single'  => array( __( 'Auction', 'logicanvas-auctions' ), '[wcap_single_auction]' ),
			'live'    => array( __( 'Live auction', 'logicanvas-auctions' ), '[wcap_live_room]' ),
			'submit'  => array( __( 'Submit auction', 'logicanvas-auctions' ), '[wcap_submit_auction]' ),
			'holder'  => array( __( 'Auction holder dashboard', 'logicanvas-auctions' ), '[wcap_holder_dashboard]' ),
			'bidder'  => array( __( 'Bidder dashboard', 'logicanvas-auctions' ), '[wcap_bidder_dashboard]' ),
			'my_bids' => array( __( 'My bids', 'logicanvas-auctions' ), '[wcap_my_bids]' ),
			'my_wins' => array( __( 'My wins', 'logicanvas-auctions' ), '[wcap_my_wins]' ),
			'pay'     => array( __( 'Pay for won auction', 'logicanvas-auctions' ), '[wcap_pay_award]' ),
			'terms'   => array( __( 'Auction terms', 'logicanvas-auctions' ), '<p>' . esc_html__( 'Auction terms will be published here.', 'logicanvas-auctions' ) . '</p>' ),
			'apply'   => array( __( 'Become an auction holder', 'logicanvas-auctions' ), '[wcap_holder_apply]' ),
			'login'   => array( __( 'Log in', 'logicanvas-auctions' ), '[wcap_login]', 'login' ),
		);

		$pages = get_option( Config::OPTION_PAGES, array() );
		if ( ! is_array( $pages ) ) {
			$pages = array();
		}

		foreach ( $defs as $key => $def ) {
			if ( ! empty( $pages[ $key ] ) && get_post( (int) $pages[ $key ] ) ) {
				continue;
			}

			$post = array(
				'post_title'   => $def[0],
				'post_content' => $def[1],
				'post_status'  => 'publish',
				'post_type'    => 'page',
			);
			if ( ! empty( $def[2] ) ) {
				$post['post_name'] = (string) $def[2];
			}

			$id = wp_insert_post( $post );
			if ( $id && ! is_wp_error( $id ) ) {
				$pages[ $key ] = (int) $id;
			}
		}

		update_option( Config::OPTION_PAGES, $pages, false );
	}
}
