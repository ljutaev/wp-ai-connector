<?php
declare(strict_types=1);

namespace WPAIConnector\Tests\Unit\Modules\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPAIConnector\Modules\Core\Controllers\QueryController;

final class QueryControllerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_permissions_check_requires_manage_options(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$result = ( new QueryController() )->permissions_check(
			$this->createMock( \WP_REST_Request::class )
		);

		self::assertInstanceOf( \WP_Error::class, $result );
		$data = $result->get_error_data();
		self::assertIsArray( $data );
		self::assertSame( 'manage_options', $data['capability'] );
	}

	public function test_permissions_check_returns_true_when_capable(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$result = ( new QueryController() )->permissions_check(
			$this->createMock( \WP_REST_Request::class )
		);

		self::assertTrue( $result );
	}

	public function test_is_safe_select_accepts_simple_select(): void {
		self::assertTrue( QueryController::is_safe( 'SELECT ID, post_title FROM wp_posts LIMIT 10' ) );
	}

	public function test_is_safe_select_accepts_with_cte(): void {
		self::assertTrue( QueryController::is_safe( 'WITH cte AS (SELECT ID FROM wp_posts) SELECT * FROM cte' ) );
	}

	public function test_is_safe_rejects_insert(): void {
		self::assertFalse( QueryController::is_safe( "INSERT INTO wp_options (option_name) VALUES ('x')" ) );
	}

	public function test_is_safe_rejects_update(): void {
		self::assertFalse( QueryController::is_safe( 'UPDATE wp_options SET option_value = 1' ) );
	}

	public function test_is_safe_rejects_delete(): void {
		self::assertFalse( QueryController::is_safe( 'DELETE FROM wp_posts WHERE ID=1' ) );
	}

	public function test_is_safe_rejects_drop(): void {
		self::assertFalse( QueryController::is_safe( 'DROP TABLE wp_posts' ) );
	}

	public function test_is_safe_rejects_stacked_statements(): void {
		self::assertFalse( QueryController::is_safe( 'SELECT 1; DROP TABLE wp_posts' ) );
	}

	public function test_is_safe_rejects_union_with_insert(): void {
		self::assertFalse( QueryController::is_safe( 'SELECT 1 UNION INSERT INTO wp_options VALUES (1,2,3,4)' ) );
	}

	public function test_inject_limit_adds_limit_when_missing(): void {
		$sql = QueryController::inject_limit( 'SELECT ID FROM wp_posts', 500 );

		self::assertStringEndsWith( 'LIMIT 500', $sql );
	}

	public function test_inject_limit_respects_existing_lower_limit(): void {
		$sql = QueryController::inject_limit( 'SELECT ID FROM wp_posts LIMIT 10', 500 );

		self::assertStringContainsString( 'LIMIT 10', $sql );
		self::assertStringNotContainsString( 'LIMIT 500', $sql );
	}

	public function test_inject_limit_caps_existing_high_limit(): void {
		$sql = QueryController::inject_limit( 'SELECT ID FROM wp_posts LIMIT 99999', 500 );

		self::assertStringContainsString( 'LIMIT 500', $sql );
	}

	public function test_register_routes_calls_register_rest_route(): void {
		Functions\expect( 'register_rest_route' )
			->once()
			->andReturn( true );

		( new QueryController() )->register_routes();

		self::assertTrue( true ); // Brain Monkey validates call count in tearDown.
	}
}
