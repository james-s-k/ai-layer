<?php
/**
 * Registers the Proof / Trust Signals custom post type.
 *
 * @package WPAIL\PostTypes
 */

declare(strict_types=1);

namespace WPAIL\PostTypes;

use WPAIL\Admin\SettingsPage;

class ProofPostType {

	const POST_TYPE = 'wpail_proof';

	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	public function register_post_type(): void {
		$is_public = (bool) SettingsPage::get( SettingsPage::SETTING_PROOF_PUBLIC, false );
		$slug      = (string) SettingsPage::get( SettingsPage::SETTING_PROOF_SLUG, 'proof' );
		if ( $slug === '' ) {
			$slug = 'proof';
		}

		register_post_type( self::POST_TYPE, [
			'labels' => [
				'name'               => __( 'Proof & Trust',          'ai-layer' ),
				'singular_name'      => __( 'Proof Item',             'ai-layer' ),
				'add_new'            => __( 'Add New Proof Item',      'ai-layer' ),
				'add_new_item'       => __( 'Add New Proof Item',      'ai-layer' ),
				'edit_item'          => __( 'Edit Proof Item',         'ai-layer' ),
				'view_item'          => __( 'View Proof Item',         'ai-layer' ),
				'search_items'       => __( 'Search Proof',            'ai-layer' ),
				'not_found'          => __( 'No proof items found.',   'ai-layer' ),
				'not_found_in_trash' => __( 'No proof items in trash.','ai-layer' ),
				'menu_name'          => __( 'Proof & Trust',           'ai-layer' ),
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
