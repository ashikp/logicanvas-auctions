<?php
/**
 * Sample / demo auction for Setup (admin-only).
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Auction;

use LogicanvasAuctions\Admin\Settings;
use LogicanvasAuctions\Config;
use LogicanvasAuctions\Core\SystemClock;
use WP_Error;

final class DemoAuction {

	public const OPTION_ID = 'wcap_demo_auction_id';

	/**
	 * Create a public timed demo lot and approve it so bidding can open.
	 *
	 * @return int|WP_Error New auction ID.
	 */
	public static function create( int $user_id ) {
		if ( ! user_can( $user_id, Config::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Only administrators can create the demo auction.', 'logicanvas-auctions' ) );
		}

		$existing = self::current_id();
		if ( $existing > 0 && get_post( $existing ) ) {
			return new WP_Error( 'exists', __( 'A demo auction already exists. Delete it first if you want a new one.', 'logicanvas-auctions' ) );
		}

		$clock   = new SystemClock();
		$now_ts  = $clock->timestamp();
		$start   = gmdate( 'Y-m-d H:i:s', $now_ts );
		$end     = gmdate( 'Y-m-d H:i:s', $now_ts + DAY_IN_SECONDS );
		$settings = Settings::get();
		$increment = (string) ( $settings['min_increment'] ?? '1.00' );

		$input = array(
			'title'             => __( 'Demo lot — Antique desk clock', 'logicanvas-auctions' ),
			'description'       => '<p>' . esc_html__( 'This is a sample auction created by Setup so you can try bidding right away. Edit or delete it anytime.', 'logicanvas-auctions' ) . '</p>',
			'short_description' => __( 'Sample timed auction for trying Logicanvas Auctions.', 'logicanvas-auctions' ),
			'type'              => AuctionType::TIMED,
			'visibility'        => Visibility::PUBLIC_LISTED,
			'product_source'    => 'new',
			'condition'         => 'used_good',
			'starting_price'    => '25.00',
			'reserve_price'     => '',
			'min_increment'     => $increment,
			'buy_now'           => '150.00',
			'start_at_utc'      => $start,
			'end_at_utc'        => $end,
			'fulfilment_type'  => 'shipping',
			'fulfilment_notes' => __( 'DEMO — sample listing from Auctions → Setup. Safe to edit or delete.', 'logicanvas-auctions' ),
		);

		$service = new AuctionService();
		$id      = $service->create( $user_id, $input );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		update_post_meta( (int) $id, '_wcap_is_demo', '1' );
		update_option( self::OPTION_ID, (int) $id, false );

		$approved = $service->approve( (int) $id, $user_id );
		if ( is_wp_error( $approved ) ) {
			return $approved;
		}

		/**
		 * Fires after the Setup demo auction is created and approved.
		 *
		 * @param int $auction_id Auction ID.
		 * @param int $user_id    Admin user ID.
		 */
		do_action( 'wcap_demo_auction_created', (int) $id, $user_id );

		return (int) $id;
	}

	/**
	 * Trash the demo auction post and clear the option. Does not wipe unrelated lots.
	 *
	 * @return true|WP_Error
	 */
	public static function delete( int $user_id ) {
		if ( ! user_can( $user_id, Config::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Only administrators can delete the demo auction.', 'logicanvas-auctions' ) );
		}

		$id = self::current_id();
		if ( $id < 1 ) {
			return new WP_Error( 'missing', __( 'No demo auction is registered.', 'logicanvas-auctions' ) );
		}

		$post = get_post( $id );
		if ( ! $post || Config::CPT !== $post->post_type ) {
			delete_option( self::OPTION_ID );
			return true;
		}

		if ( '1' !== (string) get_post_meta( $id, '_wcap_is_demo', true ) ) {
			return new WP_Error( 'not_demo', __( 'The registered lot is not marked as a demo and was not deleted.', 'logicanvas-auctions' ) );
		}

		wp_trash_post( $id );
		delete_option( self::OPTION_ID );

		/**
		 * Fires after the Setup demo auction is trashed.
		 *
		 * @param int $auction_id Auction ID.
		 * @param int $user_id    Admin user ID.
		 */
		do_action( 'wcap_demo_auction_deleted', $id, $user_id );

		return true;
	}

	public static function current_id(): int {
		return absint( get_option( self::OPTION_ID, 0 ) );
	}

	public static function exists(): bool {
		$id = self::current_id();
		return $id > 0 && (bool) get_post( $id );
	}
}
