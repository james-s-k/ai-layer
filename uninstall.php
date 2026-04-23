<?php
/**
 * Runs when the plugin is deleted from the WordPress admin.
 *
 * Only removes data when the user has explicitly opted in via Settings →
 * Data Management → "Remove data on deletion". Disabled by default so
 * reinstalling the plugin preserves all existing content.
 *
 * @package WPAIL
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = (array) get_option( 'wpail_settings', [] );

if ( empty( $settings['delete_data_on_uninstall'] ) ) {
	return;
}

// ------------------------------------------------------------------
// Delete all CPT posts (WordPress cascades and removes their postmeta).
// ------------------------------------------------------------------

$post_types = [
	'wpail_service',
	'wpail_location',
	'wpail_faq',
	'wpail_proof',
	'wpail_action',
	'wpail_answer',
];

foreach ( $post_types as $post_type ) {
	$ids = get_posts( [
		'post_type'      => $post_type,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	] );

	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
}

// ------------------------------------------------------------------
// Delete all plugin options and transients.
// Using a prefix wildcard covers everything — no need to maintain a list.
// ------------------------------------------------------------------

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE 'wpail\_%'
	    OR option_name LIKE '\_transient\_wpail\_%'
	    OR option_name LIKE '\_transient\_timeout\_wpail\_%'"
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery

// ------------------------------------------------------------------
// Flush rewrite rules so CPT-based routes are removed cleanly.
// ------------------------------------------------------------------

flush_rewrite_rules();
