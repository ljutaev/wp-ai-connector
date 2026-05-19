<?php
declare(strict_types=1);

namespace WPAIConnector\Tests\Unit\Modules\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPAIConnector\Modules\Core\Controllers\CronController;

final class CronControllerTest extends TestCase {

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

		$controller = new CronController();
		$request    = $this->createMock( \WP_REST_Request::class );

		$result = $controller->permissions_check( $request );

		self::assertInstanceOf( \WP_Error::class, $result );
		$data = $result->get_error_data();
		self::assertIsArray( $data );
		self::assertSame( 'manage_options', $data['capability'] );
	}

	public function test_permissions_check_returns_true_when_capable(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$result = ( new CronController() )->permissions_check(
			$this->createMock( \WP_REST_Request::class )
		);

		self::assertTrue( $result );
	}

	public function test_get_items_returns_array(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( '_get_cron_array' )->justReturn(
			array(
				1716153600 => array(
					'my_hook' => array(
						'abc123' => array(
							'schedule' => 'hourly',
							'args'     => array(),
							'interval' => 3600,
						),
					),
				),
			)
		);

		$controller = new CronController();
		$request    = $this->createMock( \WP_REST_Request::class );
		$request->method( 'get_param' )->with( 'hook' )->willReturn( null );

		$response = $controller->get_items( $request );

		self::assertInstanceOf( \WP_REST_Response::class, $response );
		$data = $response->get_data();
		self::assertIsArray( $data );
		self::assertNotEmpty( $data );
		self::assertSame( 'my_hook', $data[0]['hook'] );
	}

	public function test_register_routes_calls_register_rest_route(): void {
		Functions\expect( 'register_rest_route' )
			->once()
			->andReturn( true );

		( new CronController() )->register_routes();

		self::assertTrue( true ); // Brain Monkey validates call count in tearDown.
	}
}
