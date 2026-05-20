<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\WooCommerce;

use WPAIConnector\Core\Container;
use WPAIConnector\Modules\AbstractModule;
use WPAIConnector\Modules\WooCommerce\Conditionals\WooCommerceConditional;
use WPAIConnector\Modules\WooCommerce\Controllers\CouponsController;
use WPAIConnector\Modules\WooCommerce\Controllers\CustomersController;
use WPAIConnector\Modules\WooCommerce\Controllers\OrdersController;
use WPAIConnector\Modules\WooCommerce\Controllers\ProductsController;
use WPAIConnector\Modules\WooCommerce\Controllers\ReportsController;

final class WooCommerceModule extends AbstractModule {

	public function name(): string {
		return 'woocommerce';
	}

	public function version(): string {
		return '0.2.0';
	}

	/** @return array<int, \WPAIConnector\Modules\ConditionalInterface> */
	public function conditionals(): array {
		return array(
			new WooCommerceConditional(),
		);
	}

	public function register( Container $container ): void {
		$container->set( OrdersController::class, static fn () => new OrdersController() );
		$container->set( ProductsController::class, static fn () => new ProductsController() );
		$container->set( CustomersController::class, static fn () => new CustomersController() );
		$container->set( CouponsController::class, static fn () => new CouponsController() );
		$container->set( ReportsController::class, static fn () => new ReportsController() );

		add_action(
			'rest_api_init',
			static function () use ( $container ): void {
				$container->get( OrdersController::class )->register_routes();
				$container->get( ProductsController::class )->register_routes();
				$container->get( CustomersController::class )->register_routes();
				$container->get( CouponsController::class )->register_routes();
				$container->get( ReportsController::class )->register_routes();
			}
		);
	}

	/** @return array<string, mixed> */
	public function manifest(): array {
		$wc_version = defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown';

		return array(
			'name'        => 'woocommerce',
			'version'     => $this->version(),
			'detected'    => true,
			'host_plugin' => array(
				'name'    => 'WooCommerce',
				'version' => $wc_version,
			),
			'storage'     => WcDb::hpos_enabled() ? 'hpos' : 'legacy',
			'routes'      => array(

				// ── Orders ──────────────────────────────────────────────────
				array(
					'method'      => 'GET',
					'path'        => '/woocommerce/orders',
					'scope'       => 'woo:orders:read',
					'description' => 'List orders. Auto-detects HPOS vs legacy storage.',
					'parameters'  => array(
						array(
							'name'    => 'status',
							'in'      => 'query',
							'type'    => 'string',
							'default' => 'any',
							'enum'    => array( 'any', 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ),
						),
						array(
							'name' => 'customer_id',
							'in'   => 'query',
							'type' => 'integer',
						),
						array(
							'name'    => 'limit',
							'in'      => 'query',
							'type'    => 'integer',
							'default' => 20,
						),
						array(
							'name'    => 'page',
							'in'      => 'query',
							'type'    => 'integer',
							'default' => 1,
						),
						array(
							'name'    => 'orderby',
							'in'      => 'query',
							'type'    => 'string',
							'default' => 'date_created_gmt',
						),
						array(
							'name'    => 'order',
							'in'      => 'query',
							'type'    => 'string',
							'default' => 'DESC',
							'enum'    => array( 'ASC', 'DESC' ),
						),
					),
					'ai_hint'     => "status='processing' = unfulfilled paid; 'completed' = shipped; 'any' = all. Each row includes billing name, total, currency.",
				),
				array(
					'method'      => 'GET',
					'path'        => '/woocommerce/orders/{id}',
					'scope'       => 'woo:orders:read',
					'description' => 'Single order with full billing address and line_items[].',
					'ai_hint'     => 'line_items[].product_id, name, qty, line_total are always present.',
				),
				array(
					'method'      => 'POST',
					'path'        => '/woocommerce/orders',
					'scope'       => 'woo:orders:write',
					'description' => 'Create an order via wc_create_order. Triggers stock deduction and email hooks.',
					'parameters'  => array(
						array(
							'name'    => 'status',
							'in'      => 'body',
							'type'    => 'string',
							'default' => 'pending',
						),
						array(
							'name' => 'customer_id',
							'in'   => 'body',
							'type' => 'integer',
						),
						array(
							'name' => 'billing_email',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'billing_first_name',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'billing_last_name',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'note',
							'in'   => 'body',
							'type' => 'string',
						),
					),
				),
				array(
					'method'      => 'POST',
					'path'        => '/woocommerce/orders/{id}',
					'scope'       => 'woo:orders:write',
					'description' => 'Update order status or add an internal note.',
					'parameters'  => array(
						array(
							'name' => 'status',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'note',
							'in'   => 'body',
							'type' => 'string',
						),
					),
					'ai_hint'     => "To complete an order: POST {id} with status='completed'. To refund: status='refunded'.",
				),
				array(
					'method'      => 'GET',
					'path'        => '/woocommerce/orders/{id}/notes',
					'scope'       => 'woo:orders:read',
					'description' => 'List all internal and customer-visible notes for an order.',
				),
				array(
					'method'      => 'POST',
					'path'        => '/woocommerce/orders/{id}/notes',
					'scope'       => 'woo:orders:write',
					'description' => 'Add a note to an order.',
					'parameters'  => array(
						array(
							'name'     => 'note',
							'in'       => 'body',
							'type'     => 'string',
							'required' => true,
						),
						array(
							'name'    => 'customer',
							'in'      => 'body',
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),

				// ── Products ─────────────────────────────────────────────────
				array(
					'method'      => 'GET',
					'path'        => '/woocommerce/products',
					'scope'       => 'woo:products:read',
					'description' => 'List products via wp_wc_product_meta_lookup (zero-hydration).',
					'parameters'  => array(
						array(
							'name'    => 'status',
							'in'      => 'query',
							'type'    => 'string',
							'default' => 'publish',
							'enum'    => array( 'publish', 'draft', 'pending', 'private' ),
						),
						array(
							'name' => 'sku',
							'in'   => 'query',
							'type' => 'string',
						),
						array(
							'name' => 'stock_status',
							'in'   => 'query',
							'type' => 'string',
							'enum' => array( 'instock', 'outofstock', 'onbackorder' ),
						),
						array(
							'name' => 'featured',
							'in'   => 'query',
							'type' => 'boolean',
						),
						array(
							'name'    => 'limit',
							'in'      => 'query',
							'type'    => 'integer',
							'default' => 20,
						),
						array(
							'name'    => 'orderby',
							'in'      => 'query',
							'type'    => 'string',
							'default' => 'date',
							'enum'    => array( 'date', 'id', 'price', 'title', 'stock_quantity' ),
						),
						array(
							'name'    => 'order',
							'in'      => 'query',
							'type'    => 'string',
							'default' => 'DESC',
							'enum'    => array( 'ASC', 'DESC' ),
						),
					),
					'ai_hint'     => 'Returns price (min_price), max_price, stock_status. Filter by sku for exact product lookup.',
				),
				array(
					'method'      => 'GET',
					'path'        => '/woocommerce/products/{id}',
					'scope'       => 'woo:products:read',
					'description' => 'Full product detail including description, dimensions, regular/sale price, ratings.',
				),
				array(
					'method'      => 'POST',
					'path'        => '/woocommerce/products/{id}',
					'scope'       => 'woo:products:write',
					'description' => 'Update product name, price, description, status, or stock.',
					'parameters'  => array(
						array(
							'name' => 'name',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'regular_price',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'sale_price',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'status',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'stock_quantity',
							'in'   => 'body',
							'type' => 'integer',
						),
						array(
							'name' => 'manage_stock',
							'in'   => 'body',
							'type' => 'boolean',
						),
					),
					'ai_hint'     => "To start a sale: set both regular_price and sale_price. To end sale: set sale_price to empty string ''.",
				),
				array(
					'method'      => 'GET',
					'path'        => '/woocommerce/products/{id}/variations',
					'scope'       => 'woo:products:read',
					'description' => 'List all variations of a variable product with attributes (color, size, etc.).',
					'ai_hint'     => 'Each variation includes attributes[] map like {pa_color: Red, pa_size: L}.',
				),

				// ── Customers ────────────────────────────────────────────────
				array(
					'method'      => 'GET',
					'path'        => '/woocommerce/customers',
					'scope'       => 'woo:customers:read',
					'description' => 'List customers from wp_wc_customer_lookup.',
					'parameters'  => array(
						array(
							'name'    => 'limit',
							'in'      => 'query',
							'type'    => 'integer',
							'default' => 20,
						),
						array(
							'name'    => 'page',
							'in'      => 'query',
							'type'    => 'integer',
							'default' => 1,
						),
						array(
							'name'    => 'order',
							'in'      => 'query',
							'type'    => 'string',
							'default' => 'DESC',
							'enum'    => array( 'ASC', 'DESC' ),
						),
					),
					'ai_hint'     => 'Returns customer_id, user_id, email, name, country, date_registered, date_last_active.',
				),
				array(
					'method'      => 'GET',
					'path'        => '/woocommerce/customers/{id}',
					'scope'       => 'woo:customers:read',
					'description' => 'Single customer profile by customer_id (not user_id).',
				),

				// ── Coupons ──────────────────────────────────────────────────
				array(
					'method'      => 'GET',
					'path'        => '/woocommerce/coupons',
					'scope'       => 'woo:coupons:read',
					'description' => 'List coupons with discount type, amount, usage counts.',
					'parameters'  => array(
						array(
							'name'    => 'status',
							'in'      => 'query',
							'type'    => 'string',
							'default' => 'publish',
						),
						array(
							'name'    => 'limit',
							'in'      => 'query',
							'type'    => 'integer',
							'default' => 20,
						),
						array(
							'name'    => 'page',
							'in'      => 'query',
							'type'    => 'integer',
							'default' => 1,
						),
					),
					'ai_hint'     => 'discount_type: percentage | fixed_cart | fixed_product. date_expires is ISO date string or null.',
				),
				array(
					'method'      => 'GET',
					'path'        => '/woocommerce/coupons/{id}',
					'scope'       => 'woo:coupons:read',
					'description' => 'Full coupon detail including usage limits and restrictions.',
				),
				array(
					'method'      => 'POST',
					'path'        => '/woocommerce/coupons',
					'scope'       => 'woo:coupons:write',
					'description' => 'Create a new coupon.',
					'parameters'  => array(
						array(
							'name'     => 'code',
							'in'       => 'body',
							'type'     => 'string',
							'required' => true,
						),
						array(
							'name'    => 'discount_type',
							'in'      => 'body',
							'type'    => 'string',
							'default' => 'fixed_cart',
						),
						array(
							'name'     => 'amount',
							'in'       => 'body',
							'type'     => 'string',
							'required' => true,
						),
						array(
							'name' => 'date_expires',
							'in'   => 'body',
							'type' => 'string',
						),
						array(
							'name' => 'usage_limit',
							'in'   => 'body',
							'type' => 'integer',
						),
					),
				),
				array(
					'method'      => 'POST',
					'path'        => '/woocommerce/coupons/{id}',
					'scope'       => 'woo:coupons:write',
					'description' => 'Update coupon code, amount, expiry, or limits.',
				),

				// ── Reports ──────────────────────────────────────────────────
				array(
					'method'      => 'GET',
					'path'        => '/woocommerce/reports/sales',
					'scope'       => 'woo:reports:read',
					'description' => 'Revenue summary for a date range from wp_wc_order_stats. Excludes cancelled/refunded/failed.',
					'parameters'  => array(
						array(
							'name'     => 'date_from',
							'in'       => 'query',
							'type'     => 'string',
							'required' => true,
						),
						array(
							'name'     => 'date_to',
							'in'       => 'query',
							'type'     => 'string',
							'required' => true,
						),
					),
					'ai_hint'     => 'Dates must be YYYY-MM-DD. Returns order_count, gross_revenue, net_revenue, total_tax, total_shipping, items_sold.',
				),
				array(
					'method'      => 'GET',
					'path'        => '/woocommerce/reports/top-products',
					'scope'       => 'woo:reports:read',
					'description' => 'Top-selling products by quantity sold in a date range.',
					'parameters'  => array(
						array(
							'name'     => 'date_from',
							'in'       => 'query',
							'type'     => 'string',
							'required' => true,
						),
						array(
							'name'     => 'date_to',
							'in'       => 'query',
							'type'     => 'string',
							'required' => true,
						),
						array(
							'name'    => 'limit',
							'in'      => 'query',
							'type'    => 'integer',
							'default' => 10,
						),
					),
					'ai_hint'     => 'Returns product_id, product_name, qty_sold, total_revenue sorted by qty_sold DESC.',
				),
			),
		);
	}
}
