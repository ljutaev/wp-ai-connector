<?php
declare(strict_types=1);

namespace WPAIConnector\Admin;

use WPAIConnector\Auth\ApiKeyFactory;
use WPAIConnector\Auth\ApiKeyRepository;
use WPAIConnector\Audit\AuditLogger;

/**
 * Top-level admin menu page with Keys and Audit Log tabs.
 */
final class SettingsPage {

	private const MENU_SLUG = 'wp-ai-connector';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_wpaic_create_key', array( $this, 'handle_create_key' ) );
		add_action( 'admin_post_wpaic_revoke_key', array( $this, 'handle_revoke_key' ) );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'WP AI Connector', 'wp-ai-connector' ),
			__( 'AI Connector', 'wp-ai-connector' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-rest-api',
			80
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'wp-ai-connector' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'keys'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'WP AI Connector', 'wp-ai-connector' ) . '</h1>';

		// Tab navigation.
		$tabs = array(
			'keys'  => __( 'API Keys', 'wp-ai-connector' ),
			'audit' => __( 'Audit Log', 'wp-ai-connector' ),
		);

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$url    = add_query_arg(
				array(
					'page' => self::MENU_SLUG,
					'tab'  => $slug,
				),
				admin_url( 'admin.php' )
			);
			$active = $slug === $tab ? ' nav-tab-active' : '';
			echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . esc_attr( $active ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		echo '<div class="tab-content" style="margin-top:20px;">';

		if ( 'keys' === $tab ) {
			$this->render_keys_tab();
		} else {
			$this->render_audit_tab();
		}

		echo '</div></div>';
	}

	private function render_keys_tab(): void {
		$repo = new ApiKeyRepository();
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$table = $wpdb->prefix . 'wpaic_keys';
		$keys  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT k.*, u.user_login FROM {$table} k LEFT JOIN {$wpdb->users} u ON k.user_id = u.ID WHERE k.revoked_at IS NULL ORDER BY k.created_at DESC LIMIT %d",
				100
			),
			ARRAY_A
		);
		// phpcs:enable

		$new_key = get_transient( 'wpaic_new_key_' . get_current_user_id() );
		if ( false !== $new_key ) {
			delete_transient( 'wpaic_new_key_' . get_current_user_id() );
			echo '<div class="notice notice-success"><p>';
			echo '<strong>' . esc_html__( 'Key created — copy it now, it will not be shown again:', 'wp-ai-connector' ) . '</strong><br>';
			echo '<code style="font-size:14px;user-select:all;">' . esc_html( (string) $new_key ) . '</code>';
			echo '</p></div>';
		}

		// Create key form.
		echo '<h2>' . esc_html__( 'Create API Key', 'wp-ai-connector' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="wpaic_create_key">';
		wp_nonce_field( 'wpaic_create_key' );

		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="wpaic_label">' . esc_html__( 'Label', 'wp-ai-connector' ) . '</label></th>';
		echo '<td><input type="text" id="wpaic_label" name="wpaic_label" class="regular-text" required placeholder="e.g. Claude Desktop"></td></tr>';
		echo '<tr><th><label for="wpaic_scope">' . esc_html__( 'Scope', 'wp-ai-connector' ) . '</label></th>';
		echo '<td><input type="text" id="wpaic_scope" name="wpaic_scope" class="regular-text" value="*" required>';
		echo '<p class="description">' . esc_html__( 'Use * for full access, or comma-separated scopes like posts:read,options:read', 'wp-ai-connector' ) . '</p></td></tr>';
		echo '</tbody></table>';

		echo wp_kses_post( '<p>' . get_submit_button( __( 'Generate Key', 'wp-ai-connector' ), 'primary', 'submit', false ) . '</p>' );
		echo '</form>';

		// Keys table.
		echo '<h2>' . esc_html__( 'Active Keys', 'wp-ai-connector' ) . '</h2>';

		if ( empty( $keys ) ) {
			echo '<p>' . esc_html__( 'No active keys.', 'wp-ai-connector' ) . '</p>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Label', 'wp-ai-connector' ) . '</th>';
		echo '<th>' . esc_html__( 'Key prefix', 'wp-ai-connector' ) . '</th>';
		echo '<th>' . esc_html__( 'Scope', 'wp-ai-connector' ) . '</th>';
		echo '<th>' . esc_html__( 'User', 'wp-ai-connector' ) . '</th>';
		echo '<th>' . esc_html__( 'Last used', 'wp-ai-connector' ) . '</th>';
		echo '<th>' . esc_html__( 'Created', 'wp-ai-connector' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'wp-ai-connector' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( (array) $keys as $row ) {
			$scopes    = json_decode( (string) $row['scope'], true );
			$scope_str = is_array( $scopes ) ? implode( ', ', $scopes ) : '*';

			echo '<tr>';
			echo '<td>' . esc_html( (string) $row['label'] ) . '</td>';
			echo '<td><code>' . esc_html( (string) $row['truncated_key'] ) . '…</code></td>';
			echo '<td>' . esc_html( $scope_str ) . '</td>';
			echo '<td>' . esc_html( (string) ( $row['user_login'] ?? '—' ) ) . '</td>';
			echo '<td>' . esc_html( $row['last_used_at'] ? (string) $row['last_used_at'] : '—' ) . '</td>';
			echo '<td>' . esc_html( (string) $row['created_at'] ) . '</td>';
			echo '<td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;">';
			echo '<input type="hidden" name="action" value="wpaic_revoke_key">';
			echo '<input type="hidden" name="key_id" value="' . esc_attr( (string) $row['id'] ) . '">';
			wp_nonce_field( 'wpaic_revoke_key_' . (int) $row['id'] );
			echo '<button type="submit" class="button button-small button-link-delete" onclick="return confirm(\'' . esc_js( __( 'Revoke this key?', 'wp-ai-connector' ) ) . '\')">';
			echo esc_html__( 'Revoke', 'wp-ai-connector' );
			echo '</button></form>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	private function render_audit_tab(): void {
		$logger = new AuditLogger();
		$page   = isset( $_GET['apage'] ) ? max( 1, (int) $_GET['apage'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$limit  = 50;
		$offset = ( $page - 1 ) * $limit;
		$total  = $logger->count();
		$rows   = $logger->query(
			array(
				'limit'  => $limit,
				'offset' => $offset,
			)
		);

		echo '<h2>' . esc_html__( 'Audit Log', 'wp-ai-connector' ) . '</h2>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No audit log entries yet.', 'wp-ai-connector' ) . '</p>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		foreach ( array( 'Time', 'Method', 'Route', 'Status', 'User', 'IP', 'Key' ) as $col ) {
			echo '<th>' . esc_html__( $col, 'wp-ai-connector' ) . '</th>'; // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$status_class = (int) $row['status'] >= 400 ? 'color:red;' : '';
			echo '<tr>';
			echo '<td>' . esc_html( (string) $row['created_at'] ) . '</td>';
			echo '<td><code>' . esc_html( (string) $row['method'] ) . '</code></td>';
			echo '<td><small>' . esc_html( (string) $row['route'] ) . '</small></td>';
			echo '<td style="' . esc_attr( $status_class ) . '">' . esc_html( (string) $row['status'] ) . '</td>';
			echo '<td>' . esc_html( $row['user_id'] ? (string) $row['user_id'] : '—' ) . '</td>';
			echo '<td>' . esc_html( $row['ip'] ?? '—' ) . '</td>';
			echo '<td>' . esc_html( $row['key_id'] ? '#' . (string) $row['key_id'] : '—' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		// Pagination.
		$pages = (int) ceil( $total / $limit );
		if ( $pages > 1 ) {
			echo '<div style="margin-top:10px;">';
			for ( $i = 1; $i <= $pages; $i++ ) {
				$url = add_query_arg(
					array(
						'page'  => self::MENU_SLUG,
						'tab'   => 'audit',
						'apage' => $i,
					),
					admin_url( 'admin.php' )
				);
				echo '<a href="' . esc_url( $url ) . '" class="button' . ( $i === $page ? ' button-primary' : '' ) . '" style="margin-right:4px;">' . esc_html( (string) $i ) . '</a>';
			}
			echo '</div>';
		}
	}

	public function handle_create_key(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'wp-ai-connector' ) );
		}

		check_admin_referer( 'wpaic_create_key' );

		$label  = sanitize_text_field( wp_unslash( (string) ( $_POST['wpaic_label'] ?? '' ) ) );
		$scope  = sanitize_text_field( wp_unslash( (string) ( $_POST['wpaic_scope'] ?? '*' ) ) );
		$scopes = array_filter( array_map( 'trim', explode( ',', $scope ) ) );

		if ( empty( $label ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => self::MENU_SLUG,
						'error' => 'label',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$factory    = new ApiKeyFactory();
		$gen        = $factory->generate();
		$probe_hash = hash_hmac( 'sha256', $gen->plaintext, wp_salt( 'auth' ) );

		( new ApiKeyRepository() )->create(
			user_id:       get_current_user_id(),
			label:         $label,
			hash:          $probe_hash,
			salt:          $gen->salt,
			truncated_key: $gen->truncated,
			scopes:        array_values( $scopes ),
		);

		set_transient( 'wpaic_new_key_' . get_current_user_id(), $gen->plaintext, 60 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => self::MENU_SLUG,
					'tab'  => 'keys',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_revoke_key(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'wp-ai-connector' ) );
		}

		$key_id = (int) wp_unslash( $_POST['key_id'] ?? 0 );
		check_admin_referer( 'wpaic_revoke_key_' . $key_id );

		if ( $key_id > 0 ) {
			( new ApiKeyRepository() )->revoke( $key_id );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => self::MENU_SLUG,
					'tab'  => 'keys',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
