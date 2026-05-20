<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\WooCommerce\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\Modules\WooCommerce\WcDb;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

/**
 * GET /woocommerce/customers       — list (direct SQL from wp_wc_customer_lookup)
 * GET /woocommerce/customers/{id}  — single customer
 */
final class CustomersController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/woocommerce/customers',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'limit' => array(
							'type'    => 'integer',
							'default' => 20,
						),
						'page'  => array(
							'type'    => 'integer',
							'default' => 1,
						),
						'order' => array(
							'type'    => 'string',
							'default' => 'DESC',
							'enum'    => array( 'ASC', 'DESC' ),
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/woocommerce/customers/(?P<id>\d+)',
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
			)
		);
	}

	public function permissions_check( mixed $request ): bool|\WP_Error {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return ErrorResponse::forbidden_capability( 'manage_woocommerce' );
		}
		return true;
	}

	/** Direct SQL — wp_wc_customer_lookup (zero-hydration). */
	public function get_items( mixed $request ): WP_REST_Response {
		$limit = min( (int) $request->get_param( 'limit' ), 100 );
		$page  = max( 1, (int) $request->get_param( 'page' ) );
		$order = sanitize_key( (string) $request->get_param( 'order' ) );

		$rows  = WcDb::get_customers_list( $limit, $page, $order );
		$items = array_map( array( $this, 'format_customer_row' ), $rows );

		return new WP_REST_Response( $items, 200 );
	}

	/** Direct SQL single customer. */
	public function get_item( mixed $request ): WP_REST_Response|\WP_Error {
		$id  = (int) $request->get_param( 'id' );
		$row = WcDb::get_customer( $id );

		if ( null === $row ) {
			return ErrorResponse::not_found( 'Customer not found.' );
		}

		$data           = $this->format_customer_row( $row );
		$data['_links'] = array(
			'self' => rest_url( "{$this->namespace}/woocommerce/customers/{$id}" ),
		);

		return new WP_REST_Response( $data, 200 );
	}

	/** @param array<string, mixed> $row */
	private function format_customer_row( array $row ): array {
		return array(
			'customer_id'      => (int) ( $row['customer_id'] ?? 0 ),
			'user_id'          => (int) ( $row['user_id'] ?? 0 ),
			'username'         => (string) ( $row['username'] ?? '' ),
			'first_name'       => (string) ( $row['first_name'] ?? '' ),
			'last_name'        => (string) ( $row['last_name'] ?? '' ),
			'email'            => (string) ( $row['email'] ?? '' ),
			'country'          => (string) ( $row['country'] ?? '' ),
			'city'             => (string) ( $row['city'] ?? '' ),
			'postcode'         => (string) ( $row['postcode'] ?? '' ),
			'date_registered'  => (string) ( $row['date_registered'] ?? '' ),
			'date_last_active' => (string) ( $row['date_last_active'] ?? '' ),
			'_links'           => array(
				'self' => rest_url( "{$this->namespace}/woocommerce/customers/{$row['customer_id']}" ),
			),
		);
	}
}
