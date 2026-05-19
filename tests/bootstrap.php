<?php
/**
 * PHPUnit bootstrap.
 *
 * For unit tests we boot Brain Monkey to mock WordPress globals.
 * Integration tests have their own setUp() that boots wp-env.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs/WpStubs.php';

if ( ! defined( 'WPAIC_TESTING' ) ) {
	define( 'WPAIC_TESTING', true );
}

if ( ! defined( 'WPAIC_FILE' ) ) {
	define( 'WPAIC_FILE', dirname( __DIR__ ) . '/wp-ai-connector.php' );
}
