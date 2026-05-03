<?php
/**
 * Registers the FAQs custom post type.
 *
 * @package WPAIL\PostTypes
 */

declare(strict_types=1);

namespace WPAIL\PostTypes;

use WPAIL\Admin\SettingsPage;

class FaqPostType {

	const POST_TYPE = 'wpail_faq';

	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	public function register_post_type(): void {
		$is_public = (bool) SettingsPage::get( SettingsPage::SETTING_FAQ_PUBLIC, false );
		$slug      = (string) SettingsPage::get( SettingsPage::SETTING_FAQ_SLUG, 'faqs' );
		if ( $slug === '' ) {
			$slug = 'faqs';
		}

		register_post_type( self::POST_TYPE, [
			'labels' => [
				'name'               => __( 'FAQs',                   'ai-layer' ),
				'singular_name'      => __( 'FAQ',                    'ai-layer' ),
				'add_new'            => __( 'Add New FAQ',             'ai-layer' ),
				'add_new_item'       => __( 'Add New FAQ',             'ai-layer' ),
				'edit_item'          => __( 'Edit FAQ',                'ai-layer' ),
				'view_item'          => __( 'View FAQ',                'ai-layer' ),
				'search_items'       => __( 'Search FAQs',             'ai-layer' ),
				'not_found'          => __( 'No FAQs found.',          'ai-layer' ),
				'not_found_in_trash' => __( 'No FAQs in trash.',       'ai-layer' ),
				'menu_name'          => __( 'FAQs',                    'ai-layer' ),
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
