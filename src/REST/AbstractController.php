<?php
declare(strict_types=1);

namespace WPAIConnector\REST;

use WP_REST_Controller;
use WP_REST_Response;

/**
 * Base for every WP AI Connector REST controller.
 *
 * Provides _links enrichment and stable error helpers. Pagination headers
 * (X-WP-Total, X-WP-TotalPages) come from WP core when controllers extend this.
 */
abstract class AbstractController extends WP_REST_Controller {

	protected string $namespace = 'wp-ai-connector/v1';

	/**
	 * @param array<string, mixed> $links Map of rel => href.
	 */
	protected function enrich_links( WP_REST_Response $response, array $links ): WP_REST_Response {
		foreach ( $links as $rel => $href ) {
			$response->add_link( $rel, $href );
		}

		return $response;
	}
}
