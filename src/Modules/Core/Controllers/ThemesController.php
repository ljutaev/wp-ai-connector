<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Core\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

final class ThemesController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/themes',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/themes/active',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_active' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	public function permissions_check( mixed $request ): bool|\WP_Error {
		if ( ! current_user_can( 'switch_themes' ) ) {
			return ErrorResponse::forbidden_capability( 'switch_themes' );
		}

		return true;
	}

	public function get_items( mixed $request ): WP_REST_Response {
		$themes = wp_get_themes();
		$items  = array();

		foreach ( $themes as $stylesheet => $theme ) {
			if ( ! $theme instanceof \WP_Theme ) {
				continue;
			}
			$items[] = $this->prepare_theme( $theme );
		}

		return new WP_REST_Response( $items, 200 );
	}

	public function get_active( mixed $request ): WP_REST_Response {
		$theme = wp_get_theme();

		return new WP_REST_Response( $this->prepare_theme( $theme ), 200 );
	}

	/** @return array<string, mixed> */
	private function prepare_theme( \WP_Theme $theme ): array {
		return array(
			'name'        => $theme->get( 'Name' ),
			'stylesheet'  => $theme->get_stylesheet(),
			'template'    => $theme->get_template(),
			'version'     => $theme->get( 'Version' ),
			'description' => $theme->get( 'Description' ),
			'author'      => $theme->get( 'Author' ),
			'theme_uri'   => $theme->get( 'ThemeURI' ),
			'status'      => $theme->get_stylesheet() === get_stylesheet() ? 'active' : 'inactive',
		);
	}
}
