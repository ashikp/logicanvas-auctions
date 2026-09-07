<?php
/**
 * PHPUnit bootstrap for domain unit tests (no WordPress required).
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/src/Autoloader.php';
LogicanvasAuctions\Autoloader::register();

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		unset( $hook, $args );
		return $value;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}
