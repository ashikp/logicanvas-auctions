<?php
/**
 * Shared account presentation for holder and bidder dashboards.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Frontend\Dashboards;

use LogicanvasAuctions\Frontend\PluginPages;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;

final class AccountProfile {

	/**
	 * @return array<string, mixed>
	 */
	public static function for_user( int $user_id ): array {
		$user = get_userdata( $user_id );
		$name = $user ? (string) $user->display_name : __( 'Account', 'logicanvas-auctions' );
		$hour = (int) wp_date( 'G' );
		if ( $hour < 12 ) {
			$hello = __( 'Good morning', 'logicanvas-auctions' );
		} elseif ( $hour < 18 ) {
			$hello = __( 'Good afternoon', 'logicanvas-auctions' );
		} else {
			$hello = __( 'Good evening', 'logicanvas-auctions' );
		}

		$parts    = preg_split( '/\s+/', trim( $name ) ) ?: array();
		$initials = '';
		foreach ( array_slice( $parts, 0, 2 ) as $part ) {
			$initials .= strtoupper( substr( $part, 0, 1 ) );
		}
		if ( '' === $initials ) {
			$initials = 'A';
		}

		return array(
			'name'      => $name,
			'initials'  => $initials,
			'greeting'  => sprintf(
				/* translators: 1: Good morning/afternoon/evening, 2: display name */
				__( '%1$s, %2$s.', 'logicanvas-auctions' ),
				$hello,
				$name
			),
			'site_name' => (string) get_bloginfo( 'name' ),
			'urls'      => array(
				'archive' => PluginPages::url( 'archive' ),
				'submit'  => PluginPages::url( 'submit' ),
				'apply'   => PluginPages::url( 'apply' ),
				'holder'  => PluginPages::url( 'holder' ),
				'bidder'  => PluginPages::url( 'bidder' ),
				'my_bids' => PluginPages::url( 'my_bids' ),
				'my_wins' => PluginPages::url( 'my_wins' ),
				'pay'     => PluginPages::url( 'pay' ),
				'live'    => PluginPages::url( 'live' ),
				'terms'   => PluginPages::url( 'terms' ),
			),
		);
	}

	public static function watch_count( int $user_id ): int {
		global $wpdb;

		$table = \LogicanvasAuctions\Config::table( \LogicanvasAuctions\Config::TABLE_WATCHES );

		return (int) QueryCache::remember(
			QueryCache::key( 'watch_count', $user_id ),
			60,
			static function () use ( $wpdb, $table, $user_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM %i WHERE user_id = %d',
						$table,
						$user_id
					)
				);
			}
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function watched( int $user_id ): array {
		global $wpdb;

		$table = \LogicanvasAuctions\Config::table( \LogicanvasAuctions\Config::TABLE_WATCHES );
		$ids   = QueryCache::remember(
			QueryCache::key( 'watched', $user_id ),
			60,
			static function () use ( $wpdb, $table, $user_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_col(
					$wpdb->prepare(
						'SELECT auction_id FROM %i WHERE user_id = %d ORDER BY id DESC LIMIT 50',
						$table,
						$user_id
					)
				);
			}
		);
		if ( ! is_array( $ids ) ) {
			return array();
		}

		$repo = new \LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository();
		$out  = array();
		foreach ( $ids as $id ) {
			$id      = (int) $id;
			$auction = $repo->find( $id );
			$post    = get_post( $id );
			$out[]   = array(
				'id'        => $id,
				'title'     => $post ? $post->post_title : '#' . $id,
				'state'     => $auction ? $auction->state() : '',
				'price'     => $auction ? $auction->current_amount()->formatted() . ' ' . $auction->currency() : '',
				'bids'      => $auction ? $auction->bid_count() : 0,
				'permalink' => get_permalink( $id ),
				'image'     => get_the_post_thumbnail_url( $id, 'medium' ) ?: '',
				'type'      => $auction ? $auction->type() : 'timed',
				'end'       => $auction ? $auction->end_at_utc() : '',
			);
		}

		return $out;
	}
}
