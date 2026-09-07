<?php
/**
 * Format auction listing copy for frontend display (matches editor preview).
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Support;

final class ListingContent {

	/**
	 * Render body HTML the same way WordPress renders post content.
	 */
	public static function format_body( string $content ): string {
		$content = trim( $content );
		if ( '' === $content ) {
			return '';
		}

		return (string) apply_filters( 'the_content', $content );
	}

	/**
	 * Render short description / excerpt with paragraph breaks when plain text.
	 */
	public static function format_excerpt( string $text ): string {
		$text = trim( $text );
		if ( '' === $text ) {
			return '';
		}

		if ( ! str_contains( $text, '<' ) ) {
			return wpautop( esc_html( $text ) );
		}

		return (string) apply_filters( 'the_content', $text );
	}
}
