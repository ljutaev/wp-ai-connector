<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Core\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\Manifest\ManifestGenerator;
use WPAIConnector\Manifest\SkillGenerator;
use WPAIConnector\Modules\ModuleInterface;
use WPAIConnector\REST\AbstractController;

final class SkillController extends AbstractController {

	/**
	 * @param array<int, ModuleInterface> $modules
	 */
	public function __construct(
		private readonly ManifestGenerator $manifest_generator,
		private readonly SkillGenerator $skill_generator,
		private readonly array $modules,
	) {
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/skill',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function get_item( WP_REST_Request $request ): WP_REST_Response {
		$manifest = $this->manifest_generator->generate( $this->modules );
		$markdown = $this->skill_generator->generate( $manifest );

		$response = new WP_REST_Response( $markdown, 200 );
		$response->header( 'Content-Type', 'text/markdown; charset=utf-8' );
		return $response;
	}
}
