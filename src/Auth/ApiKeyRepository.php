<?php
declare(strict_types=1);

namespace WPAIConnector\Auth;

use DateTimeImmutable;

class ApiKeyRepository {

	private const TABLE_SUFFIX = 'wpaic_keys';

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * @param array<int, string> $scopes
	 */
	public function create(
		int $user_id,
		string $label,
		string $hash,
		string $salt,
		string $truncated_key,
		array $scopes,
		?DateTimeImmutable $expires_at = null,
	): int {
		global $wpdb;

		$wpdb->insert(
			$this->table(),
			[
				'user_id'       => $user_id,
				'label'         => $label,
				'hash'          => $hash,
				'salt'          => $salt,
				'truncated_key' => $truncated_key,
				'scope'         => (string) wp_json_encode( $scopes ),
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
				'expires_at'    => $expires_at?->format( 'Y-m-d H:i:s' ),
			],
		);

		return (int) $wpdb->insert_id;
	}

	public function find( int $id ): ?ApiKey {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id ),
			ARRAY_A,
		);

		return $row ? $this->row_to_key( $row ) : null;
	}

	public function find_by_hash( string $hash ): ?ApiKey {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table()} WHERE hash = %s", $hash ),
			ARRAY_A,
		);

		return $row ? $this->row_to_key( $row ) : null;
	}

	public function revoke( int $id ): void {
		global $wpdb;
		$wpdb->update(
			$this->table(),
			[ 'revoked_at' => gmdate( 'Y-m-d H:i:s' ) ],
			[ 'id' => $id ],
		);
	}

	public function touch( int $id, string $ip ): void {
		global $wpdb;
		$wpdb->update(
			$this->table(),
			[
				'last_used_at' => gmdate( 'Y-m-d H:i:s' ),
				'last_used_ip' => $ip,
			],
			[ 'id' => $id ],
		);
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function row_to_key( array $row ): ApiKey {
		$scopes = json_decode( (string) $row['scope'], true );
		if ( ! is_array( $scopes ) ) {
			$scopes = [];
		}

		return new ApiKey(
			id:            (int) $row['id'],
			user_id:       (int) $row['user_id'],
			label:         (string) $row['label'],
			hash:          (string) $row['hash'],
			truncated_key: (string) $row['truncated_key'],
			scopes:        array_values( array_map( 'strval', $scopes ) ),
			last_used_at:  $row['last_used_at'] ? new DateTimeImmutable( (string) $row['last_used_at'] ) : null,
			last_used_ip:  $row['last_used_ip'] ? (string) $row['last_used_ip'] : null,
			created_at:    new DateTimeImmutable( (string) $row['created_at'] ),
			expires_at:    $row['expires_at']  ? new DateTimeImmutable( (string) $row['expires_at'] )  : null,
			revoked_at:    $row['revoked_at']  ? new DateTimeImmutable( (string) $row['revoked_at'] )  : null,
		);
	}
}
