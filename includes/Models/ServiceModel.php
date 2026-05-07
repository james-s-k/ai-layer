<?php
/**
 * Canonical Service model.
 *
 * @package WPAIL\Models
 */

declare(strict_types=1);

namespace WPAIL\Models;

class ServiceModel {

	public function __construct(
		public readonly int     $id               = 0,
		public readonly string  $slug             = '',
		public readonly string  $name             = '',
		public readonly string  $category         = '',
		public readonly string  $status           = 'active',
		public readonly string  $short_summary    = '',
		public readonly string  $long_summary     = '',
		/** @var array<string> */
		public readonly array   $customer_types   = [],
		/** @var array<string> */
		public readonly array   $service_modes    = [],
		/** @var array<string> */
		public readonly array   $keywords         = [],
		/** @var array<string> Private — used in answer matching. */
		public readonly array   $synonyms         = [],
		/** Private — used for intent detection. */
		public readonly string  $common_problems  = '',
		public readonly string  $pricing_type     = '',
		public readonly ?float  $from_price       = null,
		public readonly string  $currency         = 'GBP',
		public readonly string  $price_notes      = '',
		public readonly bool    $available        = true,
		/** @var array<string> */
		public readonly array   $benefits         = [],
		/** @var array<int> */
		public readonly array   $related_faq_ids      = [],
		/** @var array<int> */
		public readonly array   $related_proof_ids    = [],
		/** @var array<int> */
		public readonly array   $related_action_ids   = [],
		/** @var array<int> */
		public readonly array   $related_location_ids = [],
		public readonly string  $linked_page_url  = '',
		public readonly string  $schema_type      = '',
		public readonly string  $modified_at      = '',
	) {}

	/**
	 * Public REST representation.
	 * Excludes internal fields (synonyms, common_problems, schema_type).
	 *
	 * @param array<array{id:int,title:string,slug:string}> $faqs
	 * @param array<array{id:int,title:string,slug:string}> $proof
	 * @param array<array{id:int,title:string,slug:string}> $actions
	 * @param array<array{id:int,title:string,slug:string}> $locations
	 * @return array<string, mixed>
	 */
	public function to_public_array(
		array $faqs      = [],
		array $proof     = [],
		array $actions   = [],
		array $locations = []
	): array {
		return [
			'id'             => $this->id,
			'slug'           => $this->slug,
			'name'           => $this->name,
			'modified_at'    => $this->modified_at ?: null,
			'category'       => $this->category,
			'status'         => $this->status,
			'short_summary'  => $this->short_summary,
			'long_summary'   => $this->long_summary,
			'customer_types' => $this->customer_types,
			'service_modes'  => $this->service_modes,
			'keywords'       => $this->keywords,
			'pricing'        => [
				'type'     => $this->pricing_type,
				'from'     => $this->from_price,
				'currency' => $this->currency,
				'notes'    => $this->price_notes,
			],
			'available'      => $this->available,
			'benefits'       => $this->benefits,
			'linked_page'    => $this->linked_page_url ?: null,
			'faqs'           => $faqs,
			'proof'          => $proof,
			'actions'        => $actions,
			'locations'      => $locations,
		];
	}

	/** Minimal summary for embedding in other objects. */
	public function to_summary_array(): array {
		return [
			'id'          => $this->id,
			'slug'        => $this->slug,
			'name'        => $this->name,
			'modified_at' => $this->modified_at ?: null,
		];
	}
}
