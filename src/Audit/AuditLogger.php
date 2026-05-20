<?php
declare(strict_types=1);

namespace WPAIConnector\Audit;

/**
 * Writes audit log entries to wp_wpaic_audit_log.
 */
final class AuditLogger {

	private const TABLE_SUFFIX = 'wpaic_audit_log';

	/**
	 * Record a completed REST request.
	 *
	 * @param array<string, mixed> $entry
	 */
	public function record( array $entry ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . self::TABLE_SUFFIX,
			array(
				'key_id'      => isset( $entry['key_id'] ) ? (int) $entry['key_id'] : null,
				'user_id'     => isset( $entry['user_id'] ) ? (int) $entry['user_id'] : null,
				'route'       => (string) ( $entry['route'] ?? '' ),
				'method'      => strtoupper( (string) ( $entry['method'] ?? 'GET' ) ),
				'status'      => (int) ( $entry['status'] ?? 200 ),
				'ip'          => isset( $entry['ip'] ) ? (string) $entry['ip'] : null,
				'user_agent'  => isset( $entry['user_agent'] ) ? substr( (string) $entry['user_agent'], 0, 255 ) : null,
				'duration_ms' => isset( $entry['duration_ms'] ) ? (int) $entry['duration_ms'] : null,
				'created_at'  => gmdate( 'Y-m-d H:i:s' ),
			),
		);
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<int, array<string, mixed>>
	 */
	public function query( array $args = array() ): array {
		global $wpdb;

		$table  = $wpdb->prefix . self::TABLE_SUFFIX;
		$limit  = min( (int) ( $args['limit'] ?? 50 ), 500 );
		$offset = (int) ( $args['offset'] ?? 0 );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	public function count(): int {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_SUFFIX;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Delete entries older than $days days.
	 */
	public function purge( int $days = 30 ): int {
		global $wpdb;

		$table     = $wpdb->prefix . self::TABLE_SUFFIX;
		$timestamp = strtotime( "-{$days} days" );
		$cutoff    = gmdate( 'Y-m-d H:i:s', is_int( $timestamp ) ? $timestamp : 0 );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$deleted = $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff )
		);
		// phpcs:enable

		return (int) $deleted;
	}
}
