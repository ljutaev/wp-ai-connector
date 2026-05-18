<?php
declare(strict_types=1);

namespace WPAIConnector\Modules;

use WPAIConnector\Core\Container;

final class ModuleRegistry {

	public function __construct( private readonly Container $container ) {
	}

	/**
	 * @param array<int, ModuleInterface> $candidates
	 * @return array<int, ModuleInterface>
	 */
	public function load( array $candidates ): array {
		$active = [];

		foreach ( $candidates as $module ) {
			if ( ! $this->all_conditionals_met( $module ) ) {
				continue;
			}

			$module->register( $this->container );
			$active[] = $module;
		}

		return $active;
	}

	private function all_conditionals_met( ModuleInterface $module ): bool {
		foreach ( $module->conditionals() as $conditional ) {
			if ( ! $conditional->is_met() ) {
				return false;
			}
		}

		return true;
	}
}
