<?php
declare(strict_types=1);

namespace WPAIConnector\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WPAIConnector\Core\Container;

final class ContainerTest extends TestCase {

	public function test_get_returns_the_same_instance_on_repeated_calls(): void {
		$container = new Container();
		$container->set( 'service', static fn () => new \stdClass() );

		$a = $container->get( 'service' );
		$b = $container->get( 'service' );

		self::assertSame( $a, $b );
	}

	public function test_get_resolves_factory_with_the_container_passed_in(): void {
		$container = new Container();
		$container->set( 'dep', static fn () => 'dep-value' );
		$container->set( 'composite', static fn ( Container $c ) => $c->get( 'dep' ) . '-composed' );

		self::assertSame( 'dep-value-composed', $container->get( 'composite' ) );
	}

	public function test_get_throws_when_id_unknown(): void {
		$container = new Container();

		$this->expectException( \OutOfBoundsException::class );
		$this->expectExceptionMessage( "Service 'missing' is not registered" );

		$container->get( 'missing' );
	}

	public function test_has_returns_correct_boolean(): void {
		$container = new Container();
		$container->set( 'present', static fn () => 1 );

		self::assertTrue( $container->has( 'present' ) );
		self::assertFalse( $container->has( 'absent' ) );
	}
}
