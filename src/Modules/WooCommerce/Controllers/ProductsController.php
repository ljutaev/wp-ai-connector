<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\WooCommerce\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

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
						'status'   => array(
							'type'    => 'string',
							'default' => 'publish',
						),
						'type'     => array( 'type' => 'string' ),
						'category' => array( 'type' => 'string' ),
						'sku'      => array( 'type' => 'string' ),
						'featured' => array( 'type' => 'boolean' ),
						'limit'    => array(
							'type'    => 'integer',
							'default' => 20,
						),
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
						),
						'orderby'  => array(
							'type'    => 'string',
							'default' => 'date',
						),
						'order'    => array(
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
						'id'                 => array( 'type' => 'integer', 'required' => true ),
						'name'               => array( 'type' => 'string' ),
						'regular_price'      => array( 'type' => 'string' ),
						'sale_price'         => array( 'type' => 'string' ),
						'description'        => array( 'type' => 'string' ),
						'short_description'  => array( 'type' => 'string' ),
						'status'             => array( 'type' => 'string' ),
						'stock_quantity'     => array( 'type' => 'integer' ),
						'manage_stock'       => array( 'type' => 'boolean' ),
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

	public function get_items( mixed $request ): WP_REST_Response {
		$args = array(
			'status'  => sanitize_key( (string) $request->get_param( 'status' ) ),
			'limit'   => min( (int) $request->get_param( 'limit' ), 100 ),
			'page'    => (int) $request->get_param( 'page' ),
			'orderby' => sanitize_key( (string) $request->get_param( 'orderby' ) ),
			'order'   => strtoupper( sanitize_key( (string) $request->get_param( 'order' ) ) ),
		);

		$type = $request->get_param( 'type' );
		if ( null !== $type ) {
			$args['type'] = sanitize_key( (string) $type );
		}

		$category = $request->get_param( 'category' );
		if ( null !== $category ) {
			$args['category'] = array( sanitize_key( (string) $category ) );
		}

		$sku = $request->get_param( 'sku' );
		if ( null !== $sku ) {
			$args['sku'] = sanitize_text_field( (string) $sku );
		}

		$featured = $request->get_param( 'featured' );
		if ( null !== $featured ) {
			$args['featured'] = (bool) $featured;
		}

		$products = wc_get_products( $args );
		$items    = array();

		foreach ( $products as $product ) {
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}
			$items[] = $this->prepare_product( $product );
		}

		return new WP_REST_Response( $items, 200 );
	}

	public function get_item( mixed $request ): WP_REST_Response|\WP_Error {
		$product = wc_get_product( (int) $request->get_param( 'id' ) );

		if ( false === $product || ! $product instanceof \WC_Product ) {
			return ErrorResponse::not_found( 'Product not found.' );
		}

		$data = $this->prepare_product( $product );

		// Add full description and stock details in single view.
		$data['description']       = $product->get_description();
		$data['short_description'] = $product->get_short_description();
		$data['stock_quantity']    = $product->get_stock_quantity();
		$data['manage_stock']      = $product->managing_stock();
		$data['backorders']        = $product->get_backorders();
		$data['weight']            = $product->get_weight();
		$data['dimensions']        = array(
			'length' => $product->get_length(),
			'width'  => $product->get_width(),
			'height' => $product->get_height(),
		);

		$response = new WP_REST_Response( $data, 200 );

		return $this->enrich_links(
			$response,
			array( 'self' => rest_url( "{$this->namespace}/woocommerce/products/{$product->get_id()}" ) )
		);
	}

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

	/** @return array<string, mixed> */
	private function prepare_product( \WC_Product $product ): array {
		return array(
			'id'            => $product->get_id(),
			'name'          => $product->get_name(),
			'slug'          => $product->get_slug(),
			'type'          => $product->get_type(),
			'status'        => $product->get_status(),
			'sku'           => $product->get_sku(),
			'price'         => $product->get_price(),
			'regular_price' => $product->get_regular_price(),
			'sale_price'    => $product->get_sale_price(),
			'on_sale'       => $product->is_on_sale(),
			'stock_status'  => $product->get_stock_status(),
			'total_sales'   => $product->get_total_sales(),
			'categories'    => array_map(
				static fn ( $term ) => array( 'id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug ),
				get_the_terms( $product->get_id(), 'product_cat' ) ?: array()
			),
			'_links'        => array(
				'self' => rest_url( "{$this->namespace}/woocommerce/products/{$product->get_id()}" ),
			),
		);
	}
}
