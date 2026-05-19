<?php
declare(strict_types=1);

namespace WPAIConnector\Auth;

/**
 * Capability scope matcher. Supports exact match, segment-prefix wildcards
 * ("woo:*" matches "woo:orders:read"), and full wildcard "*".
 */
final class KeyScope {

	/**
	 * @param array<int, string> $scopes Scopes granted to the key.
	 * @param string             $required Scope required by the endpoint.
	 */
	public static function allows( array $scopes, string $required ): bool {
		foreach ( $scopes as $granted ) {
			if ( self::matches( $granted, $required ) ) {
				return true;
			}
		}
		return false;
	}

	private static function matches( string $granted, string $required ): bool {
		if ( '*' === $granted ) {
			return true;
		}

		if ( $granted === $required ) {
			return true;
		}

		if ( ! str_ends_with( $granted, ':*' ) ) {
			return false;
		}

		$prefix = substr( $granted, 0, -1 ); // strip trailing * to get the namespace prefix
		return str_starts_with( $required, $prefix );
	}
}
