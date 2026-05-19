<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Core\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

final class MediaController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/media',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'mime_type'      => array( 'type' => 'string' ),
						'posts_per_page' => array(
							'type'    => 'integer',
							'default' => 20,
						),
						'paged'          => array(
							'type'    => 'integer',
							'default' => 1,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/media/(?P<id>\d+)',
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
		if ( ! current_user_can( 'upload_files' ) ) {
			return ErrorResponse::forbidden_capability( 'upload_files' );
		}

		return true;
	}

	public function get_items( mixed $request ): WP_REST_Response {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => (int) $request->get_param( 'posts_per_page' ),
			'paged'          => (int) $request->get_param( 'paged' ),
		);

		$mime = $request->get_param( 'mime_type' );
		if ( null !== $mime ) {
			$args['post_mime_type'] = sanitize_text_field( (string) $mime );
		}

		$query = new \WP_Query( $args );
		$items = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$items[] = $this->prepare_attachment( $post );
		}

		$response = new WP_REST_Response( $items, 200 );
		$response->header( 'X-WP-Total', (string) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );

		return $response;
	}

	public function get_item( mixed $request ): WP_REST_Response|\WP_Error {
		$post = get_post( (int) $request->get_param( 'id' ) );

		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
			return ErrorResponse::not_found( 'Media item not found.' );
		}

		$response = new WP_REST_Response( $this->prepare_attachment( $post ), 200 );

		return $this->enrich_links(
			$response,
			array( 'self' => rest_url( "{$this->namespace}/media/{$post->ID}" ) )
		);
	}

	/** @return array<string, mixed> */
	private function prepare_attachment( \WP_Post $post ): array {
		return array(
			'ID'        => $post->ID,
			'title'     => $post->post_title,
			'mime_type' => $post->post_mime_type,
			'url'       => wp_get_attachment_url( $post->ID ),
			'date'      => $post->post_date,
			'alt'       => get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
			'_links'    => array(
				'self' => rest_url( "{$this->namespace}/media/{$post->ID}" ),
			),
		);
	}
}
