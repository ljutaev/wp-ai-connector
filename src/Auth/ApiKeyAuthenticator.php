<?php
declare(strict_types=1);

namespace WPAIConnector\Auth;

use DateTimeImmutable;

final class ApiKeyAuthenticator {

	public function __construct(
		private readonly ApiKeyRepository $repository,
		private readonly ApiKeyFactory $factory,
		private readonly DateTimeImmutable $now,
	) {
	}

	public function authenticate( string $plaintext ): ?ApiKey {
		$probe = $this->factory->hash( $plaintext, '' );

		$candidate = $this->repository->find_by_hash( $probe );
		if ( null === $candidate ) {
			return null;
		}

		if ( ! $candidate->is_usable( $this->now ) ) {
			return null;
		}

		return $candidate;
	}
}
