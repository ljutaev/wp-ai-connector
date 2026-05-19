<?php
declare(strict_types=1);

namespace WPAIConnector\Auth;

/**
 * Generates new API keys and derives their persistent hash.
 *
 * Keys take the form: wpaic_live_<26 base32 chars>.
 * The plaintext is shown to the user exactly once. Persistent storage holds
 * only `hash_hmac('sha256', $token, wp_salt('auth').$salt)`.
 */
final class ApiKeyFactory {

	private const PREFIX        = 'wpaic_live_';
	private const RANDOM_LENGTH = 26;
	private const TRUNCATE_TAIL = 4;
	private const ALPHABET      = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // RFC 4648 base32

	public function generate(): GeneratedKey {
		$random = $this->random_string( self::RANDOM_LENGTH );
		$plain  = self::PREFIX . $random;
		$salt   = bin2hex( random_bytes( 16 ) );

		return new GeneratedKey(
			plaintext: $plain,
			truncated: self::PREFIX . substr( $random, 0, self::TRUNCATE_TAIL ),
			salt:      $salt,
			hash:      $this->hash( $plain, $salt ),
		);
	}

	public function hash( string $plaintext, string $salt ): string {
		$wp_salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : 'fallback-salt';
		return hash_hmac( 'sha256', $plaintext, $wp_salt . $salt );
	}

	private function random_string( int $length ): string {
		$alphabet = self::ALPHABET;
		$max      = strlen( $alphabet ) - 1;
		$out      = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$out .= $alphabet[ random_int( 0, $max ) ];
		}

		return $out;
	}
}

