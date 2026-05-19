<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Core\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

final class CommentsController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/comments',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'status'  => array(
							'type'    => 'string',
							'default' => 'approve',
						),
						'post_id' => array( 'type' => 'integer' ),
						'number'  => array(
							'type'    => 'integer',
							'default' => 20,
						),
						'paged'   => array(
							'type'    => 'integer',
							'default' => 1,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/comments/(?P<id>\d+)',
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
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'id'               => array(
							'type'     => 'integer',
							'required' => true,
						),
						'comment_approved' => array( 'type' => 'string' ),
						'comment_content'  => array( 'type' => 'string' ),
					),
				),
			)
		);
	}

	public function permissions_check( mixed $request ): bool|\WP_Error {
		if ( ! current_user_can( 'moderate_comments' ) ) {
			return ErrorResponse::forbidden_capability( 'moderate_comments' );
		}

		return true;
	}

	public function get_items( mixed $request ): WP_REST_Response {
		$args = array(
			'status' => sanitize_key( (string) $request->get_param( 'status' ) ),
			'number' => (int) $request->get_param( 'number' ),
			'paged'  => (int) $request->get_param( 'paged' ),
		);

		$post_id = $request->get_param( 'post_id' );
		if ( null !== $post_id ) {
			$args['post_id'] = (int) $post_id;
		}

		$comments = get_comments( $args );
		$items    = array();

		if ( ! is_array( $comments ) ) {
			return new WP_REST_Response( $items, 200 );
		}

		foreach ( $comments as $comment ) {
			if ( ! $comment instanceof \WP_Comment ) {
				continue;
			}
			$items[] = $this->prepare_comment( $comment );
		}

		return new WP_REST_Response( $items, 200 );
	}

	public function get_item( mixed $request ): WP_REST_Response|\WP_Error {
		$comment = get_comment( (int) $request->get_param( 'id' ) );

		if ( ! $comment instanceof \WP_Comment ) {
			return ErrorResponse::not_found( 'Comment not found.' );
		}

		$response = new WP_REST_Response( $this->prepare_comment( $comment ), 200 );

		return $this->enrich_links(
			$response,
			array( 'self' => rest_url( "{$this->namespace}/comments/{$comment->comment_ID}" ) )
		);
	}

	public function update_item( mixed $request ): WP_REST_Response|\WP_Error {
		$id      = (int) $request->get_param( 'id' );
		$comment = get_comment( $id );

		if ( ! $comment instanceof \WP_Comment ) {
			return ErrorResponse::not_found( 'Comment not found.' );
		}

		$data = array( 'comment_ID' => $id );

		$approved = $request->get_param( 'comment_approved' );
		if ( null !== $approved ) {
			$data['comment_approved'] = sanitize_key( (string) $approved );
		}

		$content = $request->get_param( 'comment_content' );
		if ( null !== $content ) {
			$data['comment_content'] = sanitize_textarea_field( (string) $content );
		}

		$result = wp_update_comment( $data, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->get_item( $request );
	}

	/** @return array<string, mixed> */
	private function prepare_comment( \WP_Comment $comment ): array {
		return array(
			'comment_ID'       => (int) $comment->comment_ID,
			'comment_post_ID'  => (int) $comment->comment_post_ID,
			'comment_author'   => $comment->comment_author,
			'comment_date'     => $comment->comment_date,
			'comment_content'  => $comment->comment_content,
			'comment_approved' => $comment->comment_approved,
			'comment_type'     => $comment->comment_type,
			'_links'           => array(
				'self' => rest_url( "{$this->namespace}/comments/{$comment->comment_ID}" ),
			),
		);
	}
}
