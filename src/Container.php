<?php
/**
 * Minimal service container.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions;

use Closure;
use InvalidArgumentException;

final class Container {

	/**
	 * @var array<string, Closure(self): object>
	 */
	private array $factories = array();

	/**
	 * @var array<string, object>
	 */
	private array $instances = array();

	public function set( string $id, Closure $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	public function singleton( string $id, object $instance ): void {
		$this->instances[ $id ] = $instance;
	}

	public function has( string $id ): bool {
		return isset( $this->instances[ $id ] ) || isset( $this->factories[ $id ] );
	}

	public function get( string $id ): object {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %s: service identifier */
					'Service "%s" is not registered.',
					esc_html( $id )
				)
			);
		}

		$instance                 = ( $this->factories[ $id ] )( $this );
		$this->instances[ $id ] = $instance;

		return $instance;
	}
}
