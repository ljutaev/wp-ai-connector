<?php
declare(strict_types=1);

namespace WPAIConnector\Auth;

final class GeneratedKey {

	public function __construct(
		public readonly string $plaintext,
		public readonly string $truncated,
		public readonly string $salt,
		public readonly string $hash,
	) {
	}
}
