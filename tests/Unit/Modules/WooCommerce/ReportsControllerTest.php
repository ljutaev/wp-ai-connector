<?php
declare(strict_types=1);

namespace WPAIConnector\Tests\Unit\Modules\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPAIConnector\Modules\WooCommerce\Controllers\ReportsController;

final class ReportsControllerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_permissions_check_requires_manage_woocommerce(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$result = ( new ReportsController() )->permissions_check(
			$this->createMock( \WP_REST_Request::class )
		);

		self::assertInstanceOf( \WP_Error::class, $result );
		$data = $result->get_error_data();
		self::assertIsArray( $data );
		self::assertSame( 'manage_woocommerce', $data['capability'] );
	}

	public function test_permissions_check_returns_true_when_capable(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$result = ( new ReportsController() )->permissions_check(
			$this->createMock( \WP_REST_Request::class )
		);

		self::assertTrue( $result );
	}

	public function test_register_routes_calls_register_rest_route(): void {
		Functions\expect( 'register_rest_route' )
			->times( 2 )
			->andReturn( true );

		( new ReportsController() )->register_routes();

		self::assertTrue( true ); // Brain Monkey validates call count in tearDown.
	}

	public function test_get_sales_returns_validation_error_on_bad_date(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'sanitize_text_field' )->returnArg();

		$request = new \WP_REST_Request();
		$request->set_param( 'date_from', 'not-a-date' );
		$request->set_param( 'date_to', '2026-01-31' );

		$result = ( new ReportsController() )->get_sales( $request );

		self::assertInstanceOf( \WP_Error::class, $result );
	}
}
