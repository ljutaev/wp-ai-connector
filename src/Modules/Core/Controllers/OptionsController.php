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
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'key' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'key'   => array(
							'type'     => 'string',
							'required' => true,
						),
						'value' => array( 'required' => true ),
					),
				),
			)
		);
	}

	public function permissions_check( mixed $request ): bool|\WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return ErrorResponse::forbidden_capability( 'manage_options' );
		}

		$key = (string) $request->get_param( 'key' );
		if ( ! $this->allowlist->is_allowed( $key ) ) {
			return ErrorResponse::make( 'wpaic_options_not_allowed', "Option '{$key}' is not in the allowlist.", 403, array( 'key' => $key ) );
		}

		return true;
	}

	public function get_item( mixed $request ): WP_REST_Response {
		$key   = (string) $request->get_param( 'key' );
		$value = get_option( $key, null );

		$response = new WP_REST_Response(
			array(
				'key'   => $key,
				'value' => $value,
			),
			200
		);

		return $this->enrich_links( $response, array( 'self' => rest_url( "{$this->namespace}/options/{$key}" ) ) );
	}

	public function update_item( mixed $request ): WP_REST_Response {
		$key   = (string) $request->get_param( 'key' );
		$value = $request->get_param( 'value' );

		update_option( $key, $value );

		return $this->get_item( $request );
	}
}
