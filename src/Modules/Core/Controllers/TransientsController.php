<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Core\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

final class TransientsController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/transients/(?P<key>[a-zA-Z0-9_\-]+)',
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
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'key' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/transients/(?P<key>[a-zA-Z0-9_\-]+)/flush',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'flush_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'key' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			)
		);
	}

	public function permissions_check( mixed $request ): bool|\WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return ErrorResponse::forbidden_capability( 'manage_options' );
		}

		return true;
	}

	public function get_item( mixed $request ): WP_REST_Response {
		$key   = sanitize_key( (string) $request->get_param( 'key' ) );
		$value = get_transient( $key );

		$response = new WP_REST_Response(
			array(
				'key'     => $key,
				'value'   => false !== $value ? $value : null,
				'expired' => false === $value,
			),
			200
		);

		return $this->enrich_links(
			$response,
			array( 'self' => rest_url( "{$this->namespace}/transients/{$key}" ) )
		);
	}

	public function delete_item( mixed $request ): WP_REST_Response {
		$key     = sanitize_key( (string) $request->get_param( 'key' ) );
		$deleted = delete_transient( $key );

		return new WP_REST_Response(
			array(
				'key'     => $key,
				'deleted' => $deleted,
			),
			200
		);
	}

	public function flush_item( mixed $request ): WP_REST_Response {
		return $this->delete_item( $request );
	}
}
