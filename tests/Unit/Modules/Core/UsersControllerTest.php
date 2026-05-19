<?php
declare(strict_types=1);

namespace WPAIConnector\Tests\Unit\Modules\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPAIConnector\Modules\Core\Controllers\UsersController;

final class UsersControllerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_permissions_check_requires_list_users(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$controller = new UsersController();
		$request    = $this->createMock( \WP_REST_Request::class );

		$result = $controller->permissions_check( $request );

		self::assertInstanceOf( \WP_Error::class, $result );
		$data = $result->get_error_data();
		self::assertIsArray( $data );
		self::assertSame( 'list_users', $data['capability'] );
	}

	public function test_permissions_check_returns_true_when_capable(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$result = ( new UsersController() )->permissions_check(
			$this->createMock( \WP_REST_Request::class )
		);

		self::assertTrue( $result );
	}

	public function test_register_routes_calls_register_rest_route(): void {
		Functions\expect( 'register_rest_route' )
			->times( 2 )
			->andReturn( true );

		( new UsersController() )->register_routes();

		self::assertTrue( true ); // Brain Monkey validates call count in tearDown.
	}
}
