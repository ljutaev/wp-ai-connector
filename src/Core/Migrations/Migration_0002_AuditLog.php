<?php
declare(strict_types=1);

namespace WPAIConnector\Core\Migrations;

final class Migration_0002_AuditLog {

	public static function up(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = $wpdb->prefix . 'wpaic_audit_log';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			key_id BIGINT UNSIGNED NULL,
			user_id BIGINT UNSIGNED NULL,
			route VARCHAR(190) NOT NULL,
			method VARCHAR(8) NOT NULL,
			status SMALLINT NOT NULL DEFAULT 200,
			ip VARCHAR(45) NULL,
			user_agent VARCHAR(255) NULL,
			duration_ms INT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY key_id_created (key_id, created_at),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta( $sql );
	}
}
