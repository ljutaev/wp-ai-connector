<?php
declare(strict_types=1);

namespace WPAIConnector\Manifest;

use WPAIConnector\Modules\ModuleInterface;

final class ManifestGenerator {

	public const PLUGIN_VERSION = '0.2.0-alpha';

	/**
	 * @param array<int, ModuleInterface> $modules
	 * @return array<string, mixed>
	 */
	public function generate( array $modules ): array {
		return array(
			'plugin'  => array(
				'name'        => 'WP AI Connector',
				'version'     => self::PLUGIN_VERSION,
				'site_url'    => function_exists( 'home_url' ) ? home_url() : '',
				'wp_version'  => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '',
				'php_version' => PHP_VERSION,
			),
			'modules' => array_values(
				array_map(
					static fn ( ModuleInterface $m ): array => $m->manifest(),
					$modules,
				)
			),
		);
	}
}
