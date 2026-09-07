<?php
/**
 * Live host actions. Server remains authoritative.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Auction;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Award\AwardService;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository;
use WP_Error;

final class LiveAuctionService {

	public function __construct(
		private AuctionService $auctions = new AuctionService(),
		private AwardService $awards = new AwardService()
	) {}

	/**
	 * @param array<string, mixed> $params Extra action data (e.g. extend seconds).
	 * @return true|WP_Error
	 */
	public function host_action( int $auction_id, int $user_id, string $action, string $reason = '', array $params = array() ) {
		$auction = ( new WpdbAuctionRepository() )->find( $auction_id );
		if ( ! $auction ) {
			return new WP_Error( 'not_found', __( 'Auction not found.', 'logicanvas-auctions' ) );
		}

		if ( ! $auction->is_live() ) {
			return new WP_Error( 'not_live', __( 'Not a live auction.', 'logicanvas-auctions' ) );
		}

		if ( $auction->holder_id() !== $user_id && ! user_can( $user_id, Config::CAP_MODERATE_AUCTIONS ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot host this auction.', 'logicanvas-auctions' ) );
		}

		if ( ! user_can( $user_id, Config::CAP_HOST_LIVE ) && ! user_can( $user_id, Config::CAP_MODERATE_AUCTIONS ) ) {
			return new WP_Error( 'forbidden', __( 'Missing host capability.', 'logicanvas-auctions' ) );
		}

		switch ( $action ) {
			case 'open_lobby':
				return $this->auctions->transition( $auction_id, AuctionState::LOBBY, $user_id, 'user', $reason ?: 'Lobby opened' );
			case 'start':
				return $this->auctions->transition( $auction_id, AuctionState::LIVE, $user_id, 'user', $reason ?: 'Live started' );
			case 'pause':
				return $this->auctions->transition( $auction_id, AuctionState::PAUSED, $user_id, 'user', $reason ?: 'Paused' );
			case 'resume':
				return $this->auctions->transition( $auction_id, AuctionState::LIVE, $user_id, 'user', $reason ?: 'Resumed' );
			case 'going_once':
				return $this->auctions->transition( $auction_id, AuctionState::GOING_ONCE, $user_id, 'user', 'Going once' );
			case 'going_twice':
				return $this->auctions->transition( $auction_id, AuctionState::GOING_TWICE, $user_id, 'user', 'Going twice' );
			case 'sell':
				$this->awards->sell_live( $auction_id, $user_id );
				return true;
			case 'unsold':
				$this->awards->mark_unsold( $auction_id, $user_id, $reason ?: 'Marked unsold' );
				return true;
			case 'cancel':
				if ( '' === $reason ) {
					return new WP_Error( 'reason', __( 'A reason is required to cancel.', 'logicanvas-auctions' ) );
				}
				return $this->auctions->transition( $auction_id, AuctionState::CANCELLED, $user_id, 'user', $reason );
			case 'extend':
				return $this->extend( $auction_id, $user_id, (int) ( $params['seconds'] ?? 30 ) );
			default:
				return new WP_Error( 'unknown', __( 'Unknown host action.', 'logicanvas-auctions' ) );
		}
	}

	/**
	 * @return true|WP_Error
	 */
	private function extend( int $auction_id, int $user_id, int $seconds ) {
		$seconds = max( 5, min( 600, $seconds ) );
		$repo    = new WpdbAuctionRepository();
		$auction = $repo->find( $auction_id );
		if ( ! $auction ) {
			return new WP_Error( 'not_found', __( 'Auction not found.', 'logicanvas-auctions' ) );
		}

		$end = $auction->end_at_utc() ? strtotime( $auction->end_at_utc() . ' UTC' ) : time();
		$new = gmdate( 'Y-m-d H:i:s', $end + $seconds );
		$repo->update_state(
			$auction_id,
			array(
				'end_at_utc'     => $new,
				'sequence'       => $auction->sequence() + 1,
				'updated_at_utc' => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		( new \LogicanvasAuctions\Infrastructure\Database\WpdbEventRepository() )->append(
			$auction_id,
			$auction->sequence() + 1,
			'extended',
			$user_id,
			'user',
			array( 'end_at_utc' => $new, 'seconds' => $seconds ),
			gmdate( 'Y-m-d H:i:s' )
		);

		return true;
	}

	public function heartbeat( int $auction_id, int $user_id ): void {
		global $wpdb;

		$now = gmdate( 'Y-m-d H:i:s' );
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (auction_id, user_id, role, joined_at_utc, last_seen_utc) VALUES (%d, %d, %s, %s, %s)
				ON DUPLICATE KEY UPDATE last_seen_utc = VALUES(last_seen_utc)',
				Config::table( Config::TABLE_PARTICIPANTS ),
				$auction_id,
				$user_id,
				'bidder',
				$now,
				$now
			)
		);

		QueryCache::bust_auction( $auction_id );
	}

	public function participant_count( int $auction_id ): int {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 30 );
		$table  = Config::table( Config::TABLE_PARTICIPANTS );

		return (int) QueryCache::remember(
			QueryCache::key( 'participants', $auction_id, $cutoff ),
			15,
			static function () use ( $wpdb, $table, $auction_id, $cutoff ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM %i WHERE auction_id = %d AND last_seen_utc >= %s',
						$table,
						$auction_id,
						$cutoff
					)
				);
			}
		);
	}
}
