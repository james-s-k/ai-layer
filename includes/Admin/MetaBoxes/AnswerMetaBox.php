<?php
/**
 * Meta box for Answers.
 *
 * @package WPAIL\Admin\MetaBoxes
 */

declare(strict_types=1);

namespace WPAIL\Admin\MetaBoxes;

use WPAIL\Support\FieldDefinitions;

class AnswerMetaBox extends BaseMetaBox {

	protected function post_type(): string { return 'wpail_answer'; }
	protected function box_id(): string    { return 'wpail_answer_details'; }
	protected function box_title(): string { return __( 'Answer Details', 'ai-layer' ); }

	protected function field_definitions(): array {
		return FieldDefinitions::answer();
	}

	protected function field_groups(): array {
		return [
			'Matching'       => [ 'query_patterns' ],
			'Answer'         => [ 'short_answer', 'long_answer', 'confidence' ],
			'Relationships'  => [ 'related_services', 'related_locations', 'next_actions', 'source_faq_ids' ],
		];
	}

	protected function description(): string {
		return __( 'Authored Answers bypass the engine entirely. When an incoming query contains any of the Query Patterns below, this answer is returned immediately at highest priority — no scoring, no assembly. Use these for your most predictable, high-stakes questions where you need to guarantee a specific response. For general coverage, add <a href="edit.php?post_type=wpail_faq">FAQs</a> instead and let the engine assemble answers automatically.', 'ai-layer' );
	}
}
