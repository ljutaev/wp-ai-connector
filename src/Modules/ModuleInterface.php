<?php
declare(strict_types=1);

namespace WPAIConnector\Modules;

use WPAIConnector\Core\Container;

interface ModuleInterface {

	public function name(): string;

	public function version(): string;

	/** @return array<int, ConditionalInterface> */
	public function conditionals(): array;

	public function register( Container $container ): void;

	/** @return iterable<int, array{namespace: string, route: string, args: array<string, mixed>}> */
	public function routes(): iterable;

	/** @return array<string, mixed> Contributes to GET /manifest */
	public function manifest(): array;
}
