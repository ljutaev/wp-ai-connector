<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Core\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\Modules\Core\OptionsAllowlist;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

final class OptionsController extends AbstractController {

	public function __construct( private readonly OptionsAllowlist $allowlist ) {
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/options/(?P<key>[a-zA-Z0-9_\-]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => [ 'key' => [ 'type' => 'string', 'required' => true ] ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => [
						'key'   => [ 'type' => 'string', 'required' => true ],
						'value' => [ 'required' => true ],
					],
				],
			]
		);
	}

	public function permissions_check( WP_REST_Request $request ): bool|\WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return ErrorResponse::forbidden_capability( 'manage_options' );
		}

		$key = (string) $request->get_param( 'key' );
		if ( ! $this->allowlist->is_allowed( $key ) ) {
			return ErrorResponse::make( 'wpaic_options_not_allowed', "Option '{$key}' is not in the allowlist.", 403, [ 'key' => $key ] );
		}

		return true;
	}

	public function get_item( WP_REST_Request $request ): WP_REST_Response {
		$key   = (string) $request->get_param( 'key' );
		$value = get_option( $key, null );

		$response = new WP_REST_Response(
			[
				'key'   => $key,
				'value' => $value,
			],
			200
		);

		return $this->enrich_links( $response, [ 'self' => rest_url( "{$this->namespace}/options/{$key}" ) ] );
	}

	public function update_item( WP_REST_Request $request ): WP_REST_Response {
		$key   = (string) $request->get_param( 'key' );
		$value = $request->get_param( 'value' );

		update_option( $key, $value );

		return $this->get_item( $request );
	}
}
