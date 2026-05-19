<?php
declare(strict_types=1);

namespace WPAIConnector\Tests\Unit\Modules\WooCommerce;

use PHPUnit\Framework\TestCase;
use WPAIConnector\Modules\WooCommerce\Conditionals\WooCommerceConditional;

final class WooCommerceConditionalTest extends TestCase {

	public function test_is_not_met_when_woocommerce_class_missing(): void {
		// WooCommerce is not loaded in unit test env.
		$conditional = new WooCommerceConditional();

		self::assertFalse( $conditional->is_met() );
	}

	public function test_reason_mentions_woocommerce(): void {
		$conditional = new WooCommerceConditional();

		self::assertStringContainsString( 'WooCommerce', $conditional->reason() );
	}
}
