<?php
/**
 * Registers the Locations custom post type.
 *
 * Locations are CPTs (not taxonomies) because they are rich objects:
 * they carry summaries, radii, postcode metadata, and bidirectional
 * relationships. Taxonomies cannot hold this structure cleanly.
 *
 * @package WPAIL\PostTypes
 */

declare(strict_types=1);

namespace WPAIL\PostTypes;

use WPAIL\Admin\SettingsPage;

class LocationPostType {

	const POST_TYPE = 'wpail_location';

	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	public function register_post_type(): void {
		$is_public = (bool) SettingsPage::get( SettingsPage::SETTING_LOCATION_PUBLIC, false );
		$slug      = (string) SettingsPage::get( SettingsPage::SETTING_LOCATION_SLUG, 'locations' );
		if ( $slug === '' ) {
			$slug = 'locations';
		}

		register_post_type( self::POST_TYPE, [
			'labels' => [
				'name'               => __( 'Locations',              'ai-layer' ),
				'singular_name'      => __( 'Location',               'ai-layer' ),
				'add_new'            => __( 'Add New Location',        'ai-layer' ),
				'add_new_item'       => __( 'Add New Location',        'ai-layer' ),
				'edit_item'          => __( 'Edit Location',           'ai-layer' ),
				'view_item'          => __( 'View Location',           'ai-layer' ),
				'search_items'       => __( 'Search Locations',        'ai-layer' ),
				'not_found'          => __( 'No locations found.',     'ai-layer' ),
				'not_found_in_trash' => __( 'No locations in trash.',  'ai-layer' ),
				'menu_name'          => __( 'Locations',               'ai-layer' ),
			],
			'public'              => $is_public,
			'publicly_queryable'  => $is_public,
			'show_ui'             => true,
			'show_in_menu'        => 'wpail_dashboard',
			'show_in_rest'        => false,
			'supports'            => [ 'title', 'revisions' ],
			'has_archive'         => $is_public ? $slug : false,
			'rewrite'             => $is_public ? [ 'slug' => $slug, 'with_front' => false ] : false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		] );
	}
}
