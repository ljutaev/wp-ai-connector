<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\WooCommerce\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\Modules\WooCommerce\WcDb;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

/**
 * GET  /woocommerce/coupons        — list (direct SQL, zero-hydration)
 * GET  /woocommerce/coupons/{id}   — single coupon (direct SQL)
 * POST /woocommerce/coupons        — create (WC_Coupon::save for hook integrity)
 * POST /woocommerce/coupons/{id}   — update (WC_Coupon::save)
 */
final class CouponsController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/woocommerce/coupons',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'status' => array(
							'type'    => 'string',
							'default' => 'publish',
						),
						'limit'  => array(
							'type'    => 'integer',
							'default' => 20,
						),
						'page'   => array(
							'type'    => 'integer',
							'default' => 1,
						),
						'order'  => array(
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
						'code'           => array(
							'type'     => 'string',
							'required' => true,
						),
						'discount_type'  => array(
							'type'    => 'string',
							'default' => 'fixed_cart',
							'enum'    => array( 'percentage', 'fixed_cart', 'fixed_product' ),
						),
						'amount'         => array(
							'type'     => 'string',
							'required' => true,
						),
						'description'    => array( 'type' => 'string' ),
						'date_expires'   => array( 'type' => 'string' ),
						'usage_limit'    => array( 'type' => 'integer' ),
						'individual_use' => array( 'type' => 'boolean' ),
						'free_shipping'  => array( 'type' => 'boolean' ),
						'minimum_amount' => array( 'type' => 'string' ),
						'maximum_amount' => array( 'type' => 'string' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/woocommerce/coupons/(?P<id>\d+)',
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
						'id'             => array(
							'type'     => 'integer',
							'required' => true,
						),
						'code'           => array( 'type' => 'string' ),
						'discount_type'  => array( 'type' => 'string' ),
						'amount'         => array( 'type' => 'string' ),
						'description'    => array( 'type' => 'string' ),
						'date_expires'   => array( 'type' => 'string' ),
						'usage_limit'    => array( 'type' => 'integer' ),
						'individual_use' => array( 'type' => 'boolean' ),
						'free_shipping'  => array( 'type' => 'boolean' ),
						'minimum_amount' => array( 'type' => 'string' ),
						'maximum_amount' => array( 'type' => 'string' ),
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

	/** Direct SQL list — zero-hydration via pivot on wp_postmeta. */
	public function get_items( mixed $request ): WP_REST_Response {
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$limit  = min( (int) $request->get_param( 'limit' ), 100 );
		$page   = max( 1, (int) $request->get_param( 'page' ) );
		$order  = sanitize_key( (string) $request->get_param( 'order' ) );

		$rows  = WcDb::get_coupons_list( $limit, $page, $status, $order );
		$items = array_map( array( $this, 'format_coupon_row' ), $rows );

		return new WP_REST_Response( $items, 200 );
	}

	/** Direct SQL single coupon with full metadata. */
	public function get_item( mixed $request ): WP_REST_Response|\WP_Error {
		$id  = (int) $request->get_param( 'id' );
		$row = WcDb::get_coupon( $id );

		if ( null === $row ) {
			return ErrorResponse::not_found( 'Coupon not found.' );
		}

		$data           = $this->format_coupon_row( $row );
		$data['_links'] = array(
			'self' => rest_url( "{$this->namespace}/woocommerce/coupons/{$id}" ),
		);

		return new WP_REST_Response( $data, 200 );
	}

	/** Create coupon via WC_Coupon — fires woocommerce_new_coupon hook. */
	public function create_item( mixed $request ): WP_REST_Response|\WP_Error {
		$code = sanitize_text_field( strtolower( (string) $request->get_param( 'code' ) ) );

		if ( '' === $code ) {
			return ErrorResponse::validation( 'Coupon code cannot be empty.', 'code' );
		}

		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( sanitize_key( (string) $request->get_param( 'discount_type' ) ) );
		$coupon->set_amount( wc_format_decimal( (string) $request->get_param( 'amount' ) ) );

		$description = $request->get_param( 'description' );
		if ( null !== $description ) {
			$coupon->set_description( sanitize_textarea_field( (string) $description ) );
		}

		$date_expires = $request->get_param( 'date_expires' );
		if ( null !== $date_expires ) {
			$coupon->set_date_expires( sanitize_text_field( (string) $date_expires ) );
		}

		$usage_limit = $request->get_param( 'usage_limit' );
		if ( null !== $usage_limit ) {
			$coupon->set_usage_limit( (int) $usage_limit );
		}

		$individual_use = $request->get_param( 'individual_use' );
		if ( null !== $individual_use ) {
			$coupon->set_individual_use( (bool) $individual_use );
		}

		$free_shipping = $request->get_param( 'free_shipping' );
		if ( null !== $free_shipping ) {
			$coupon->set_free_shipping( (bool) $free_shipping );
		}

		$minimum_amount = $request->get_param( 'minimum_amount' );
		if ( null !== $minimum_amount ) {
			$coupon->set_minimum_amount( wc_format_decimal( (string) $minimum_amount ) );
		}

		$maximum_amount = $request->get_param( 'maximum_amount' );
		if ( null !== $maximum_amount ) {
			$coupon->set_maximum_amount( wc_format_decimal( (string) $maximum_amount ) );
		}

		$id = $coupon->save();

		return new WP_REST_Response(
			array(
				'id'   => $id,
				'code' => $code,
			),
			201
		);
	}

	/** Update coupon fields via WC_Coupon — preserves hooks and cache invalidation. */
	public function update_item( mixed $request ): WP_REST_Response|\WP_Error {
		$id     = (int) $request->get_param( 'id' );
		$coupon = new \WC_Coupon( $id );

		if ( ! $coupon->get_id() ) {
			return ErrorResponse::not_found( 'Coupon not found.' );
		}

		$code = $request->get_param( 'code' );
		if ( null !== $code ) {
			$coupon->set_code( sanitize_text_field( strtolower( (string) $code ) ) );
		}

		$discount_type = $request->get_param( 'discount_type' );
		if ( null !== $discount_type ) {
			$coupon->set_discount_type( sanitize_key( (string) $discount_type ) );
		}

		$amount = $request->get_param( 'amount' );
		if ( null !== $amount ) {
			$coupon->set_amount( wc_format_decimal( (string) $amount ) );
		}

		$description = $request->get_param( 'description' );
		if ( null !== $description ) {
			$coupon->set_description( sanitize_textarea_field( (string) $description ) );
		}

		$date_expires = $request->get_param( 'date_expires' );
		if ( null !== $date_expires ) {
			$coupon->set_date_expires( sanitize_text_field( (string) $date_expires ) );
		}

		$usage_limit = $request->get_param( 'usage_limit' );
		if ( null !== $usage_limit ) {
			$coupon->set_usage_limit( (int) $usage_limit );
		}

		$individual_use = $request->get_param( 'individual_use' );
		if ( null !== $individual_use ) {
			$coupon->set_individual_use( (bool) $individual_use );
		}

		$free_shipping = $request->get_param( 'free_shipping' );
		if ( null !== $free_shipping ) {
			$coupon->set_free_shipping( (bool) $free_shipping );
		}

		$minimum_amount = $request->get_param( 'minimum_amount' );
		if ( null !== $minimum_amount ) {
			$coupon->set_minimum_amount( wc_format_decimal( (string) $minimum_amount ) );
		}

		$maximum_amount = $request->get_param( 'maximum_amount' );
		if ( null !== $maximum_amount ) {
			$coupon->set_maximum_amount( wc_format_decimal( (string) $maximum_amount ) );
		}

		$coupon->save();

		return $this->get_item( $request );
	}

	/** @param array<string, mixed> $row */
	private function format_coupon_row( array $row ): array {
		$expires_ts = (int) ( $row['date_expires'] ?? 0 );

		return array(
			'id'             => (int) ( $row['id'] ?? 0 ),
			'code'           => (string) ( $row['code'] ?? '' ),
			'description'    => (string) ( $row['description'] ?? '' ),
			'status'         => (string) ( $row['status'] ?? '' ),
			'discount_type'  => (string) ( $row['discount_type'] ?? '' ),
			'amount'         => (string) ( $row['amount'] ?? '0' ),
			'date_expires'   => $expires_ts > 0 ? gmdate( 'Y-m-d', $expires_ts ) : null,
			'usage_count'    => (int) ( $row['usage_count'] ?? 0 ),
			'usage_limit'    => null !== $row['usage_limit'] ? (int) $row['usage_limit'] : null,
			'individual_use' => 'yes' === ( $row['individual_use'] ?? 'no' ),
			'free_shipping'  => 'yes' === ( $row['free_shipping'] ?? 'no' ),
			'minimum_amount' => (string) ( $row['minimum_amount'] ?? '' ),
			'maximum_amount' => (string) ( $row['maximum_amount'] ?? '' ),
			'date_created'   => (string) ( $row['date_created'] ?? '' ),
			'_links'         => array(
				'self' => rest_url( "{$this->namespace}/woocommerce/coupons/{$row['id']}" ),
			),
		);
	}
}
