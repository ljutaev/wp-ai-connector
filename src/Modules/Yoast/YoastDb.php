<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Yoast;

/**
 * Shared Yoast SEO database helper.
 *
 * Reads directly from wp_yoast_indexable, wp_yoast_primary_term,
 * wp_yoast_indexable_hierarchy, and wp_yoast_seo_links tables.
 *
 * READ strategy  : direct $wpdb (zero-hydration, no WP_Post hydration)
 * WRITE strategy : postmeta _yoast_wpseo_* + fires wpseo_save_indexable hook
 *                  to keep the indexable table in sync.
 */
final class YoastDb {

	/** True when the wp_yoast_indexable table exists. */
	public static function indexable_table_exists(): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'yoast_indexable';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	// -----------------------------------------------------------------
	// Indexable reads
	// -----------------------------------------------------------------

	/**
	 * Read a single indexable row by object_id + object_type.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_indexable( int $object_id, string $object_type ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'yoast_indexable';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE object_id = %d AND object_type = %s LIMIT 1",
				$object_id,
				$object_type
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? $row : null;
	}

	/**
	 * List cornerstone content (high-value posts marked by editor).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_cornerstone_posts( int $limit, int $page ): array {
		global $wpdb;
		$table  = $wpdb->prefix . 'yoast_indexable';
		$offset = ( $page - 1 ) * $limit;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT object_id, object_sub_type AS post_type, title, description,
				        primary_focus_keyword, primary_focus_keyword_score, readability_score,
				        permalink, link_count, incoming_link_count
				FROM {$table}
				WHERE object_type = 'post' AND is_cornerstone = 1
				ORDER BY incoming_link_count DESC
				LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Posts with low SEO or readability scores — surfaced for AI agents to improve.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_needs_improvement( int $threshold, int $limit, int $page ): array {
		global $wpdb;
		$table  = $wpdb->prefix . 'yoast_indexable';
		$offset = ( $page - 1 ) * $limit;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT object_id, object_sub_type AS post_type, title, description,
				        primary_focus_keyword, primary_focus_keyword_score, readability_score,
				        permalink, post_status
				FROM {$table}
				WHERE object_type = 'post'
				  AND post_status = 'publish'
				  AND ( primary_focus_keyword_score < %d OR readability_score < %d )
				  AND primary_focus_keyword_score IS NOT NULL
				ORDER BY primary_focus_keyword_score ASC
				LIMIT %d OFFSET %d",
				$threshold,
				$threshold,
				$limit,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Site-wide SEO health summary — counts and averages across all posts.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_health_summary(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'yoast_indexable';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			"SELECT
			    COUNT(*) AS total_indexables,
			    SUM(CASE WHEN is_cornerstone = 1 THEN 1 ELSE 0 END) AS cornerstone_count,
			    SUM(CASE WHEN is_robots_noindex = 1 THEN 1 ELSE 0 END) AS noindexed_count,
			    SUM(CASE WHEN primary_focus_keyword IS NULL OR primary_focus_keyword = '' THEN 1 ELSE 0 END) AS missing_focus_keyphrase,
			    SUM(CASE WHEN title IS NULL OR title = '' THEN 1 ELSE 0 END) AS missing_seo_title,
			    SUM(CASE WHEN description IS NULL OR description = '' THEN 1 ELSE 0 END) AS missing_meta_description,
			    AVG(primary_focus_keyword_score) AS avg_seo_score,
			    AVG(readability_score) AS avg_readability_score
			FROM {$table}
			WHERE object_type = 'post' AND post_status = 'publish'",
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? $row : array();
	}

	// -----------------------------------------------------------------
	// Internal links (wp_yoast_seo_links)
	// -----------------------------------------------------------------

	/**
	 * Internal links FROM a post (outgoing).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_outgoing_links( int $post_id ): array {
		global $wpdb;
		$table_links     = $wpdb->prefix . 'yoast_seo_links';
		$table_indexable = $wpdb->prefix . 'yoast_indexable';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.url, l.target_post_id, l.type,
				        i.title AS target_title, i.permalink AS target_permalink
				FROM {$table_links} l
				LEFT JOIN {$table_indexable} i ON l.target_post_id = i.object_id AND i.object_type = 'post'
				WHERE l.post_id = %d",
				$post_id
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Internal links TO a post (incoming) — useful for "what links here".
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_incoming_links( int $post_id ): array {
		global $wpdb;
		$table_links     = $wpdb->prefix . 'yoast_seo_links';
		$table_indexable = $wpdb->prefix . 'yoast_indexable';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.url, l.post_id AS source_post_id, l.type,
				        i.title AS source_title, i.permalink AS source_permalink
				FROM {$table_links} l
				LEFT JOIN {$table_indexable} i ON l.post_id = i.object_id AND i.object_type = 'post'
				WHERE l.target_post_id = %d",
				$post_id
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Posts with no incoming internal links (orphaned content).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_orphaned_posts( int $limit, int $page ): array {
		global $wpdb;
		$table  = $wpdb->prefix . 'yoast_indexable';
		$offset = ( $page - 1 ) * $limit;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT object_id, object_sub_type AS post_type, title, permalink,
				        primary_focus_keyword, primary_focus_keyword_score,
				        incoming_link_count, link_count
				FROM {$table}
				WHERE object_type = 'post'
				  AND post_status = 'publish'
				  AND ( incoming_link_count = 0 OR incoming_link_count IS NULL )
				ORDER BY object_id DESC
				LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	// -----------------------------------------------------------------
	// Primary term (wp_yoast_primary_term)
	// -----------------------------------------------------------------

	/** Primary category/term assignments for a post: { taxonomy => term_id }. */
	public static function get_primary_terms( int $post_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'yoast_primary_term';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT taxonomy, term_id FROM {$table} WHERE post_id = %d",
				$post_id
			),
			ARRAY_A
		);
		// phpcs:enable

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['taxonomy'] ] = (int) $row['term_id'];
		}
		return $out;
	}
}
