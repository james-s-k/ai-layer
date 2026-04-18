<?php
/**
 * Meta box for Proof / Trust Signals.
 *
 * @package WPAIL\Admin\MetaBoxes
 */

declare(strict_types=1);

namespace WPAIL\Admin\MetaBoxes;

use WPAIL\Support\FieldDefinitions;

class ProofMetaBox extends BaseMetaBox {

	protected function post_type(): string { return 'wpail_proof'; }
	protected function box_id(): string    { return 'wpail_proof_details'; }
	protected function box_title(): string { return __( 'Proof Details', 'ai-ready-layer' ); }

	protected function field_definitions(): array {
		return FieldDefinitions::proof();
	}

	protected function field_groups(): array {
		return [
			'Proof Item'     => [ 'proof_type', 'headline', 'content', 'source_name', 'source_context', 'rating', 'is_public' ],
			'Relationships'  => [ 'related_services', 'related_locations' ],
		];
	}
}
