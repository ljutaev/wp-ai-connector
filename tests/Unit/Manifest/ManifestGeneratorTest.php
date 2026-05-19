<?php
declare(strict_types=1);

namespace WPAIConnector\Tests\Unit\Manifest;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPAIConnector\Manifest\ManifestGenerator;
use WPAIConnector\Modules\AbstractModule;

final class ManifestGeneratorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubs(
			array(
				'home_url'     => static fn () => 'https://example.com',
				'get_bloginfo' => static fn () => '7.0',
			)
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_includes_plugin_info(): void {
		$result = ( new ManifestGenerator() )->generate( array() );

		self::assertSame( 'WP AI Connector', $result['plugin']['name'] );
		self::assertSame( 'https://example.com', $result['plugin']['site_url'] );
		self::assertSame( PHP_VERSION, $result['plugin']['php_version'] );
	}

	public function test_assembles_module_manifests(): void {
		$module = new class() extends AbstractModule {
			public function name(): string {
				return 'demo'; }
			public function version(): string {
				return '0.1.0'; }
			public function manifest(): array {
				return array(
					'name'    => 'demo',
					'version' => '0.1.0',
					'routes'  => array(
						array(
							'method' => 'GET',
							'path'   => '/demo',
						),
					),
				);
			}
		};

		$result = ( new ManifestGenerator() )->generate( array( $module ) );

		self::assertCount( 1, $result['modules'] );
		self::assertSame( 'demo', $result['modules'][0]['name'] );
		self::assertSame( '/demo', $result['modules'][0]['routes'][0]['path'] );
	}
}
