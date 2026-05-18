<?php
declare(strict_types=1);

namespace WPAIConnector\Core\Migrations;

final class Migration_0001_Keys {

	public static function up(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = $wpdb->prefix . 'wpaic_keys';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			label VARCHAR(190) NOT NULL DEFAULT '',
			hash CHAR(64) NOT NULL,
			salt CHAR(32) NOT NULL,
			truncated_key VARCHAR(32) NOT NULL,
			scope LONGTEXT NOT NULL,
			last_used_at DATETIME NULL,
			last_used_ip VARCHAR(45) NULL,
			created_at DATETIME NOT NULL,
			expires_at DATETIME NULL,
			revoked_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY hash (hash),
			KEY user_id (user_id),
			KEY revoked_at (revoked_at)
		) {$charset};";

		dbDelta( $sql );
	}
}
