<?php
declare(strict_types=1);

namespace WPAIConnector\Tests\Unit\Modules\Conditionals;

use PHPUnit\Framework\TestCase;
use WPAIConnector\Modules\Conditionals\WordPressVersionConditional;

final class WordPressVersionConditionalTest extends TestCase {

	public function test_is_met_when_wp_version_is_higher(): void {
		$c = new WordPressVersionConditional( '6.6', '7.0.0' );
		self::assertTrue( $c->is_met() );
	}

	public function test_is_not_met_when_wp_version_is_lower(): void {
		$c = new WordPressVersionConditional( '6.6', '6.5.0' );
		self::assertFalse( $c->is_met() );
	}

	public function test_reason_describes_requirement(): void {
		$c = new WordPressVersionConditional( '6.6' );
		self::assertStringContainsString( '6.6', $c->reason() );
	}
}
