<?php
declare(strict_types=1);

namespace WPAIConnector\Core;

use DateTimeImmutable;
use WPAIConnector\Admin\SettingsPage;
use WPAIConnector\Audit\AuditLogger;
use WPAIConnector\Auth\ApiKeyAuthenticator;
use WPAIConnector\Auth\ApiKeyFactory;
use WPAIConnector\Auth\ApiKeyRepository;
use WPAIConnector\Auth\BearerHeaderReader;
use WPAIConnector\Auth\RateLimiter;
use WPAIConnector\Auth\RestAuthBridge;
use WPAIConnector\Core\Migrations\Migration_0002_AuditLog;
use WPAIConnector\Manifest\ManifestGenerator;
use WPAIConnector\Manifest\SkillGenerator;
use WPAIConnector\Modules\Core\Controllers\ManifestController;
use WPAIConnector\Modules\Core\Controllers\SkillController;
use WPAIConnector\Modules\Core\CoreModule;
use WPAIConnector\Modules\ModuleInterface;
use WPAIConnector\Modules\ModuleRegistry;

final class Plugin {

	/** @var self|null */
	private static ?self $instance = null;

	private function __construct(
		public readonly string $plugin_file,
		public readonly Container $container,
	) {
	}

	public static function boot( string $plugin_file ): void {
		if ( null !== self::$instance ) {
			return;
		}

		self::$instance = new self( $plugin_file, new Container() );
		self::$instance->register();
	}

	private function register(): void {
		register_activation_hook( $this->plugin_file, array( Installer::class, 'activate' ) );

		add_action( 'plugins_loaded', array( Installer::class, 'maybe_upgrade' ), 5 );

		add_action( 'init', array( $this, 'load_modules' ), 5 );
		add_action( 'rest_api_init', array( $this, 'register_meta_routes' ) );
		add_action( 'cli_init', array( $this, 'register_cli' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'log_request' ), 10, 3 );

		if ( is_admin() ) {
			( new SettingsPage() )->register();
		}
	}

	public function load_modules(): void {
		/** @var array<int, ModuleInterface> $defaults */
		$defaults = array( new CoreModule() );

		/** @var array<int, ModuleInterface> $candidates */
		$candidates = apply_filters( 'wp_ai_connector_modules', $defaults );

		$registry = new ModuleRegistry( $this->container );
		$active   = $registry->load( $candidates );

		$this->container->set( 'active_modules', static fn () => $active );
		$this->wire_auth();
	}

	public function register_meta_routes(): void {
		/** @var array<int, ModuleInterface> $modules */
		$modules = $this->container->has( 'active_modules' )
			? $this->container->get( 'active_modules' )
			: array();

		$manifest_gen = new ManifestGenerator();
		$skill_gen    = new SkillGenerator();

		( new ManifestController( $manifest_gen, $modules ) )->register_routes();
		( new SkillController( $manifest_gen, $skill_gen, $modules ) )->register_routes();
	}

	public function register_cli(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'ai-connector key', \WPAIConnector\CLI\KeyCommand::class );
		}
	}

	public function log_request( mixed $response, mixed $handler, mixed $request ): mixed {
		// Only log requests to our namespace.
		if ( ! $request instanceof \WP_REST_Request ) {
			return $response;
		}

		$route = $request->get_route();
		if ( ! str_starts_with( $route, '/wp-ai-connector/' ) ) {
			return $response;
		}

		/** @var \WPAIConnector\Auth\ApiKey|null $key */
		$key    = $GLOBALS['wpaic_current_key'] ?? null;
		$status = $response instanceof \WP_REST_Response ? $response->get_status() : 200;

		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
			: '';

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		( new AuditLogger() )->record(
			array(
				'key_id'     => $key?->id,
				'user_id'    => $key?->user_id ?? get_current_user_id(),
				'route'      => $route,
				'method'     => $request->get_method(),
				'status'     => $status,
				'ip'         => $ip,
				'user_agent' => $ua,
			)
		);

		return $response;
	}

	private function wire_auth(): void {
		$repository    = new ApiKeyRepository();
		$factory       = new ApiKeyFactory();
		$authenticator = new ApiKeyAuthenticator( $repository, $factory, new DateTimeImmutable() );
		$rate_limiter  = new RateLimiter();

		( new RestAuthBridge( new BearerHeaderReader(), $authenticator, $repository, $rate_limiter ) )->register();
	}
}
