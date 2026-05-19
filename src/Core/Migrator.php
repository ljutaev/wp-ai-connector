<?php
declare(strict_types=1);

namespace WPAIConnector\Core;

/**
 * Runs ordered migrations. Each migration is a static class with up(): void.
 *
 * Schema version is stored in option `wpaic_schema_version`.
 */
final class Migrator {

	public const TARGET_VERSION = 2;
	public const OPTION_KEY     = 'wpaic_schema_version';

	/**
	 * @param array<int, class-string> $migrations Indexed by version number (1-based).
	 */
	public function __construct( private readonly array $migrations ) {
	}

	public function run(): void {
		$current = (int) get_option( self::OPTION_KEY, 0 );

		for ( $version = $current + 1; $version <= self::TARGET_VERSION; $version++ ) {
			if ( ! isset( $this->migrations[ $version ] ) ) {
				continue;
			}

			$class = $this->migrations[ $version ];
			$class::up();

			update_option( self::OPTION_KEY, $version, false );
		}
	}
}
