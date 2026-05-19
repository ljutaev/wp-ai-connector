<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Core\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

final class PostsController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/posts',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'post_type'      => array(
							'type'    => 'string',
							'default' => 'post',
						),
						'post_status'    => array(
							'type'    => 'string',
							'default' => 'publish',
						),
						'posts_per_page' => array(
							'type'    => 'integer',
							'default' => 20,
						),
						'paged'          => array(
							'type'    => 'integer',
							'default' => 1,
						),
						's'              => array(
							'type' => 'string',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/posts/(?P<id>\d+)',
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
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'write_permissions_check' ),
					'args'                => array(
						'id'           => array(
							'type'     => 'integer',
							'required' => true,
						),
						'post_title'   => array( 'type' => 'string' ),
						'post_content' => array( 'type' => 'string' ),
						'post_status'  => array( 'type' => 'string' ),
					),
				),
			)
		);
	}

	public function permissions_check( mixed $request ): bool|\WP_Error {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return ErrorResponse::forbidden_capability( 'edit_posts' );
		}

		return true;
	}

	public function write_permissions_check( mixed $request ): bool|\WP_Error {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return ErrorResponse::forbidden_capability( 'edit_posts' );
		}

		return true;
	}

	public function get_items( mixed $request ): WP_REST_Response {
		$args = array(
			'post_type'      => sanitize_key( (string) $request->get_param( 'post_type' ) ),
			'post_status'    => sanitize_key( (string) $request->get_param( 'post_status' ) ),
			'posts_per_page' => (int) $request->get_param( 'posts_per_page' ),
			'paged'          => (int) $request->get_param( 'paged' ),
		);

		$search = $request->get_param( 's' );
		if ( null !== $search ) {
			$args['s'] = sanitize_text_field( (string) $search );
		}

		$query = new \WP_Query( $args );
		$items = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$items[] = $this->prepare_post( $post );
		}

		$response = new WP_REST_Response( $items, 200 );
		$response->header( 'X-WP-Total', (string) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );

		return $response;
	}

	public function get_item( mixed $request ): WP_REST_Response|\WP_Error {
		$post = get_post( (int) $request->get_param( 'id' ) );

		if ( ! $post instanceof \WP_Post ) {
			return ErrorResponse::not_found( 'Post not found.' );
		}

		$response = new WP_REST_Response( $this->prepare_post( $post ), 200 );

		return $this->enrich_links(
			$response,
			array( 'self' => rest_url( "{$this->namespace}/posts/{$post->ID}" ) )
		);
	}

	public function update_item( mixed $request ): WP_REST_Response|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post ) {
			return ErrorResponse::not_found( 'Post not found.' );
		}

		$data = array( 'ID' => $id );

		$title = $request->get_param( 'post_title' );
		if ( null !== $title ) {
			$data['post_title'] = sanitize_text_field( (string) $title );
		}

		$content = $request->get_param( 'post_content' );
		if ( null !== $content ) {
			$data['post_content'] = wp_kses_post( (string) $content );
		}

		$status = $request->get_param( 'post_status' );
		if ( null !== $status ) {
			$data['post_status'] = sanitize_key( (string) $status );
		}

		$result = wp_update_post( $data, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->get_item( $request );
	}

	/** @return array<string, mixed> */
	private function prepare_post( \WP_Post $post ): array {
		return array(
			'ID'          => $post->ID,
			'post_title'  => $post->post_title,
			'post_status' => $post->post_status,
			'post_type'   => $post->post_type,
			'post_date'   => $post->post_date,
			'post_author' => (int) $post->post_author,
			'guid'        => $post->guid,
			'_links'      => array(
				'self' => rest_url( "{$this->namespace}/posts/{$post->ID}" ),
			),
		);
	}
}
