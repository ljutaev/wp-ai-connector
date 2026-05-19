<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\WooCommerce\Conditionals;

use WPAIConnector\Modules\ConditionalInterface;

final class WooCommerceConditional implements ConditionalInterface {

	public function is_met(): bool {
		return class_exists( 'WooCommerce' );
	}

	public function reason(): string {
		return 'WooCommerce plugin must be active (class WooCommerce not found).';
	}
}
