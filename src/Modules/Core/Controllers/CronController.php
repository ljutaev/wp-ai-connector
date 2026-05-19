<?php
declare(strict_types=1);

namespace WPAIConnector\Modules\Core\Controllers;

use WP_REST_Response;
use WP_REST_Server;
use WPAIConnector\REST\AbstractController;
use WPAIConnector\REST\ErrorResponse;

final class CronController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/cron',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'hook' => array( 'type' => 'string' ),
					),
				),
			)
		);
	}

	public function permissions_check( mixed $request ): bool|\WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return ErrorResponse::forbidden_capability( 'manage_options' );
		}

		return true;
	}

	public function get_items( mixed $request ): WP_REST_Response {
		$crons       = _get_cron_array();
		$filter_hook = $request->get_param( 'hook' );
		$items       = array();

		if ( ! is_array( $crons ) ) {
			return new WP_REST_Response( $items, 200 );
		}

		foreach ( $crons as $timestamp => $hooks ) {
			if ( ! is_array( $hooks ) ) {
				continue;
			}
			foreach ( $hooks as $hook => $events ) {
				if ( null !== $filter_hook && $hook !== $filter_hook ) {
					continue;
				}
				if ( ! is_array( $events ) ) {
					continue;
				}
				foreach ( $events as $event ) {
					if ( ! is_array( $event ) ) {
						continue;
					}
					$items[] = array(
						'hook'      => $hook,
						'timestamp' => (int) $timestamp,
						'next_run'  => gmdate( 'Y-m-d\TH:i:s\Z', (int) $timestamp ),
						'schedule'  => $event['schedule'] ?? false,
						'interval'  => isset( $event['interval'] ) ? (int) $event['interval'] : null,
						'args'      => $event['args'] ?? array(),
					);
				}
			}
		}

		usort(
			$items,
			static fn ( array $a, array $b ): int => $a['timestamp'] <=> $b['timestamp']
		);

		return new WP_REST_Response( $items, 200 );
	}
}
