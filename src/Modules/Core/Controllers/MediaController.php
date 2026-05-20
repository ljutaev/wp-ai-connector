<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Core\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

/**
 * GET    /media            — list attachments (filterable by mime_type)
 * GET    /media/{id}       — single attachment with all registered sizes
 * POST   /media/{id}       — update alt text, title, caption
 * DELETE /media/{id}       — move to trash (force=true for permanent delete)
 * POST   /media/upload     — upload a file via multipart/form-data
 */
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
						'parent'         => array( 'type' => 'integer' ),
						'posts_per_page' => array(
							'type'    => 'integer',
							'default' => 20,
						),
						'paged'          => array(
							'type'    => 'integer',
							'default' => 1,
						),
						'orderby'        => array(
							'type'    => 'string',
							'default' => 'date',
						),
						'order'          => array(
							'type'    => 'string',
							'default' => 'DESC',
							'enum'    => array( 'ASC', 'DESC' ),
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
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'write_permissions_check' ),
					'args'                => array(
						'id'          => array(
							'type'     => 'integer',
							'required' => true,
						),
						'title'       => array( 'type' => 'string' ),
						'alt_text'    => array( 'type' => 'string' ),
						'caption'     => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'write_permissions_check' ),
					'args'                => array(
						'id'    => array(
							'type'     => 'integer',
							'required' => true,
						),
						'force' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/media/upload',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'upload_item' ),
					'permission_callback' => array( $this, 'write_permissions_check' ),
					'args'                => array(
						'title'    => array( 'type' => 'string' ),
						'alt_text' => array( 'type' => 'string' ),
						'caption'  => array( 'type' => 'string' ),
						'post_id'  => array( 'type' => 'integer' ),
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

	public function write_permissions_check( mixed $request ): bool|\WP_Error {
		if ( ! current_user_can( 'upload_files' ) ) {
			return ErrorResponse::forbidden_capability( 'upload_files' );
		}
		return true;
	}

	public function get_items( mixed $request ): WP_REST_Response {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => min( (int) $request->get_param( 'posts_per_page' ), 100 ),
			'paged'          => max( 1, (int) $request->get_param( 'paged' ) ),
			'orderby'        => sanitize_key( (string) $request->get_param( 'orderby' ) ),
			'order'          => sanitize_key( (string) $request->get_param( 'order' ) ),
		);

		$mime = $request->get_param( 'mime_type' );
		if ( null !== $mime ) {
			$args['post_mime_type'] = sanitize_text_field( (string) $mime );
		}

		$parent = $request->get_param( 'parent' );
		if ( null !== $parent ) {
			$args['post_parent'] = (int) $parent;
		}

		$query = new \WP_Query( $args );
		$items = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$items[] = $this->prepare_attachment_summary( $post );
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

		$data = $this->prepare_attachment_detail( $post );

		$response = new WP_REST_Response( $data, 200 );
		return $this->enrich_links(
			$response,
			array( 'self' => rest_url( "{$this->namespace}/media/{$post->ID}" ) )
		);
	}

	public function update_item( mixed $request ): WP_REST_Response|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
			return ErrorResponse::not_found( 'Media item not found.' );
		}

		$update = array( 'ID' => $id );

		$title = $request->get_param( 'title' );
		if ( null !== $title ) {
			$update['post_title'] = sanitize_text_field( (string) $title );
		}

		$caption = $request->get_param( 'caption' );
		if ( null !== $caption ) {
			$update['post_excerpt'] = sanitize_textarea_field( (string) $caption );
		}

		$description = $request->get_param( 'description' );
		if ( null !== $description ) {
			$update['post_content'] = wp_kses_post( (string) $description );
		}

		if ( count( $update ) > 1 ) {
			wp_update_post( $update );
		}

		$alt_text = $request->get_param( 'alt_text' );
		if ( null !== $alt_text ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( (string) $alt_text ) );
		}

		return $this->get_item( $request );
	}

	public function delete_item( mixed $request ): WP_REST_Response|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
			return ErrorResponse::not_found( 'Media item not found.' );
		}

		$force  = (bool) $request->get_param( 'force' );
		$result = wp_delete_attachment( $id, $force );

		if ( false === $result ) {
			return ErrorResponse::make( 'wpaic_delete_failed', 'Could not delete attachment.', 500 );
		}

		return new WP_REST_Response(
			array(
				'deleted' => true,
				'id'      => $id,
			),
			200
		);
	}

	public function upload_item( mixed $request ): WP_REST_Response|\WP_Error {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- REST API uses bearer token auth; nonce not applicable.
		if ( empty( $_FILES['file'] ) ) {
			return ErrorResponse::validation( 'No file provided. Send file as multipart/form-data field "file".', 'file' );
		}

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$upload = wp_handle_upload( $_FILES['file'], array( 'test_form' => false ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( isset( $upload['error'] ) ) {
			return ErrorResponse::make( 'wpaic_upload_failed', (string) $upload['error'], 400 );
		}

		$title = $request->get_param( 'title' );
		$file  = (string) ( $upload['file'] ?? '' );
		$url   = (string) ( $upload['url'] ?? '' );
		$type  = (string) ( $upload['type'] ?? '' );

		$attachment = array(
			'post_mime_type' => $type,
			'post_title'     => null !== $title
				? sanitize_text_field( (string) $title )
				: preg_replace( '/\.[^.]+$/', '', basename( $file ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$caption = $request->get_param( 'caption' );
		if ( null !== $caption ) {
			$attachment['post_excerpt'] = sanitize_textarea_field( (string) $caption );
		}

		$post_id = $request->get_param( 'post_id' );
		$att_id  = wp_insert_attachment( $attachment, $file, null !== $post_id ? (int) $post_id : 0, true );

		if ( is_wp_error( $att_id ) ) {
			return $att_id;
		}

		$metadata = wp_generate_attachment_metadata( (int) $att_id, $file );
		wp_update_attachment_metadata( (int) $att_id, $metadata );

		$alt_text = $request->get_param( 'alt_text' );
		if ( null !== $alt_text ) {
			update_post_meta( (int) $att_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $alt_text ) );
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return new WP_REST_Response(
			array(
				'id'  => (int) $att_id,
				'url' => $url,
			),
			201
		);
	}

	/** @return array<string, mixed> */
	private function prepare_attachment_summary( \WP_Post $post ): array {
		$meta      = wp_get_attachment_metadata( $post->ID );
		$is_image  = str_starts_with( $post->post_mime_type, 'image/' );
		$thumbnail = $is_image ? wp_get_attachment_image_src( $post->ID, 'thumbnail' ) : false;

		return array(
			'id'        => $post->ID,
			'title'     => $post->post_title,
			'mime_type' => $post->post_mime_type,
			'url'       => wp_get_attachment_url( $post->ID ),
			'date'      => $post->post_date,
			'alt_text'  => (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
			'width'     => $is_image && is_array( $meta ) ? (int) ( $meta['width'] ?? 0 ) : null,
			'height'    => $is_image && is_array( $meta ) ? (int) ( $meta['height'] ?? 0 ) : null,
			'thumbnail' => is_array( $thumbnail ) ? $thumbnail[0] : null,
			'_links'    => array(
				'self' => rest_url( "{$this->namespace}/media/{$post->ID}" ),
			),
		);
	}

	/** @return array<string, mixed> */
	private function prepare_attachment_detail( \WP_Post $post ): array {
		$meta     = wp_get_attachment_metadata( $post->ID );
		$is_image = str_starts_with( $post->post_mime_type, 'image/' );

		$sizes = array();
		if ( $is_image ) {
			foreach ( get_intermediate_image_sizes() as $size ) {
				$src = wp_get_attachment_image_src( $post->ID, $size );
				if ( is_array( $src ) ) {
					$sizes[ $size ] = array(
						'url'    => $src[0],
						'width'  => (int) $src[1],
						'height' => (int) $src[2],
					);
				}
			}
			// Always include full size.
			$full = wp_get_attachment_image_src( $post->ID, 'full' );
			if ( is_array( $full ) ) {
				$sizes['full'] = array(
					'url'    => $full[0],
					'width'  => (int) $full[1],
					'height' => (int) $full[2],
				);
			}
		}

		return array(
			'id'          => $post->ID,
			'title'       => $post->post_title,
			'caption'     => $post->post_excerpt,
			'description' => $post->post_content,
			'mime_type'   => $post->post_mime_type,
			'url'         => wp_get_attachment_url( $post->ID ),
			'date'        => $post->post_date,
			'alt_text'    => (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
			'width'       => $is_image && is_array( $meta ) ? (int) ( $meta['width'] ?? 0 ) : null,
			'height'      => $is_image && is_array( $meta ) ? (int) ( $meta['height'] ?? 0 ) : null,
			'file_size'   => is_array( $meta ) ? (int) ( $meta['filesize'] ?? 0 ) : null,
			'sizes'       => $sizes,
			'post_parent' => $post->post_parent,
			'_links'      => array(
				'self' => rest_url( "{$this->namespace}/media/{$post->ID}" ),
			),
		);
	}
}
