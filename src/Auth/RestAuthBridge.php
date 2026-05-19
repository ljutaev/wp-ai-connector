<?php
declare(strict_types=1);

namespace WPAIConnector\Auth;

use WP_Error;
use WPAIConnector\REST\ErrorResponse;

/**
 * Bridges incoming REST requests to our bearer-token authenticator.
 * Registered on `rest_authentication_errors` at priority 99 so it runs
 * after any other auth provider has had a chance.
 */
final class RestAuthBridge {

	public function __construct(
		private readonly BearerHeaderReader $reader,
		private readonly ApiKeyAuthenticator $authenticator,
		private readonly ApiKeyRepository $repository,
		private readonly ?RateLimiter $rate_limiter = null,
	) {
	}

	public function register(): void {
		add_filter( 'rest_authentication_errors', array( $this, 'authenticate' ), 99 );
	}

	/**
	 * @param mixed $existing  The current result of the filter chain.
	 * @return mixed
	 */
	public function authenticate( $existing ) {
		if ( true === $existing || $existing instanceof WP_Error ) {
			return $existing;
		}

		$token = $this->reader->read();
		if ( null === $token ) {
			return $existing;
		}

		$key = $this->authenticator->authenticate( $token );
		if ( null === $key ) {
			return ErrorResponse::unauthorized( 'Bearer token invalid or revoked.' );
		}

		if ( null !== $this->rate_limiter && ! $this->rate_limiter->allow( $key->id ) ) {
			$response = ErrorResponse::make( 'wpaic_rate_limited', 'Rate limit exceeded.', 429 );
			$response->add_data(
				array(
					'status'      => 429,
					'limit'       => $this->rate_limiter->limit(),
					'window'      => $this->rate_limiter->window(),
					'retry_after' => $this->rate_limiter->window(),
				)
			);
			return $response;
		}

		wp_set_current_user( $key->user_id );

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$this->repository->touch( $key->id, $ip );

		// Store key on request context for downstream use (audit log, scope check).
		$GLOBALS['wpaic_current_key'] = $key;

		return true;
	}
}
