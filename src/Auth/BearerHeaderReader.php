<?php
declare(strict_types=1);

namespace WPAIConnector\Auth;

/**
 * Reads the bearer token from an inbound request, handling the FastCGI / Wordfence /
 * mod_security variants that drop or rename the Authorization header.
 */
final class BearerHeaderReader {

	public function read(): ?string {
		$value = $this->raw_header_value();

		if ( null === $value ) {
			return null;
		}

		$value = trim( $value );

		if ( 0 !== stripos( $value, 'Bearer' ) ) {
			return null;
		}

		$token = trim( substr( $value, strlen( 'Bearer' ) ) );

		return '' === $token ? null : $token;
	}

	private function raw_header_value(): ?string {
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- bearer token is validated downstream, not sanitized here.
			$val = (string) $_SERVER['HTTP_AUTHORIZATION'];
			return function_exists( 'wp_unslash' ) ? (string) wp_unslash( $val ) : stripslashes( $val );
		}

		if ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- bearer token is validated downstream, not sanitized here.
			$val = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
			return function_exists( 'wp_unslash' ) ? (string) wp_unslash( $val ) : stripslashes( $val );
		}

		if ( function_exists( 'getallheaders' ) ) {
			$headers = array_change_key_case( (array) getallheaders(), CASE_LOWER );
			if ( ! empty( $headers['authorization'] ) ) {
				return (string) $headers['authorization'];
			}
		}

		return null;
	}
}
