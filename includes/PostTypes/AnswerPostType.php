<?php
/**
 * Registers the Answers custom post type.
 *
 * Answers are manually-curated structured responses that the /answers
 * endpoint uses as high-confidence matches. The rules-based engine
 * also assembles answers dynamically from FAQs + services + locations,
 * but manually authored answers take priority.
 *
 * @package WPAIL\PostTypes
 */

declare(strict_types=1);

namespace WPAIL\PostTypes;

use WPAIL\Licensing\Features;

class AnswerPostType {

	const POST_TYPE = 'wpail_answer';

	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	public function register_post_type(): void {
		$is_pro = Features::answers_enabled();

		// The CPT is always registered — even in free — so that any existing
		// wpail_answer posts are never orphaned on downgrade or licence expiry.
		// show_ui and show_in_menu are suppressed in free; the locked upgrade
		// placeholder is added separately by AdminMenu.
		register_post_type( self::POST_TYPE, [
			'labels' => [
				'name'               => __( 'Answers',                'ai-ready-layer' ),
				'singular_name'      => __( 'Answer',                 'ai-ready-layer' ),
				'add_new'            => __( 'Add New Answer',          'ai-ready-layer' ),
				'add_new_item'       => __( 'Add New Answer',          'ai-ready-layer' ),
				'edit_item'          => __( 'Edit Answer',             'ai-ready-layer' ),
				'view_item'          => __( 'View Answer',             'ai-ready-layer' ),
				'search_items'       => __( 'Search Answers',          'ai-ready-layer' ),
				'not_found'          => __( 'No answers found.',       'ai-ready-layer' ),
				'not_found_in_trash' => __( 'No answers in trash.',    'ai-ready-layer' ),
				'menu_name'          => __( 'Answers',                 'ai-ready-layer' ),
			],
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => $is_pro,
			'show_in_menu'        => $is_pro ? 'wpail_dashboard' : false,
			'show_in_rest'        => false,
			'supports'            => [ 'title', 'revisions' ],
			'has_archive'         => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		] );
	}
}
