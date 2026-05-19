<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Core\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

final class PluginsController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/plugins',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'status' => array(
							'type'    => 'string',
							'enum'    => array( 'all', 'active', 'inactive' ),
							'default' => 'all',
						),
					),
				),
			)
		);
	}

	public function permissions_check( mixed $request ): bool|\WP_Error {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return ErrorResponse::forbidden_capability( 'activate_plugins' );
		}

		return true;
	}

	public function get_items( mixed $request ): WP_REST_Response {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$status      = sanitize_key( (string) $request->get_param( 'status' ) );
		$items       = array();

		foreach ( $all_plugins as $file => $data ) {
			$is_active = is_plugin_active( $file );

			if ( 'active' === $status && ! $is_active ) {
				continue;
			}

			if ( 'inactive' === $status && $is_active ) {
				continue;
			}

			$items[] = array(
				'plugin_file'  => $file,
				'name'         => $data['Name'],
				'version'      => $data['Version'],
				'description'  => $data['Description'],
				'author'       => $data['Author'],
				'plugin_uri'   => $data['PluginURI'],
				'network_only' => ! empty( $data['Network'] ),
				'active'       => $is_active,
			);
		}

		return new WP_REST_Response( $items, 200 );
	}
}
