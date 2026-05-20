<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Yoast;

use WPAIConnector\Core\Container;
use WPAIConnector\Modules\AbstractModule;
use WPAIConnector\Modules\Yoast\Conditionals\YoastConditional;
use WPAIConnector\Modules\Yoast\Controllers\SeoController;

final class YoastModule extends AbstractModule {

	public function name(): string {
		return 'yoast-seo';
	}

	public function version(): string {
		return '0.1.0';
	}

	/** @return array<int, \WPAIConnector\Modules\ConditionalInterface> */
	public function conditionals(): array {
		return array(
			new YoastConditional(),
		);
	}

	public function register( Container $container ): void {
		$container->set( SeoController::class, static fn () => new SeoController() );

		add_action(
			'rest_api_init',
			static function () use ( $container ): void {
				$container->get( SeoController::class )->register_routes();
			}
		);
	}

	/** @return array<string, mixed> */
	public function manifest(): array {
		$version = defined( 'WPSEO_VERSION' ) ? WPSEO_VERSION : 'unknown';

		return array(
			'name'        => 'yoast-seo',
			'version'     => $this->version(),
			'detected'    => true,
			'host_plugin' => array(
				'name'    => 'Yoast SEO',
				'version' => $version,
			),
			'routes'      => array(
				array(
					'method'      => 'GET',
					'path'        => '/yoast/posts/{id}',
					'scope'       => 'yoast:read',
					'description' => 'Get SEO metadata for a post. Reads from wp_yoast_indexable (Yoast 14+) with postmeta fallback.',
					'ai_hint'     => 'Returns seo_title, meta_description, focus_keyphrase, canonical, noindex/nofollow, og_* and twitter_* fields. source=indexable means fresh data; source=postmeta means indexable table missing.',
				),
				array(
					'method'      => 'POST',
					'path'        => '/yoast/posts/{id}',
					'scope'       => 'yoast:write',
					'description' => 'Update Yoast SEO fields for a post.',
					'parameters'  => array(
						array(
							'name' => 'seo_title',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'meta_description',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'focus_keyphrase',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'canonical',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'noindex',
							'in'   => 'body',
							'type' => 'boolean',
						),
						array(
							'name' => 'og_title',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'og_description',
							'in'   => 'body',
							'type' => 'string',
						),
					),
					'ai_hint'     => 'To hide a post from search engines: POST noindex=true. To set a custom title without %%sep%% template: set seo_title to a plain string.',
				),
				array(
					'method'      => 'GET',
					'path'        => '/yoast/terms/{id}',
					'scope'       => 'yoast:read',
					'description' => 'Get SEO metadata for a taxonomy term (category, tag, custom taxonomy).',
					'ai_hint'     => 'Uses wpseo_taxonomy_meta option if indexable table unavailable.',
				),
				array(
					'method'      => 'GET',
					'path'        => '/yoast/settings',
					'scope'       => 'yoast:read',
					'description' => 'Site-wide Yoast SEO configuration: company/person entity, social profiles, separator.',
				),
			),
		);
	}
}
