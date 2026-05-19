<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Core\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

final class MenusController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/menus',
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
			'/menus/(?P<id>\d+)',
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
		if ( ! current_user_can( 'manage_options' ) ) {
			return ErrorResponse::forbidden_capability( 'manage_options' );
		}

		return true;
	}

	public function get_items( mixed $request ): WP_REST_Response {
		$menus = wp_get_nav_menus();
		$items = array();

		if ( is_array( $menus ) ) {
			foreach ( $menus as $menu ) {
				if ( ! $menu instanceof \WP_Term ) {
					continue;
				}
				$items[] = $this->prepare_menu( $menu );
			}
		}

		return new WP_REST_Response( $items, 200 );
	}

	public function get_item( mixed $request ): WP_REST_Response|\WP_Error {
		$menu = wp_get_nav_menu_object( (int) $request->get_param( 'id' ) );

		if ( false === $menu || ! $menu instanceof \WP_Term ) {
			return ErrorResponse::not_found( 'Menu not found.' );
		}

		$menu_items = wp_get_nav_menu_items( $menu->term_id );
		$items      = array();

		if ( is_array( $menu_items ) ) {
			foreach ( $menu_items as $item ) {
				if ( ! $item instanceof \WP_Post ) {
					continue;
				}
				$items[] = array(
					'ID'         => $item->ID,
					'title'      => $item->post_title,
					'url'        => get_post_meta( $item->ID, '_menu_item_url', true ),
					'object'     => get_post_meta( $item->ID, '_menu_item_object', true ),
					'object_id'  => (int) get_post_meta( $item->ID, '_menu_item_object_id', true ),
					'menu_order' => $item->menu_order,
					'parent'     => (int) get_post_meta( $item->ID, '_menu_item_menu_item_parent', true ),
				);
			}
		}

		$data          = $this->prepare_menu( $menu );
		$data['items'] = $items;

		$response = new WP_REST_Response( $data, 200 );

		return $this->enrich_links(
			$response,
			array( 'self' => rest_url( "{$this->namespace}/menus/{$menu->term_id}" ) )
		);
	}

	/** @return array<string, mixed> */
	private function prepare_menu( \WP_Term $menu ): array {
		return array(
			'term_id' => $menu->term_id,
			'name'    => $menu->name,
			'slug'    => $menu->slug,
			'count'   => $menu->count,
			'_links'  => array(
				'self' => rest_url( "{$this->namespace}/menus/{$menu->term_id}" ),
			),
		);
	}
}
