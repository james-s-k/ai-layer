<?php
/**
 * Registers the Actions custom post type.
 *
 * @package WPAIL\PostTypes
 */

declare(strict_types=1);

namespace WPAIL\PostTypes;

class ActionPostType {

	const POST_TYPE = 'wpail_action';

	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	public function register_post_type(): void {
		register_post_type( self::POST_TYPE, [
			'labels' => [
				'name'               => __( 'Actions',                'ai-layer' ),
				'singular_name'      => __( 'Action',                 'ai-layer' ),
				'add_new'            => __( 'Add New Action',          'ai-layer' ),
				'add_new_item'       => __( 'Add New Action',          'ai-layer' ),
				'edit_item'          => __( 'Edit Action',             'ai-layer' ),
				'view_item'          => __( 'View Action',             'ai-layer' ),
				'search_items'       => __( 'Search Actions',          'ai-layer' ),
				'not_found'          => __( 'No actions found.',       'ai-layer' ),
				'not_found_in_trash' => __( 'No actions in trash.',    'ai-layer' ),
				'menu_name'          => __( 'Actions',                 'ai-layer' ),
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
