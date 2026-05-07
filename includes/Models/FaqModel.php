<?php
/**
 * Canonical FAQ model.
 *
 * @package WPAIL\Models
 */

declare(strict_types=1);

namespace WPAIL\Models;

class FaqModel {

	public function __construct(
		public readonly int    $id              = 0,
		public readonly string $slug            = '',
		public readonly string $question        = '',
		public readonly string $short_answer    = '',
		public readonly string $long_answer     = '',
		public readonly string $status          = 'published',
		/** @var array<int> */
		public readonly array  $related_service_ids  = [],
		/** @var array<int> */
		public readonly array  $related_location_ids = [],
		/** @var array<string> Private — for intent matching. */
		public readonly array  $intent_tags     = [],
		/** Private — higher = matched first. */
		public readonly int    $priority        = 0,
		public readonly bool   $is_public       = true,
		public readonly string $modified_at     = '',
	) {}

	/**
	 * @param array<array{id:int,title:string,slug:string}> $services
	 * @param array<array{id:int,title:string,slug:string}> $locations
	 * @return array<string, mixed>
	 */
	public function to_public_array( array $services = [], array $locations = [] ): array {
		return [
			'id'           => $this->id,
			'slug'         => $this->slug,
			'modified_at'  => $this->modified_at ?: null,
			'question'     => $this->question,
			'short_answer' => $this->short_answer,
			'long_answer'  => $this->long_answer,
			'services'     => $services,
			'locations'    => $locations,
		];
	}

	public function to_summary_array(): array {
		return [
			'id'           => $this->id,
			'question'     => $this->question,
			'short_answer' => $this->short_answer,
		];
	}
}
