<?php
declare(strict_types=1);

namespace WPAIConnector\Tests\Unit\Modules\Yoast;

use PHPUnit\Framework\TestCase;
use WPAIConnector\Modules\Yoast\Conditionals\YoastConditional;

final class YoastConditionalTest extends TestCase {

	public function test_is_not_met_when_yoast_class_missing(): void {
		// WPSEO_Options is not loaded in unit test env.
		$conditional = new YoastConditional();

		self::assertFalse( $conditional->is_met() );
	}

	public function test_reason_mentions_yoast(): void {
		$conditional = new YoastConditional();

		self::assertStringContainsString( 'Yoast', $conditional->reason() );
	}
}
