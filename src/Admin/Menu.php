<?php
/**
 * WordPress admin menu and screens.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Admin;

use LogicanvasAuctions\Admin\Pages\AuctionsPage;
use LogicanvasAuctions\Admin\Pages\AwardsPage;
use LogicanvasAuctions\Admin\Pages\BidsPage;
use LogicanvasAuctions\Admin\Pages\DashboardPage;
use LogicanvasAuctions\Admin\Pages\DiagnosticsPage;
use LogicanvasAuctions\Admin\Pages\HoldersPage;
use LogicanvasAuctions\Admin\Pages\SettingsPage;
use LogicanvasAuctions\Admin\Pages\SettlementsPage;
use LogicanvasAuctions\Admin\Pages\SetupWizard;
use LogicanvasAuctions\Config;
use LogicanvasAuctions\Frontend\PluginPages;

final class Menu {

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menus' ), 9 );
		add_action( 'admin_menu', array( $this, 'ensure_dashboard_submenu' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_wcap_admin_action', array( $this, 'handle_action' ) );
		add_action( 'admin_post_wcap_view_payout_proof', array( $this, 'view_payout_proof' ) );
		add_action( 'admin_notices', array( $this, 'render_notices' ) );
		add_action( 'load-post-new.php', array( $this, 'redirect_new_auction' ) );
		add_filter( 'parent_file', array( $this, 'fix_category_parent_menu' ) );
		add_filter( 'submenu_file', array( $this, 'fix_category_submenu' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( Config::plugin_file() ), array( $this, 'plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Keep Auctions menu open while managing auction categories.
	 *
	 * @param string $parent_file Parent file.
	 */
	public function fix_category_parent_menu( string $parent_file ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && Config::TAXONOMY_CAT === $screen->taxonomy ) {
			return 'wcap-dashboard';
		}
		return $parent_file;
	}

	/**
	 * Highlight Categories submenu on taxonomy screens.
	 *
	 * @param string|null $submenu_file Submenu file.
	 */
	public function fix_category_submenu( $submenu_file ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && Config::TAXONOMY_CAT === $screen->taxonomy ) {
			return 'edit-tags.php?taxonomy=' . Config::TAXONOMY_CAT . '&post_type=' . Config::CPT;
		}
		return (string) $submenu_file;
	}

	public function enqueue_admin_assets( string $hook ): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_wcap = str_starts_with( $page, 'wcap-' )
			|| ( $screen && ( str_contains( (string) $screen->id, 'wcap-' ) || Config::CPT === $screen->post_type || Config::TAXONOMY_CAT === $screen->taxonomy ) );

		if ( ! $is_wcap ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'wcap-admin',
			plugins_url( 'assets/dist/css/admin.css', Config::plugin_file() ),
			array( 'dashicons' ),
			Config::VERSION
		);
	}

	/**
	 * @param string[] $links
	 * @return string[]
	 */
	public function plugin_action_links( array $links ): array {
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wcap-dashboard' ) ) . '">' . esc_html__( 'Dashboard', 'logicanvas-auctions' ) . '</a>';
		$links[] = '<a href="' . esc_url( Config::DOCS_URI ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Docs', 'logicanvas-auctions' ) . '</a>';
		return $links;
	}

	/**
	 * @param string[] $links
	 * @return string[]
	 */
	public function plugin_row_meta( array $links, string $file ): array {
		if ( plugin_basename( Config::plugin_file() ) !== $file ) {
			return $links;
		}

		$links[] = '<a href="' . esc_url( Config::DOCS_URI ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Documentation', 'logicanvas-auctions' ) . '</a>';
		return $links;
	}

	public function menus(): void {
		add_menu_page(
			Config::PRODUCT_NAME,
			__( 'Auctions', 'logicanvas-auctions' ),
			Config::CAP_MODERATE_AUCTIONS,
			'wcap-dashboard',
			array( DashboardPage::class, 'render' ),
			'dashicons-store',
			56
		);

		// Same slug as parent = renames the first submenu to Dashboard (WordPress core pattern).
		add_submenu_page(
			'wcap-dashboard',
			__( 'Dashboard', 'logicanvas-auctions' ),
			__( 'Dashboard', 'logicanvas-auctions' ),
			Config::CAP_MODERATE_AUCTIONS,
			'wcap-dashboard',
			array( DashboardPage::class, 'render' )
		);

		$create_url = PluginPages::create_url();

		if ( '' !== $create_url ) {
			add_submenu_page(
				'wcap-dashboard',
				__( 'Add Auction', 'logicanvas-auctions' ),
				__( 'Add Auction', 'logicanvas-auctions' ),
				Config::CAP_CREATE_AUCTIONS,
				'wcap-add-auction',
				array( $this, 'redirect_add_auction_page' )
			);
		} else {
			add_submenu_page( 'wcap-dashboard', __( 'Add Auction', 'logicanvas-auctions' ), __( 'Add Auction', 'logicanvas-auctions' ), Config::CAP_CREATE_AUCTIONS, 'post-new.php?post_type=' . Config::CPT );
		}
		add_submenu_page( 'wcap-dashboard', __( 'All Auctions', 'logicanvas-auctions' ), __( 'All Auctions', 'logicanvas-auctions' ), Config::CAP_MODERATE_AUCTIONS, 'wcap-auctions', array( new AuctionsPage(), 'render' ) );
		add_submenu_page(
			'wcap-dashboard',
			__( 'Auction Categories', 'logicanvas-auctions' ),
			__( 'Categories', 'logicanvas-auctions' ),
			Config::CAP_MODERATE_AUCTIONS,
			'edit-tags.php?taxonomy=' . Config::TAXONOMY_CAT . '&post_type=' . Config::CPT
		);
		add_submenu_page( 'wcap-dashboard', __( 'Holders', 'logicanvas-auctions' ), __( 'Holders', 'logicanvas-auctions' ), Config::CAP_MANAGE_HOLDERS, 'wcap-holders', array( new HoldersPage(), 'render' ) );
		add_submenu_page( 'wcap-dashboard', __( 'Bids', 'logicanvas-auctions' ), __( 'Bids', 'logicanvas-auctions' ), Config::CAP_MANAGE_BIDS, 'wcap-bids', array( new BidsPage(), 'render' ) );
		add_submenu_page( 'wcap-dashboard', __( 'Awards', 'logicanvas-auctions' ), __( 'Awards', 'logicanvas-auctions' ), Config::CAP_MODERATE_AUCTIONS, 'wcap-awards', array( new AwardsPage(), 'render' ) );
		add_submenu_page( 'wcap-dashboard', __( 'Settlements', 'logicanvas-auctions' ), __( 'Settlements', 'logicanvas-auctions' ), Config::CAP_MANAGE_SETTLEMENTS, 'wcap-settlements', array( new SettlementsPage(), 'render' ) );
		add_submenu_page( 'wcap-dashboard', __( 'Settings', 'logicanvas-auctions' ), __( 'Settings', 'logicanvas-auctions' ), Config::CAP_MANAGE_SETTINGS, 'wcap-settings', array( new SettingsPage(), 'render' ) );
		add_submenu_page( 'wcap-dashboard', __( 'Diagnostics', 'logicanvas-auctions' ), __( 'Diagnostics', 'logicanvas-auctions' ), Config::CAP_MANAGE_SETTINGS, 'wcap-diagnostics', array( new DiagnosticsPage(), 'render' ) );
		add_submenu_page( 'wcap-dashboard', __( 'Setup', 'logicanvas-auctions' ), __( 'Setup Wizard', 'logicanvas-auctions' ), Config::CAP_MANAGE_SETTINGS, 'wcap-setup', array( new SetupWizard(), 'render' ) );
	}

	/**
	 * Keep Dashboard as the first submenu even after CPT items are attached.
	 */
	public function ensure_dashboard_submenu(): void {
		global $submenu;

		if ( empty( $submenu['wcap-dashboard'] ) || ! is_array( $submenu['wcap-dashboard'] ) ) {
			return;
		}

		$items     = $submenu['wcap-dashboard'];
		$dashboard = null;
		$rest      = array();

		foreach ( $items as $item ) {
			$slug = (string) ( $item[2] ?? '' );
			// Drop native CPT list clutter; All Auctions covers moderation.
			if ( str_starts_with( $slug, 'edit.php?post_type=' . Config::CPT ) ) {
				continue;
			}
			if ( 'wcap-dashboard' === $slug && null === $dashboard ) {
				$item[0]   = __( 'Dashboard', 'logicanvas-auctions' );
				$dashboard = $item;
				continue;
			}
			$rest[] = $item;
		}

		if ( null === $dashboard ) {
			$dashboard = array(
				__( 'Dashboard', 'logicanvas-auctions' ),
				Config::CAP_MODERATE_AUCTIONS,
				'wcap-dashboard',
			);
		}

		$submenu['wcap-dashboard'] = array_values( array_merge( array( $dashboard ), $rest ) );
	}

	public function redirect_add_auction_page(): void {
		$url = PluginPages::create_url();
		if ( '' === $url ) {
			wp_die( esc_html__( 'Create the auction submission page in Auctions → Setup first.', 'logicanvas-auctions' ) );
		}
		wp_safe_redirect( $url );
		exit;
	}

	public function redirect_new_auction(): void {
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( (string) $_GET['post_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( Config::CPT !== $post_type ) {
			return;
		}

		$url = PluginPages::create_url();
		if ( '' === $url ) {
			return;
		}

		wp_safe_redirect( $url );
		exit;
	}

	public function handle_action(): void {
		if ( ! current_user_can( Config::CAP_MODERATE_AUCTIONS ) ) {
			wp_die( esc_html__( 'Forbidden.', 'logicanvas-auctions' ) );
		}

		check_admin_referer( 'wcap_admin_action' );

		$action     = sanitize_key( (string) wp_unslash( $_POST['wcap_action'] ?? '' ) );
		$auction_id = isset( $_POST['auction_id'] ) ? absint( $_POST['auction_id'] ) : 0;
		$reason     = sanitize_textarea_field( (string) wp_unslash( $_POST['reason'] ?? '' ) );
		$user_id    = get_current_user_id();

		$service = new \LogicanvasAuctions\Domain\Auction\AuctionService();
		$awards  = new \LogicanvasAuctions\Domain\Award\AwardService();
		$holders = new \LogicanvasAuctions\Domain\Holder\HolderService();
		$result  = true;

		switch ( $action ) {
			case 'approve_auction':
				$result = $service->approve( $auction_id, $user_id );
				break;
			case 'reject_auction':
				$result = $service->reject( $auction_id, $user_id, $reason );
				break;
			case 'cancel_auction':
				$result = $service->transition( $auction_id, 'cancelled', $user_id, 'user', $reason ?: 'Cancelled by administrator' );
				break;
			case 'force_close':
				$awards->close( $auction_id, $user_id, $reason ?: 'Force close' );
				$result = true;
				break;
			case 'approve_holder':
				$holders->approve( isset( $_POST['holder_user_id'] ) ? absint( $_POST['holder_user_id'] ) : 0, $user_id );
				break;
			case 'reject_holder':
				$holders->reject( isset( $_POST['holder_user_id'] ) ? absint( $_POST['holder_user_id'] ) : 0, $user_id, $reason );
				break;
			case 'void_bid':
				$this->void_bid( isset( $_POST['bid_id'] ) ? absint( $_POST['bid_id'] ) : 0, $user_id, $reason );
				break;
			case 'payout_paid':
				( new \LogicanvasAuctions\Domain\Settlement\SettlementService() )->set_payout_status( isset( $_POST['settlement_id'] ) ? absint( $_POST['settlement_id'] ) : 0, 'paid', $user_id );
				break;
			case 'payout_request_approve':
				$result = $this->payout_request_status( 'approved', $user_id, $reason );
				if ( ! is_wp_error( $result ) ) {
					$this->maybe_save_payout_proof( $user_id, false );
				}
				break;
			case 'payout_request_paid':
				$result = $this->payout_request_status( 'paid', $user_id, $reason );
				if ( ! is_wp_error( $result ) ) {
					$this->maybe_save_payout_proof( $user_id, false );
				}
				break;
			case 'payout_request_reject':
				$result = $this->payout_request_status( 'rejected', $user_id, $reason );
				break;
			case 'payout_request_proof':
				$result = $this->maybe_save_payout_proof( $user_id, true );
				break;
		}

		if ( is_wp_error( $result ) ) {
			$this->flash( 'error', $result->get_error_message() );
		} else {
			$this->flash( 'success', $this->success_message( $action ) );
		}

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'admin.php?page=wcap-auctions' );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	public function render_notices(): void {
		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			return;
		}

		$key  = 'wcap_admin_notice_' . $user_id;
		$data = get_transient( $key );
		if ( ! is_array( $data ) || empty( $data['message'] ) ) {
			return;
		}

		delete_transient( $key );
		$class = ( 'error' === ( $data['type'] ?? '' ) ) ? 'notice notice-error' : 'notice notice-success';
		echo '<div class="' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( (string) $data['message'] ) . '</p></div>';
	}

	private function flash( string $type, string $message ): void {
		set_transient(
			'wcap_admin_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);
	}

	private function success_message( string $action ): string {
		$messages = array(
			'approve_auction' => __( 'Auction approved and scheduled.', 'logicanvas-auctions' ),
			'reject_auction'  => __( 'Auction rejected.', 'logicanvas-auctions' ),
			'cancel_auction'  => __( 'Auction cancelled.', 'logicanvas-auctions' ),
			'force_close'     => __( 'Auction close requested.', 'logicanvas-auctions' ),
			'approve_holder'  => __( 'Holder approved.', 'logicanvas-auctions' ),
			'reject_holder'   => __( 'Holder rejected.', 'logicanvas-auctions' ),
			'void_bid'        => __( 'Bid voided.', 'logicanvas-auctions' ),
			'payout_paid'             => __( 'Payout marked paid.', 'logicanvas-auctions' ),
			'payout_request_approve'  => __( 'Payout request approved.', 'logicanvas-auctions' ),
			'payout_request_paid'     => __( 'Payout request marked paid.', 'logicanvas-auctions' ),
			'payout_request_reject'   => __( 'Payout request rejected.', 'logicanvas-auctions' ),
			'payout_request_proof'    => __( 'Transaction proof saved.', 'logicanvas-auctions' ),
		);

		return $messages[ $action ] ?? __( 'Action completed.', 'logicanvas-auctions' );
	}

	/**
	 * @return true|\WP_Error
	 */
	private function maybe_save_payout_proof( int $actor_id, bool $required ) {
		$request_id = isset( $_POST['payout_request_id'] ) ? absint( $_POST['payout_request_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$reference  = isset( $_POST['transaction_reference'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['transaction_reference'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$details    = isset( $_POST['transaction_details'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['transaction_details'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$file       = isset( $_FILES['proof_file'] ) && is_array( $_FILES['proof_file'] ) ? $_FILES['proof_file'] : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$has_file = is_array( $file ) && ! empty( $file['tmp_name'] ) && (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_NO_FILE;
		if ( ! $required && '' === $reference && '' === $details && ! $has_file ) {
			return true;
		}

		return ( new \LogicanvasAuctions\Domain\Settlement\PayoutRequestService() )->save_transaction_proof(
			$request_id,
			$actor_id,
			array(
				'transaction_reference' => $reference,
				'transaction_details'   => $details,
			),
			$file,
			! $required
		);
	}

	public function view_payout_proof(): void {
		$request_id = isset( $_GET['request_id'] ) ? absint( $_GET['request_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $request_id < 1 || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), 'wcap_view_payout_proof_' . $request_id ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_die( esc_html__( 'Invalid proof link.', 'logicanvas-auctions' ), 403 );
		}

		( new \LogicanvasAuctions\Domain\Settlement\PayoutRequestService() )->stream_proof( $request_id, get_current_user_id() );
	}

	/**
	 * @return true|\WP_Error
	 */
	private function payout_request_status( string $status, int $actor_id, string $note ) {
		if ( ! current_user_can( Config::CAP_MANAGE_SETTLEMENTS ) ) {
			return new \WP_Error( 'forbidden', __( 'Forbidden.', 'logicanvas-auctions' ) );
		}

		$request_id = isset( $_POST['payout_request_id'] ) ? absint( $_POST['payout_request_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $request_id < 1 ) {
			return new \WP_Error( 'invalid', __( 'Invalid payout request.', 'logicanvas-auctions' ) );
		}

		return ( new \LogicanvasAuctions\Domain\Settlement\PayoutRequestService() )->set_status( $request_id, $status, $actor_id, $note );
	}

	private function void_bid( int $bid_id, int $actor_id, string $reason ): void {
		if ( ! current_user_can( Config::CAP_MANAGE_BIDS ) || $bid_id < 1 || '' === $reason ) {
			return;
		}

		$bids = new \LogicanvasAuctions\Infrastructure\Database\WpdbBidRepository();
		$bid  = $bids->find( $bid_id );
		if ( ! $bid ) {
			return;
		}

		$bids->void( $bid_id, $actor_id, $reason, gmdate( 'Y-m-d H:i:s' ) );
		( new \LogicanvasAuctions\Infrastructure\Database\WpdbAuditRepository() )->write(
			$bid->auction_id(),
			'bid_voided',
			$actor_id,
			$reason,
			array( 'bid_id' => $bid_id ),
			gmdate( 'Y-m-d H:i:s' )
		);

		$this->rebuild_auction_from_bids( $bid->auction_id() );
	}

	private function rebuild_auction_from_bids( int $auction_id ): void {
		$bids     = new \LogicanvasAuctions\Infrastructure\Database\WpdbBidRepository();
		$auctions = new \LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository();
		$auction  = $auctions->find( $auction_id );
		if ( ! $auction ) {
			return;
		}

		$highest = $bids->highest_accepted( $auction_id );
		$count   = $bids->count_for_auction( $auction_id, true );

		$auctions->update_state(
			$auction_id,
			array(
				'current_amount'    => $highest ? $highest->amount()->amount() : $auction->starting_amount()->amount(),
				'current_leader_id' => $highest ? $highest->bidder_id() : null,
				'bid_count'         => $count,
				'updated_at_utc'    => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}
}
