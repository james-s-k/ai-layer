<?php
/**
 * Registers the Proof / Trust Signals custom post type.
 *
 * @package WPAIL\PostTypes
 */

declare(strict_types=1);

namespace WPAIL\PostTypes;

class ProofPostType {

	const POST_TYPE = 'wpail_proof';

	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	public function register_post_type(): void {
		register_post_type( self::POST_TYPE, [
			'labels' => [
				'name'               => __( 'Proof & Trust',          'ai-ready-layer' ),
				'singular_name'      => __( 'Proof Item',             'ai-ready-layer' ),
				'add_new'            => __( 'Add New Proof Item',      'ai-ready-layer' ),
				'add_new_item'       => __( 'Add New Proof Item',      'ai-ready-layer' ),
				'edit_item'          => __( 'Edit Proof Item',         'ai-ready-layer' ),
				'view_item'          => __( 'View Proof Item',         'ai-ready-layer' ),
				'search_items'       => __( 'Search Proof',            'ai-ready-layer' ),
				'not_found'          => __( 'No proof items found.',   'ai-ready-layer' ),
				'not_found_in_trash' => __( 'No proof items in trash.','ai-ready-layer' ),
				'menu_name'          => __( 'Proof & Trust',           'ai-ready-layer' ),
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
