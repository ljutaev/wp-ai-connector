<?php
declare(strict_types=1);

namespace WPAIConnector\Tests\Unit\Modules;

use PHPUnit\Framework\TestCase;
use WPAIConnector\Core\Container;
use WPAIConnector\Modules\AbstractModule;
use WPAIConnector\Modules\ConditionalInterface;
use WPAIConnector\Modules\ModuleRegistry;

final class ModuleRegistryTest extends TestCase {

	public function test_modules_with_unmet_conditionals_are_excluded(): void {
		$enabled  = $this->module_named( 'enabled', true );
		$disabled = $this->module_named( 'disabled', false );

		$registry = new ModuleRegistry( new Container() );
		$active   = $registry->load( array( $enabled, $disabled ) );

		self::assertCount( 1, $active );
		self::assertSame( 'enabled', $active[0]->name() );
	}

	public function test_module_register_is_called_for_active_modules_only(): void {
		$registered = array();
		$enabled    = new class( 'enabled', true, $registered ) extends AbstractModule {
			public function __construct( private string $n, private bool $on, public array &$reg ) {}
			public function name(): string {
				return $this->n; }
			public function version(): string {
				return '0.0.1'; }
			public function conditionals(): array {
				return array(
					new class( $this->on ) implements ConditionalInterface {
						public function __construct( private bool $on ) {}
						public function is_met(): bool {
							return $this->on; }
						public function reason(): string {
							return ''; }
					},
				); }
			public function register( \WPAIConnector\Core\Container $c ): void {
				$this->reg[] = $this->n;
			}
		};

		$registry = new ModuleRegistry( new Container() );
		$registry->load( array( $enabled ) );

		self::assertSame( array( 'enabled' ), $registered );
	}

	private function module_named( string $name, bool $conditional_met ): AbstractModule {
		return new class( $name, $conditional_met ) extends AbstractModule {
			public function __construct( private string $n, private bool $on ) {}
			public function name(): string {
				return $this->n; }
			public function version(): string {
				return '0.0.1'; }
			public function conditionals(): array {
				return array(
					new class( $this->on ) implements ConditionalInterface {
						public function __construct( private bool $on ) {}
						public function is_met(): bool {
							return $this->on; }
						public function reason(): string {
							return ''; }
					},
				);
			}
		};
	}
}
