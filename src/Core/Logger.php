<?php
/**
 * Structured logger with secret redaction.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Core;

use LogicanvasAuctions\Config;

final class Logger {

	/**
	 * @param array<string, mixed> $context
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( 'info', $message, $context );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			if ( ! in_array( $level, array( 'error', 'warning' ), true ) ) {
				return;
			}
		}

		$context = $this->redact( $context );
		$line    = sprintf(
			'[%s] [%s] %s %s',
			Config::SLUG,
			strtoupper( $level ),
			$message,
			$context ? wp_json_encode( $context ) : ''
		);

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		/**
		 * Fires after a plugin log entry is written.
		 *
		 * @param string               $level   Log level.
		 * @param string               $message Message.
		 * @param array<string, mixed> $context Redacted context.
		 */
		do_action( 'wcap_logged', $level, $message, $context );
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	private function redact( array $context ): array {
		$sensitive = array( 'token', 'password', 'secret', 'authorization', 'card', 'cvv', 'idempotency_key', 'email', 'ip', 'user_agent', 'max_amount', 'proxy_max' );

		foreach ( $context as $key => $value ) {
			$key_l = strtolower( (string) $key );
			foreach ( $sensitive as $needle ) {
				if ( str_contains( $key_l, $needle ) ) {
					$context[ $key ] = '[redacted]';
					continue 2;
				}
			}
			if ( is_array( $value ) ) {
				$context[ $key ] = $this->redact( $value );
			}
		}

		return $context;
	}
}
