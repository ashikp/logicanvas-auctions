<?php
/**
 * QR payload helpers for single auction pages.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Support;

final class AuctionQr {

	public static function unique_id( int $auction_id ): string {
		return 'WCAP-' . $auction_id;
	}

	/**
	 * Compact plain-text card for the details QR code.
	 *
	 * @param array<string, mixed> $state Presenter public_state payload.
	 */
	public static function details_text( int $auction_id, array $state ): string {
		$title   = (string) ( $state['title'] ?? '' );
		$excerpt = wp_strip_all_tags( (string) ( $state['excerpt'] ?? '' ) );
		if ( '' === $excerpt ) {
			$excerpt = wp_trim_words( wp_strip_all_tags( (string) ( $state['content'] ?? '' ) ), 24, '…' );
		}
		$lines = array(
			self::unique_id( $auction_id ),
			$title,
			$excerpt,
			sprintf(
				/* translators: 1: start UTC, 2: end UTC */
				__( 'Added: %1$s | Ends: %2$s', 'logicanvas-auctions' ),
				(string) ( $state['start_at_utc'] ?? '' ),
				(string) ( $state['end_at_utc'] ?? '—' )
			),
		);

		return implode( "\n", array_filter( $lines ) );
	}

	public static function share_url( int $auction_id ): string {
		$url = get_permalink( $auction_id );
		return is_string( $url ) ? $url : '';
	}
}
