<?php
/**
 * Shared wp-admin layout.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Admin;

use LogicanvasAuctions\Config;

final class Screen {

	public static function open( string $title, string $subtitle = '', string $action_html = '' ): void {
		$logo = Config::logo_url();

		echo '<div class="wrap wcap-admin">';
		echo '<div class="wcap-admin__hero">';
		echo '<div class="wcap-admin__hero-brand">';
		if ( '' !== $logo ) {
			echo '<img class="wcap-admin__logo" src="' . esc_url( $logo ) . '" alt="' . esc_attr( Config::PRODUCT_NAME ) . '" width="180" height="60" decoding="async" />';
		} else {
			echo '<p class="wcap-admin__kicker">' . esc_html( Config::PRODUCT_NAME ) . '</p>';
		}
		echo '<div class="wcap-admin__hero-copy">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		if ( '' !== $subtitle ) {
			echo '<p class="wcap-admin__lede">' . esc_html( $subtitle ) . '</p>';
		}
		echo '</div>';
		echo '</div>';
		echo '<div class="wcap-admin__hero-actions">';
		if ( '' !== $action_html ) {
			echo $action_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<a class="wcap-admin__btn wcap-admin__btn--ghost" href="' . esc_url( Config::DOCS_URI ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Documentation', 'logicanvas-auctions' ) . '</a>';
		echo '</div>';
		echo '</div>';
		echo '<div class="wcap-admin__body">';
	}

	public static function close(): void {
		echo '</div></div>';
	}

	public static function panel_open( string $class = '' ): void {
		$class = trim( 'wcap-admin__panel ' . $class );
		echo '<div class="' . esc_attr( $class ) . '">';
	}

	public static function panel_close(): void {
		echo '</div>';
	}

	public static function add_link( string $label, string $url ): string {
		return '<a class="wcap-admin__btn" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}
}
