<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Yoast\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\Modules\Yoast\YoastDb;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

/**
 * GET  /yoast/posts/{id}  — full SEO data including schema, robots, cornerstone, reading time
 * POST /yoast/posts/{id}  — Update SEO fields (writes postmeta + fires wpseo_save_indexable)
 * GET  /yoast/terms/{id}  — SEO data for taxonomy terms
 * GET  /yoast/settings    — Site-wide Yoast settings (titles, social, person/company)
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
						'breadcrumb_title'    => array( 'type' => 'string' ),
						'is_cornerstone'      => array( 'type' => 'boolean' ),
						'noindex'             => array( 'type' => 'boolean' ),
						'nofollow'            => array( 'type' => 'boolean' ),
						'noarchive'           => array( 'type' => 'boolean' ),
						'noimageindex'        => array( 'type' => 'boolean' ),
						'nosnippet'           => array( 'type' => 'boolean' ),
						'og_title'            => array( 'type' => 'string' ),
						'og_description'      => array( 'type' => 'string' ),
						'og_image'            => array( 'type' => 'string' ),
						'twitter_title'       => array( 'type' => 'string' ),
						'twitter_description' => array( 'type' => 'string' ),
						'twitter_image'       => array( 'type' => 'string' ),
						'schema_page_type'    => array( 'type' => 'string' ),
						'schema_article_type' => array( 'type' => 'string' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/yoast/posts/(?P<id>\d+)/internal-links',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_post_links' ),
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

		register_rest_route(
			$this->namespace,
			'/yoast/health',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_health' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/yoast/cornerstone',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_cornerstone' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'limit' => array(
							'type'    => 'integer',
							'default' => 20,
						),
						'page'  => array(
							'type'    => 'integer',
							'default' => 1,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/yoast/needs-improvement',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_needs_improvement' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'threshold' => array(
							'type'    => 'integer',
							'default' => 40,
						),
						'limit'     => array(
							'type'    => 'integer',
							'default' => 20,
						),
						'page'      => array(
							'type'    => 'integer',
							'default' => 1,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/yoast/orphaned',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_orphaned' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'limit' => array(
							'type'    => 'integer',
							'default' => 20,
						),
						'page'  => array(
							'type'    => 'integer',
							'default' => 1,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/yoast/search-appearance',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_search_appearance' ),
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

	public function get_post_seo( mixed $request ): WP_REST_Response|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post ) {
			return ErrorResponse::not_found( 'Post not found.' );
		}

		$indexable = YoastDb::get_indexable( $id, 'post' );

		if ( null !== $indexable ) {
			$data           = $this->format_indexable( $indexable );
			$data['source'] = 'indexable';
		} else {
			$data           = $this->read_postmeta( $id );
			$data['source'] = 'postmeta';
		}

		$data['object_id']     = $id;
		$data['object_type']   = 'post';
		$data['post_type']     = $post->post_type;
		$data['primary_terms'] = YoastDb::get_primary_terms( $id );

		return new WP_REST_Response( $data, 200 );
	}

	public function update_post_seo( mixed $request ): WP_REST_Response|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post ) {
			return ErrorResponse::not_found( 'Post not found.' );
		}

		// Map text fields to _yoast_wpseo_* postmeta keys.
		$text_map = array(
			'seo_title'           => '_yoast_wpseo_title',
			'meta_description'    => '_yoast_wpseo_metadesc',
			'focus_keyphrase'     => '_yoast_wpseo_focuskw',
			'canonical'           => '_yoast_wpseo_canonical',
			'breadcrumb_title'    => '_yoast_wpseo_bctitle',
			'og_title'            => '_yoast_wpseo_opengraph-title',
			'og_description'      => '_yoast_wpseo_opengraph-description',
			'og_image'            => '_yoast_wpseo_opengraph-image',
			'twitter_title'       => '_yoast_wpseo_twitter-title',
			'twitter_description' => '_yoast_wpseo_twitter-description',
			'twitter_image'       => '_yoast_wpseo_twitter-image',
			'schema_page_type'    => '_yoast_wpseo_schema_page_type',
			'schema_article_type' => '_yoast_wpseo_schema_article_type',
		);

		foreach ( $text_map as $param => $meta_key ) {
			$value = $request->get_param( $param );
			if ( null !== $value ) {
				update_post_meta( $id, $meta_key, sanitize_text_field( (string) $value ) );
			}
		}

		// Robots flags stored as '1' / '0' strings or yes/no advanced.
		$robots_map = array(
			'noindex'      => '_yoast_wpseo_meta-robots-noindex',
			'nofollow'     => '_yoast_wpseo_meta-robots-nofollow',
			'noarchive'    => '_yoast_wpseo_meta-robots-adv',
			'noimageindex' => '_yoast_wpseo_meta-robots-adv',
			'nosnippet'    => '_yoast_wpseo_meta-robots-adv',
		);

		$noindex = $request->get_param( 'noindex' );
		if ( null !== $noindex ) {
			update_post_meta( $id, '_yoast_wpseo_meta-robots-noindex', (bool) $noindex ? '1' : '0' );
		}

		$nofollow = $request->get_param( 'nofollow' );
		if ( null !== $nofollow ) {
			update_post_meta( $id, '_yoast_wpseo_meta-robots-nofollow', (bool) $nofollow ? '1' : '0' );
		}

		// Advanced robots (noarchive/noimageindex/nosnippet) stored comma-separated in one meta.
		$adv = array();
		foreach ( array( 'noarchive', 'noimageindex', 'nosnippet' ) as $flag ) {
			$val = $request->get_param( $flag );
			if ( null !== $val && (bool) $val ) {
				$adv[] = $flag;
			}
		}
		if ( array() !== $adv ) {
			update_post_meta( $id, '_yoast_wpseo_meta-robots-adv', implode( ',', $adv ) );
		}

		// Cornerstone is a boolean stored as '1' meta if true, deleted if false.
		$cornerstone = $request->get_param( 'is_cornerstone' );
		if ( null !== $cornerstone ) {
			if ( (bool) $cornerstone ) {
				update_post_meta( $id, '_yoast_wpseo_is_cornerstone', '1' );
			} else {
				delete_post_meta( $id, '_yoast_wpseo_is_cornerstone' );
			}
		}

		// Trigger Yoast to re-sync the indexable table from postmeta.
		if ( function_exists( 'YoastSEO' ) ) {
			do_action( 'wpseo_save_indexable', $id, 'post' );
		}

		return $this->get_post_seo( $request );
	}

	public function get_post_links( mixed $request ): WP_REST_Response|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post ) {
			return ErrorResponse::not_found( 'Post not found.' );
		}

		return new WP_REST_Response(
			array(
				'post_id'  => $id,
				'outgoing' => YoastDb::get_outgoing_links( $id ),
				'incoming' => YoastDb::get_incoming_links( $id ),
			),
			200
		);
	}

	public function get_term_seo( mixed $request ): WP_REST_Response|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$term = get_term( $id );

		if ( ! $term instanceof \WP_Term ) {
			return ErrorResponse::not_found( 'Term not found.' );
		}

		$indexable = YoastDb::get_indexable( $id, 'term' );

		if ( null !== $indexable ) {
			$data           = $this->format_indexable( $indexable );
			$data['source'] = 'indexable';
		} else {
			$data           = $this->read_term_meta( $term );
			$data['source'] = 'taxonomy_meta';
		}

		$data['object_id']   = $id;
		$data['object_type'] = 'term';
		$data['taxonomy']    = $term->taxonomy;

		return new WP_REST_Response( $data, 200 );
	}

	public function get_settings( mixed $request ): WP_REST_Response {
		$wpseo        = (array) get_option( 'wpseo', array() );
		$wpseo_social = (array) get_option( 'wpseo_social', array() );
		$wpseo_titles = (array) get_option( 'wpseo_titles', array() );

		return new WP_REST_Response(
			array(
				'company_or_person'   => (string) ( $wpseo_titles['company_or_person'] ?? 'company' ),
				'company_name'        => (string) ( $wpseo_titles['company_name'] ?? '' ),
				'company_alt_name'    => (string) ( $wpseo_titles['company_alternate_name'] ?? '' ),
				'company_logo'        => (string) ( $wpseo_titles['company_logo'] ?? '' ),
				'person_name'         => (string) ( $wpseo_titles['person_name'] ?? '' ),
				'person_logo'         => (string) ( $wpseo_titles['person_logo'] ?? '' ),
				'separator'           => (string) ( $wpseo_titles['separator'] ?? '-' ),
				'breadcrumbs_enabled' => (bool) ( $wpseo_titles['breadcrumbs-enable'] ?? false ),
				'breadcrumbs_sep'     => (string) ( $wpseo_titles['breadcrumbs-sep'] ?? '»' ),
				'breadcrumbs_home'    => (string) ( $wpseo_titles['breadcrumbs-home'] ?? '' ),
				'og_default_image'    => (string) ( $wpseo_social['og_default_image'] ?? '' ),
				'facebook_site'       => (string) ( $wpseo_social['facebook_site'] ?? '' ),
				'twitter_site'        => (string) ( $wpseo_social['twitter_site'] ?? '' ),
				'twitter_card_type'   => (string) ( $wpseo_social['twitter_card_type'] ?? 'summary_large_image' ),
				'linkedin_url'        => (string) ( $wpseo_social['linkedin_url'] ?? '' ),
				'instagram_url'       => (string) ( $wpseo_social['instagram_url'] ?? '' ),
				'youtube_url'         => (string) ( $wpseo_social['youtube_url'] ?? '' ),
				'pinterest_url'       => (string) ( $wpseo_social['pinterest_url'] ?? '' ),
				'rss_footer'          => (string) ( $wpseo['rssbefore'] ?? '' ),
				'enable_xml_sitemap'  => (bool) ( $wpseo['enable_xml_sitemap'] ?? true ),
			),
			200
		);
	}

	public function get_health( mixed $request ): WP_REST_Response {
		$summary = YoastDb::get_health_summary();

		return new WP_REST_Response(
			array(
				'total_indexables'         => (int) ( $summary['total_indexables'] ?? 0 ),
				'cornerstone_count'        => (int) ( $summary['cornerstone_count'] ?? 0 ),
				'noindexed_count'          => (int) ( $summary['noindexed_count'] ?? 0 ),
				'missing_focus_keyphrase'  => (int) ( $summary['missing_focus_keyphrase'] ?? 0 ),
				'missing_seo_title'        => (int) ( $summary['missing_seo_title'] ?? 0 ),
				'missing_meta_description' => (int) ( $summary['missing_meta_description'] ?? 0 ),
				'avg_seo_score'            => null !== $summary['avg_seo_score'] ? round( (float) $summary['avg_seo_score'], 1 ) : null,
				'avg_readability_score'    => null !== $summary['avg_readability_score'] ? round( (float) $summary['avg_readability_score'], 1 ) : null,
			),
			200
		);
	}

	public function get_cornerstone( mixed $request ): WP_REST_Response {
		$limit = min( (int) $request->get_param( 'limit' ), 100 );
		$page  = max( 1, (int) $request->get_param( 'page' ) );

		$rows = YoastDb::get_cornerstone_posts( $limit, $page );

		return new WP_REST_Response( $rows, 200 );
	}

	public function get_needs_improvement( mixed $request ): WP_REST_Response {
		$threshold = max( 0, min( 100, (int) $request->get_param( 'threshold' ) ) );
		$limit     = min( (int) $request->get_param( 'limit' ), 100 );
		$page      = max( 1, (int) $request->get_param( 'page' ) );

		$rows = YoastDb::get_needs_improvement( $threshold, $limit, $page );

		return new WP_REST_Response(
			array(
				'threshold' => $threshold,
				'items'     => $rows,
			),
			200
		);
	}

	public function get_orphaned( mixed $request ): WP_REST_Response {
		$limit = min( (int) $request->get_param( 'limit' ), 100 );
		$page  = max( 1, (int) $request->get_param( 'page' ) );

		$rows = YoastDb::get_orphaned_posts( $limit, $page );

		return new WP_REST_Response( $rows, 200 );
	}

	/** Search appearance: per-post-type and per-taxonomy title/desc templates, noindex defaults. */
	public function get_search_appearance( mixed $request ): WP_REST_Response {
		$titles = (array) get_option( 'wpseo_titles', array() );

		$post_types = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			$post_types[ $pt->name ] = array(
				'label'          => $pt->label,
				'title_template' => (string) ( $titles[ "title-{$pt->name}" ] ?? '' ),
				'metadesc'       => (string) ( $titles[ "metadesc-{$pt->name}" ] ?? '' ),
				'noindex'        => (bool) ( $titles[ "noindex-{$pt->name}" ] ?? false ),
				'schema_page'    => (string) ( $titles[ "schema-page-type-{$pt->name}" ] ?? '' ),
				'schema_article' => (string) ( $titles[ "schema-article-type-{$pt->name}" ] ?? '' ),
				'show_in_search' => ! (bool) ( $titles[ "noindex-{$pt->name}" ] ?? false ),
			);
		}

		$taxonomies = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
			$taxonomies[ $tax->name ] = array(
				'label'          => $tax->label,
				'title_template' => (string) ( $titles[ "title-tax-{$tax->name}" ] ?? '' ),
				'metadesc'       => (string) ( $titles[ "metadesc-tax-{$tax->name}" ] ?? '' ),
				'noindex'        => (bool) ( $titles[ "noindex-tax-{$tax->name}" ] ?? false ),
			);
		}

		return new WP_REST_Response(
			array(
				'separator'  => (string) ( $titles['separator'] ?? '-' ),
				'home'       => array(
					'title'    => (string) ( $titles['title-home-wpseo'] ?? '' ),
					'metadesc' => (string) ( $titles['metadesc-home-wpseo'] ?? '' ),
				),
				'author'     => array(
					'title'           => (string) ( $titles['title-author-wpseo'] ?? '' ),
					'metadesc'        => (string) ( $titles['metadesc-author-wpseo'] ?? '' ),
					'noindex'         => (bool) ( $titles['noindex-author-wpseo'] ?? false ),
					'noposts_noindex' => (bool) ( $titles['noindex-author-noposts-wpseo'] ?? true ),
				),
				'archive'    => array(
					'title'    => (string) ( $titles['title-archive-wpseo'] ?? '' ),
					'metadesc' => (string) ( $titles['metadesc-archive-wpseo'] ?? '' ),
					'noindex'  => (bool) ( $titles['noindex-archive-wpseo'] ?? true ),
				),
				'search'     => array( 'title' => (string) ( $titles['title-search-wpseo'] ?? '' ) ),
				'not_found'  => array( 'title' => (string) ( $titles['title-404-wpseo'] ?? '' ) ),
				'post_types' => $post_types,
				'taxonomies' => $taxonomies,
			),
			200
		);
	}

	/**
	 * Map a raw indexable row to our API response shape — exposes ALL columns.
	 *
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function format_indexable( array $row ): array {
		$adv = (string) ( $row['robots_adv'] ?? '' );

		return array(
			// Core SEO.
			'seo_title'                => (string) ( $row['title'] ?? '' ),
			'meta_description'         => (string) ( $row['description'] ?? '' ),
			'focus_keyphrase'          => (string) ( $row['primary_focus_keyword'] ?? '' ),
			'canonical'                => (string) ( $row['canonical'] ?? '' ),
			'permalink'                => (string) ( $row['permalink'] ?? '' ),
			'breadcrumb_title'         => (string) ( $row['breadcrumb_title'] ?? '' ),

			// Robots.
			'noindex'                  => '1' === (string) ( $row['is_robots_noindex'] ?? '0' ),
			'nofollow'                 => '1' === (string) ( $row['is_robots_nofollow'] ?? '0' ),
			'noarchive'                => '1' === (string) ( $row['is_robots_noarchive'] ?? '0' ),
			'noimageindex'             => '1' === (string) ( $row['is_robots_noimageindex'] ?? '0' ),
			'nosnippet'                => '1' === (string) ( $row['is_robots_nosnippet'] ?? '0' ),

			// Analysis.
			'seo_score'                => null !== $row['primary_focus_keyword_score'] ? (int) $row['primary_focus_keyword_score'] : null,
			'readability_score'        => null !== $row['readability_score'] ? (int) $row['readability_score'] : null,
			'inclusive_language_score' => null !== ( $row['inclusive_language_score'] ?? null ) ? (int) $row['inclusive_language_score'] : null,
			'is_cornerstone'           => '1' === (string) ( $row['is_cornerstone'] ?? '0' ),
			'estimated_reading_time'   => null !== ( $row['estimated_reading_time_minutes'] ?? null ) ? (int) $row['estimated_reading_time_minutes'] : null,

			// Open Graph.
			'og_title'                 => (string) ( $row['open_graph_title'] ?? '' ),
			'og_description'           => (string) ( $row['open_graph_description'] ?? '' ),
			'og_image'                 => (string) ( $row['open_graph_image'] ?? '' ),
			'og_image_id'              => (string) ( $row['open_graph_image_id'] ?? '' ),
			'og_image_source'          => (string) ( $row['open_graph_image_source'] ?? '' ),

			// Twitter.
			'twitter_title'            => (string) ( $row['twitter_title'] ?? '' ),
			'twitter_description'      => (string) ( $row['twitter_description'] ?? '' ),
			'twitter_image'            => (string) ( $row['twitter_image'] ?? '' ),
			'twitter_image_id'         => (string) ( $row['twitter_image_id'] ?? '' ),
			'twitter_image_source'     => (string) ( $row['twitter_image_source'] ?? '' ),

			// Schema.
			'schema_page_type'         => (string) ( $row['schema_page_type'] ?? '' ),
			'schema_article_type'      => (string) ( $row['schema_article_type'] ?? '' ),

			// Links.
			'link_count'               => null !== $row['link_count'] ? (int) $row['link_count'] : 0,
			'incoming_link_count'      => null !== $row['incoming_link_count'] ? (int) $row['incoming_link_count'] : 0,

			// Status.
			'post_status'              => (string) ( $row['post_status'] ?? '' ),
			'is_public'                => null !== $row['is_public'] ? (bool) $row['is_public'] : null,
			'is_protected'             => (bool) ( $row['is_protected'] ?? false ),
		);
	}

	/**
	 * Fallback: read Yoast SEO data from postmeta (_yoast_wpseo_* keys).
	 *
	 * @return array<string, mixed>
	 */
	private function read_postmeta( int $post_id ): array {
		$get = static fn ( string $key ) => (string) get_post_meta( $post_id, $key, true );
		$adv = $get( '_yoast_wpseo_meta-robots-adv' );

		return array(
			'seo_title'                => $get( '_yoast_wpseo_title' ),
			'meta_description'         => $get( '_yoast_wpseo_metadesc' ),
			'focus_keyphrase'          => $get( '_yoast_wpseo_focuskw' ),
			'canonical'                => $get( '_yoast_wpseo_canonical' ),
			'breadcrumb_title'         => $get( '_yoast_wpseo_bctitle' ),
			'noindex'                  => '1' === $get( '_yoast_wpseo_meta-robots-noindex' ),
			'nofollow'                 => '1' === $get( '_yoast_wpseo_meta-robots-nofollow' ),
			'noarchive'                => str_contains( $adv, 'noarchive' ),
			'noimageindex'             => str_contains( $adv, 'noimageindex' ),
			'nosnippet'                => str_contains( $adv, 'nosnippet' ),
			'seo_score'                => null,
			'readability_score'        => null,
			'inclusive_language_score' => null,
			'is_cornerstone'           => '1' === $get( '_yoast_wpseo_is_cornerstone' ),
			'estimated_reading_time'   => null,
			'og_title'                 => $get( '_yoast_wpseo_opengraph-title' ),
			'og_description'           => $get( '_yoast_wpseo_opengraph-description' ),
			'og_image'                 => $get( '_yoast_wpseo_opengraph-image' ),
			'og_image_id'              => $get( '_yoast_wpseo_opengraph-image-id' ),
			'og_image_source'          => '',
			'twitter_title'            => $get( '_yoast_wpseo_twitter-title' ),
			'twitter_description'      => $get( '_yoast_wpseo_twitter-description' ),
			'twitter_image'            => $get( '_yoast_wpseo_twitter-image' ),
			'twitter_image_id'         => $get( '_yoast_wpseo_twitter-image-id' ),
			'twitter_image_source'     => '',
			'schema_page_type'         => $get( '_yoast_wpseo_schema_page_type' ),
			'schema_article_type'      => $get( '_yoast_wpseo_schema_article_type' ),
			'permalink'                => (string) get_permalink( $post_id ),
			'link_count'               => 0,
			'incoming_link_count'      => 0,
			'post_status'              => (string) get_post_status( $post_id ),
			'is_public'                => null,
			'is_protected'             => false,
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
			'breadcrumb_title'    => $get( 'wpseo_bctitle' ),
			'noindex'             => '1' === $get( 'wpseo_noindex' ),
			'nofollow'            => false,
			'noarchive'           => false,
			'noimageindex'        => false,
			'nosnippet'           => false,
			'seo_score'           => null,
			'readability_score'   => null,
			'is_cornerstone'      => false,
			'og_title'            => $get( 'wpseo_opengraph-title' ),
			'og_description'      => $get( 'wpseo_opengraph-description' ),
			'og_image'            => $get( 'wpseo_opengraph-image' ),
			'og_image_id'         => $get( 'wpseo_opengraph-image-id' ),
			'twitter_title'       => $get( 'wpseo_twitter-title' ),
			'twitter_description' => $get( 'wpseo_twitter-description' ),
			'twitter_image'       => $get( 'wpseo_twitter-image' ),
			'twitter_image_id'    => $get( 'wpseo_twitter-image-id' ),
			'schema_page_type'    => '',
			'schema_article_type' => '',
			'permalink'           => (string) get_term_link( $term->term_id ),
			'link_count'          => 0,
			'incoming_link_count' => 0,
		);
	}
}
