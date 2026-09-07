<?php
/**
 * Action Scheduler + WP-Cron fallback.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\Scheduler;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Auction\AuctionService;
use LogicanvasAuctions\Domain\Award\AwardService;

final class AuctionScheduler {

	public function register(): void {
		add_action( Config::HOOK_CLOSE_AUCTION, array( $this, 'handle_close' ), 10, 1 );
		add_action( Config::HOOK_START_AUCTION, array( $this, 'handle_start' ), 10, 1 );
		add_action( Config::HOOK_PAYMENT_REMINDER, array( $this, 'handle_reminder' ), 10, 2 );
		add_action( Config::HOOK_PAYMENT_EXPIRE, array( $this, 'handle_expire' ), 10, 2 );
		add_action( Config::CRON_HOOK, array( $this, 'process_overdue' ) );
	}

	public function schedule_close( int $auction_id, string $end_at_utc ): void {
		$ts = strtotime( $end_at_utc . ' UTC' );
		if ( ! $ts ) {
			return;
		}

		$this->unschedule_close( $auction_id );
		$this->single( $ts, Config::HOOK_CLOSE_AUCTION, array( $auction_id ) );
	}

	public function reschedule_close( int $auction_id, string $end_at_utc ): void {
		$this->schedule_close( $auction_id, $end_at_utc );
	}

	public function unschedule_close( int $auction_id ): void {
		$this->unschedule( Config::HOOK_CLOSE_AUCTION, array( $auction_id ) );
	}

	public function schedule_start( int $auction_id, string $start_at_utc ): void {
		$ts = strtotime( $start_at_utc . ' UTC' );
		if ( ! $ts ) {
			return;
		}
		$this->unschedule( Config::HOOK_START_AUCTION, array( $auction_id ) );
		// Past start times must still fire; Action Scheduler / WP-Cron can drop them otherwise.
		if ( $ts <= time() ) {
			$this->handle_start( $auction_id );
			return;
		}
		$this->single( $ts, Config::HOOK_START_AUCTION, array( $auction_id ) );
	}

	public function schedule_payment_deadline( int $award_id, int $auction_id, string $deadline_utc ): void {
		$ts = strtotime( $deadline_utc . ' UTC' );
		if ( ! $ts ) {
			return;
		}

		$remind = $ts - HOUR_IN_SECONDS * 12;
		if ( $remind > time() ) {
			$this->single( $remind, Config::HOOK_PAYMENT_REMINDER, array( $award_id, $auction_id ) );
		}
		$this->single( $ts, Config::HOOK_PAYMENT_EXPIRE, array( $award_id, $auction_id ) );
	}

	public function handle_close( int $auction_id ): void {
		( new AwardService() )->close( $auction_id, 0, 'Scheduled close' );
	}

	public function handle_start( int $auction_id ): void {
		( new AuctionService() )->start_if_due( $auction_id );
	}

	public function handle_reminder( int $award_id, int $auction_id ): void {
		do_action( 'wcap_payment_reminder', $auction_id, $award_id );
	}

	public function handle_expire( int $award_id, int $auction_id ): void {
		unset( $auction_id );
		( new AwardService() )->expire( $award_id );
	}

	public function process_overdue(): void {
		$repo   = new \LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository();
		$now    = gmdate( 'Y-m-d H:i:s' );
		$due    = $repo->query(
			array(
				'state'            => array( 'active', 'closing', 'paused_admin' ),
				'type'             => 'timed',
				'ending_before'    => $now,
				'include_unlisted' => true,
				'limit'            => 50,
			)
		);

		foreach ( $due as $auction ) {
			( new AwardService() )->close( $auction->id(), 0, 'Overdue recovery' );
		}

		$scheduled = $repo->query(
			array(
				'state'            => array( 'scheduled' ),
				'include_unlisted' => true,
				'limit'            => 50,
				'orderby'          => 'start_at_utc',
			)
		);
		foreach ( $scheduled as $auction ) {
			( new AuctionService() )->start_if_due( $auction->id() );
		}
	}

	/**
	 * Request-time failsafe when a page is viewed after the scheduled start.
	 * Starts immediately so bidding is not stuck waiting on delayed cron.
	 */
	public function maybe_start_if_due( int $auction_id ): void {
		( new AuctionService() )->start_if_due( $auction_id );
	}

	/**
	 * Request-time failsafe when a page is viewed after end.
	 */
	public function maybe_close_if_ended( int $auction_id ): void {
		$auction = ( new \LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository() )->find( $auction_id );
		if ( ! $auction || ! $auction->is_timed() || ! $auction->end_at_utc() ) {
			return;
		}

		if ( strtotime( $auction->end_at_utc() . ' UTC' ) > time() ) {
			return;
		}

		if ( ! in_array( $auction->state(), array( 'active', 'paused_admin', 'closing' ), true ) ) {
			return;
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( Config::HOOK_CLOSE_AUCTION, array( $auction_id ), Config::SCHEDULER_GROUP, true );
			return;
		}

		( new AwardService() )->close( $auction_id, 0, 'Request-time failsafe' );
	}

	/**
	 * @param array<int, mixed> $args
	 */
	private function single( int $timestamp, string $hook, array $args ): void {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( $timestamp, $hook, $args, Config::SCHEDULER_GROUP, true );
			return;
		}

		wp_schedule_single_event( $timestamp, $hook, $args );
	}

	/**
	 * @param array<int, mixed> $args
	 */
	private function unschedule( string $hook, array $args ): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( $hook, $args, Config::SCHEDULER_GROUP );
			return;
		}

		wp_clear_scheduled_hook( $hook, $args );
	}
}
