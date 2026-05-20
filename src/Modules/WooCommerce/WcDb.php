<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\WooCommerce;

/**
 * Shared WooCommerce database helper.
 *
 * Centralises HPOS detection and table name resolution so individual
 * controllers never need to know about WC internals.
 *
 * READ strategy  : direct $wpdb queries (zero-hydration, sub-50ms)
 * WRITE strategy : WC functions (wc_create_order, $product->save, etc.)
 *                  to preserve cache invalidation, hooks, and email triggers.
 */
final class WcDb {

	/** True when WooCommerce ≥ 7.1 HPOS tables are in use. */
	public static function hpos_enabled(): bool {
		return get_option( 'woocommerce_custom_orders_table_enabled', 'no' ) === 'yes';
	}

	/**
	 * Normalise an order status string.
	 * WooCommerce stores statuses with the "wc-" prefix in HPOS; the
	 * legacy wp_posts.post_status also uses "wc-". We strip it for
	 * consistent API output.
	 */
	public static function normalise_status( string $raw ): string {
		return str_starts_with( $raw, 'wc-' ) ? substr( $raw, 3 ) : $raw;
	}

	/**
	 * Prefix a raw order status for use in a WHERE clause.
	 * Both HPOS and legacy store "wc-processing", not "processing".
	 */
	public static function prefix_status( string $status ): string {
		if ( '' === $status || 'any' === $status ) {
			return $status;
		}
		return str_starts_with( $status, 'wc-' ) ? $status : 'wc-' . $status;
	}

	// -----------------------------------------------------------------
	// HPOS table helpers
	// -----------------------------------------------------------------

	/** @return array<int, array<string, mixed>> */
	public static function get_orders_hpos(
		string $status,
		int $limit,
		int $page,
		?int $customer_id,
		string $orderby,
		string $order
	): array {
		global $wpdb;

		$table_orders    = $wpdb->prefix . 'wc_orders';
		$table_addresses = $wpdb->prefix . 'wc_order_addresses';
		$table_ops       = $wpdb->prefix . 'wc_order_operational_data';

		$allowed_orderby = array( 'date_created_gmt', 'total_amount', 'id' );
		$orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'date_created_gmt';
		$order           = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
		$offset          = ( $page - 1 ) * $limit;

		$where  = "WHERE o.type = 'shop_order'";
		$params = array();

		if ( '' !== $status && 'any' !== $status ) {
			$where   .= ' AND o.status = %s';
			$params[] = self::prefix_status( $status );
		}

		if ( null !== $customer_id ) {
			$where   .= ' AND o.customer_id = %d';
			$params[] = $customer_id;
		}

		$params[] = $limit;
		$params[] = $offset;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.id, o.status, o.currency, o.total_amount AS total,
				        o.customer_id, o.date_created_gmt,
				        ba.first_name AS billing_first_name, ba.last_name AS billing_last_name,
				        ba.email AS billing_email, ba.phone AS billing_phone,
				        op.payment_method_title AS payment_method
				FROM {$table_orders} o
				LEFT JOIN {$table_addresses} ba ON o.id = ba.order_id AND ba.address_type = 'billing'
				LEFT JOIN {$table_ops} op ON o.id = op.order_id
				{$where}
				ORDER BY o.{$orderby} {$order}
				LIMIT %d OFFSET %d",
				...$params
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/** @return array<int, array<string, mixed>> */
	public static function get_orders_legacy(
		string $status,
		int $limit,
		int $page,
		?int $customer_id,
		string $order
	): array {
		global $wpdb;

		$order  = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
		$offset = ( $page - 1 ) * $limit;

		$where  = "WHERE p.post_type = 'shop_order' AND p.post_status != 'trash'";
		$params = array();

		if ( '' !== $status && 'any' !== $status ) {
			$where   .= ' AND p.post_status = %s';
			$params[] = self::prefix_status( $status );
		}

		if ( null !== $customer_id ) {
			$where   .= ' AND pm_cust.meta_value = %s';
			$params[] = (string) $customer_id;
		}

		$params[] = $limit;
		$params[] = $offset;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS id,
				        REPLACE(p.post_status, 'wc-', '') AS status,
				        p.post_date_gmt AS date_created_gmt,
				        pm_total.meta_value AS total,
				        pm_currency.meta_value AS currency,
				        pm_email.meta_value AS billing_email,
				        pm_fname.meta_value AS billing_first_name,
				        pm_lname.meta_value AS billing_last_name,
				        pm_phone.meta_value AS billing_phone,
				        pm_payment.meta_value AS payment_method,
				        COALESCE(pm_cust.meta_value, 0) AS customer_id
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_total     ON p.ID = pm_total.post_id     AND pm_total.meta_key     = '_order_total'
				LEFT JOIN {$wpdb->postmeta} pm_currency  ON p.ID = pm_currency.post_id  AND pm_currency.meta_key  = '_order_currency'
				LEFT JOIN {$wpdb->postmeta} pm_email     ON p.ID = pm_email.post_id     AND pm_email.meta_key     = '_billing_email'
				LEFT JOIN {$wpdb->postmeta} pm_fname     ON p.ID = pm_fname.post_id     AND pm_fname.meta_key     = '_billing_first_name'
				LEFT JOIN {$wpdb->postmeta} pm_lname     ON p.ID = pm_lname.post_id     AND pm_lname.meta_key     = '_billing_last_name'
				LEFT JOIN {$wpdb->postmeta} pm_phone     ON p.ID = pm_phone.post_id     AND pm_phone.meta_key     = '_billing_phone'
				LEFT JOIN {$wpdb->postmeta} pm_payment   ON p.ID = pm_payment.post_id   AND pm_payment.meta_key   = '_payment_method_title'
				LEFT JOIN {$wpdb->postmeta} pm_cust      ON p.ID = pm_cust.post_id      AND pm_cust.meta_key      = '_customer_user'
				{$where}
				ORDER BY p.post_date {$order}
				LIMIT %d OFFSET %d",
				...$params
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/** @return array<int, array<string, mixed>> */
	public static function get_order_items( int $order_id ): array {
		global $wpdb;

		$table_items    = $wpdb->prefix . 'woocommerce_order_items';
		$table_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT oi.order_item_id, oi.order_item_name AS name, oi.order_item_type AS type,
				        MAX(CASE WHEN oim.meta_key = '_product_id'   THEN oim.meta_value END) AS product_id,
				        MAX(CASE WHEN oim.meta_key = '_variation_id' THEN oim.meta_value END) AS variation_id,
				        MAX(CASE WHEN oim.meta_key = '_qty'          THEN oim.meta_value END) AS qty,
				        MAX(CASE WHEN oim.meta_key = '_line_total'   THEN oim.meta_value END) AS line_total,
				        MAX(CASE WHEN oim.meta_key = '_line_subtotal' THEN oim.meta_value END) AS line_subtotal
				FROM {$table_items} oi
				LEFT JOIN {$table_itemmeta} oim ON oi.order_item_id = oim.order_item_id
				WHERE oi.order_id = %d AND oi.order_item_type = 'line_item'
				GROUP BY oi.order_item_id",
				$order_id
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/** @return array<int, array<string, mixed>> */
	public static function get_products_list(
		string $status,
		?string $type,
		?string $sku,
		?bool $featured,
		?string $stock_status,
		int $limit,
		int $page,
		string $orderby,
		string $order
	): array {
		global $wpdb;

		$lookup  = $wpdb->prefix . 'wc_product_meta_lookup';
		$allowed = array( 'date', 'id', 'price', 'title', 'stock_quantity' );
		$col_map = array(
			'date'           => 'p.post_date',
			'id'             => 'p.ID',
			'price'          => 'ml.min_price',
			'title'          => 'p.post_title',
			'stock_quantity' => 'ml.stock_quantity',
		);
		$orderby = in_array( $orderby, $allowed, true ) ? $col_map[ $orderby ] : 'p.post_date';
		$order   = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
		$offset  = ( $page - 1 ) * $limit;

		$post_type = null !== $type && 'variation' === $type ? 'product_variation' : 'product';

		$where  = "WHERE p.post_type = '{$post_type}' AND p.post_status = %s";
		$params = array( $status );

		if ( null !== $sku && '' !== $sku ) {
			$where   .= ' AND ml.sku = %s';
			$params[] = $sku;
		}

		if ( null !== $featured ) {
			// featured is stored in postmeta _featured='yes'/'no' — use join only when filtered
			$where   .= ' AND EXISTS (SELECT 1 FROM ' . $wpdb->postmeta . " pfeat WHERE pfeat.post_id = p.ID AND pfeat.meta_key = '_featured' AND pfeat.meta_value = %s)";
			$params[] = $featured ? 'yes' : 'no';
		}

		if ( null !== $stock_status ) {
			$where   .= ' AND ml.stock_status = %s';
			$params[] = $stock_status;
		}

		$params[] = $limit;
		$params[] = $offset;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS id, p.post_title AS name, p.post_name AS slug, p.post_type AS type,
				        p.post_status AS status, p.post_date AS date_created,
				        ml.sku, ml.min_price AS price, ml.max_price,
				        ml.stock_quantity, ml.stock_status,
				        ml.virtual, ml.downloadable
				FROM {$wpdb->posts} p
				INNER JOIN {$lookup} ml ON p.ID = ml.product_id
				{$where}
				ORDER BY {$orderby} {$order}
				LIMIT %d OFFSET %d",
				...$params
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/** @return array<int, array<string, mixed>> */
	public static function get_product_variations( int $parent_id ): array {
		global $wpdb;

		$lookup = $wpdb->prefix . 'wc_product_meta_lookup';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS id, p.post_status AS status,
				        ml.sku, ml.min_price AS price,
				        ml.stock_quantity, ml.stock_status
				FROM {$wpdb->posts} p
				INNER JOIN {$lookup} ml ON p.ID = ml.product_id
				WHERE p.post_type = 'product_variation' AND p.post_parent = %d AND p.post_status != 'trash'
				ORDER BY p.menu_order ASC",
				$parent_id
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		// Attach variation attributes (pa_color, pa_size, etc.)
		foreach ( $rows as &$row ) {
			$row['attributes'] = self::get_variation_attributes( (int) $row['id'] );
		}

		return $rows;
	}

	/** @return array<string, string> */
	private static function get_variation_attributes( int $variation_id ): array {
		global $wpdb;

		$like = $wpdb->esc_like( 'attribute_' ) . '%';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->postmeta}
				WHERE post_id = %d AND meta_key LIKE %s",
				$variation_id,
				$like
			),
			ARRAY_A
		);
		// phpcs:enable

		$attrs = array();
		foreach ( (array) $rows as $row ) {
			$key           = str_replace( 'attribute_', '', (string) $row['meta_key'] );
			$attrs[ $key ] = (string) $row['meta_value'];
		}
		return $attrs;
	}

	/** @return array<int, array<string, mixed>> */
	public static function get_customers_list( int $limit, int $page, string $order ): array {
		global $wpdb;

		$table  = $wpdb->prefix . 'wc_customer_lookup';
		$order  = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
		$offset = ( $page - 1 ) * $limit;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT customer_id, user_id, username, first_name, last_name, email,
				        country, city, postcode, date_last_active, date_registered
				FROM {$table}
				ORDER BY date_registered {$order}
				LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/** @return array<string, mixed>|null */
	public static function get_customer( int $customer_id ): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'wc_customer_lookup';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE customer_id = %d",
				$customer_id
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? $row : null;
	}

	/** @return array<string, mixed> Sales summary for a date range. */
	public static function get_sales_report( string $date_from, string $date_to ): array {
		global $wpdb;

		$stats = $wpdb->prefix . 'wc_order_stats';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS order_count,
				        SUM(total_sales) AS gross_revenue,
				        SUM(net_total) AS net_revenue,
				        SUM(tax_total) AS total_tax,
				        SUM(shipping_total) AS total_shipping,
				        SUM(num_items_sold) AS items_sold
				FROM {$stats}
				WHERE status NOT IN ('wc-cancelled','wc-refunded','wc-failed')
				  AND date_created_gmt BETWEEN %s AND %s",
				$date_from . ' 00:00:00',
				$date_to . ' 23:59:59'
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? $row : array();
	}

	/** @return array<int, array<string, mixed>> Top-selling products by quantity. */
	public static function get_top_products( int $limit, string $date_from, string $date_to ): array {
		global $wpdb;

		$lookup = $wpdb->prefix . 'wc_order_product_lookup';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT opl.product_id,
				        p.post_title AS product_name,
				        SUM(opl.product_qty) AS total_qty_sold,
				        SUM(opl.product_net_revenue) AS total_revenue
				FROM {$lookup} opl
				INNER JOIN {$wpdb->posts} p ON opl.product_id = p.ID
				WHERE opl.date_created BETWEEN %s AND %s
				GROUP BY opl.product_id, p.post_title
				ORDER BY total_qty_sold DESC
				LIMIT %d",
				$date_from . ' 00:00:00',
				$date_to . ' 23:59:59',
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	// -----------------------------------------------------------------
	// Single-product full detail
	// -----------------------------------------------------------------

	/** @return array<string, mixed>|null Full product row including dimensions and prices. */
	public static function get_product( int $product_id ): ?array {
		global $wpdb;

		$lookup = $wpdb->prefix . 'wc_product_meta_lookup';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.ID AS id, p.post_title AS name, p.post_name AS slug,
				        p.post_content AS description, p.post_excerpt AS short_description,
				        p.post_status AS status, p.post_date AS date_created, p.post_modified AS date_modified,
				        ml.sku, ml.min_price AS price, ml.max_price,
				        ml.stock_quantity, ml.stock_status, ml.virtual, ml.downloadable,
				        ml.average_rating, ml.rating_count, ml.total_sales,
				        pm_rp.meta_value AS regular_price,
				        pm_sp.meta_value AS sale_price,
				        pm_w.meta_value  AS weight,
				        pm_l.meta_value  AS length,
				        pm_wi.meta_value AS width,
				        pm_h.meta_value  AS height
				FROM {$wpdb->posts} p
				INNER JOIN {$lookup} ml ON p.ID = ml.product_id
				LEFT JOIN {$wpdb->postmeta} pm_rp ON p.ID = pm_rp.post_id AND pm_rp.meta_key = '_regular_price'
				LEFT JOIN {$wpdb->postmeta} pm_sp ON p.ID = pm_sp.post_id AND pm_sp.meta_key = '_sale_price'
				LEFT JOIN {$wpdb->postmeta} pm_w  ON p.ID = pm_w.post_id  AND pm_w.meta_key  = '_weight'
				LEFT JOIN {$wpdb->postmeta} pm_l  ON p.ID = pm_l.post_id  AND pm_l.meta_key  = '_length'
				LEFT JOIN {$wpdb->postmeta} pm_wi ON p.ID = pm_wi.post_id AND pm_wi.meta_key = '_width'
				LEFT JOIN {$wpdb->postmeta} pm_h  ON p.ID = pm_h.post_id  AND pm_h.meta_key  = '_height'
				WHERE p.ID = %d AND p.post_type IN ('product', 'product_variation')",
				$product_id
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? $row : null;
	}

	// -----------------------------------------------------------------
	// Coupons (post_type = 'shop_coupon')
	// -----------------------------------------------------------------

	/** @return array<int, array<string, mixed>> */
	public static function get_coupons_list(
		int $limit,
		int $page,
		string $status,
		string $order
	): array {
		global $wpdb;

		$order  = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
		$offset = ( $page - 1 ) * $limit;

		$where  = "WHERE p.post_type = 'shop_coupon' AND p.post_status != 'trash'";
		$params = array();

		if ( '' !== $status && 'any' !== $status ) {
			$where   .= ' AND p.post_status = %s';
			$params[] = $status;
		}

		$params[] = $limit;
		$params[] = $offset;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS id, p.post_title AS code, p.post_excerpt AS description,
				        p.post_status AS status, p.post_date AS date_created,
				        MAX(CASE WHEN pm.meta_key = 'discount_type'     THEN pm.meta_value END) AS discount_type,
				        MAX(CASE WHEN pm.meta_key = 'coupon_amount'     THEN pm.meta_value END) AS amount,
				        MAX(CASE WHEN pm.meta_key = 'date_expires'      THEN pm.meta_value END) AS date_expires,
				        MAX(CASE WHEN pm.meta_key = 'usage_count'       THEN pm.meta_value END) AS usage_count,
				        MAX(CASE WHEN pm.meta_key = 'usage_limit'       THEN pm.meta_value END) AS usage_limit,
				        MAX(CASE WHEN pm.meta_key = 'individual_use'    THEN pm.meta_value END) AS individual_use,
				        MAX(CASE WHEN pm.meta_key = 'free_shipping'     THEN pm.meta_value END) AS free_shipping,
				        MAX(CASE WHEN pm.meta_key = 'minimum_amount'    THEN pm.meta_value END) AS minimum_amount,
				        MAX(CASE WHEN pm.meta_key = 'maximum_amount'    THEN pm.meta_value END) AS maximum_amount
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				{$where}
				GROUP BY p.ID
				ORDER BY p.post_date {$order}
				LIMIT %d OFFSET %d",
				...$params
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/** @return array<string, mixed>|null */
	public static function get_coupon( int $coupon_id ): ?array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.ID AS id, p.post_title AS code, p.post_excerpt AS description,
				        p.post_status AS status, p.post_date AS date_created, p.post_modified AS date_modified,
				        MAX(CASE WHEN pm.meta_key = 'discount_type'           THEN pm.meta_value END) AS discount_type,
				        MAX(CASE WHEN pm.meta_key = 'coupon_amount'           THEN pm.meta_value END) AS amount,
				        MAX(CASE WHEN pm.meta_key = 'date_expires'            THEN pm.meta_value END) AS date_expires,
				        MAX(CASE WHEN pm.meta_key = 'usage_count'             THEN pm.meta_value END) AS usage_count,
				        MAX(CASE WHEN pm.meta_key = 'usage_limit'             THEN pm.meta_value END) AS usage_limit,
				        MAX(CASE WHEN pm.meta_key = 'usage_limit_per_user'    THEN pm.meta_value END) AS usage_limit_per_user,
				        MAX(CASE WHEN pm.meta_key = 'individual_use'          THEN pm.meta_value END) AS individual_use,
				        MAX(CASE WHEN pm.meta_key = 'free_shipping'           THEN pm.meta_value END) AS free_shipping,
				        MAX(CASE WHEN pm.meta_key = 'exclude_sale_items'      THEN pm.meta_value END) AS exclude_sale_items,
				        MAX(CASE WHEN pm.meta_key = 'minimum_amount'          THEN pm.meta_value END) AS minimum_amount,
				        MAX(CASE WHEN pm.meta_key = 'maximum_amount'          THEN pm.meta_value END) AS maximum_amount
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.ID = %d AND p.post_type = 'shop_coupon'
				GROUP BY p.ID",
				$coupon_id
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? $row : null;
	}
}
