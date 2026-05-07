<?php
/**
 * Canonical Location model.
 *
 * @package WPAIL\Models
 */

declare(strict_types=1);

namespace WPAIL\Models;

class LocationModel {

	public function __construct(
		public readonly int    $id               = 0,
		public readonly string $slug             = '',
		public readonly string $name             = '',
		public readonly string $location_type    = '',
		public readonly string $region           = '',
		public readonly string $country          = 'GB',
		/** @var array<string> */
		public readonly array  $postcode_prefixes = [],
		public readonly bool   $is_primary       = false,
		public readonly ?float $service_radius_km = null,
		public readonly string $summary          = '',
		/** @var array<int> */
		public readonly array  $related_service_ids = [],
		/** @var array<int> */
		public readonly array  $local_proof_ids     = [],
		public readonly string $linked_page_url  = '',
		public readonly string $modified_at      = '',
	) {}

	/**
	 * @param array<array{id:int,title:string,slug:string}> $services
	 * @param array<array{id:int,title:string,slug:string}> $proof
	 * @return array<string, mixed>
	 */
	public function to_public_array( array $services = [], array $proof = [] ): array {
		return [
			'id'               => $this->id,
			'slug'             => $this->slug,
			'name'             => $this->name,
			'modified_at'      => $this->modified_at ?: null,
			'type'             => $this->location_type,
			'region'           => $this->region,
			'country'          => $this->country,
			'postcode_prefixes'=> $this->postcode_prefixes,
			'is_primary'       => $this->is_primary,
			'service_radius_km'=> $this->service_radius_km,
			'summary'          => $this->summary,
			'linked_page'      => $this->linked_page_url ?: null,
			'services'         => $services,
			'local_proof'      => $proof,
		];
	}

	public function to_summary_array(): array {
		return [
			'id'          => $this->id,
			'slug'        => $this->slug,
			'name'        => $this->name,
			'modified_at' => $this->modified_at ?: null,
		];
	}
}
