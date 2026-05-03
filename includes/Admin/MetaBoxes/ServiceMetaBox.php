<?php
/**
 * Meta box for Services.
 *
 * @package WPAIL\Admin\MetaBoxes
 */

declare(strict_types=1);

namespace WPAIL\Admin\MetaBoxes;

use WPAIL\Support\FieldDefinitions;

class ServiceMetaBox extends BaseMetaBox {

	protected function post_type(): string { return 'wpail_service'; }
	protected function box_id(): string    { return 'wpail_service_details'; }
	protected function box_title(): string { return __( 'Service Details', 'ai-layer' ); }

	protected function field_definitions(): array {
		return FieldDefinitions::service();
	}

	protected function field_groups(): array {
		return [
			'Overview'      => [ 'category', 'status', 'short_summary', 'long_summary', 'customer_types', 'service_modes' ],
			'Matching'      => [ 'keywords', 'synonyms', 'common_problems' ],
			'Pricing'       => [ 'pricing_type', 'from_price', 'currency', 'price_notes', 'available' ],
			'Content'       => [ 'benefits', 'linked_page_url' ],
			'Relationships' => [ 'related_faqs', 'related_proof', 'related_actions', 'related_locations' ],
			'Schema'        => [ 'schema_type' ],
		];
	}
}
