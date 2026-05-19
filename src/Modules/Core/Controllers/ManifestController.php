<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Core\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\Manifest\ManifestGenerator;
use WPAIConnector\Modules\ModuleInterface;
use WPAIConnector\REST\AbstractController;

final class ManifestController extends AbstractController {

	private const CACHE_KEY     = 'wpaic_manifest_cache';
	private const CACHE_SECONDS = 60;

	/**
	 * @param array<int, ModuleInterface> $modules
	 */
	public function __construct(
		private readonly ManifestGenerator $generator,
		private readonly array $modules,
	) {
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/manifest',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function get_item( mixed $request ): WP_REST_Response {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return new WP_REST_Response( $cached, 200 );
		}

		$manifest = $this->generator->generate( $this->modules );
		set_transient( self::CACHE_KEY, $manifest, self::CACHE_SECONDS );

		return new WP_REST_Response( $manifest, 200 );
	}
}
