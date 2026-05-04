<?php
/**
 * Custom analytics table: schema creation and upgrade.
 *
 * @package WPAIL\Analytics
 */

declare(strict_types=1);

namespace WPAIL\Analytics;

class AnalyticsTable {

	const NAME = 'wpail_analytics';

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::NAME;
	}

	public static function install(): void {
		global $wpdb;
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			endpoint varchar(100) NOT NULL DEFAULT '',
			query_text text DEFAULT NULL,
			matched tinyint(1) NOT NULL DEFAULT 0,
			confidence varchar(20) DEFAULT NULL,
			source varchar(50) DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY endpoint (endpoint),
			KEY created_at (created_at),
			KEY matched (matched)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function drop(): void {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
}
