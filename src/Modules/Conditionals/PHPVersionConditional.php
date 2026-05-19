<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Conditionals;

use WPAIConnector\Modules\ConditionalInterface;

final class PHPVersionConditional implements ConditionalInterface {

	public function __construct(
		private readonly string $minimum,
		private readonly string $runtime = PHP_VERSION,
	) {
	}

	public function is_met(): bool {
		return version_compare( $this->runtime, $this->minimum, '>=' );
	}

	public function reason(): string {
		return sprintf( 'Requires PHP %s or newer (found %s).', $this->minimum, $this->runtime );
	}
}
