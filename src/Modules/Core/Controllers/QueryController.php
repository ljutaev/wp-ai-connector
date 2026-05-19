<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Core\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

/**
 * POST /query — run a read-only SQL SELECT against the site database.
 *
 * Only SELECT and WITH (CTE) statements are allowed. A server-side
 * deny-list blocks destructive keywords. LIMIT is auto-injected when
 * missing and capped at the configured maximum.
 */
final class QueryController extends AbstractController {

	private const DEFAULT_LIMIT = 500;
	private const MAX_LIMIT     = 2000;

	/**
	 * Deny-list patterns. Matched case-insensitively against the full statement.
	 * Semicolons are rejected outright to prevent stacked statements.
	 *
	 * @var array<int, string>
	 */
	private const DENY_PATTERNS = array(
		'/;/',                              // stacked statements
		'/\bINSERT\b/i',
		'/\bUPDATE\b/i',
		'/\bDELETE\b/i',
		'/\bDROP\b/i',
		'/\bCREATE\b/i',
		'/\bALTER\b/i',
		'/\bTRUNCATE\b/i',
		'/\bREPLACE\b/i',
		'/\bRENAME\b/i',
		'/\bCALL\b/i',
		'/\bEXEC\b/i',
		'/INTO\s+OUTFILE/i',
		'/LOAD_FILE\s*\(/i',
		'/LOAD\s+DATA/i',
	);

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/query',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'execute' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'sql'   => array(
							'type'     => 'string',
							'required' => true,
						),
						'limit' => array(
							'type'    => 'integer',
							'default' => self::DEFAULT_LIMIT,
						),
					),
				),
			)
		);
	}

	public function permissions_check( mixed $request ): bool|\WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return ErrorResponse::forbidden_capability( 'manage_options' );
		}

		return true;
	}

	public function execute( mixed $request ): WP_REST_Response|\WP_Error {
		$sql         = trim( (string) $request->get_param( 'sql' ) );
		$limit       = min( (int) $request->get_param( 'limit' ), self::MAX_LIMIT );
		$limit       = max( 1, $limit );

		if ( ! self::is_safe( $sql ) ) {
			return ErrorResponse::make(
				'wpaic_query_forbidden',
				'Only SELECT and WITH statements are allowed. Destructive keywords are blocked.',
				403,
				array( 'hint' => 'Remove any INSERT, UPDATE, DELETE, DROP, or stacked statements (;).' )
			);
		}

		$sql = self::inject_limit( $sql, $limit );

		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results( $sql, ARRAY_A );
		// phpcs:enable

		if ( null === $results ) {
			return ErrorResponse::make(
				'wpaic_query_error',
				'Query failed: ' . $wpdb->last_error,
				500,
				array( 'last_error' => $wpdb->last_error )
			);
		}

		return new WP_REST_Response(
			array(
				'rows'       => $results,
				'row_count'  => count( $results ),
				'limit_used' => $limit,
			),
			200
		);
	}

	/**
	 * Returns true when the SQL statement is safe to execute (SELECT/WITH only).
	 */
	public static function is_safe( string $sql ): bool {
		$normalised = trim( $sql );

		// Must start with SELECT or WITH (case-insensitive).
		if ( ! preg_match( '/^(SELECT|WITH)\b/i', $normalised ) ) {
			return false;
		}

		foreach ( self::DENY_PATTERNS as $pattern ) {
			if ( preg_match( $pattern, $normalised ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Adds or caps the LIMIT clause.
	 *
	 * - If no LIMIT: appends LIMIT $max.
	 * - If LIMIT present but > $max: replaces with $max.
	 * - If LIMIT present and <= $max: leaves it unchanged.
	 */
	public static function inject_limit( string $sql, int $max ): string {
		if ( preg_match( '/\bLIMIT\s+(\d+)/i', $sql, $matches ) ) {
			$existing = (int) $matches[1];
			if ( $existing > $max ) {
				return preg_replace( '/\bLIMIT\s+\d+/i', 'LIMIT ' . $max, $sql ) ?? $sql;
			}
			return $sql;
		}

		return rtrim( $sql ) . ' LIMIT ' . $max;
	}
}
