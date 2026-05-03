<?php
/**
 * Meta box for Locations.
 *
 * @package WPAIL\Admin\MetaBoxes
 */

declare(strict_types=1);

namespace WPAIL\Admin\MetaBoxes;

use WPAIL\Support\FieldDefinitions;

class LocationMetaBox extends BaseMetaBox {

	protected function post_type(): string { return 'wpail_location'; }
	protected function box_id(): string    { return 'wpail_location_details'; }
	protected function box_title(): string { return __( 'Location Details', 'ai-layer' ); }

	protected function field_definitions(): array {
		return FieldDefinitions::location();
	}

	protected function field_groups(): array {
		return [
			'Location Info'  => [ 'location_type', 'region', 'country', 'postcode_prefixes', 'is_primary', 'service_radius_km' ],
			'Content'        => [ 'summary', 'linked_page_url' ],
			'Relationships'  => [ 'related_services', 'local_proof' ],
		];
	}
}
