<?php
/**
 * Scheduled daily cleanup of analytics data beyond the retention window.
 *
 * @package WPAIL\Analytics
 */

declare(strict_types=1);

namespace WPAIL\Analytics;

use WPAIL\Admin\SettingsPage;

class AnalyticsCleanup {

	const CRON_HOOK = 'wpail_analytics_cleanup';

	public function register(): void {
		add_action( self::CRON_HOOK, [ $this, 'run' ] );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public function run(): void {
		$days = (int) SettingsPage::get( SettingsPage::SETTING_ANALYTICS_RETENTION_DAYS, 365 );
		if ( $days <= 0 ) {
			return;
		}
		( new AnalyticsRepository() )->delete_older_than( $days );
	}
}
