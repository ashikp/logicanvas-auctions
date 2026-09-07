<?php
/**
 * Theme-overridable templates.
 *
 * Override path: your-theme/logicanvas-auctions/{name}.php
 *
 * Templates receive a single prefixed bag: $wcap (array of view data).
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Frontend;

use LogicanvasAuctions\Config;

final class TemplateLoader {

	public function register(): void {
		add_filter( 'single_template', array( $this, 'single_template' ) );
	}

	public function single_template( string $template ): string {
		if ( ! is_singular( Config::CPT ) ) {
			return $template;
		}

		$override = locate_template( Config::TEMPLATE_DIR . '/single-auction.php' );
		if ( $override ) {
			return $override;
		}

		$plugin = Config::plugin_dir() . 'templates/single/single-auction.php';
		return is_readable( $plugin ) ? $plugin : $template;
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public static function render( string $name, array $args = array() ): void {
		$path = self::locate( $name );
		if ( ! $path ) {
			return;
		}

		$wcap = $args;
		echo '<div class="wcap-root">';
		include $path;
		echo '</div>';
	}

	/**
	 * Include a theme-overridable partial without wrapping another .wcap-root.
	 *
	 * @param array<string, mixed> $args
	 */
	public static function include_partial( string $name, array $args = array() ): void {
		$path = self::locate( $name );
		if ( ! $path ) {
			return;
		}

		$wcap = $args;
		include $path;
	}

	public static function locate( string $name ): string {
		$name   = str_replace( '..', '', $name );
		$plugin = Config::plugin_dir() . 'templates/' . $name . '.php';

		// Seller create/edit forms must always come from the plugin (category + save fields).
		$locked = array( 'holder/form-submit', 'holder/form-edit' );
		if ( in_array( $name, $locked, true ) && is_readable( $plugin ) ) {
			return $plugin;
		}

		$override = locate_template( Config::TEMPLATE_DIR . '/' . $name . '.php' );
		if ( $override ) {
			return $override;
		}

		return is_readable( $plugin ) ? $plugin : '';
	}
}
