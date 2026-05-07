<?php
/**
 * Records write operations on AI Layer entities for security auditing.
 *
 * Call AuditLogger::log() immediately after any successful CREATE, UPDATE,
 * or DELETE on an entity. The log is viewable in AI Layer → Analytics.
 *
 * @package WPAIL\Analytics
 */

declare(strict_types=1);

namespace WPAIL\Analytics;

class AuditLogger {

	const ACTION_CREATE = 'create';
	const ACTION_UPDATE = 'update';
	const ACTION_DELETE = 'delete';

	/**
	 * Record a write operation.
	 *
	 * @param string $action      One of the ACTION_* constants.
	 * @param string $entity_type WordPress post type slug, e.g. 'wpail_service'.
	 * @param int    $entity_id   Post ID of the affected entity.
	 */
	public static function log( string $action, string $entity_type, int $entity_id ): void {
		global $wpdb;

		$user       = wp_get_current_user();
		$user_id    = ( $user instanceof \WP_User && $user->ID > 0 ) ? $user->ID : 0;
		$user_login = ( $user instanceof \WP_User ) ? $user->user_login : '';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			AuditTable::table_name(),
			[
				'action'      => sanitize_key( $action ),
				'entity_type' => sanitize_key( $entity_type ),
				'entity_id'   => $entity_id,
				'user_id'     => $user_id,
				'user_login'  => sanitize_user( $user_login ),
				'created_at'  => gmdate( 'Y-m-d H:i:s' ),
			],
			[ '%s', '%s', '%d', '%d', '%s', '%s' ]
		);
	}
}
