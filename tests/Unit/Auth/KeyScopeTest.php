<?php
declare(strict_types=1);

namespace WPAIConnector\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use WPAIConnector\Auth\KeyScope;

final class KeyScopeTest extends TestCase {

	public function test_exact_match(): void {
		self::assertTrue( KeyScope::allows( [ 'posts:read' ], 'posts:read' ) );
	}

	public function test_no_match(): void {
		self::assertFalse( KeyScope::allows( [ 'posts:read' ], 'posts:write' ) );
	}

	public function test_resource_wildcard(): void {
		self::assertTrue( KeyScope::allows( [ 'posts:*' ], 'posts:read' ) );
		self::assertTrue( KeyScope::allows( [ 'posts:*' ], 'posts:write' ) );
		self::assertFalse( KeyScope::allows( [ 'posts:*' ], 'users:read' ) );
	}

	public function test_full_wildcard(): void {
		self::assertTrue( KeyScope::allows( [ '*' ], 'anything:goes' ) );
	}

	public function test_multiple_scopes_any_match_passes(): void {
		self::assertTrue( KeyScope::allows( [ 'posts:read', 'options:read' ], 'options:read' ) );
	}

	public function test_namespaced_action(): void {
		self::assertTrue( KeyScope::allows( [ 'woo:orders:read' ], 'woo:orders:read' ) );
		self::assertTrue( KeyScope::allows( [ 'woo:orders:*' ], 'woo:orders:write' ) );
		self::assertTrue( KeyScope::allows( [ 'woo:*' ], 'woo:orders:read' ) );
	}
}
