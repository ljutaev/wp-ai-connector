<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\WooCommerce\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\Modules\WooCommerce\WcDb;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

/**
 * GET  /woocommerce/orders              — list (direct SQL, zero-hydration)
 * GET  /woocommerce/orders/{id}         — single + line items (direct SQL)
 * POST /woocommerce/orders              — create (WC functions for hook integrity)
 * POST /woocommerce/orders/{id}         — update status (WC function)
 * GET  /woocommerce/orders/{id}/notes   — order notes
 * POST /woocommerce/orders/{id}/notes   — add note
 */
final class OrdersController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/woocommerce/orders',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'status'      => array(
							'type'    => 'string',
							'default' => 'any',
						),
						'customer_id' => array( 'type' => 'integer' ),
						'limit'       => array(
							'type'    => 'integer',
							'default' => 20,
						),
						'page'        => array(
							'type'    => 'integer',
							'default' => 1,
						),
						'orderby'     => array(
							'type'    => 'string',
							'default' => 'date_created_gmt',
						),
						'order'       => array(
							'type'    => 'string',
							'default' => 'DESC',
							'enum'    => array( 'ASC', 'DESC' ),
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'write_permissions_check' ),
					'args'                => array(
						'status'             => array(
							'type'    => 'string',
							'default' => 'pending',
						),
						'customer_id'        => array(
							'type'    => 'integer',
							'default' => 0,
						),
						'billing_email'      => array( 'type' => 'string' ),
						'billing_first_name' => array( 'type' => 'string' ),
						'billing_last_name'  => array( 'type' => 'string' ),
						'note'               => array( 'type' => 'string' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/woocommerce/orders/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'write_permissions_check' ),
					'args'                => array(
						'id'     => array(
							'type'     => 'integer',
							'required' => true,
						),
						'status' => array( 'type' => 'string' ),
						'note'   => array( 'type' => 'string' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/woocommerce/orders/(?P<id>\d+)/notes',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_notes' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_note' ),
					'permission_callback' => array( $this, 'write_permissions_check' ),
					'args'                => array(
						'id'       => array(
							'type'     => 'integer',
							'required' => true,
						),
						'note'     => array(
							'type'     => 'string',
							'required' => true,
						),
						'customer' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);
	}

	public function permissions_check( mixed $request ): bool|\WP_Error {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return ErrorResponse::forbidden_capability( 'manage_woocommerce' );
		}
		return true;
	}

	public function write_permissions_check( mixed $request ): bool|\WP_Error {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return ErrorResponse::forbidden_capability( 'manage_woocommerce' );
		}
		return true;
	}

	/** Direct SQL list — zero-hydration. */
	public function get_items( mixed $request ): WP_REST_Response {
		$status      = sanitize_key( (string) $request->get_param( 'status' ) );
		$limit       = min( (int) $request->get_param( 'limit' ), 100 );
		$page        = max( 1, (int) $request->get_param( 'page' ) );
		$customer_id = $request->get_param( 'customer_id' ) !== null ? (int) $request->get_param( 'customer_id' ) : null;
		$orderby     = sanitize_key( (string) $request->get_param( 'orderby' ) );
		$order       = sanitize_key( (string) $request->get_param( 'order' ) );

		if ( WcDb::hpos_enabled() ) {
			$rows = WcDb::get_orders_hpos( $status, $limit, $page, $customer_id, $orderby, $order );
		} else {
			$rows = WcDb::get_orders_legacy( $status, $limit, $page, $customer_id, $order );
		}

		$items = array_map( array( $this, 'format_order_row' ), $rows );

		return new WP_REST_Response( $items, 200 );
	}

	/** Direct SQL single order + line items. */
	public function get_item( mixed $request ): WP_REST_Response|\WP_Error {
		$id = (int) $request->get_param( 'id' );

		// Fetch single order using same direct-SQL path.
		if ( WcDb::hpos_enabled() ) {
			$rows = WcDb::get_orders_hpos( 'any', 1, 1, null, 'id', 'DESC' );
			global $wpdb;
			$table_orders    = $wpdb->prefix . 'wc_orders';
			$table_addresses = $wpdb->prefix . 'wc_order_addresses';
			$table_ops       = $wpdb->prefix . 'wc_order_operational_data';
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT o.id, o.status, o.currency, o.total_amount AS total,
					        o.customer_id, o.date_created_gmt,
					        ba.first_name AS billing_first_name, ba.last_name AS billing_last_name,
					        ba.email AS billing_email, ba.phone AS billing_phone,
					        ba.address_1 AS billing_address_1, ba.address_2 AS billing_address_2,
					        ba.city AS billing_city, ba.state AS billing_state,
					        ba.postcode AS billing_postcode, ba.country AS billing_country,
					        op.payment_method_title AS payment_method
					FROM {$table_orders} o
					LEFT JOIN {$table_addresses} ba ON o.id = ba.order_id AND ba.address_type = 'billing'
					LEFT JOIN {$table_ops} op ON o.id = op.order_id
					WHERE o.id = %d AND o.type = 'shop_order'",
					$id
				),
				ARRAY_A
			);
			// phpcs:enable
		} else {
			global $wpdb;
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row(
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
					        pm_addr1.meta_value AS billing_address_1,
					        pm_city.meta_value AS billing_city,
					        pm_state.meta_value AS billing_state,
					        pm_zip.meta_value AS billing_postcode,
					        pm_country.meta_value AS billing_country,
					        pm_payment.meta_value AS payment_method,
					        COALESCE(pm_cust.meta_value, 0) AS customer_id
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->postmeta} pm_total    ON p.ID = pm_total.post_id    AND pm_total.meta_key    = '_order_total'
					LEFT JOIN {$wpdb->postmeta} pm_currency ON p.ID = pm_currency.post_id AND pm_currency.meta_key = '_order_currency'
					LEFT JOIN {$wpdb->postmeta} pm_email    ON p.ID = pm_email.post_id    AND pm_email.meta_key    = '_billing_email'
					LEFT JOIN {$wpdb->postmeta} pm_fname    ON p.ID = pm_fname.post_id    AND pm_fname.meta_key    = '_billing_first_name'
					LEFT JOIN {$wpdb->postmeta} pm_lname    ON p.ID = pm_lname.post_id    AND pm_lname.meta_key    = '_billing_last_name'
					LEFT JOIN {$wpdb->postmeta} pm_phone    ON p.ID = pm_phone.post_id    AND pm_phone.meta_key    = '_billing_phone'
					LEFT JOIN {$wpdb->postmeta} pm_addr1    ON p.ID = pm_addr1.post_id    AND pm_addr1.meta_key    = '_billing_address_1'
					LEFT JOIN {$wpdb->postmeta} pm_city     ON p.ID = pm_city.post_id     AND pm_city.meta_key     = '_billing_city'
					LEFT JOIN {$wpdb->postmeta} pm_state    ON p.ID = pm_state.post_id    AND pm_state.meta_key    = '_billing_state'
					LEFT JOIN {$wpdb->postmeta} pm_zip      ON p.ID = pm_zip.post_id      AND pm_zip.meta_key      = '_billing_postcode'
					LEFT JOIN {$wpdb->postmeta} pm_country  ON p.ID = pm_country.post_id  AND pm_country.meta_key  = '_billing_country'
					LEFT JOIN {$wpdb->postmeta} pm_payment  ON p.ID = pm_payment.post_id  AND pm_payment.meta_key  = '_payment_method_title'
					LEFT JOIN {$wpdb->postmeta} pm_cust     ON p.ID = pm_cust.post_id     AND pm_cust.meta_key     = '_customer_user'
					WHERE p.ID = %d AND p.post_type = 'shop_order'",
					$id
				),
				ARRAY_A
			);
			// phpcs:enable
		}

		if ( ! is_array( $row ) ) {
			return ErrorResponse::not_found( 'Order not found.' );
		}

		$data               = $this->format_order_row( $row );
		$data['line_items'] = WcDb::get_order_items( $id );

		$response = new WP_REST_Response( $data, 200 );
		return $this->enrich_links(
			$response,
			array( 'self' => rest_url( "{$this->namespace}/woocommerce/orders/{$id}" ) )
		);
	}

	/** Create order via WC functions (preserves hooks, emails, stock). */
	public function create_item( mixed $request ): WP_REST_Response|\WP_Error {
		$order = wc_create_order(
			array(
				'status'      => sanitize_key( (string) $request->get_param( 'status' ) ),
				'customer_id' => (int) $request->get_param( 'customer_id' ),
			)
		);

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$billing_email = $request->get_param( 'billing_email' );
		if ( null !== $billing_email ) {
			$order->set_billing_email( sanitize_email( (string) $billing_email ) );
		}

		$first = $request->get_param( 'billing_first_name' );
		if ( null !== $first ) {
			$order->set_billing_first_name( sanitize_text_field( (string) $first ) );
		}

		$last = $request->get_param( 'billing_last_name' );
		if ( null !== $last ) {
			$order->set_billing_last_name( sanitize_text_field( (string) $last ) );
		}

		$order->save();

		$note = $request->get_param( 'note' );
		if ( null !== $note ) {
			$order->add_order_note( sanitize_textarea_field( (string) $note ), false, true );
		}

		return new WP_REST_Response(
			array(
				'id'     => $order->get_id(),
				'status' => $order->get_status(),
			),
			201
		);
	}

	/** Update status/note via WC functions. */
	public function update_item( mixed $request ): WP_REST_Response|\WP_Error {
		$order = wc_get_order( (int) $request->get_param( 'id' ) );

		if ( false === $order || ! $order instanceof \WC_Order ) {
			return ErrorResponse::not_found( 'Order not found.' );
		}

		$status = $request->get_param( 'status' );
		if ( null !== $status ) {
			$order->update_status( sanitize_key( (string) $status ) );
		}

		$note = $request->get_param( 'note' );
		if ( null !== $note ) {
			$order->add_order_note( sanitize_textarea_field( (string) $note ), false, true );
		}

		return new WP_REST_Response(
			array(
				'id'     => $order->get_id(),
				'status' => $order->get_status(),
			),
			200
		);
	}

	public function get_notes( mixed $request ): WP_REST_Response|\WP_Error {
		$order = wc_get_order( (int) $request->get_param( 'id' ) );

		if ( false === $order || ! $order instanceof \WC_Order ) {
			return ErrorResponse::not_found( 'Order not found.' );
		}

		$notes = wc_get_order_notes( array( 'order_id' => $order->get_id() ) );
		$items = array();

		foreach ( (array) $notes as $note ) {
			$items[] = array(
				'id'           => $note->comment_ID,
				'note'         => $note->comment_content,
				'customer'     => (bool) $note->comment_approved,
				'date_created' => $note->comment_date,
				'author'       => $note->comment_author,
			);
		}

		return new WP_REST_Response( $items, 200 );
	}

	public function add_note( mixed $request ): WP_REST_Response|\WP_Error {
		$order = wc_get_order( (int) $request->get_param( 'id' ) );

		if ( false === $order || ! $order instanceof \WC_Order ) {
			return ErrorResponse::not_found( 'Order not found.' );
		}

		$note_id = $order->add_order_note(
			sanitize_textarea_field( (string) $request->get_param( 'note' ) ),
			(bool) $request->get_param( 'customer' ),
			true
		);

		return new WP_REST_Response( array( 'note_id' => $note_id ), 201 );
	}

	/** @param array<string, mixed> $row */
	private function format_order_row( array $row ): array {
		return array(
			'id'             => (int) $row['id'],
			'status'         => WcDb::normalise_status( (string) $row['status'] ),
			'currency'       => (string) ( $row['currency'] ?? 'USD' ),
			'total'          => (string) ( $row['total'] ?? '0.00' ),
			'customer_id'    => (int) ( $row['customer_id'] ?? 0 ),
			'billing_email'  => (string) ( $row['billing_email'] ?? '' ),
			'billing_name'   => trim( (string) ( $row['billing_first_name'] ?? '' ) . ' ' . (string) ( $row['billing_last_name'] ?? '' ) ),
			'billing_phone'  => (string) ( $row['billing_phone'] ?? '' ),
			'payment_method' => (string) ( $row['payment_method'] ?? '' ),
			'date_created'   => (string) ( $row['date_created_gmt'] ?? '' ),
			'_links'         => array(
				'self' => rest_url( "{$this->namespace}/woocommerce/orders/{$row['id']}" ),
			),
		);
	}
}
