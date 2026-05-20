<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\WooCommerce\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\Modules\WooCommerce\WcDb;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

/**
 * GET  /woocommerce/products                    — list (direct SQL, zero-hydration)
 * GET  /woocommerce/products/{id}               — single + full details (direct SQL)
 * POST /woocommerce/products/{id}               — update (WC functions for hook integrity)
 * GET  /woocommerce/products/{id}/variations    — list variations (direct SQL)
 */
final class ProductsController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/woocommerce/products',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'status'       => array(
							'type'    => 'string',
							'default' => 'publish',
						),
						'sku'          => array( 'type' => 'string' ),
						'stock_status' => array(
							'type' => 'string',
							'enum' => array( 'instock', 'outofstock', 'onbackorder' ),
						),
						'featured'     => array( 'type' => 'boolean' ),
						'limit'        => array(
							'type'    => 'integer',
							'default' => 20,
						),
						'page'         => array(
							'type'    => 'integer',
							'default' => 1,
						),
						'orderby'      => array(
							'type'    => 'string',
							'default' => 'date',
						),
						'order'        => array(
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
			'/woocommerce/products/(?P<id>\d+)',
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
						'id'                => array(
							'type'     => 'integer',
							'required' => true,
						),
						'name'              => array( 'type' => 'string' ),
						'regular_price'     => array( 'type' => 'string' ),
						'sale_price'        => array( 'type' => 'string' ),
						'description'       => array( 'type' => 'string' ),
						'short_description' => array( 'type' => 'string' ),
						'status'            => array( 'type' => 'string' ),
						'stock_quantity'    => array( 'type' => 'integer' ),
						'manage_stock'      => array( 'type' => 'boolean' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/woocommerce/products/(?P<id>\d+)/variations',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_variations' ),
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

	public function write_permissions_check( mixed $request ): bool|\WP_Error {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return ErrorResponse::forbidden_capability( 'manage_woocommerce' );
		}
		return true;
	}

	/** Direct SQL list — zero-hydration via wp_wc_product_meta_lookup. */
	public function get_items( mixed $request ): WP_REST_Response {
		$status       = sanitize_key( (string) $request->get_param( 'status' ) );
		$limit        = min( (int) $request->get_param( 'limit' ), 100 );
		$page         = max( 1, (int) $request->get_param( 'page' ) );
		$orderby      = sanitize_key( (string) $request->get_param( 'orderby' ) );
		$order        = sanitize_key( (string) $request->get_param( 'order' ) );
		$sku          = $request->get_param( 'sku' );
		$stock_status = $request->get_param( 'stock_status' );
		$featured     = $request->get_param( 'featured' );

		$rows  = WcDb::get_products_list(
			$status,
			null,
			null !== $sku ? sanitize_text_field( (string) $sku ) : null,
			null !== $featured ? (bool) $featured : null,
			null !== $stock_status ? sanitize_key( (string) $stock_status ) : null,
			$limit,
			$page,
			$orderby,
			$order
		);
		$items = array_map( array( $this, 'format_product_row' ), $rows );

		return new WP_REST_Response( $items, 200 );
	}

	/** Direct SQL single product — full detail including dimensions and prices. */
	public function get_item( mixed $request ): WP_REST_Response|\WP_Error {
		$id  = (int) $request->get_param( 'id' );
		$row = WcDb::get_product( $id );

		if ( null === $row ) {
			return ErrorResponse::not_found( 'Product not found.' );
		}

		$data = array(
			'id'                => (int) $row['id'],
			'name'              => (string) ( $row['name'] ?? '' ),
			'slug'              => (string) ( $row['slug'] ?? '' ),
			'status'            => (string) ( $row['status'] ?? '' ),
			'sku'               => (string) ( $row['sku'] ?? '' ),
			'price'             => (string) ( $row['price'] ?? '0' ),
			'max_price'         => (string) ( $row['max_price'] ?? '0' ),
			'regular_price'     => (string) ( $row['regular_price'] ?? '' ),
			'sale_price'        => (string) ( $row['sale_price'] ?? '' ),
			'stock_status'      => (string) ( $row['stock_status'] ?? 'instock' ),
			'stock_quantity'    => null !== $row['stock_quantity'] ? (int) $row['stock_quantity'] : null,
			'virtual'           => (bool) $row['virtual'],
			'downloadable'      => (bool) $row['downloadable'],
			'average_rating'    => (string) ( $row['average_rating'] ?? '0.00' ),
			'rating_count'      => (int) ( $row['rating_count'] ?? 0 ),
			'total_sales'       => (int) ( $row['total_sales'] ?? 0 ),
			'description'       => (string) ( $row['description'] ?? '' ),
			'short_description' => (string) ( $row['short_description'] ?? '' ),
			'date_created'      => (string) ( $row['date_created'] ?? '' ),
			'date_modified'     => (string) ( $row['date_modified'] ?? '' ),
			'dimensions'        => array(
				'weight' => (string) ( $row['weight'] ?? '' ),
				'length' => (string) ( $row['length'] ?? '' ),
				'width'  => (string) ( $row['width'] ?? '' ),
				'height' => (string) ( $row['height'] ?? '' ),
			),
			'_links'            => array(
				'self' => rest_url( "{$this->namespace}/woocommerce/products/{$id}" ),
			),
		);

		return new WP_REST_Response( $data, 200 );
	}

	/** Update product via WC functions — preserves hooks, stock recalc, cache. */
	public function update_item( mixed $request ): WP_REST_Response|\WP_Error {
		$product = wc_get_product( (int) $request->get_param( 'id' ) );

		if ( false === $product || ! $product instanceof \WC_Product ) {
			return ErrorResponse::not_found( 'Product not found.' );
		}

		$name = $request->get_param( 'name' );
		if ( null !== $name ) {
			$product->set_name( sanitize_text_field( (string) $name ) );
		}

		$regular_price = $request->get_param( 'regular_price' );
		if ( null !== $regular_price ) {
			$product->set_regular_price( wc_format_decimal( (string) $regular_price ) );
		}

		$sale_price = $request->get_param( 'sale_price' );
		if ( null !== $sale_price ) {
			$product->set_sale_price( wc_format_decimal( (string) $sale_price ) );
		}

		$description = $request->get_param( 'description' );
		if ( null !== $description ) {
			$product->set_description( wp_kses_post( (string) $description ) );
		}

		$short_description = $request->get_param( 'short_description' );
		if ( null !== $short_description ) {
			$product->set_short_description( wp_kses_post( (string) $short_description ) );
		}

		$status = $request->get_param( 'status' );
		if ( null !== $status ) {
			$product->set_status( sanitize_key( (string) $status ) );
		}

		$stock_quantity = $request->get_param( 'stock_quantity' );
		if ( null !== $stock_quantity ) {
			$product->set_stock_quantity( (int) $stock_quantity );
		}

		$manage_stock = $request->get_param( 'manage_stock' );
		if ( null !== $manage_stock ) {
			$product->set_manage_stock( (bool) $manage_stock );
		}

		$product->save();

		return $this->get_item( $request );
	}

	/** Direct SQL variations list with attributes. */
	public function get_variations( mixed $request ): WP_REST_Response|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$rows = WcDb::get_product_variations( $id );

		return new WP_REST_Response( $rows, 200 );
	}

	/** @param array<string, mixed> $row */
	private function format_product_row( array $row ): array {
		return array(
			'id'           => (int) $row['id'],
			'name'         => (string) ( $row['name'] ?? '' ),
			'slug'         => (string) ( $row['slug'] ?? '' ),
			'status'       => (string) ( $row['status'] ?? '' ),
			'sku'          => (string) ( $row['sku'] ?? '' ),
			'price'        => (string) ( $row['price'] ?? '0' ),
			'max_price'    => (string) ( $row['max_price'] ?? '0' ),
			'stock_status' => (string) ( $row['stock_status'] ?? 'instock' ),
			'virtual'      => (bool) $row['virtual'],
			'downloadable' => (bool) $row['downloadable'],
			'date_created' => (string) ( $row['date_created'] ?? '' ),
			'_links'       => array(
				'self' => rest_url( "{$this->namespace}/woocommerce/products/{$row['id']}" ),
			),
		);
	}
}
