<?php
declare(strict_types=1);

namespace WPAIConnector\Manifest;

final class SkillGenerator {

	/**
	 * @param array<string, mixed> $manifest
	 */
	public function generate( array $manifest ): string {
		$site_url = (string) ( $manifest['plugin']['site_url'] ?? '' );
		$parsed   = function_exists( 'wp_parse_url' ) ? wp_parse_url( $site_url, PHP_URL_HOST ) : parse_url( $site_url, PHP_URL_HOST );
		$host     = is_string( $parsed ) ? $parsed : '';
		$slug     = 'wp-ai-connector-' . str_replace( '.', '-', $host );

		$out  = "---\n";
		$out .= "name: {$slug}\n";
		$out .= "description: Manage WordPress site {$host} via WP AI Connector REST API\n";
		$out .= "---\n\n";
		$out .= "Base URL: `{$site_url}/wp-json/wp-ai-connector/v1`\n";
		$out .= "Set header: `Authorization: Bearer <YOUR_KEY>`\n\n";
		$out .= "## Endpoints\n\n";

		foreach ( (array) ( $manifest['modules'] ?? [] ) as $module ) {
			foreach ( (array) ( $module['routes'] ?? [] ) as $route ) {
				$method = (string) ( $route['method'] ?? '' );
				$path   = (string) ( $route['path'] ?? '' );
				$desc   = (string) ( $route['description'] ?? '' );
				$out   .= "### {$method} {$path}\n\n";
				if ( '' !== $desc ) {
					$out .= "{$desc}\n\n";
				}
				$out .= "```bash\ncurl \"\$BASE{$path}\" -H \"\$AUTH\"\n```\n\n";
			}
		}

		return $out;
	}
}
