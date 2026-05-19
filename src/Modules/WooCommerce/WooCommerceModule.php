<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\WooCommerce;

use WPAIConnector\Core\Container;
use WPAIConnector\Modules\AbstractModule;
use WPAIConnector\Modules\WooCommerce\Conditionals\WooCommerceConditional;
use WPAIConnector\Modules\WooCommerce\Controllers\OrdersController;
use WPAIConnector\Modules\WooCommerce\Controllers\ProductsController;

final class WooCommerceModule extends AbstractModule {

	public function name(): string {
		return 'woocommerce';
	}

	public function version(): string {
		return '0.1.0';
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

		add_action(
			'rest_api_init',
			static function () use ( $container ): void {
				$container->get( OrdersController::class )->register_routes();
				$container->get( ProductsController::class )->register_routes();
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
			'routes'      => array(
				array(
					'method'      => 'GET',
					'path'        => '/woocommerce/orders',
					'scope'       => 'woo:orders:read',
					'description' => 'List WooCommerce orders. Filter by status and customer.',
					'parameters'  => array(
						array(
							'name'    => 'status',
							'in'      => 'query',
							'type'    => 'string',
							'default' => 'any',
							'enum'    => array( 'any', 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ),
						),
						array( 'name' => 'customer_id', 'in' => 'query', 'type' => 'integer' ),
						array( 'name' => 'limit', 'in' => 'query', 'type' => 'integer', 'default' => 20 ),
						array( 'name' => 'page', 'in' => 'query', 'type' => 'integer', 'default' => 1 ),
					),
					'ai_hint'     => "Use status='processing' for unfulfilled paid orders, 'completed' for shipped, 'any' for all. Each order includes billing name, total, currency, item_count.",
				),
				array(
					'method'      => 'GET',
					'path'        => '/woocommerce/orders/{id}',
					'scope'       => 'woo:orders:read',
					'description' => 'Get a single order with full line items.',
					'ai_hint'     => 'Response includes line_items[] with product_id, name, quantity, total, and sku.',
				),
				array(
					'method'      => 'GET',
					'path'        => '/woocommerce/products',
					'scope'       => 'woo:products:read',
					'description' => 'List WooCommerce products. Filter by status, type, category, SKU.',
					'parameters'  => array(
						array( 'name' => 'status', 'in' => 'query', 'type' => 'string', 'default' => 'publish', 'enum' => array( 'publish', 'draft', 'pending', 'private' ) ),
						array( 'name' => 'type', 'in' => 'query', 'type' => 'string', 'enum' => array( 'simple', 'variable', 'grouped', 'external' ) ),
						array( 'name' => 'category', 'in' => 'query', 'type' => 'string' ),
						array( 'name' => 'sku', 'in' => 'query', 'type' => 'string' ),
						array( 'name' => 'featured', 'in' => 'query', 'type' => 'boolean' ),
						array( 'name' => 'limit', 'in' => 'query', 'type' => 'integer', 'default' => 20 ),
					),
					'ai_hint'     => "Returns price, regular_price, sale_price, on_sale, stock_status, categories. Use type='variable' for products with variations.",
				),
				array(
					'method'      => 'GET',
					'path'        => '/woocommerce/products/{id}',
					'scope'       => 'woo:products:read',
					'description' => 'Get a single product with full details including stock and dimensions.',
				),
				array(
					'method'      => 'POST',
					'path'        => '/woocommerce/products/{id}',
					'scope'       => 'woo:products:write',
					'description' => 'Update a product name, price, description, status, or stock.',
					'parameters'  => array(
						array( 'name' => 'name', 'in' => 'body', 'type' => 'string' ),
						array( 'name' => 'regular_price', 'in' => 'body', 'type' => 'string' ),
						array( 'name' => 'sale_price', 'in' => 'body', 'type' => 'string' ),
						array( 'name' => 'status', 'in' => 'body', 'type' => 'string' ),
						array( 'name' => 'stock_quantity', 'in' => 'body', 'type' => 'integer' ),
					),
					'ai_hint'     => "To set a sale, provide both regular_price and sale_price. To end sale, set sale_price to empty string.",
				),
			),
		);
	}
}
