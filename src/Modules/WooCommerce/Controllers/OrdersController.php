<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\WooCommerce\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

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
							'default' => 'date',
						),
						'order'       => array(
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
			)
		);
	}

	public function permissions_check( mixed $request ): bool|\WP_Error {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return ErrorResponse::forbidden_capability( 'manage_woocommerce' );
		}

		return true;
	}

	public function get_items( mixed $request ): WP_REST_Response {
		$args = array(
			'limit'   => min( (int) $request->get_param( 'limit' ), 100 ),
			'page'    => (int) $request->get_param( 'page' ),
			'orderby' => sanitize_key( (string) $request->get_param( 'orderby' ) ),
			'order'   => strtoupper( sanitize_key( (string) $request->get_param( 'order' ) ) ),
		);

		$status = $request->get_param( 'status' );
		if ( null !== $status && 'any' !== $status ) {
			$args['status'] = sanitize_key( (string) $status );
		}

		$customer_id = $request->get_param( 'customer_id' );
		if ( null !== $customer_id ) {
			$args['customer_id'] = (int) $customer_id;
		}

		$orders = wc_get_orders( $args );
		$items  = array();

		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			$items[] = $this->prepare_order( $order );
		}

		return new WP_REST_Response( $items, 200 );
	}

	public function get_item( mixed $request ): WP_REST_Response|\WP_Error {
		$order = wc_get_order( (int) $request->get_param( 'id' ) );

		if ( false === $order || ! $order instanceof \WC_Order ) {
			return ErrorResponse::not_found( 'Order not found.' );
		}

		$data = $this->prepare_order( $order );

		// Include line items in single-order view.
		$line_items = array();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$line_items[] = array(
				'product_id' => $item->get_product_id(),
				'name'       => $item->get_name(),
				'quantity'   => $item->get_quantity(),
				'subtotal'   => $item->get_subtotal(),
				'total'      => $item->get_total(),
				'sku'        => $item->get_product() ? $item->get_product()->get_sku() : '',
			);
		}
		$data['line_items'] = $line_items;

		$response = new WP_REST_Response( $data, 200 );

		return $this->enrich_links(
			$response,
			array( 'self' => rest_url( "{$this->namespace}/woocommerce/orders/{$order->get_id()}" ) )
		);
	}

	/** @return array<string, mixed> */
	private function prepare_order( \WC_Order $order ): array {
		return array(
			'id'              => $order->get_id(),
			'status'          => $order->get_status(),
			'currency'        => $order->get_currency(),
			'total'           => $order->get_total(),
			'subtotal'        => $order->get_subtotal(),
			'total_tax'       => $order->get_total_tax(),
			'shipping_total'  => $order->get_shipping_total(),
			'customer_id'     => $order->get_customer_id(),
			'billing_email'   => $order->get_billing_email(),
			'billing_name'    => $order->get_formatted_billing_full_name(),
			'payment_method'  => $order->get_payment_method_title(),
			'date_created'    => $order->get_date_created()?->format( 'Y-m-d H:i:s' ),
			'date_modified'   => $order->get_date_modified()?->format( 'Y-m-d H:i:s' ),
			'item_count'      => $order->get_item_count(),
			'_links'          => array(
				'self' => rest_url( "{$this->namespace}/woocommerce/orders/{$order->get_id()}" ),
			),
		);
	}
}
