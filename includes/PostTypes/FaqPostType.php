<?php
/**
 * Registers the FAQs custom post type.
 *
 * @package WPAIL\PostTypes
 */

declare(strict_types=1);

namespace WPAIL\PostTypes;

class FaqPostType {

	const POST_TYPE = 'wpail_faq';

	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	public function register_post_type(): void {
		register_post_type( self::POST_TYPE, [
			'labels' => [
				'name'               => __( 'FAQs',                   'ai-ready-layer' ),
				'singular_name'      => __( 'FAQ',                    'ai-ready-layer' ),
				'add_new'            => __( 'Add New FAQ',             'ai-ready-layer' ),
				'add_new_item'       => __( 'Add New FAQ',             'ai-ready-layer' ),
				'edit_item'          => __( 'Edit FAQ',                'ai-ready-layer' ),
				'view_item'          => __( 'View FAQ',                'ai-ready-layer' ),
				'search_items'       => __( 'Search FAQs',             'ai-ready-layer' ),
				'not_found'          => __( 'No FAQs found.',          'ai-ready-layer' ),
				'not_found_in_trash' => __( 'No FAQs in trash.',       'ai-ready-layer' ),
				'menu_name'          => __( 'FAQs',                    'ai-ready-layer' ),
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
