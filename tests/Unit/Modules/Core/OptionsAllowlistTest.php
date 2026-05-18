<?php
declare(strict_types=1);

namespace WPAIConnector\Tests\Unit\Modules\Core;

use Brain\Monkey;
use Brain\Monkey\Filters;
use PHPUnit\Framework\TestCase;
use WPAIConnector\Modules\Core\OptionsAllowlist;

final class OptionsAllowlistTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_allows_blog_public(): void {
		Filters\expectApplied( 'wp_ai_connector_options_allowlist' )->andReturnFirstArg();

		self::assertTrue( ( new OptionsAllowlist() )->is_allowed( 'blog_public' ) );
	}

	public function test_allows_blogname_blogdescription_admin_email(): void {
		Filters\expectApplied( 'wp_ai_connector_options_allowlist' )->andReturnFirstArg();
		$allowlist = new OptionsAllowlist();

		self::assertTrue( $allowlist->is_allowed( 'blogname' ) );
		self::assertTrue( $allowlist->is_allowed( 'blogdescription' ) );
		self::assertTrue( $allowlist->is_allowed( 'admin_email' ) );
	}

	public function test_rejects_sensitive_keys_even_if_filter_adds_them(): void {
		Filters\expectApplied( 'wp_ai_connector_options_allowlist' )
			->andReturn( [ 'blogname', 'auth_key' ] );

		$allowlist = new OptionsAllowlist();

		self::assertTrue( $allowlist->is_allowed( 'blogname' ) );
		self::assertFalse( $allowlist->is_allowed( 'auth_key' ) );
	}

	public function test_rejects_unknown_options(): void {
		Filters\expectApplied( 'wp_ai_connector_options_allowlist' )->andReturnFirstArg();

		self::assertFalse( ( new OptionsAllowlist() )->is_allowed( 'random_made_up_option' ) );
	}

	public function test_known_options_returns_documented_set(): void {
		Filters\expectApplied( 'wp_ai_connector_options_allowlist' )->andReturnFirstArg();

		$keys = ( new OptionsAllowlist() )->known();

		self::assertContains( 'blog_public', $keys );
		self::assertContains( 'blogname', $keys );
		self::assertContains( 'timezone_string', $keys );
	}
}
