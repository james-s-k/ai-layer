<?php
/**
 * Canonical Proof / Trust Signal model.
 *
 * @package WPAIL\Models
 */

declare(strict_types=1);

namespace WPAIL\Models;

class ProofModel {

	public function __construct(
		public readonly int    $id             = 0,
		public readonly string $slug           = '',
		public readonly string $proof_type     = '',
		public readonly string $headline       = '',
		public readonly string $content        = '',
		public readonly string $source_name    = '',
		public readonly string $source_context = '',
		public readonly ?float $rating         = null,
		/** @var array<int> */
		public readonly array  $related_service_ids  = [],
		/** @var array<int> */
		public readonly array  $related_location_ids = [],
		public readonly bool   $is_public      = true,
		public readonly string $modified_at    = '',
	) {}

	/**
	 * @param array<array{id:int,title:string,slug:string}> $services
	 * @param array<array{id:int,title:string,slug:string}> $locations
	 * @return array<string, mixed>
	 */
	public function to_public_array( array $services = [], array $locations = [] ): array {
		return [
			'id'             => $this->id,
			'slug'           => $this->slug,
			'modified_at'    => $this->modified_at ?: null,
			'type'           => $this->proof_type,
			'headline'       => $this->headline,
			'content'        => $this->content,
			'source_name'    => $this->source_name,
			'source_context' => $this->source_context,
			'rating'         => $this->rating,
			'services'       => $services,
			'locations'      => $locations,
		];
	}

	public function to_summary_array(): array {
		return [
			'id'       => $this->id,
			'type'     => $this->proof_type,
			'headline' => $this->headline,
		];
	}
}
