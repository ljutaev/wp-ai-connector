<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Core\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

final class TermsController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/terms',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'taxonomy'   => array(
							'type'    => 'string',
							'default' => 'category',
						),
						'hide_empty' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'number'     => array(
							'type'    => 'integer',
							'default' => 100,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/terms/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'id'       => array(
							'type'     => 'integer',
							'required' => true,
						),
						'taxonomy' => array(
							'type'    => 'string',
							'default' => 'category',
						),
					),
				),
			)
		);
	}

	public function permissions_check( mixed $request ): bool|\WP_Error {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return ErrorResponse::forbidden_capability( 'manage_categories' );
		}

		return true;
	}

	public function get_items( mixed $request ): WP_REST_Response|\WP_Error {
		$taxonomy = sanitize_key( (string) $request->get_param( 'taxonomy' ) );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return ErrorResponse::make( 'wpaic_invalid_taxonomy', "Taxonomy '{$taxonomy}' does not exist.", 400, array( 'taxonomy' => $taxonomy ) );
		}

		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => (bool) $request->get_param( 'hide_empty' ),
			'number'     => (int) $request->get_param( 'number' ),
		);

		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$items = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$items[] = $this->prepare_term( $term );
		}

		return new WP_REST_Response( $items, 200 );
	}

	public function get_item( mixed $request ): WP_REST_Response|\WP_Error {
		$taxonomy = sanitize_key( (string) $request->get_param( 'taxonomy' ) );
		$term     = get_term( (int) $request->get_param( 'id' ), $taxonomy );

		if ( is_wp_error( $term ) ) {
			return $term;
		}

		if ( ! $term instanceof \WP_Term ) {
			return ErrorResponse::not_found( 'Term not found.' );
		}

		$response = new WP_REST_Response( $this->prepare_term( $term ), 200 );

		return $this->enrich_links(
			$response,
			array( 'self' => rest_url( "{$this->namespace}/terms/{$term->term_id}?taxonomy={$taxonomy}" ) )
		);
	}

	/** @return array<string, mixed> */
	private function prepare_term( \WP_Term $term ): array {
		return array(
			'term_id'     => $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'taxonomy'    => $term->taxonomy,
			'description' => $term->description,
			'count'       => $term->count,
			'parent'      => $term->parent,
			'_links'      => array(
				'self' => rest_url( "{$this->namespace}/terms/{$term->term_id}?taxonomy={$term->taxonomy}" ),
			),
		);
	}
}
