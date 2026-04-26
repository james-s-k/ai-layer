<?php
/**
 * Meta box for FAQs.
 *
 * @package WPAIL\Admin\MetaBoxes
 */

declare(strict_types=1);

namespace WPAIL\Admin\MetaBoxes;

use WPAIL\Support\FieldDefinitions;

class FaqMetaBox extends BaseMetaBox {

	protected function post_type(): string { return 'wpail_faq'; }
	protected function box_id(): string    { return 'wpail_faq_details'; }
	protected function box_title(): string { return __( 'FAQ Details', 'ai-ready-layer' ); }

	protected function field_definitions(): array {
		return FieldDefinitions::faq();
	}

	protected function field_groups(): array {
		return [
			'Content'        => [ 'question', 'short_answer', 'long_answer', 'status', 'is_public' ],
			'Matching'       => [ 'intent_tags', 'priority' ],
			'Relationships'  => [ 'related_services', 'related_locations' ],
		];
	}

	protected function description(): string {
		return __( 'FAQs are the answer engine\'s source material. When a query arrives and no <a href="edit.php?post_type=wpail_answer">Authored Answer</a> matches, the engine scores your FAQs by keyword overlap, intent tags, and service/location relationships — then builds a response from the best match. Write FAQs for the questions your customers actually ask. For questions where you need to guarantee a specific response word-for-word, use an Authored Answer instead.', 'ai-ready-layer' );
	}
}
