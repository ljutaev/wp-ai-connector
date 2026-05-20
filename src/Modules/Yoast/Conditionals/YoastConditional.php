<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Yoast\Conditionals;

use WPAIConnector\Modules\ConditionalInterface;

final class YoastConditional implements ConditionalInterface {

	public function is_met(): bool {
		return class_exists( 'WPSEO_Options' );
	}

	public function reason(): string {
		return 'Yoast SEO plugin is not active. Install and activate Yoast SEO to enable this module.';
	}
}
