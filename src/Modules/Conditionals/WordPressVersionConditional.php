<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Conditionals;

use WPAIConnector\Modules\ConditionalInterface;

final class WordPressVersionConditional implements ConditionalInterface {

	public function __construct(
		private readonly string $minimum,
		private readonly ?string $runtime = null,
	) {
	}

	public function is_met(): bool {
		return version_compare( $this->resolve_runtime(), $this->minimum, '>=' );
	}

	public function reason(): string {
		return sprintf( 'Requires WordPress %s or newer (found %s).', $this->minimum, $this->resolve_runtime() );
	}

	private function resolve_runtime(): string {
		if ( null !== $this->runtime ) {
			return $this->runtime;
		}

		global $wp_version;
		return is_string( $wp_version ) ? $wp_version : '0.0.0';
	}
}
