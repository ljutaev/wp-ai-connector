<?php
declare(strict_types=1);

namespace WPAIConnector\Tests\Unit\Manifest;

use PHPUnit\Framework\TestCase;
use WPAIConnector\Manifest\SkillGenerator;

final class SkillGeneratorTest extends TestCase {

	public function test_renders_frontmatter(): void {
		$markdown = ( new SkillGenerator() )->generate(
			[
				'plugin'  => [ 'site_url' => 'https://example.com', 'version' => '0.2.0-alpha' ],
				'modules' => [],
			]
		);

		self::assertStringContainsString( '---', $markdown );
		self::assertStringContainsString( 'name: wp-ai-connector-example-com', $markdown );
	}

	public function test_includes_module_routes(): void {
		$markdown = ( new SkillGenerator() )->generate(
			[
				'plugin'  => [ 'site_url' => 'https://example.com', 'version' => '0.2.0-alpha' ],
				'modules' => [
					[
						'name'   => 'core',
						'routes' => [
							[ 'method' => 'GET', 'path' => '/options/{key}', 'description' => 'Read a WP option.' ],
						],
					],
				],
			]
		);

		self::assertStringContainsString( '### GET /options/{key}', $markdown );
		self::assertStringContainsString( 'Read a WP option.', $markdown );
	}
}
