<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Core\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

final class UsersController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/users',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'role'   => array( 'type' => 'string' ),
						'number' => array(
							'type'    => 'integer',
							'default' => 20,
						),
						'paged'  => array(
							'type'    => 'integer',
							'default' => 1,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/users/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);
	}

	public function permissions_check( mixed $request ): bool|\WP_Error {
		if ( ! current_user_can( 'list_users' ) ) {
			return ErrorResponse::forbidden_capability( 'list_users' );
		}

		return true;
	}

	public function get_items( mixed $request ): WP_REST_Response {
		$args = array(
			'number' => (int) $request->get_param( 'number' ),
			'paged'  => (int) $request->get_param( 'paged' ),
		);

		$role = $request->get_param( 'role' );
		if ( null !== $role ) {
			$args['role'] = sanitize_key( (string) $role );
		}

		$users = get_users( $args );
		$items = array();

		foreach ( $users as $user ) {
			if ( ! $user instanceof \WP_User ) {
				continue;
			}
			$items[] = $this->prepare_user( $user );
		}

		return new WP_REST_Response( $items, 200 );
	}

	public function get_item( mixed $request ): WP_REST_Response|\WP_Error {
		$user = get_user_by( 'id', (int) $request->get_param( 'id' ) );

		if ( false === $user ) {
			return ErrorResponse::not_found( 'User not found.' );
		}

		$response = new WP_REST_Response( $this->prepare_user( $user ), 200 );

		return $this->enrich_links(
			$response,
			array( 'self' => rest_url( "{$this->namespace}/users/{$user->ID}" ) )
		);
	}

	/** @return array<string, mixed> */
	private function prepare_user( \WP_User $user ): array {
		return array(
			'ID'           => $user->ID,
			'user_login'   => $user->user_login,
			'user_email'   => $user->user_email,
			'display_name' => $user->display_name,
			'roles'        => $user->roles,
			'registered'   => $user->user_registered,
			'_links'       => array(
				'self' => rest_url( "{$this->namespace}/users/{$user->ID}" ),
			),
		);
	}
}
