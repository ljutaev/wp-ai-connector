<?php
declare(strict_types=1);

namespace WPAIConnector\Core;

use Closure;
use OutOfBoundsException;

/**
 * Minimal DI container. Registers services as factories and memoises results.
 */
final class Container {

	/** @var array<string, Closure(Container): mixed> */
	private array $factories = array();

	/** @var array<string, mixed> */
	private array $instances = array();

	/**
	 * @param Closure(Container): mixed $factory
	 */
	public function set( string $id, Closure $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] );
	}

	public function get( string $id ): mixed {
		if ( ! isset( $this->factories[ $id ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message is not rendered as HTML.
			throw new OutOfBoundsException( "Service '{$id}' is not registered" );
		}

		if ( ! array_key_exists( $id, $this->instances ) ) {
			$this->instances[ $id ] = ( $this->factories[ $id ] )( $this );
		}

		return $this->instances[ $id ];
	}
}
