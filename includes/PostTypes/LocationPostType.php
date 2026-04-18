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

class LocationPostType {

	const POST_TYPE = 'wpail_location';

	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	public function register_post_type(): void {
		register_post_type( self::POST_TYPE, [
			'labels' => [
				'name'               => __( 'Locations',              'ai-ready-layer' ),
				'singular_name'      => __( 'Location',               'ai-ready-layer' ),
				'add_new'            => __( 'Add New Location',        'ai-ready-layer' ),
				'add_new_item'       => __( 'Add New Location',        'ai-ready-layer' ),
				'edit_item'          => __( 'Edit Location',           'ai-ready-layer' ),
				'view_item'          => __( 'View Location',           'ai-ready-layer' ),
				'search_items'       => __( 'Search Locations',        'ai-ready-layer' ),
				'not_found'          => __( 'No locations found.',     'ai-ready-layer' ),
				'not_found_in_trash' => __( 'No locations in trash.',  'ai-ready-layer' ),
				'menu_name'          => __( 'Locations',               'ai-ready-layer' ),
			],
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => 'wpail_dashboard',
			'show_in_rest'        => false,
			'supports'            => [ 'title', 'revisions' ],
			'has_archive'         => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		] );
	}
}
