<?php
declare(strict_types=1);

namespace WPAIConnector\Auth;

use DateTimeImmutable;

/**
 * Value object representing a row in wp_wpaic_keys.
 */
final class ApiKey {

	/**
	 * @param array<int, string> $scopes
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $user_id,
		public readonly string $label,
		public readonly string $hash,
		public readonly string $truncated_key,
		public readonly array $scopes,
		public readonly ?DateTimeImmutable $last_used_at,
		public readonly ?string $last_used_ip,
		public readonly DateTimeImmutable $created_at,
		public readonly ?DateTimeImmutable $expires_at,
		public readonly ?DateTimeImmutable $revoked_at,
	) {
	}

	public function is_revoked(): bool {
		return null !== $this->revoked_at;
	}

	public function is_expired( DateTimeImmutable $now ): bool {
		return null !== $this->expires_at && $this->expires_at <= $now;
	}

	public function is_usable( DateTimeImmutable $now ): bool {
		return ! $this->is_revoked() && ! $this->is_expired( $now );
	}
}
