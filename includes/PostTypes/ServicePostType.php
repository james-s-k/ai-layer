<?php
/**
 * Registers the Services custom post type.
 *
 * @package WPAIL\PostTypes
 */

declare(strict_types=1);

namespace WPAIL\PostTypes;

use WPAIL\Admin\SettingsPage;

class ServicePostType {

	const POST_TYPE = 'wpail_service';

	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	public function register_post_type(): void {
		$is_public = (bool) SettingsPage::get( SettingsPage::SETTING_SERVICE_PUBLIC, false );
		$slug      = (string) SettingsPage::get( SettingsPage::SETTING_SERVICE_SLUG, 'services' );
		if ( $slug === '' ) {
			$slug = 'services';
		}

		register_post_type( self::POST_TYPE, [
			'labels' => [
				'name'               => __( 'Services',              'ai-ready-layer' ),
				'singular_name'      => __( 'Service',               'ai-ready-layer' ),
				'add_new'            => __( 'Add New Service',        'ai-ready-layer' ),
				'add_new_item'       => __( 'Add New Service',        'ai-ready-layer' ),
				'edit_item'          => __( 'Edit Service',           'ai-ready-layer' ),
				'view_item'          => __( 'View Service',           'ai-ready-layer' ),
				'search_items'       => __( 'Search Services',        'ai-ready-layer' ),
				'not_found'          => __( 'No services found.',     'ai-ready-layer' ),
				'not_found_in_trash' => __( 'No services in trash.',  'ai-ready-layer' ),
				'menu_name'          => __( 'Services',               'ai-ready-layer' ),
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
			'menu_icon'           => 'dashicons-admin-generic',
		] );
	}
}
