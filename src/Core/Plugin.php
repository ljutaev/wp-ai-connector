<?php
declare(strict_types=1);

namespace WPAIConnector\Core;

use DateTimeImmutable;
use WPAIConnector\Auth\ApiKeyAuthenticator;
use WPAIConnector\Auth\ApiKeyFactory;
use WPAIConnector\Auth\ApiKeyRepository;
use WPAIConnector\Auth\BearerHeaderReader;
use WPAIConnector\Auth\RestAuthBridge;
use WPAIConnector\Manifest\ManifestGenerator;
use WPAIConnector\Manifest\SkillGenerator;
use WPAIConnector\Modules\Core\Controllers\ManifestController;
use WPAIConnector\Modules\Core\Controllers\SkillController;
use WPAIConnector\Modules\Core\CoreModule;
use WPAIConnector\Modules\ModuleInterface;
use WPAIConnector\Modules\ModuleRegistry;

final class Plugin {

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

	private function wire_auth(): void {
		$repository    = new ApiKeyRepository();
		$factory       = new ApiKeyFactory();
		$authenticator = new ApiKeyAuthenticator( $repository, $factory, new DateTimeImmutable() );

		( new RestAuthBridge( new BearerHeaderReader(), $authenticator, $repository ) )->register();
	}
}
