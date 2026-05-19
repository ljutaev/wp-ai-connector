<?php
declare(strict_types=1);

namespace WPAIConnector\Tests\Unit\Modules\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPAIConnector\Modules\Core\Controllers\TransientsController;

final class TransientsControllerTest extends TestCase {

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

		$controller = new TransientsController();
		$request    = $this->createMock( \WP_REST_Request::class );

		$result = $controller->permissions_check( $request );

		self::assertInstanceOf( \WP_Error::class, $result );
		$data = $result->get_error_data();
		self::assertIsArray( $data );
		self::assertSame( 'manage_options', $data['capability'] );
	}

	public function test_permissions_check_returns_true_when_capable(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$result = ( new TransientsController() )->permissions_check(
			$this->createMock( \WP_REST_Request::class )
		);

		self::assertTrue( $result );
	}

	public function test_get_item_returns_value_when_transient_exists(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_transient' )->justReturn( 'cached_value' );
		Functions\when( 'rest_url' )->justReturn( 'http://example.com/wp-json/wp-ai-connector/v1/transients/my_key' );
		Functions\when( 'sanitize_key' )->returnArg();

		$controller = new TransientsController();
		$request    = $this->createMock( \WP_REST_Request::class );
		$request->method( 'get_param' )->with( 'key' )->willReturn( 'my_key' );

		$response = $controller->get_item( $request );

		self::assertInstanceOf( \WP_REST_Response::class, $response );
		$data = $response->get_data();
		self::assertIsArray( $data );
		self::assertSame( 'my_key', $data['key'] );
		self::assertSame( 'cached_value', $data['value'] );
		self::assertFalse( $data['expired'] );
	}

	public function test_get_item_returns_expired_true_when_transient_missing(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'rest_url' )->justReturn( 'http://example.com/wp-json/wp-ai-connector/v1/transients/gone' );
		Functions\when( 'sanitize_key' )->returnArg();

		$controller = new TransientsController();
		$request    = $this->createMock( \WP_REST_Request::class );
		$request->method( 'get_param' )->with( 'key' )->willReturn( 'gone' );

		$response = $controller->get_item( $request );

		self::assertInstanceOf( \WP_REST_Response::class, $response );
		$data = $response->get_data();
		self::assertIsArray( $data );
		self::assertTrue( $data['expired'] );
	}

	public function test_register_routes_calls_register_rest_route(): void {
		Functions\expect( 'register_rest_route' )
			->times( 2 )
			->andReturn( true );

		( new TransientsController() )->register_routes();

		self::assertTrue( true ); // Brain Monkey validates call count in tearDown.
	}
}
