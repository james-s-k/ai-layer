<?php
/**
 * Canonical Action model.
 *
 * @package WPAIL\Models
 */

declare(strict_types=1);

namespace WPAIL\Models;

class ActionModel {

	public function __construct(
		public readonly int    $id                = 0,
		public readonly string $slug              = '',
		public readonly string $action_type       = '',
		public readonly string $label             = '',
		public readonly string $description       = '',
		public readonly string $phone             = '',
		public readonly string $url               = '',
		public readonly string $method            = '',
		/** Private — for future conditional display. */
		public readonly string $availability_rule = '',
		/** @var array<int> */
		public readonly array  $related_service_ids  = [],
		/** @var array<int> */
		public readonly array  $related_location_ids = [],
		public readonly bool   $is_public         = true,
	) {}

	/**
	 * @return array<string, mixed>
	 */
	public function to_public_array( array $services = [], array $locations = [] ): array {
		return [
			'id'          => $this->id,
			'slug'        => $this->slug,
			'type'        => $this->action_type,
			'label'       => $this->label,
			'description' => $this->description,
			'phone'       => $this->phone ?: null,
			'url'         => $this->url ?: null,
			'method'      => $this->method,
			'services'    => $services,
			'locations'   => $locations,
		];
	}

	public function to_summary_array(): array {
		return [
			'id'     => $this->id,
			'type'   => $this->action_type,
			'label'  => $this->label,
			'phone'  => $this->phone ?: null,
			'url'    => $this->url ?: null,
			'method' => $this->method,
		];
	}
}
