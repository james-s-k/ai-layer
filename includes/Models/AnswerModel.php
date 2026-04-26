<?php
/**
 * Canonical Answer model.
 *
 * Represents a structured answer assembled by the rules-based answer engine
 * or authored manually. Returned by the /answers endpoint.
 *
 * @package WPAIL\Models
 */

declare(strict_types=1);

namespace WPAIL\Models;

class AnswerModel {

	public function __construct(
		public readonly string  $short_answer     = '',
		public readonly string  $long_answer      = '',
		public readonly string  $confidence       = 'medium',
		public readonly string  $source           = 'dynamic',  // 'manual' | 'dynamic' | 'faq'
		/** @var array<string> Query patterns (private — admin only). */
		public readonly array   $query_patterns   = [],
		/** @var array<int> */
		public readonly array   $related_service_ids  = [],
		/** @var array<int> */
		public readonly array   $related_location_ids = [],
		/** @var array<int> */
		public readonly array   $next_action_ids  = [],
		/** @var array<int> */
		public readonly array   $source_faq_ids   = [],
		public readonly ?int    $post_id          = null,
	) {}

	/**
	 * Full REST response array.
	 *
	 * @param array<array<string, mixed>> $services   Resolved service summaries.
	 * @param array<array<string, mixed>> $locations  Resolved location summaries.
	 * @param array<array<string, mixed>> $actions    Resolved action summaries.
	 * @param array<array<string, mixed>> $source_faqs Resolved FAQ summaries.
	 * @param array<array<string, mixed>> $supporting_data Extra context (proof, etc).
	 * @return array<string, mixed>
	 */
	public function to_public_array(
		array $services      = [],
		array $locations     = [],
		array $actions       = [],
		array $source_faqs   = [],
		array $supporting_data = []
	): array {
		return [
			'answer_short'     => $this->short_answer,
			'answer_long'      => $this->long_answer,
			'confidence'       => $this->confidence,
			'source'           => $this->source,
			'services'         => $services,
			'locations'        => $locations,
			'actions'          => $actions,
			'source_faqs'      => $source_faqs,
			'supporting_data'  => $supporting_data,
		];
	}
}
