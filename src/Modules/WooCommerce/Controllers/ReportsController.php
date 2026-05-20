<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\WooCommerce\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\Modules\WooCommerce\WcDb;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

/**
 * GET /woocommerce/reports/sales        — aggregated revenue summary (wp_wc_order_stats)
 * GET /woocommerce/reports/top-products — top-selling products by qty (wp_wc_order_product_lookup)
 */
final class ReportsController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/woocommerce/reports/sales',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_sales' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'date_from' => array(
							'type'     => 'string',
							'required' => true,
						),
						'date_to'   => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/woocommerce/reports/top-products',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_top_products' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'date_from' => array(
							'type'     => 'string',
							'required' => true,
						),
						'date_to'   => array(
							'type'     => 'string',
							'required' => true,
						),
						'limit'     => array(
							'type'    => 'integer',
							'default' => 10,
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

	/** Sales summary from wp_wc_order_stats — excludes cancelled/refunded/failed. */
	public function get_sales( mixed $request ): WP_REST_Response|\WP_Error {
		$date_from = sanitize_text_field( (string) $request->get_param( 'date_from' ) );
		$date_to   = sanitize_text_field( (string) $request->get_param( 'date_to' ) );

		if ( ! $this->valid_date( $date_from ) || ! $this->valid_date( $date_to ) ) {
			return ErrorResponse::validation( 'date_from and date_to must be YYYY-MM-DD format.', 'date_from' );
		}

		$row = WcDb::get_sales_report( $date_from, $date_to );

		return new WP_REST_Response(
			array(
				'date_from'      => $date_from,
				'date_to'        => $date_to,
				'order_count'    => (int) ( $row['order_count'] ?? 0 ),
				'gross_revenue'  => number_format( (float) ( $row['gross_revenue'] ?? 0 ), 2, '.', '' ),
				'net_revenue'    => number_format( (float) ( $row['net_revenue'] ?? 0 ), 2, '.', '' ),
				'total_tax'      => number_format( (float) ( $row['total_tax'] ?? 0 ), 2, '.', '' ),
				'total_shipping' => number_format( (float) ( $row['total_shipping'] ?? 0 ), 2, '.', '' ),
				'items_sold'     => (int) ( $row['items_sold'] ?? 0 ),
			),
			200
		);
	}

	/** Top-selling products by qty from wp_wc_order_product_lookup. */
	public function get_top_products( mixed $request ): WP_REST_Response|\WP_Error {
		$date_from = sanitize_text_field( (string) $request->get_param( 'date_from' ) );
		$date_to   = sanitize_text_field( (string) $request->get_param( 'date_to' ) );
		$limit     = min( (int) $request->get_param( 'limit' ), 50 );

		if ( ! $this->valid_date( $date_from ) || ! $this->valid_date( $date_to ) ) {
			return ErrorResponse::validation( 'date_from and date_to must be YYYY-MM-DD format.', 'date_from' );
		}

		$rows  = WcDb::get_top_products( $limit, $date_from, $date_to );
		$items = array_map(
			function ( array $row ): array {
				return array(
					'product_id'    => (int) $row['product_id'],
					'product_name'  => (string) ( $row['product_name'] ?? '' ),
					'qty_sold'      => (int) ( $row['total_qty_sold'] ?? 0 ),
					'total_revenue' => number_format( (float) ( $row['total_revenue'] ?? 0 ), 2, '.', '' ),
					'_links'        => array(
						'product' => rest_url( "{$this->namespace}/woocommerce/products/{$row['product_id']}" ),
					),
				);
			},
			$rows
		);

		return new WP_REST_Response(
			array(
				'date_from' => $date_from,
				'date_to'   => $date_to,
				'items'     => $items,
			),
			200
		);
	}

	private function valid_date( string $date ): bool {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}
		$parts = explode( '-', $date );
		return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] );
	}
}
