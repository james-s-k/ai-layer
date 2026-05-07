<?php
/**
 * Read access to the audit log table.
 *
 * @package WPAIL\Analytics
 */

declare(strict_types=1);

namespace WPAIL\Analytics;

class AuditRepository {

	/**
	 * Return recent audit log entries, newest first.
	 *
	 * @param int $limit Maximum rows to return.
	 * @param int $days  Look-back window in days; 0 = all time.
	 * @return array<array<string, mixed>>
	 */
	public function get_recent( int $limit = 50, int $days = 30 ): array {
		global $wpdb;
		$table = AuditTable::table_name();

		if ( $days > 0 ) {
			$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE created_at >= %s ORDER BY created_at DESC LIMIT %d",
					$since,
					$limit
				),
				ARRAY_A
			);
			// phpcs:enable
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit ),
			ARRAY_A
		);
		// phpcs:enable
	}
}
