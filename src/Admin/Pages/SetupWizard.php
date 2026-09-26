<?php
/**
 * Onboarding wizard. Creates ordinary WordPress pages with shortcodes.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Admin\Pages;

use LogicanvasAuctions\Admin\Screen;
use LogicanvasAuctions\Admin\Settings;
use LogicanvasAuctions\Config;

final class SetupWizard {

	public function render(): void {
		if ( ! current_user_can( Config::CAP_MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'Forbidden.', 'logicanvas-auctions' ) );
		}

		$settings = Settings::get();
		$done     = false;

		if ( isset( $_POST['wcap_setup'] ) ) {
			check_admin_referer( 'wcap_setup' );
			$this->save_setup_options();
			$this->create_pages();
			$done     = true;
			$settings = Settings::get();
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Setup complete. Your auction pages are ready — you can edit them under Pages anytime.', 'logicanvas-auctions' ) . '</p></div>';
		}

		$pages = get_option( Config::OPTION_PAGES, array() );
		$ready = is_array( $pages ) && ! empty( $pages['archive'] );

		Screen::open(
			__( 'Setup', 'logicanvas-auctions' ),
			__( 'Three quick steps: create pages, choose optional store modes, then add your first auction.', 'logicanvas-auctions' )
		);

		Screen::panel_open( '', __( 'How it works', 'logicanvas-auctions' ) );
		echo '<ol class="wcap-setup-steps">';
		echo '<li><strong>' . esc_html__( 'Create pages', 'logicanvas-auctions' ) . '</strong> — ' . esc_html__( 'Adds normal WordPress pages for the auction catalog, dashboards, login, and checkout payment.', 'logicanvas-auctions' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Optional modes', 'logicanvas-auctions' ) . '</strong> — ' . esc_html__( 'Nothing replaces WordPress login or hides your WooCommerce shop unless you turn those options on.', 'logicanvas-auctions' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Add an auction', 'logicanvas-auctions' ) . '</strong> — ' . esc_html__( 'Create a timed or live lot from Auctions → Add Auction (or the seller submit page).', 'logicanvas-auctions' ) . '</li>';
		echo '</ol>';
		Screen::panel_close();

		Screen::panel_open( '', __( 'Create auction pages', 'logicanvas-auctions' ) );
		echo '<form method="post">';
		wp_nonce_field( 'wcap_setup' );

		echo '<fieldset class="wcap-setup-options">';
		echo '<legend>' . esc_html__( 'Optional store modes (safe defaults are off)', 'logicanvas-auctions' ) . '</legend>';

		$this->setup_check(
			'replace_wp_login',
			__( 'Replace WordPress login (wp-login.php) with the auction Log in page', 'logicanvas-auctions' ),
			! empty( $settings['replace_wp_login'] ),
			__( 'Leave off to keep the normal WordPress / WooCommerce login. You can change this later in Settings.', 'logicanvas-auctions' )
		);
		$this->setup_check(
			'login_redirect_dashboard',
			__( 'After login, send sellers and bidders to their auction dashboard', 'logicanvas-auctions' ),
			! empty( $settings['login_redirect_dashboard'] ),
			__( 'Administrators still land in wp-admin.', 'logicanvas-auctions' )
		);
		$this->setup_check(
			'restrict_wp_admin',
			__( 'Block wp-admin for sellers and bidders', 'logicanvas-auctions' ),
			! empty( $settings['restrict_wp_admin'] ),
			__( 'They use the frontend dashboards instead. Administrators are never blocked.', 'logicanvas-auctions' )
		);
		$this->setup_check(
			'disable_wc_catalog',
			__( 'Hide the WooCommerce shop and single product pages', 'logicanvas-auctions' ),
			! empty( $settings['disable_wc_catalog'] ),
			__( 'Cart, checkout, and My Account stay available for winners. Leave off if you also sell regular products.', 'logicanvas-auctions' )
		);
		echo '</fieldset>';

		$button = $ready
			? __( 'Update pages & options', 'logicanvas-auctions' )
			: __( 'Create pages & continue', 'logicanvas-auctions' );
		echo '<p><button class="button button-primary button-hero" name="wcap_setup" value="1">' . esc_html( $button ) . '</button>';
		if ( $ready || $done ) {
			echo ' <a class="button button-hero" href="' . esc_url( admin_url( 'post-new.php?post_type=' . Config::CPT ) ) . '">' . esc_html__( 'Add your first auction', 'logicanvas-auctions' ) . '</a>';
			echo ' <a class="button button-hero" href="' . esc_url( admin_url( 'admin.php?page=wcap-dashboard' ) ) . '">' . esc_html__( 'Go to dashboard', 'logicanvas-auctions' ) . '</a>';
		}
		echo '</p>';
		echo '</form>';
		Screen::panel_close();

		if ( is_array( $pages ) && $pages ) {
			Screen::panel_open( '', __( 'Assigned pages', 'logicanvas-auctions' ) );
			echo '<p class="description">' . esc_html__( 'These are ordinary WordPress pages. Edit them with the block editor or Elementor. Deleted pages are not recreated automatically.', 'logicanvas-auctions' ) . '</p>';
			echo '<ul class="wcap-admin__pages">';
			$labels = $this->page_labels();
			foreach ( $pages as $key => $id ) {
				$id    = (int) $id;
				$label = $labels[ $key ] ?? (string) $key;
				$edit  = get_edit_post_link( $id );
				$view  = get_permalink( $id );
				echo '<li><strong>' . esc_html( $label ) . '</strong>';
				if ( $edit ) {
					echo ' — <a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'logicanvas-auctions' ) . '</a>';
				}
				if ( $view ) {
					echo ' · <a href="' . esc_url( $view ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View', 'logicanvas-auctions' ) . '</a>';
				}
				echo '</li>';
			}
			echo '</ul>';
			Screen::panel_close();
		}

		Screen::close();
	}

	private function save_setup_options(): void {
		$current = Settings::get();
		$keys    = array( 'replace_wp_login', 'login_redirect_dashboard', 'restrict_wp_admin', 'disable_wc_catalog' );
		foreach ( $keys as $key ) {
			$current[ $key ] = (bool) filter_input( INPUT_POST, 'wcap_' . $key, FILTER_VALIDATE_BOOLEAN );
		}
		update_option( Config::OPTION_SETTINGS, $current, true );
	}

	/**
	 * @return array<string, string>
	 */
	private function page_labels(): array {
		return array(
			'archive' => __( 'Auction catalog', 'logicanvas-auctions' ),
			'single'  => __( 'Single auction', 'logicanvas-auctions' ),
			'live'    => __( 'Live room', 'logicanvas-auctions' ),
			'submit'  => __( 'Submit listing', 'logicanvas-auctions' ),
			'holder'  => __( 'Seller dashboard', 'logicanvas-auctions' ),
			'bidder'  => __( 'Bidder dashboard', 'logicanvas-auctions' ),
			'my_bids' => __( 'My bids', 'logicanvas-auctions' ),
			'my_wins' => __( 'My wins', 'logicanvas-auctions' ),
			'pay'     => __( 'Pay for win', 'logicanvas-auctions' ),
			'terms'   => __( 'Auction terms', 'logicanvas-auctions' ),
			'apply'   => __( 'Become a seller', 'logicanvas-auctions' ),
			'login'   => __( 'Log in', 'logicanvas-auctions' ),
		);
	}

	private function setup_check( string $key, string $label, bool $on, string $help ): void {
		$name = 'wcap_' . $key;
		echo '<p class="wcap-setup-check">';
		echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( $on, true, false ) . ' /> <strong>' . esc_html( $label ) . '</strong></label>';
		echo '<span class="description">' . esc_html( $help ) . '</span>';
		echo '</p>';
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
