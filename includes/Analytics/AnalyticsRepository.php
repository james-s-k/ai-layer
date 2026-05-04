<?php
/**
 * Query methods for the analytics dashboard.
 *
 * @package WPAIL\Analytics
 */

declare(strict_types=1);

namespace WPAIL\Analytics;

class AnalyticsRepository {

	private string $table;

	public function __construct() {
		$this->table = AnalyticsTable::table_name();
	}

	/**
	 * Total hits per endpoint.
	 *
	 * @param int $days 0 = all time
	 * @return array<array{endpoint:string,hits:string}>
	 */
	public function get_endpoint_hits( int $days = 30 ): array {
		global $wpdb;
		$where = $this->where_clause( $days );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results( "SELECT endpoint, COUNT(*) AS hits FROM {$this->table} {$where} GROUP BY endpoint ORDER BY hits DESC", ARRAY_A ) ?: [];
	}

	/**
	 * Top N queries by total frequency.
	 *
	 * @param int $limit
	 * @param int $days 0 = all time
	 * @return array<array{query_text:string,count:string,matched_count:string,unmatched_count:string}>
	 */
	public function get_top_queries( int $limit = 20, int $days = 30 ): array {
		global $wpdb;
		$base_where = $this->where_clause( $days );
		$and        = $this->and_clause( $days );
		$condition  = '' === $base_where
			? "WHERE query_text IS NOT NULL AND query_text != ''"
			: "{$base_where} AND query_text IS NOT NULL AND query_text != ''";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT query_text, COUNT(*) AS count, SUM(matched) AS matched_count, SUM(1 - matched) AS unmatched_count FROM {$this->table} {$condition} GROUP BY query_text ORDER BY count DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Queries that returned no match, ordered by frequency.
	 *
	 * @param int $limit
	 * @param int $days 0 = all time
	 * @return array<array{query_text:string,count:string}>
	 */
	public function get_unanswered_queries( int $limit = 20, int $days = 30 ): array {
		global $wpdb;
		$base_where = $this->where_clause( $days );
		$condition  = '' === $base_where
			? "WHERE query_text IS NOT NULL AND query_text != '' AND matched = 0"
			: "{$base_where} AND query_text IS NOT NULL AND query_text != '' AND matched = 0";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT query_text, COUNT(*) AS count FROM {$this->table} {$condition} GROUP BY query_text ORDER BY count DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Summary stat row.
	 *
	 * @param int $days 0 = all time
	 * @return array{total_hits:int,total_queries:int,answered:int,unanswered:int,answer_rate:int}
	 */
	public function get_summary( int $days = 30 ): array {
		global $wpdb;
		$where = $this->where_clause( $days );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			"SELECT
				COUNT(*) AS total_hits,
				SUM(CASE WHEN query_text IS NOT NULL AND query_text != '' THEN 1 ELSE 0 END) AS total_queries,
				SUM(CASE WHEN query_text IS NOT NULL AND query_text != '' AND matched = 1 THEN 1 ELSE 0 END) AS answered,
				SUM(CASE WHEN query_text IS NOT NULL AND query_text != '' AND matched = 0 THEN 1 ELSE 0 END) AS unanswered
			FROM {$this->table} {$where}",
			ARRAY_A
		);

		$total_queries = (int) ( $row['total_queries'] ?? 0 );
		$answered      = (int) ( $row['answered'] ?? 0 );

		return [
			'total_hits'    => (int) ( $row['total_hits'] ?? 0 ),
			'total_queries' => $total_queries,
			'answered'      => $answered,
			'unanswered'    => (int) ( $row['unanswered'] ?? 0 ),
			'answer_rate'   => $total_queries > 0 ? (int) round( $answered / $total_queries * 100 ) : 0,
		];
	}

	/**
	 * Delete records older than the given number of days.
	 *
	 * @param int $days Must be >= 1.
	 * @return int Number of rows deleted.
	 */
	public function delete_older_than( int $days ): int {
		global $wpdb;
		$before = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM {$this->table} WHERE created_at < %s",
				$before
			)
		);
	}

	// ------------------------------------------------------------------

	private function since_datetime( int $days ): ?string {
		if ( $days <= 0 ) {
			return null;
		}
		return gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
	}

	private function where_clause( int $days ): string {
		global $wpdb;
		$since = $this->since_datetime( $days );
		if ( null === $since ) {
			return '';
		}
		return $wpdb->prepare( 'WHERE created_at >= %s', $since );
	}

	private function and_clause( int $days ): string {
		global $wpdb;
		$since = $this->since_datetime( $days );
		if ( null === $since ) {
			return '';
		}
		return $wpdb->prepare( 'AND created_at >= %s', $since );
	}
}
