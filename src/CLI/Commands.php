<?php
/**
 * WP-CLI commands.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\CLI;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Award\AwardService;
use LogicanvasAuctions\Infrastructure\Database\Migrator;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository;
use LogicanvasAuctions\Infrastructure\Scheduler\AuctionScheduler;
use LogicanvasAuctions\Support\Diagnostics;

final class Commands {

	public function register(): void {
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		\WP_CLI::add_command( 'logicanvas-auctions', $this );
	}

	/**
	 * List auctions.
	 *
	 * ## OPTIONS
	 *
	 * [--state=<state>]
	 * : Filter by state.
	 *
	 * @when after_wp_load
	 */
	public function list( $args, $assoc_args ): void { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.listFound
		unset( $args );
		$repo = new WpdbAuctionRepository();
		$items = $repo->query(
			array(
				'state'            => ! empty( $assoc_args['state'] ) ? (array) $assoc_args['state'] : array(),
				'include_unlisted' => true,
				'limit'            => 50,
			)
		);
		$rows = array();
		foreach ( $items as $auction ) {
			$rows[] = array(
				'id'     => $auction->id(),
				'type'   => $auction->type(),
				'state'  => $auction->state(),
				'price'  => $auction->current_amount()->amount(),
			);
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'type', 'state', 'price' ) );
	}

	/**
	 * Close an auction safely.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Auction ID.
	 */
	public function close( $args ): void {
		( new AwardService() )->close( (int) $args[0], 0, 'WP-CLI close' );
		\WP_CLI::success( 'Close requested for auction ' . $args[0] );
	}

	/**
	 * Process overdue auctions.
	 */
	public function overdue(): void {
		( new AuctionScheduler() )->process_overdue();
		\WP_CLI::success( 'Overdue processing complete.' );
	}

	/**
	 * Rebuild leader/price from accepted bids.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Auction ID.
	 */
	public function rebuild( $args ): void {
		$id       = (int) $args[0];
		$bids     = new \LogicanvasAuctions\Infrastructure\Database\WpdbBidRepository();
		$auctions = new WpdbAuctionRepository();
		$auction  = $auctions->find( $id );
		if ( ! $auction ) {
			\WP_CLI::error( 'Not found' );
		}
		$highest = $bids->highest_accepted( $id );
		$auctions->update_state(
			$id,
			array(
				'current_amount'    => $highest ? $highest->amount()->amount() : $auction->starting_amount()->amount(),
				'current_leader_id' => $highest ? $highest->bidder_id() : null,
				'bid_count'         => $bids->count_for_auction( $id, true ),
				'updated_at_utc'    => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		\WP_CLI::success( 'Rebuilt auction ' . $id );
	}

	/**
	 * Check database schema.
	 */
	public function schema(): void {
		$ok = ( new Migrator() )->tables_exist();
		if ( $ok ) {
			\WP_CLI::success( 'Canonical auction table exists.' );
			return;
		}
		\WP_CLI::error( 'Tables missing. Run activation or migrate.' );
	}

	/**
	 * Run diagnostics.
	 */
	public function diagnostics(): void {
		foreach ( ( new Diagnostics() )->report() as $k => $v ) {
			\WP_CLI::log( $k . ': ' . ( is_bool( $v ) ? ( $v ? 'yes' : 'no' ) : (string) $v ) );
		}
	}

	/**
	 * Reschedule start/close jobs for an auction.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Auction ID.
	 */
	public function reschedule( $args ): void {
		$auction = ( new WpdbAuctionRepository() )->find( (int) $args[0] );
		if ( ! $auction ) {
			\WP_CLI::error( 'Not found' );
		}
		$scheduler = new AuctionScheduler();
		$scheduler->schedule_start( $auction->id(), $auction->start_at_utc() );
		if ( $auction->end_at_utc() ) {
			$scheduler->schedule_close( $auction->id(), $auction->end_at_utc() );
		}
		\WP_CLI::success( 'Rescheduled.' );
	}

	/**
	 * Run migrations.
	 */
	public function migrate(): void {
		( new Migrator() )->migrate();
		\WP_CLI::success( 'Migrated to ' . Config::DB_VERSION );
	}
}
