<?php
/**
 * Plugin Name:       WP AI Connector
 * Plugin URI:        https://github.com/ljutaev/wp-ai-connector
 * Description:       Lightweight REST API & MCP connector for WordPress. Manage your site from Claude, ChatGPT, Cursor, and the terminal.
 * Version:           0.3.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Oleksandr Lukashuk
 * Author URI:        https://github.com/ljutaev
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-ai-connector
 * Domain Path:       /languages
 *
 * @package WPAIConnector
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WPAIC_FILE' ) ) {
	define( 'WPAIC_FILE', __FILE__ );
}

require_once __DIR__ . '/vendor/autoload.php';

\WPAIConnector\Core\Plugin::boot( WPAIC_FILE );
