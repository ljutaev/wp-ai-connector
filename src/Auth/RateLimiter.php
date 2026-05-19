<?php
declare(strict_types=1);

namespace WPAIConnector\Auth;

/**
 * Sliding-window rate limiter backed by WordPress transients.
 *
 * Key format: wpaic_rl_{key_id}_{floor(time/60)}
 * Default: 60 requests per 60-second window, filterable.
 */
final class RateLimiter {

	private const DEFAULT_LIMIT  = 60;
	private const WINDOW_SECONDS = 60;

	public function __construct( private readonly int $limit = self::DEFAULT_LIMIT ) {
	}

	/**
	 * Returns true if the request should be allowed, false if rate-limited.
	 * Increments the counter on every call.
	 */
	public function allow( int $key_id ): bool {
		$limit  = (int) apply_filters( 'wp_ai_connector_rate_limit', $this->limit, $key_id );
		$bucket = $this->bucket_key( $key_id );

		$current = (int) get_transient( $bucket );

		if ( $current >= $limit ) {
			return false;
		}

		if ( 0 === $current ) {
			set_transient( $bucket, 1, self::WINDOW_SECONDS );
		} else {
			set_transient( $bucket, $current + 1, self::WINDOW_SECONDS );
		}

		return true;
	}

	/**
	 * Returns the number of remaining requests in the current window.
	 */
	public function remaining( int $key_id ): int {
		$limit   = (int) apply_filters( 'wp_ai_connector_rate_limit', $this->limit, $key_id );
		$current = (int) get_transient( $this->bucket_key( $key_id ) );

		return max( 0, $limit - $current );
	}

	public function limit(): int {
		return $this->limit;
	}

	public function window(): int {
		return self::WINDOW_SECONDS;
	}

	private function bucket_key( int $key_id ): string {
		return 'wpaic_rl_' . $key_id . '_' . (int) floor( time() / self::WINDOW_SECONDS );
	}
}
