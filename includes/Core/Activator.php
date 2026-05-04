<?php
/**
 * Plugin activation, deactivation, and upgrade routines.
 *
 * @package WPAIL\Core
 */

declare(strict_types=1);

namespace WPAIL\Core;

class Activator {

	/**
	 * Run on plugin activation.
	 * Registers CPTs first so rewrite rules flush correctly.
	 */
	public static function activate(): void {
		( new \WPAIL\PostTypes\ServicePostType() )->register();
		( new \WPAIL\PostTypes\LocationPostType() )->register();
		( new \WPAIL\PostTypes\FaqPostType() )->register();
		( new \WPAIL\PostTypes\ProofPostType() )->register();
		( new \WPAIL\PostTypes\ActionPostType() )->register();
		( new \WPAIL\PostTypes\AnswerPostType() )->register();

		// Register virtual route rewrite rules so they survive the flush.
		add_rewrite_rule( '^\.well-known/ai-layer$', 'index.php?wpail_wellknown_ai=1', 'top' );
		add_rewrite_rule( '^ai\.txt$', 'index.php?wpail_aitxt=1', 'top' );

		flush_rewrite_rules();

		\WPAIL\Analytics\AnalyticsTable::install();
		\WPAIL\Analytics\AnalyticsCleanup::schedule();

		$installed = get_option( 'wpail_version', '' );

		if ( '' === $installed ) {
			add_option( 'wpail_version', WPAIL_VERSION );
		} elseif ( version_compare( $installed, WPAIL_VERSION, '<' ) ) {
			self::upgrade( $installed );
			update_option( 'wpail_version', WPAIL_VERSION );
		}
	}

	public static function deactivate(): void {
		\WPAIL\Analytics\AnalyticsCleanup::unschedule();
		flush_rewrite_rules();
	}

	/**
	 * Version-to-version migration entry point.
	 * Add migration steps here as the data model evolves.
	 * Migrations must be idempotent.
	 */
	private static function upgrade( string $from_version ): void {
		// Ensure the analytics table exists when upgrading from older versions.
		// dbDelta is idempotent — safe to call unconditionally.
		\WPAIL\Analytics\AnalyticsTable::install();

		// Example:
		// if ( version_compare( $from_version, '1.1.0', '<' ) ) {
		//     self::migrate_1_1_0();
		// }
	}
}
