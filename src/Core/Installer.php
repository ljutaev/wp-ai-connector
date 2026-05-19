<?php
declare(strict_types=1);

namespace WPAIConnector\Core;

use WPAIConnector\Core\Migrations\Migration_0001_Keys;

final class Installer {

	public static function activate(): void {
		( new Migrator( array( 1 => Migration_0001_Keys::class ) ) )->run();
	}

	public static function maybe_upgrade(): void {
		$current = (int) get_option( Migrator::OPTION_KEY, 0 );
		if ( $current < Migrator::TARGET_VERSION ) {
			( new Migrator( array( 1 => Migration_0001_Keys::class ) ) )->run();
		}
	}
}
