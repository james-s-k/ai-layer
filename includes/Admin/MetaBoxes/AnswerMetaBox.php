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
	protected function box_title(): string { return __( 'Answer Details', 'ai-ready-layer' ); }

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
}
