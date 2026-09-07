<?php
/**
 * Assigned frontend page URLs from the setup wizard.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Frontend;

use LogicanvasAuctions\Config;

final class PluginPages {

	public static function url( string $key, array $query = array() ): string {
		$pages = get_option( Config::OPTION_PAGES, array() );
		if ( ! is_array( $pages ) || empty( $pages[ $key ] ) ) {
			return '';
		}

		$url = get_permalink( (int) $pages[ $key ] );
		if ( ! $url ) {
			return '';
		}

		return $query ? add_query_arg( $query, $url ) : $url;
	}

	public static function id( string $key ): int {
		$pages = get_option( Config::OPTION_PAGES, array() );
		if ( ! is_array( $pages ) ) {
			return 0;
		}

		return (int) ( $pages[ $key ] ?? 0 );
	}

	public static function login_url( string $redirect = '', string $view = 'login' ): string {
		$url = self::url( 'login' );
		if ( '' === $url ) {
			return '';
		}

		if ( 'login' !== $view && '' !== $view ) {
			$url = add_query_arg( 'wcap_auth', $view, $url );
		}

		if ( '' !== $redirect ) {
			$url = add_query_arg( 'redirect_to', $redirect, $url );
		}

		return $url;
	}

	/**
	 * Frontend auction submission form URL (preferred over wp-admin Gutenberg).
	 */
	public static function create_url(): string {
		$submit = self::url( 'submit' );
		if ( '' !== $submit ) {
			return $submit;
		}

		$holder = self::url( 'holder', array( 'wcap_view' => 'create' ) );
		if ( '' !== $holder ) {
			return $holder;
		}

		return '';
	}

	public static function is_login_url( string $url ): bool {
		$login = self::url( 'login' );
		if ( '' === $login || '' === $url ) {
			return false;
		}

		$left  = untrailingslashit( (string) ( wp_parse_url( $url, PHP_URL_PATH ) ?? '' ) );
		$right = untrailingslashit( (string) ( wp_parse_url( $login, PHP_URL_PATH ) ?? '' ) );

		return '' !== $left && $left === $right;
	}
}
