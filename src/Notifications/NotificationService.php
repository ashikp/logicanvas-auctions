<?php
/**
 * Notification dispatcher with WooCommerce-style emails and action hooks.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Notifications;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;

final class NotificationService {

	public function register(): void {
		add_action( 'wcap_bid_accepted', array( $this, 'on_bid' ), 10, 2 );
		add_action( 'wcap_bidder_outbid', array( $this, 'on_outbid' ), 10, 3 );
		add_action( 'wcap_award_created', array( $this, 'on_winner' ), 10, 3 );
		add_action( 'wcap_auction_approved', array( $this, 'on_approved' ), 10, 1 );
		add_action( 'wcap_holder_approved', array( $this, 'on_holder_approved' ), 10, 1 );
		add_action( 'wcap_holder_rejected', array( $this, 'on_holder_rejected' ), 10, 1 );
		add_action( 'wcap_payment_reminder', array( $this, 'on_payment_reminder' ), 10, 2 );
		add_action( 'wcap_payment_deadline_expired', array( $this, 'on_payment_expired' ), 10, 2 );
		add_action( 'wcap_auction_extended', array( $this, 'on_extended' ), 10, 2 );
		add_filter( 'woocommerce_email_classes', array( $this, 'register_emails' ) );
	}

	/**
	 * @param array<string, mixed> $emails
	 * @return array<string, mixed>
	 */
	public function register_emails( array $emails ): array {
		$emails['WCAP_Email_Bid_Accepted'] = new Emails\BidAcceptedEmail();
		$emails['WCAP_Email_Outbid']       = new Emails\OutbidEmail();
		$emails['WCAP_Email_Winner']       = new Emails\WinnerEmail();
		return $emails;
	}

	public function on_bid( $bid, $auction ): void {
		/* translators: %d: auction ID */
		$body = sprintf( __( 'Your bid on auction #%d was accepted.', 'logicanvas-auctions' ), $auction->id() );
		$this->mail_user( $bid->bidder_id(), 'bid_accepted', __( 'Your bid was accepted', 'logicanvas-auctions' ), $body );
	}

	public function on_outbid( int $user_id, $auction, $bid ): void {
		unset( $bid );
		$key = 'outbid_' . $auction->id() . '_' . $user_id;
		if ( get_transient( Config::PREFIX . $key ) ) {
			return;
		}
		set_transient( Config::PREFIX . $key, 1, 20 );
		/* translators: %d: auction ID */
		$body = sprintf( __( 'You were outbid on auction #%d.', 'logicanvas-auctions' ), $auction->id() );
		$this->mail_user( $user_id, 'outbid', __( 'You have been outbid', 'logicanvas-auctions' ), $body );
	}

	public function on_winner( int $auction_id, int $award_id, int $winner_id ): void {
		unset( $award_id );
		/* translators: %d: auction ID */
		$body = sprintf( __( 'You won auction #%d. Please complete payment.', 'logicanvas-auctions' ), $auction_id );
		$this->mail_user( $winner_id, 'winner', __( 'You won an auction', 'logicanvas-auctions' ), $body );
	}

	public function on_approved( int $auction_id ): void {
		$post = get_post( $auction_id );
		if ( $post ) {
			/* translators: %d: auction ID */
			$body = sprintf( __( 'Auction #%d was approved.', 'logicanvas-auctions' ), $auction_id );
			$this->mail_user( (int) $post->post_author, 'auction_approved', __( 'Auction approved', 'logicanvas-auctions' ), $body );
		}
	}

	public function on_holder_approved( int $user_id ): void {
		$this->mail_user( $user_id, 'holder_approved', __( 'Auction holder approved', 'logicanvas-auctions' ), __( 'Your auction holder application was approved.', 'logicanvas-auctions' ) );
	}

	public function on_holder_rejected( int $user_id ): void {
		$this->mail_user( $user_id, 'holder_rejected', __( 'Auction holder application', 'logicanvas-auctions' ), __( 'Your auction holder application was not approved.', 'logicanvas-auctions' ) );
	}

	public function on_payment_reminder( int $auction_id, int $award_id ): void {
		global $wpdb;

		$table = Config::table( Config::TABLE_AWARDS );
		$award = QueryCache::remember(
			QueryCache::key( 'award', $award_id ),
			60,
			static function () use ( $wpdb, $table, $award_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_row(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE id = %d',
						$table,
						$award_id
					),
					ARRAY_A
				);
			}
		);
		if ( $award ) {
			/* translators: %d: auction ID */
			$body = sprintf( __( 'Please pay for auction #%d before the deadline.', 'logicanvas-auctions' ), $auction_id );
			$this->mail_user( (int) $award['winner_id'], 'payment_reminder', __( 'Payment reminder', 'logicanvas-auctions' ), $body );
		}
	}

	public function on_payment_expired( int $auction_id, int $award_id ): void {
		unset( $award_id );
		$auction = ( new \LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository() )->find( $auction_id );
		if ( $auction ) {
			/* translators: %d: auction ID */
			$body = sprintf( __( 'The winner of auction #%d missed the payment deadline.', 'logicanvas-auctions' ), $auction_id );
			$this->mail_user( $auction->holder_id(), 'payment_expired', __( 'Winner did not pay', 'logicanvas-auctions' ), $body );
		}
	}

	public function on_extended( int $auction_id, string $end_at ): void {
		unset( $end_at );
		/**
		 * Fires so SMS/push integrations can react to extensions.
		 *
		 * @param int $auction_id Auction ID.
		 */
		do_action( 'wcap_notification_dispatch', 'auction_extended', $auction_id );
	}

	private function mail_user( int $user_id, string $event, string $subject, string $message ): void {
		if ( ! $this->pref_enabled( $user_id, $event ) ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return;
		}

		/**
		 * Fires before an email is sent so other channels can hook in.
		 *
		 * @param string $event   Event key.
		 * @param int    $user_id User ID.
		 * @param string $subject Subject.
		 * @param string $message Message.
		 */
		do_action( 'wcap_notification_dispatch', $event, $user_id, $subject, $message );

		wp_mail( $user->user_email, $subject, $message );
	}

	private function pref_enabled( int $user_id, string $event ): bool {
		global $wpdb;

		$table = Config::table( Config::TABLE_NOTIFICATION_PREFS );
		$row   = QueryCache::remember(
			QueryCache::key( 'notif_pref', $user_id, $event ),
			120,
			static function () use ( $wpdb, $table, $user_id, $event ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_var(
					$wpdb->prepare(
						'SELECT enabled FROM %i WHERE user_id = %d AND event_key = %s',
						$table,
						$user_id,
						$event
					)
				);
			}
		);

		if ( null === $row ) {
			return true;
		}

		return (bool) (int) $row;
	}
}
