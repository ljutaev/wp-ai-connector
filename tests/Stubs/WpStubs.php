<?php
/**
 * Minimal WordPress class stubs for PHPUnit unit tests.
 *
 * These classes are only defined when WP core is not loaded (i.e. unit test suite).
 * They provide just enough surface area for controller tests to instantiate and call methods.
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_REST_Controller' ) ) {
	abstract class WP_REST_Controller {
		/** @var string */
		protected $namespace = '';

		/** @var string */
		protected $rest_base = '';

		public function register_routes(): void {}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		/** @var mixed */
		private $data;

		/** @var int */
		private int $status;

		/** @var array<string, string> */
		private array $headers = array();

		/** @var array<string, array<int, array<string, string>>> */
		private array $links = array();

		/** @param mixed $data */
		public function __construct( $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		/** @return mixed */
		public function get_data() {
			return $this->data;
		}

		public function get_status(): int {
			return $this->status;
		}

		public function header( string $key, string $value ): void {
			$this->headers[ $key ] = $value;
		}

		public function add_link( string $rel, string $href ): void {
			$this->links[ $rel ][] = array( 'href' => $href );
		}

		/** @return array<string, string> */
		public function get_headers(): array {
			return $this->headers;
		}
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/** @var string */
		private string $code;

		/** @var string */
		private string $message;

		/** @var mixed */
		private $data;

		/** @param mixed $data */
		public function __construct( string $code = '', string $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		/** @return mixed */
		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		/** @var array<string, mixed> */
		private array $params = array();

		/** @param mixed $value */
		public function set_param( string $key, $value ): void {
			$this->params[ $key ] = $value;
		}

		/** @return mixed */
		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
		}

		/** @param array<string, mixed> $params */
		public function set_body_params( array $params ): void {
			foreach ( $params as $key => $value ) {
				$this->params[ $key ] = $value;
			}
		}
	}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	class WP_REST_Server {
		public const READABLE   = 'GET';
		public const CREATABLE  = 'POST';
		public const EDITABLE   = 'POST, PUT, PATCH';
		public const DELETABLE  = 'DELETE';
		public const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int $ID                = 0;
		public string $post_title     = '';
		public string $post_content   = '';
		public string $post_status    = 'publish';
		public string $post_type      = 'post';
		public string $post_date      = '';
		public string $post_author    = '0';
		public string $guid           = '';
		public string $post_mime_type = '';
		public int $menu_order        = 0;
	}
}

if ( ! class_exists( 'WP_User' ) ) {
	class WP_User {
		public int $ID                 = 0;
		public string $user_login      = '';
		public string $user_email      = '';
		public string $display_name    = '';
		public string $user_registered = '';
		/** @var array<int, string> */
		public array $roles = array();
	}
}

if ( ! class_exists( 'WP_Term' ) ) {
	class WP_Term {
		public int $term_id        = 0;
		public string $name        = '';
		public string $slug        = '';
		public string $taxonomy    = '';
		public string $description = '';
		public int $count          = 0;
		public int $parent         = 0;
	}
}

if ( ! class_exists( 'WP_Comment' ) ) {
	class WP_Comment {
		public string $comment_ID       = '0';
		public string $comment_post_ID  = '0';
		public string $comment_author   = '';
		public string $comment_date     = '';
		public string $comment_content  = '';
		public string $comment_approved = '1';
		public string $comment_type     = '';
	}
}

if ( ! class_exists( 'WP_Theme' ) ) {
	class WP_Theme {
		public function get( string $header ): string {
			return '';
		}

		public function get_stylesheet(): string {
			return '';
		}

		public function get_template(): string {
			return '';
		}
	}
}

if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		/** @var array<int, \WP_Post> */
		public array $posts       = array();
		public int $found_posts   = 0;
		public int $max_num_pages = 1;

		/** @param array<string, mixed> $args */
		public function __construct( array $args = array() ) {}
	}
}
