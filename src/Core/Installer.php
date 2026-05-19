<?php
declare(strict_types=1);

namespace WPAIConnector\Core;

use WPAIConnector\Core\Migrations\Migration_0001_Keys;
use WPAIConnector\Core\Migrations\Migration_0002_AuditLog;

final class Installer {

	/** @var array<int, class-string> */
	private const MIGRATIONS = array(
		1 => Migration_0001_Keys::class,
		2 => Migration_0002_AuditLog::class,
	);

	public static function activate(): void {
		( new Migrator( self::MIGRATIONS ) )->run();
	}

	public static function maybe_upgrade(): void {
		$current = (int) get_option( Migrator::OPTION_KEY, 0 );
		if ( $current < Migrator::TARGET_VERSION ) {
			( new Migrator( self::MIGRATIONS ) )->run();
		}
	}
}
