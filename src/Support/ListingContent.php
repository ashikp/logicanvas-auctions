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

		/**
		 * Filters formatted auction listing body HTML.
		 *
		 * @param string $html Formatted listing HTML.
		 */
		return (string) apply_filters( 'wcap_listing_content', self::pipeline( $content ) );
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
			$html = wpautop( esc_html( $text ) );
		} else {
			$html = self::pipeline( $text );
		}

		/**
		 * Filters formatted auction listing excerpt HTML.
		 *
		 * @param string $html Formatted excerpt HTML.
		 */
		return (string) apply_filters( 'wcap_listing_excerpt', $html );
	}

	/**
	 * Core content formatting without invoking the unprefixed `the_content` hook.
	 */
	private static function pipeline( string $content ): string {
		$content = wptexturize( $content );
		$content = convert_smilies( $content );
		$content = wpautop( $content );
		$content = shortcode_unautop( $content );

		if ( function_exists( 'wp_filter_content_tags' ) ) {
			$content = wp_filter_content_tags( $content );
		}

		$content = do_shortcode( $content );

		if ( function_exists( 'wp_replace_insecure_home_url' ) ) {
			$content = wp_replace_insecure_home_url( $content );
		}

		return $content;
	}
}
