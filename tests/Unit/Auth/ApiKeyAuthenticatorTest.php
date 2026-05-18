<?php
declare(strict_types=1);

namespace WPAIConnector\Tests\Unit\Auth;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WPAIConnector\Auth\ApiKey;
use WPAIConnector\Auth\ApiKeyAuthenticator;
use WPAIConnector\Auth\ApiKeyFactory;
use WPAIConnector\Auth\ApiKeyRepository;

final class ApiKeyAuthenticatorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubs( [ 'wp_salt' => static fn () => 'test-salt' ] );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_returns_null_when_no_matching_hash(): void {
		$repo = $this->createMock( ApiKeyRepository::class );
		$repo->method( 'find_by_hash' )->willReturn( null );

		$auth   = new ApiKeyAuthenticator( $repo, new ApiKeyFactory(), new DateTimeImmutable() );
		$result = $auth->authenticate( 'wpaic_live_nonexistent' );

		self::assertNull( $result );
	}

	public function test_returns_null_when_key_revoked(): void {
		$repo = $this->createMock( ApiKeyRepository::class );
		$repo->method( 'find_by_hash' )->willReturn( $this->key( revoked: true ) );

		$auth   = new ApiKeyAuthenticator( $repo, new ApiKeyFactory(), new DateTimeImmutable() );
		$result = $auth->authenticate( 'wpaic_live_revoked' );

		self::assertNull( $result );
	}

	public function test_returns_null_when_key_expired(): void {
		$repo = $this->createMock( ApiKeyRepository::class );
		$repo->method( 'find_by_hash' )->willReturn( $this->key( expires_at: '2020-01-01 00:00:00' ) );

		$auth   = new ApiKeyAuthenticator( $repo, new ApiKeyFactory(), new DateTimeImmutable( '2026-01-01' ) );
		$result = $auth->authenticate( 'wpaic_live_expired' );

		self::assertNull( $result );
	}

	public function test_returns_key_when_usable(): void {
		$repo = $this->createMock( ApiKeyRepository::class );
		$repo->method( 'find_by_hash' )->willReturn( $this->key() );

		$auth   = new ApiKeyAuthenticator( $repo, new ApiKeyFactory(), new DateTimeImmutable() );
		$result = $auth->authenticate( 'wpaic_live_ok' );

		self::assertNotNull( $result );
		self::assertSame( 7, $result->user_id );
	}

	private function key( bool $revoked = false, ?string $expires_at = null ): ApiKey {
		return new ApiKey(
			id:            1,
			user_id:       7,
			label:         'test',
			hash:          str_repeat( 'a', 64 ),
			truncated_key: 'wpaic_live_abcd',
			scopes:        [ '*' ],
			last_used_at:  null,
			last_used_ip:  null,
			created_at:    new DateTimeImmutable( '2026-01-01' ),
			expires_at:    $expires_at ? new DateTimeImmutable( $expires_at ) : null,
			revoked_at:    $revoked ? new DateTimeImmutable( '2026-01-02' ) : null,
		);
	}
}
