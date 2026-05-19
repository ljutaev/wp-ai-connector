<?php
declare(strict_types=1);

namespace WPAIConnector\Tests\Unit\Auth;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPAIConnector\Auth\RateLimiter;

final class RateLimiterTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_allows_first_request(): void {
		Functions\when( 'get_transient' )->justReturn( 0 );
		Functions\when( 'set_transient' )->justReturn( true );
		Filters\expectApplied( 'wp_ai_connector_rate_limit' )->andReturnFirstArg();

		$limiter = new RateLimiter( 60 );

		self::assertTrue( $limiter->allow( 1 ) );
	}

	public function test_blocks_when_limit_exceeded(): void {
		Functions\when( 'get_transient' )->justReturn( 60 );
		Filters\expectApplied( 'wp_ai_connector_rate_limit' )->andReturnFirstArg();

		$limiter = new RateLimiter( 60 );

		self::assertFalse( $limiter->allow( 1 ) );
	}

	public function test_remaining_returns_correct_count(): void {
		Functions\when( 'get_transient' )->justReturn( 10 );
		Filters\expectApplied( 'wp_ai_connector_rate_limit' )->andReturnFirstArg();

		$limiter = new RateLimiter( 60 );

		self::assertSame( 50, $limiter->remaining( 1 ) );
	}

	public function test_remaining_never_goes_below_zero(): void {
		Functions\when( 'get_transient' )->justReturn( 999 );
		Filters\expectApplied( 'wp_ai_connector_rate_limit' )->andReturnFirstArg();

		$limiter = new RateLimiter( 60 );

		self::assertSame( 0, $limiter->remaining( 1 ) );
	}

	public function test_filter_overrides_limit(): void {
		Functions\when( 'get_transient' )->justReturn( 5 );
		Filters\expectApplied( 'wp_ai_connector_rate_limit' )->andReturn( 3 );

		$limiter = new RateLimiter( 60 );

		self::assertFalse( $limiter->allow( 1 ) );
	}

	public function test_limit_and_window_accessors(): void {
		$limiter = new RateLimiter( 30 );

		self::assertSame( 30, $limiter->limit() );
		self::assertSame( 60, $limiter->window() );
	}
}
