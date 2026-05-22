<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Yoast;

use WPAIConnector\Core\Container;
use WPAIConnector\Modules\AbstractModule;
use WPAIConnector\Modules\Yoast\Conditionals\YoastConditional;
use WPAIConnector\Modules\Yoast\Controllers\SeoController;

final class YoastModule extends AbstractModule {

	public function name(): string {
		return 'yoast-seo';
	}

	public function version(): string {
		return '0.2.0';
	}

	/** @return array<int, \WPAIConnector\Modules\ConditionalInterface> */
	public function conditionals(): array {
		return array(
			new YoastConditional(),
		);
	}

	public function register( Container $container ): void {
		$container->set( SeoController::class, static fn () => new SeoController() );

		add_action(
			'rest_api_init',
			static function () use ( $container ): void {
				$container->get( SeoController::class )->register_routes();
			}
		);
	}

	/** @return array<string, mixed> */
	public function manifest(): array {
		$version = defined( 'WPSEO_VERSION' ) ? WPSEO_VERSION : 'unknown';

		return array(
			'name'        => 'yoast-seo',
			'version'     => $this->version(),
			'detected'    => true,
			'host_plugin' => array(
				'name'    => 'Yoast SEO',
				'version' => $version,
			),
			'routes'      => array(

				// ── Per-post SEO ─────────────────────────────────────────────
				array(
					'method'      => 'GET',
					'path'        => '/yoast/posts/{id}',
					'scope'       => 'yoast:read',
					'description' => 'Full SEO data for a post — reads wp_yoast_indexable (40+ fields) with postmeta fallback.',
					'ai_hint'     => 'Returns seo_title, meta_description, focus_keyphrase, canonical, breadcrumb_title, all robots flags, OG/Twitter fields, schema_page_type, schema_article_type, is_cornerstone, seo_score, readability_score, estimated_reading_time, link_count, incoming_link_count, primary_terms. source=indexable when fresh; source=postmeta when indexable table missing.',
				),
				array(
					'method'      => 'POST',
					'path'        => '/yoast/posts/{id}',
					'scope'       => 'yoast:write',
					'description' => 'Update Yoast SEO fields. Writes to _yoast_wpseo_* postmeta and fires wpseo_save_indexable hook.',
					'parameters'  => array(
						array(
							'name' => 'seo_title',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'meta_description',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'focus_keyphrase',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'canonical',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'is_cornerstone',
							'in'   => 'body',
							'type' => 'boolean',
						),
						array(
							'name' => 'noindex',
							'in'   => 'body',
							'type' => 'boolean',
						),
						array(
							'name' => 'nofollow',
							'in'   => 'body',
							'type' => 'boolean',
						),
						array(
							'name' => 'noarchive',
							'in'   => 'body',
							'type' => 'boolean',
						),
						array(
							'name' => 'schema_page_type',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'schema_article_type',
							'in'   => 'body',
							'type' => 'string',
						),
					),
					'ai_hint'     => 'To hide a post from search engines: POST noindex=true. To mark high-value content: POST is_cornerstone=true. Yoast templates like %%title%% %%sep%% %%sitename%% are supported in seo_title.',
				),
				array(
					'method'      => 'GET',
					'path'        => '/yoast/posts/{id}/internal-links',
					'scope'       => 'yoast:read',
					'description' => 'Outgoing and incoming internal links for a post (from wp_yoast_seo_links graph).',
					'ai_hint'     => 'Returns { outgoing: [...], incoming: [...] }. Each link has url, type, source/target title and permalink.',
				),

				// ── Per-term SEO ─────────────────────────────────────────────
				array(
					'method'      => 'GET',
					'path'        => '/yoast/terms/{id}',
					'scope'       => 'yoast:read',
					'description' => 'SEO data for a taxonomy term (category, tag, custom taxonomy).',
				),

				// ── Site-wide ────────────────────────────────────────────────
				array(
					'method'      => 'GET',
					'path'        => '/yoast/settings',
					'scope'       => 'yoast:read',
					'description' => 'Site-wide Yoast settings: company/person entity, social profiles, separator, breadcrumbs config.',
				),
				array(
					'method'      => 'GET',
					'path'        => '/yoast/search-appearance',
					'scope'       => 'yoast:read',
					'description' => 'Title templates, meta description templates, and noindex defaults per post type and taxonomy.',
					'ai_hint'     => 'Returns templates with %%title%% / %%sitename%% placeholders. post_types and taxonomies maps each show_in_search flag.',
				),

				// ── Audit / health ───────────────────────────────────────────
				array(
					'method'      => 'GET',
					'path'        => '/yoast/health',
					'scope'       => 'yoast:read',
					'description' => 'Site-wide SEO health summary: total indexables, cornerstone count, missing focus keyphrase, average scores.',
				),
				array(
					'method'      => 'GET',
					'path'        => '/yoast/cornerstone',
					'scope'       => 'yoast:read',
					'description' => 'Cornerstone content list — high-value posts ordered by incoming link count.',
					'parameters'  => array(
						array(
							'name'    => 'limit',
							'in'      => 'query',
							'type'    => 'integer',
							'default' => 20,
						),
						array(
							'name'    => 'page',
							'in'      => 'query',
							'type'    => 'integer',
							'default' => 1,
						),
					),
				),
				array(
					'method'      => 'GET',
					'path'        => '/yoast/needs-improvement',
					'scope'       => 'yoast:read',
					'description' => 'Published posts with SEO or readability score below threshold (default 40). Ranked worst first.',
					'parameters'  => array(
						array(
							'name'    => 'threshold',
							'in'      => 'query',
							'type'    => 'integer',
							'default' => 40,
						),
						array(
							'name'    => 'limit',
							'in'      => 'query',
							'type'    => 'integer',
							'default' => 20,
						),
					),
					'ai_hint'     => 'Use this to find posts that need rewriting/optimization. Yoast scores are 0–100.',
				),
				array(
					'method'      => 'GET',
					'path'        => '/yoast/orphaned',
					'scope'       => 'yoast:read',
					'description' => 'Posts with zero incoming internal links — candidates for new internal links from related content.',
					'parameters'  => array(
						array(
							'name'    => 'limit',
							'in'      => 'query',
							'type'    => 'integer',
							'default' => 20,
						),
					),
				),
			),
		);
	}
}
