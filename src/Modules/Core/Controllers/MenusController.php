<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Core\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

/**
 * GET /menus              — list all nav menus with their assigned locations
 * GET /menus/{id}         — single menu with full item tree
 * GET /menu-locations     — registered theme locations and which menu is assigned
 */
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

		register_rest_route(
			$this->namespace,
			'/menu-locations',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_locations' ),
					'permission_callback' => array( $this, 'permissions_check' ),
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

	/** List all nav menus with their theme location assignments. */
	public function get_items( mixed $request ): WP_REST_Response {
		$menus     = wp_get_nav_menus();
		$locations = get_nav_menu_locations();
		// Build reverse map: term_id => [location_slug, ...]
		$loc_map = array();
		foreach ( (array) $locations as $slug => $menu_id ) {
			$loc_map[ (int) $menu_id ][] = $slug;
		}

		$items = array();

		if ( is_array( $menus ) ) {
			foreach ( $menus as $menu ) {
				if ( ! $menu instanceof \WP_Term ) {
					continue;
				}
				$data              = $this->prepare_menu( $menu );
				$data['locations'] = $loc_map[ $menu->term_id ] ?? array();
				$items[]           = $data;
			}
		}

		return new WP_REST_Response( $items, 200 );
	}

	/** Single menu with full structured item tree. */
	public function get_item( mixed $request ): WP_REST_Response|\WP_Error {
		$menu = wp_get_nav_menu_object( (int) $request->get_param( 'id' ) );

		if ( false === $menu || ! $menu instanceof \WP_Term ) {
			return ErrorResponse::not_found( 'Menu not found.' );
		}

		$menu_items_raw = wp_get_nav_menu_items( $menu->term_id );
		$flat           = array();

		if ( is_array( $menu_items_raw ) ) {
			foreach ( $menu_items_raw as $item ) {
				if ( ! $item instanceof \WP_Post ) {
					continue;
				}
				$flat[] = $this->prepare_menu_item( $item );
			}
		}

		$data          = $this->prepare_menu( $menu );
		$data['items'] = $this->build_tree( $flat );

		$response = new WP_REST_Response( $data, 200 );
		return $this->enrich_links(
			$response,
			array( 'self' => rest_url( "{$this->namespace}/menus/{$menu->term_id}" ) )
		);
	}

	/** Registered theme nav menu locations with assigned menu_id. */
	public function get_locations( mixed $request ): WP_REST_Response {
		$registered = get_registered_nav_menus();
		$assigned   = get_nav_menu_locations();
		$items      = array();

		foreach ( (array) $registered as $slug => $description ) {
			$menu_id   = (int) ( $assigned[ $slug ] ?? 0 );
			$menu_name = '';

			if ( $menu_id > 0 ) {
				$term      = wp_get_nav_menu_object( $menu_id );
				$menu_name = $term instanceof \WP_Term ? $term->name : '';
			}

			$items[] = array(
				'slug'        => $slug,
				'description' => $description,
				'menu_id'     => $menu_id > 0 ? $menu_id : null,
				'menu_name'   => '' !== $menu_name ? $menu_name : null,
			);
		}

		return new WP_REST_Response( $items, 200 );
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

	/** @return array<string, mixed> */
	private function prepare_menu_item( \WP_Post $item ): array {
		$type       = (string) get_post_meta( $item->ID, '_menu_item_type', true );
		$object     = (string) get_post_meta( $item->ID, '_menu_item_object', true );
		$object_id  = (int) get_post_meta( $item->ID, '_menu_item_object_id', true );
		$url        = (string) get_post_meta( $item->ID, '_menu_item_url', true );
		$parent     = (int) get_post_meta( $item->ID, '_menu_item_menu_item_parent', true );
		$target     = (string) get_post_meta( $item->ID, '_menu_item_target', true );
		$classes    = (string) get_post_meta( $item->ID, '_menu_item_classes', true );
		$attr_title = (string) get_post_meta( $item->ID, '_menu_item_attr_title', true );

		// For post/page/taxonomy items resolve the real URL.
		if ( '' === $url && 'post_type' === $type && $object_id > 0 ) {
			$url = (string) get_permalink( $object_id );
		} elseif ( '' === $url && 'taxonomy' === $type && $object_id > 0 ) {
			$term_link = get_term_link( $object_id );
			if ( ! is_wp_error( $term_link ) ) {
				$url = $term_link;
			}
		}

		return array(
			'id'         => $item->ID,
			'title'      => $item->post_title,
			'url'        => $url,
			'type'       => $type,
			'object'     => $object,
			'object_id'  => $object_id > 0 ? $object_id : null,
			'parent_id'  => $parent > 0 ? $parent : null,
			'menu_order' => $item->menu_order,
			'target'     => '_blank' === $target ? '_blank' : '',
			'attr_title' => $attr_title,
			'classes'    => '' !== $classes ? explode( ' ', trim( $classes ) ) : array(),
			'children'   => array(),
		);
	}

	/**
	 * Build a nested tree from a flat sorted array of items.
	 *
	 * @param array<int, array<string, mixed>> $flat Items with id and parent_id.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_tree( array $flat ): array {
		$indexed = array();
		foreach ( $flat as $item ) {
			$indexed[ (int) $item['id'] ] = $item;
		}

		$roots = array();
		foreach ( $indexed as $id => $item ) {
			$pid = (int) ( $item['parent_id'] ?? 0 );
			if ( $pid > 0 && isset( $indexed[ $pid ] ) ) {
				$indexed[ $pid ]['children'][] = &$indexed[ $id ];
			} else {
				$roots[] = &$indexed[ $id ];
			}
		}

		return $roots;
	}
}
