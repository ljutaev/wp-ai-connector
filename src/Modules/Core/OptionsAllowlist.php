<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Core;

/**
 * Curated allowlist of WordPress options safe to expose via the REST API.
 *
 * Sensitive keys (auth_key, secret_key, nonce_salt, anything containing
 * _password / _secret / _key / _token) are HARD-blocked even if a filter
 * tries to add them.
 */
final class OptionsAllowlist {

	private const SENSITIVE_PATTERNS = [
		'_password',
		'_secret',
		'_token',
		'auth_key',
		'auth_salt',
		'logged_in_key',
		'logged_in_salt',
		'nonce_key',
		'nonce_salt',
		'secret_key',
	];

	/** @var array<int, string> */
	private const DEFAULT_KEYS = [
		'blogname',
		'blogdescription',
		'blog_public',
		'siteurl',
		'home',
		'admin_email',
		'timezone_string',
		'date_format',
		'time_format',
		'start_of_week',
		'WPLANG',
		'permalink_structure',
		'default_category',
		'default_post_format',
		'posts_per_page',
		'default_comment_status',
		'default_ping_status',
		'comment_registration',
		'require_name_email',
		'comments_notify',
		'moderation_notify',
		'comment_moderation',
		'comments_per_page',
		'default_comments_page',
		'comment_order',
		'thumbnail_size_w',
		'thumbnail_size_h',
		'medium_size_w',
		'medium_size_h',
		'large_size_w',
		'large_size_h',
		'uploads_use_yearmonth_folders',
		'show_on_front',
		'page_on_front',
		'page_for_posts',
		'category_base',
		'tag_base',
		'rss_use_excerpt',
		'posts_per_rss',
		'users_can_register',
		'default_role',
		'template',
		'stylesheet',
		'active_plugins',
	];

	/** @return array<int, string> */
	public function known(): array {
		/** @var array<int, string> $filtered */
		$filtered = apply_filters( 'wp_ai_connector_options_allowlist', self::DEFAULT_KEYS );
		return array_values( array_filter( array_unique( $filtered ), [ $this, 'is_allowed' ] ) );
	}

	public function is_allowed( string $key ): bool {
		foreach ( self::SENSITIVE_PATTERNS as $needle ) {
			if ( str_contains( $key, $needle ) ) {
				return false;
			}
		}

		/** @var array<int, string> $filtered */
		$filtered = apply_filters( 'wp_ai_connector_options_allowlist', self::DEFAULT_KEYS );

		return in_array( $key, $filtered, true );
	}
}
