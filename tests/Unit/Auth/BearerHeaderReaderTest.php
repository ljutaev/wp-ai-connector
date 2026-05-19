<?php
declare(strict_types=1);

namespace WPAIConnector\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use WPAIConnector\Auth\BearerHeaderReader;

final class BearerHeaderReaderTest extends TestCase {

	protected function setUp(): void {
		$_SERVER = array();
	}

	public function test_returns_token_from_standard_authorization_header(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer wpaic_live_abc123';

		self::assertSame( 'wpaic_live_abc123', ( new BearerHeaderReader() )->read() );
	}

	public function test_returns_token_from_redirect_authorization_header(): void {
		$_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer wpaic_live_redirected';

		self::assertSame( 'wpaic_live_redirected', ( new BearerHeaderReader() )->read() );
	}

	public function test_returns_null_when_no_header_present(): void {
		self::assertNull( ( new BearerHeaderReader() )->read() );
	}

	public function test_returns_null_when_scheme_is_not_bearer(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';

		self::assertNull( ( new BearerHeaderReader() )->read() );
	}

	public function test_handles_extra_whitespace(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = '   Bearer    wpaic_live_xyz   ';

		self::assertSame( 'wpaic_live_xyz', ( new BearerHeaderReader() )->read() );
	}

	public function test_returns_null_for_empty_bearer_token(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ';

		self::assertNull( ( new BearerHeaderReader() )->read() );
	}
}
