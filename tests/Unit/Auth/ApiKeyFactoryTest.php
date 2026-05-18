<?php
declare(strict_types=1);

namespace WPAIConnector\Tests\Unit\Auth;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPAIConnector\Auth\ApiKeyFactory;

final class ApiKeyFactoryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubs( [ 'wp_salt' => static fn () => 'test-salt' ] );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_generate_returns_prefixed_token(): void {
		$result = ( new ApiKeyFactory() )->generate();

		self::assertStringStartsWith( 'wpaic_live_', $result->plaintext );
		self::assertSame( 37, strlen( $result->plaintext ) ); // wpaic_live_ (11) + 26 chars
	}

	public function test_generate_hash_is_deterministic_for_same_token_and_salt(): void {
		$factory = new ApiKeyFactory();
		$first   = $factory->hash( 'wpaic_live_sample', 'per-key-salt' );
		$second  = $factory->hash( 'wpaic_live_sample', 'per-key-salt' );

		self::assertSame( $first, $second );
		self::assertSame( 64, strlen( $first ) ); // sha256 hex
	}

	public function test_generate_returns_different_tokens_each_call(): void {
		$factory = new ApiKeyFactory();
		$a       = $factory->generate();
		$b       = $factory->generate();

		self::assertNotSame( $a->plaintext, $b->plaintext );
	}

	public function test_truncated_key_is_prefix_plus_first_chars(): void {
		$result = ( new ApiKeyFactory() )->generate();

		self::assertStringStartsWith( 'wpaic_live_', $result->truncated );
		self::assertSame( 15, strlen( $result->truncated ) ); // prefix + 4 chars
	}
}
