<?php
declare(strict_types=1);

namespace WPAIConnector\Modules;

use WPAIConnector\Core\Container;

abstract class AbstractModule implements ModuleInterface {

	/** @return array<int, ConditionalInterface> */
	public function conditionals(): array {
		return array();
	}

	public function register( Container $container ): void {
		// Default: no extra wiring.
	}

	/** @return iterable<int, array{namespace: string, route: string, args: array<string, mixed>}> */
	public function routes(): iterable {
		return array();
	}

	/** @return array<string, mixed> */
	public function manifest(): array {
		return array(
			'name'    => $this->name(),
			'version' => $this->version(),
			'routes'  => array(),
		);
	}
}
