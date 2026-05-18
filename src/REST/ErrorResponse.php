<?php
declare(strict_types=1);

namespace WPAIConnector\REST;

use WP_Error;

final class ErrorResponse {

	/**
	 * @param array<string, mixed> $data Extra fields merged into WP_Error's data array.
	 */
	public static function make( string $code, string $message, int $status, array $data = [] ): WP_Error {
		return new WP_Error( $code, $message, array_merge( [ 'status' => $status ], $data ) );
	}

	public static function unauthorized( string $message = 'Authentication required.' ): WP_Error {
		return self::make( 'wpaic_unauthorized', $message, 401 );
	}

	public static function forbidden_capability( string $cap ): WP_Error {
		return self::make( 'wpaic_forbidden_cap', "Required capability '{$cap}' is missing.", 403, [ 'capability' => $cap ] );
	}

	public static function forbidden_scope( string $scope ): WP_Error {
		return self::make( 'wpaic_forbidden_scope', "Required scope '{$scope}' is missing from this key.", 403, [ 'scope' => $scope ] );
	}

	public static function not_found( string $message = 'Resource not found.' ): WP_Error {
		return self::make( 'wpaic_not_found', $message, 404 );
	}

	public static function validation( string $message, string $field ): WP_Error {
		return self::make( 'wpaic_validation', $message, 400, [ 'field' => $field ] );
	}
}
