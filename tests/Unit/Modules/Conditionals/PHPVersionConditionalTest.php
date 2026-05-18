<?php
declare(strict_types=1);

namespace WPAIConnector\Tests\Unit\Modules\Conditionals;

use PHPUnit\Framework\TestCase;
use WPAIConnector\Modules\Conditionals\PHPVersionConditional;

final class PHPVersionConditionalTest extends TestCase {

	public function test_is_met_when_runtime_version_is_higher(): void {
		$c = new PHPVersionConditional( '8.1', '20.0.0' );
		self::assertTrue( $c->is_met() );
	}

	public function test_is_not_met_when_runtime_version_is_lower(): void {
		$c = new PHPVersionConditional( '8.1', '7.4.0' );
		self::assertFalse( $c->is_met() );
	}

	public function test_reason_describes_requirement(): void {
		$c = new PHPVersionConditional( '8.1' );
		self::assertStringContainsString( '8.1', $c->reason() );
	}
}
