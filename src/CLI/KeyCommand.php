<?php
declare(strict_types=1);

namespace WPAIConnector\CLI;

use WP_CLI;
use WPAIConnector\Auth\ApiKeyFactory;
use WPAIConnector\Auth\ApiKeyRepository;

/**
 * `wp ai-connector key ...` commands.
 */
final class KeyCommand {

	/**
	 * Create a new API key.
	 *
	 * ## OPTIONS
	 *
	 * [--user=<user>]
	 * : User ID or login. Defaults to the current WP-CLI user.
	 *
	 * [--label=<label>]
	 * : Human-readable label for this key.
	 * ---
	 * default: cli-generated
	 * ---
	 *
	 * [--scope=<scope>]
	 * : Comma-separated scopes. Use `*` for full access.
	 * ---
	 * default: '*'
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp ai-connector key create --user=admin --scope="posts:read,options:*"
	 *
	 * @param array<int, string>    $args
	 * @param array<string, string> $assoc_args
	 */
	public function create( array $args, array $assoc_args ): void {
		$user_arg = $assoc_args['user'] ?? null;
		$user     = $user_arg ? get_user_by( is_numeric( $user_arg ) ? 'id' : 'login', $user_arg ) : wp_get_current_user();

		if ( false === $user || 0 === $user->ID ) {
			WP_CLI::error( 'Could not resolve user.' );
			return;
		}

		$factory = new ApiKeyFactory();
		$gen     = $factory->generate();

		$scopes = array_filter( array_map( 'trim', explode( ',', $assoc_args['scope'] ?? '*' ) ) );

		$probe_hash = hash_hmac( 'sha256', $gen->plaintext, wp_salt( 'auth' ) );

		( new ApiKeyRepository() )->create(
			user_id:       (int) $user->ID,
			label:         (string) ( $assoc_args['label'] ?? 'cli-generated' ),
			hash:          $probe_hash,
			salt:          $gen->salt,
			truncated_key: $gen->truncated,
			scopes:        array_values( $scopes ),
		);

		WP_CLI::success( "Key created. Copy it now — it will not be shown again.\n\n  {$gen->plaintext}\n" );
	}
}
