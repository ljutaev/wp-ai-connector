<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Yoast\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

/**
 * GET  /yoast/posts/{id}     — SEO metadata for a post (from wp_yoast_indexable + postmeta fallback)
 * POST /yoast/posts/{id}     — Update SEO title, description, robots, focus keyphrase
 * GET  /yoast/terms/{id}     — SEO metadata for a taxonomy term
 * GET  /yoast/settings        — Site-wide Yoast SEO settings
 */
final class SeoController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/yoast/posts/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_post_seo' ),
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
					'callback'            => array( $this, 'update_post_seo' ),
					'permission_callback' => array( $this, 'write_permissions_check' ),
					'args'                => array(
						'id'                  => array(
							'type'     => 'integer',
							'required' => true,
						),
						'seo_title'           => array( 'type' => 'string' ),
						'meta_description'    => array( 'type' => 'string' ),
						'focus_keyphrase'     => array( 'type' => 'string' ),
						'canonical'           => array( 'type' => 'string' ),
						'noindex'             => array( 'type' => 'boolean' ),
						'nofollow'            => array( 'type' => 'boolean' ),
						'og_title'            => array( 'type' => 'string' ),
						'og_description'      => array( 'type' => 'string' ),
						'twitter_title'       => array( 'type' => 'string' ),
						'twitter_description' => array( 'type' => 'string' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/yoast/terms/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_term_seo' ),
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
			'/yoast/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
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

	/** Read SEO data for a post — tries wp_yoast_indexable first, falls back to postmeta. */
	public function get_post_seo( mixed $request ): WP_REST_Response|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post ) {
			return ErrorResponse::not_found( 'Post not found.' );
		}

		$indexable = $this->get_indexable( $id, 'post' );

		if ( null !== $indexable ) {
			$data = $this->format_indexable( $indexable );
		} else {
			$data = $this->read_postmeta( $id );
		}

		$data['object_id']   = $id;
		$data['object_type'] = 'post';
		$data['post_type']   = $post->post_type;

		return new WP_REST_Response( $data, 200 );
	}

	/** Update SEO data for a post via postmeta (_yoast_wpseo_*). */
	public function update_post_seo( mixed $request ): WP_REST_Response|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post ) {
			return ErrorResponse::not_found( 'Post not found.' );
		}

		$map = array(
			'seo_title'           => '_yoast_wpseo_title',
			'meta_description'    => '_yoast_wpseo_metadesc',
			'focus_keyphrase'     => '_yoast_wpseo_focuskw',
			'canonical'           => '_yoast_wpseo_canonical',
			'og_title'            => '_yoast_wpseo_opengraph-title',
			'og_description'      => '_yoast_wpseo_opengraph-description',
			'twitter_title'       => '_yoast_wpseo_twitter-title',
			'twitter_description' => '_yoast_wpseo_twitter-description',
		);

		foreach ( $map as $param => $meta_key ) {
			$value = $request->get_param( $param );
			if ( null !== $value ) {
				update_post_meta( $id, $meta_key, sanitize_text_field( (string) $value ) );
			}
		}

		// noindex / nofollow stored as 'noindex' / 'nofollow' meta.
		$noindex = $request->get_param( 'noindex' );
		if ( null !== $noindex ) {
			update_post_meta( $id, '_yoast_wpseo_meta-robots-noindex', (bool) $noindex ? '1' : '0' );
		}

		$nofollow = $request->get_param( 'nofollow' );
		if ( null !== $nofollow ) {
			update_post_meta( $id, '_yoast_wpseo_meta-robots-nofollow', (bool) $nofollow ? '1' : '0' );
		}

		// Invalidate Yoast indexable cache for this post so it re-syncs.
		if ( function_exists( 'YoastSEO' ) ) {
			do_action( 'wpseo_save_indexable', $id, 'post' );
		}

		return $this->get_post_seo( $request );
	}

	/** Read SEO data for a taxonomy term. */
	public function get_term_seo( mixed $request ): WP_REST_Response|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$term = get_term( $id );

		if ( ! $term instanceof \WP_Term ) {
			return ErrorResponse::not_found( 'Term not found.' );
		}

		$indexable = $this->get_indexable( $id, 'term' );

		if ( null !== $indexable ) {
			$data = $this->format_indexable( $indexable );
		} else {
			// Yoast term meta is stored as wpseo_taxonomy_meta option.
			$data = $this->read_term_meta( $term );
		}

		$data['object_id']   = $id;
		$data['object_type'] = 'term';
		$data['taxonomy']    = $term->taxonomy;

		return new WP_REST_Response( $data, 200 );
	}

	/** Site-wide Yoast SEO settings (company/person, social profiles, etc.). */
	public function get_settings( mixed $request ): WP_REST_Response {
		$wpseo        = (array) get_option( 'wpseo', array() );
		$wpseo_social = (array) get_option( 'wpseo_social', array() );
		$wpseo_titles = (array) get_option( 'wpseo_titles', array() );

		return new WP_REST_Response(
			array(
				'company_or_person'   => (string) ( $wpseo['company_or_person'] ?? '' ),
				'company_name'        => (string) ( $wpseo['company_name'] ?? '' ),
				'separator_character' => (string) ( $wpseo_titles['separator'] ?? '-' ),
				'title_template'      => (string) ( $wpseo_titles['title-home-wpseo'] ?? '' ),
				'og_default_image'    => (string) ( $wpseo_social['og_default_image'] ?? '' ),
				'facebook_site'       => (string) ( $wpseo_social['facebook_site'] ?? '' ),
				'twitter_site'        => (string) ( $wpseo_social['twitter_site'] ?? '' ),
				'linkedin_url'        => (string) ( $wpseo_social['linkedin_url'] ?? '' ),
			),
			200
		);
	}

	/**
	 * Try reading from wp_yoast_indexable table (Yoast 14+).
	 *
	 * @return array<string, mixed>|null
	 */
	private function get_indexable( int $object_id, string $object_type ): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'yoast_indexable';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE object_id = %d AND object_type = %s LIMIT 1",
				$object_id,
				$object_type
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Format an indexable table row into our API shape.
	 *
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function format_indexable( array $row ): array {
		return array(
			'seo_title'           => (string) ( $row['title'] ?? '' ),
			'meta_description'    => (string) ( $row['description'] ?? '' ),
			'focus_keyphrase'     => (string) ( $row['primary_focus_keyword'] ?? '' ),
			'canonical'           => (string) ( $row['canonical'] ?? '' ),
			'noindex'             => '1' === (string) ( $row['is_robots_noindex'] ?? '0' ),
			'nofollow'            => '1' === (string) ( $row['is_robots_nofollow'] ?? '0' ),
			'og_title'            => (string) ( $row['open_graph_title'] ?? '' ),
			'og_description'      => (string) ( $row['open_graph_description'] ?? '' ),
			'og_image_url'        => (string) ( $row['open_graph_image'] ?? '' ),
			'twitter_title'       => (string) ( $row['twitter_title'] ?? '' ),
			'twitter_description' => (string) ( $row['twitter_description'] ?? '' ),
			'readability_score'   => null !== $row['readability_score'] ? (int) $row['readability_score'] : null,
			'seo_score'           => null !== $row['primary_focus_keyword_score'] ? (int) $row['primary_focus_keyword_score'] : null,
			'source'              => 'indexable',
		);
	}

	/**
	 * Fallback: read Yoast SEO data from postmeta (_yoast_wpseo_* keys).
	 *
	 * @return array<string, mixed>
	 */
	private function read_postmeta( int $post_id ): array {
		$get = static fn ( string $key ) => (string) get_post_meta( $post_id, $key, true );

		return array(
			'seo_title'           => $get( '_yoast_wpseo_title' ),
			'meta_description'    => $get( '_yoast_wpseo_metadesc' ),
			'focus_keyphrase'     => $get( '_yoast_wpseo_focuskw' ),
			'canonical'           => $get( '_yoast_wpseo_canonical' ),
			'noindex'             => '1' === $get( '_yoast_wpseo_meta-robots-noindex' ),
			'nofollow'            => '1' === $get( '_yoast_wpseo_meta-robots-nofollow' ),
			'og_title'            => $get( '_yoast_wpseo_opengraph-title' ),
			'og_description'      => $get( '_yoast_wpseo_opengraph-description' ),
			'og_image_url'        => $get( '_yoast_wpseo_opengraph-image' ),
			'twitter_title'       => $get( '_yoast_wpseo_twitter-title' ),
			'twitter_description' => $get( '_yoast_wpseo_twitter-description' ),
			'readability_score'   => null,
			'seo_score'           => null,
			'source'              => 'postmeta',
		);
	}

	/**
	 * Read Yoast term SEO from wpseo_taxonomy_meta option.
	 *
	 * @return array<string, mixed>
	 */
	private function read_term_meta( \WP_Term $term ): array {
		$all  = (array) get_option( 'wpseo_taxonomy_meta', array() );
		$meta = (array) ( $all[ $term->taxonomy ][ $term->term_id ] ?? array() );
		$get  = static fn ( string $key ) => (string) ( $meta[ $key ] ?? '' );

		return array(
			'seo_title'           => $get( 'wpseo_title' ),
			'meta_description'    => $get( 'wpseo_desc' ),
			'focus_keyphrase'     => $get( 'wpseo_focuskw' ),
			'canonical'           => $get( 'wpseo_canonical' ),
			'noindex'             => '1' === $get( 'wpseo_noindex' ),
			'nofollow'            => false,
			'og_title'            => $get( 'wpseo_opengraph-title' ),
			'og_description'      => $get( 'wpseo_opengraph-description' ),
			'og_image_url'        => $get( 'wpseo_opengraph-image' ),
			'twitter_title'       => $get( 'wpseo_twitter-title' ),
			'twitter_description' => $get( 'wpseo_twitter-description' ),
			'readability_score'   => null,
			'seo_score'           => null,
			'source'              => 'taxonomy_meta',
		);
	}
}
