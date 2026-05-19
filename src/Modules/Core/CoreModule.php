<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Core;

use WPAIConnector\Core\Container;
use WPAIConnector\Modules\AbstractModule;
use WPAIConnector\Modules\Conditionals\PHPVersionConditional;
use WPAIConnector\Modules\Conditionals\WordPressVersionConditional;
use WPAIConnector\Modules\Core\Controllers\CommentsController;
use WPAIConnector\Modules\Core\Controllers\CronController;
use WPAIConnector\Modules\Core\Controllers\MediaController;
use WPAIConnector\Modules\Core\Controllers\MenusController;
use WPAIConnector\Modules\Core\Controllers\OptionsController;
use WPAIConnector\Modules\Core\Controllers\PluginsController;
use WPAIConnector\Modules\Core\Controllers\PostsController;
use WPAIConnector\Modules\Core\Controllers\TermsController;
use WPAIConnector\Modules\Core\Controllers\ThemesController;
use WPAIConnector\Modules\Core\Controllers\TransientsController;
use WPAIConnector\Modules\Core\Controllers\UsersController;

final class CoreModule extends AbstractModule {

	public function name(): string {
		return 'core';
	}

	public function version(): string {
		return '0.3.0';
	}

	/** @return array<int, \WPAIConnector\Modules\ConditionalInterface> */
	public function conditionals(): array {
		return array(
			new PHPVersionConditional( '8.1' ),
			new WordPressVersionConditional( '6.6' ),
		);
	}

	public function register( Container $container ): void {
		$container->set( OptionsAllowlist::class, static fn () => new OptionsAllowlist() );
		$container->set(
			OptionsController::class,
			static fn ( Container $c ) => new OptionsController( $c->get( OptionsAllowlist::class ) ),
		);
		$container->set( PostsController::class, static fn () => new PostsController() );
		$container->set( UsersController::class, static fn () => new UsersController() );
		$container->set( TermsController::class, static fn () => new TermsController() );
		$container->set( CommentsController::class, static fn () => new CommentsController() );
		$container->set( MediaController::class, static fn () => new MediaController() );
		$container->set( MenusController::class, static fn () => new MenusController() );
		$container->set( PluginsController::class, static fn () => new PluginsController() );
		$container->set( ThemesController::class, static fn () => new ThemesController() );
		$container->set( TransientsController::class, static fn () => new TransientsController() );
		$container->set( CronController::class, static fn () => new CronController() );

		add_action(
			'rest_api_init',
			static function () use ( $container ): void {
				$container->get( OptionsController::class )->register_routes();
				$container->get( PostsController::class )->register_routes();
				$container->get( UsersController::class )->register_routes();
				$container->get( TermsController::class )->register_routes();
				$container->get( CommentsController::class )->register_routes();
				$container->get( MediaController::class )->register_routes();
				$container->get( MenusController::class )->register_routes();
				$container->get( PluginsController::class )->register_routes();
				$container->get( ThemesController::class )->register_routes();
				$container->get( TransientsController::class )->register_routes();
				$container->get( CronController::class )->register_routes();
			}
		);
	}

	/** @return array<string, mixed> */
	public function manifest(): array {
		$keys = ( new OptionsAllowlist() )->known();

		return array(
			'name'              => 'core',
			'version'           => $this->version(),
			'detected'          => true,
			'routes'            => array(
				// Options
				array(
					'method'      => 'GET',
					'path'        => '/options/{key}',
					'scope'       => 'options:read',
					'description' => 'Read a WordPress option from the curated allowlist.',
					'parameters'  => array(
						array(
							'name' => 'key',
							'in'   => 'path',
							'type' => 'string',
							'enum' => $keys,
						),
					),
				),
				array(
					'method'      => 'POST',
					'path'        => '/options/{key}',
					'scope'       => 'options:write',
					'description' => 'Update a WordPress option from the curated allowlist.',
					'parameters'  => array(
						array(
							'name' => 'key',
							'in'   => 'path',
							'type' => 'string',
							'enum' => $keys,
						),
						array(
							'name' => 'value',
							'in'   => 'body',
							'type' => 'string',
						),
					),
					'ai_hint'     => "Common keys: 'blog_public' (0 hides from search engines, 1 shows), 'blogname' (site title), 'blogdescription' (tagline).",
				),
				// Posts
				array(
					'method'      => 'GET',
					'path'        => '/posts',
					'scope'       => 'posts:read',
					'description' => 'List WordPress posts. Supports post_type, post_status, pagination, and search.',
					'parameters'  => array(
						array(
							'name'    => 'post_type',
							'in'      => 'query',
							'type'    => 'string',
							'default' => 'post',
						),
						array(
							'name'    => 'post_status',
							'in'      => 'query',
							'type'    => 'string',
							'default' => 'publish',
						),
						array(
							'name'    => 'posts_per_page',
							'in'      => 'query',
							'type'    => 'integer',
							'default' => 20,
						),
						array(
							'name'    => 'paged',
							'in'      => 'query',
							'type'    => 'integer',
							'default' => 1,
						),
						array(
							'name' => 's',
							'in'   => 'query',
							'type' => 'string',
						),
					),
					'ai_hint'     => "Use post_status='any' to include drafts. post_type='page' for pages, 'post' for blog posts.",
				),
				array(
					'method'      => 'GET',
					'path'        => '/posts/{id}',
					'scope'       => 'posts:read',
					'description' => 'Get a single post by ID.',
				),
				array(
					'method'      => 'POST',
					'path'        => '/posts/{id}',
					'scope'       => 'posts:write',
					'description' => 'Update a post title, content, or status.',
					'parameters'  => array(
						array(
							'name' => 'post_title',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'post_content',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'post_status',
							'in'   => 'body',
							'type' => 'string',
							'enum' => array( 'publish', 'draft', 'pending', 'private', 'trash' ),
						),
					),
				),
				// Users
				array(
					'method'      => 'GET',
					'path'        => '/users',
					'scope'       => 'users:read',
					'description' => 'List WordPress users. Filter by role.',
					'parameters'  => array(
						array(
							'name' => 'role',
							'in'   => 'query',
							'type' => 'string',
						),
						array(
							'name'    => 'number',
							'in'      => 'query',
							'type'    => 'integer',
							'default' => 20,
						),
					),
				),
				array(
					'method'      => 'GET',
					'path'        => '/users/{id}',
					'scope'       => 'users:read',
					'description' => 'Get a single user by ID.',
				),
				// Terms
				array(
					'method'      => 'GET',
					'path'        => '/terms',
					'scope'       => 'terms:read',
					'description' => 'List terms for a taxonomy. Default taxonomy: category.',
					'parameters'  => array(
						array(
							'name'    => 'taxonomy',
							'in'      => 'query',
							'type'    => 'string',
							'default' => 'category',
						),
						array(
							'name'    => 'hide_empty',
							'in'      => 'query',
							'type'    => 'boolean',
							'default' => false,
						),
					),
					'ai_hint'     => 'Built-in taxonomies: category, post_tag, nav_menu, post_format. Pass taxonomy=post_tag for tags.',
				),
				array(
					'method'      => 'GET',
					'path'        => '/terms/{id}',
					'scope'       => 'terms:read',
					'description' => 'Get a term by ID. Pass ?taxonomy= for accuracy.',
				),
				// Comments
				array(
					'method'      => 'GET',
					'path'        => '/comments',
					'scope'       => 'comments:read',
					'description' => 'List comments. Filter by status and post_id.',
					'parameters'  => array(
						array(
							'name'    => 'status',
							'in'      => 'query',
							'type'    => 'string',
							'default' => 'approve',
							'enum'    => array( 'approve', 'hold', 'spam', 'trash' ),
						),
						array(
							'name' => 'post_id',
							'in'   => 'query',
							'type' => 'integer',
						),
					),
				),
				array(
					'method'      => 'GET',
					'path'        => '/comments/{id}',
					'scope'       => 'comments:read',
					'description' => 'Get a single comment by ID.',
				),
				array(
					'method'      => 'POST',
					'path'        => '/comments/{id}',
					'scope'       => 'comments:write',
					'description' => 'Update comment status or content.',
					'ai_hint'     => "Set comment_approved to '1' to approve, '0' to hold, 'spam' to mark as spam, 'trash' to trash.",
				),
				// Media
				array(
					'method'      => 'GET',
					'path'        => '/media',
					'scope'       => 'media:read',
					'description' => 'List media library items. Filter by mime_type (e.g. image/jpeg).',
				),
				array(
					'method'      => 'GET',
					'path'        => '/media/{id}',
					'scope'       => 'media:read',
					'description' => 'Get a single media attachment by ID.',
				),
				// Menus
				array(
					'method'      => 'GET',
					'path'        => '/menus',
					'scope'       => 'menus:read',
					'description' => 'List all registered navigation menus.',
				),
				array(
					'method'      => 'GET',
					'path'        => '/menus/{id}',
					'scope'       => 'menus:read',
					'description' => 'Get a navigation menu with all its items.',
				),
				// Plugins
				array(
					'method'      => 'GET',
					'path'        => '/plugins',
					'scope'       => 'plugins:read',
					'description' => 'List installed plugins.',
					'parameters'  => array(
						array(
							'name'    => 'status',
							'in'      => 'query',
							'type'    => 'string',
							'enum'    => array( 'all', 'active', 'inactive' ),
							'default' => 'all',
						),
					),
				),
				// Themes
				array(
					'method'      => 'GET',
					'path'        => '/themes',
					'scope'       => 'themes:read',
					'description' => 'List all installed themes.',
				),
				array(
					'method'      => 'GET',
					'path'        => '/themes/active',
					'scope'       => 'themes:read',
					'description' => 'Get the currently active theme.',
				),
				// Transients
				array(
					'method'      => 'GET',
					'path'        => '/transients/{key}',
					'scope'       => 'transients:read',
					'description' => 'Read a transient by key. Returns expired:true if not set.',
				),
				array(
					'method'      => 'DELETE',
					'path'        => '/transients/{key}',
					'scope'       => 'transients:write',
					'description' => 'Delete a transient by key.',
				),
				array(
					'method'      => 'POST',
					'path'        => '/transients/{key}/flush',
					'scope'       => 'transients:write',
					'description' => 'Flush (delete) a transient by key.',
				),
				// Cron
				array(
					'method'      => 'GET',
					'path'        => '/cron',
					'scope'       => 'cron:read',
					'description' => 'List scheduled WP-Cron events sorted by next run time.',
					'parameters'  => array(
						array(
							'name' => 'hook',
							'in'   => 'query',
							'type' => 'string',
						),
					),
					'ai_hint'     => 'Each entry has hook, timestamp, next_run (UTC ISO-8601), schedule, and interval (seconds).',
				),
			),
			'options_allowlist' => $keys,
		);
	}
}
