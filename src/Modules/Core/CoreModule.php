<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Core;

use WPAIConnector\Core\Container;
use WPAIConnector\Modules\AbstractModule;
use WPAIConnector\Modules\Conditionals\PHPVersionConditional;
use WPAIConnector\Modules\Conditionals\WordPressVersionConditional;
use WPAIConnector\Modules\Core\Controllers\OptionsController;

final class CoreModule extends AbstractModule {

	public function name(): string {
		return 'core';
	}

	public function version(): string {
		return '0.2.0-alpha';
	}

	/** @return array<int, \WPAIConnector\Modules\ConditionalInterface> */
	public function conditionals(): array {
		return array(
			new PHPVersionConditional( '8.1' ),
			new WordPressVersionConditional( '6.6' ),
		);
	}

	public function register( Container $container ): void {
		$container->set( OptionsAllowlist::class, static fn () => new OptionsAllowlist() );
		$container->set(
			OptionsController::class,
			static fn ( Container $c ) => new OptionsController( $c->get( OptionsAllowlist::class ) ),
		);

		add_action(
			'rest_api_init',
			static function () use ( $container ): void {
				$container->get( OptionsController::class )->register_routes();
			}
		);
	}

	/** @return array<string, mixed> */
	public function manifest(): array {
		$keys = ( new OptionsAllowlist() )->known();

		return array(
			'name'              => 'core',
			'version'           => $this->version(),
			'detected'          => true,
			'routes'            => array(
				array(
					'method'      => 'GET',
					'path'        => '/options/{key}',
					'scope'       => 'options:read',
					'description' => 'Read a WordPress option from the curated allowlist.',
					'parameters'  => array(
						array(
							'name' => 'key',
							'in'   => 'path',
							'type' => 'string',
							'enum' => $keys,
						),
					),
				),
				array(
					'method'      => 'POST',
					'path'        => '/options/{key}',
					'scope'       => 'options:write',
					'description' => 'Update a WordPress option from the curated allowlist.',
					'parameters'  => array(
						array(
							'name' => 'key',
							'in'   => 'path',
							'type' => 'string',
							'enum' => $keys,
						),
						array(
							'name' => 'value',
							'in'   => 'body',
							'type' => 'string',
						),
					),
					'ai_hint'     => "Common keys: 'blog_public' (0 hides from search engines, 1 shows), 'blogname' (site title), 'blogdescription' (tagline).",
				),
			),
			'options_allowlist' => $keys,
		);
	}
}
