<?php
/**
 * Meta box for Actions.
 *
 * @package WPAIL\Admin\MetaBoxes
 */

declare(strict_types=1);

namespace WPAIL\Admin\MetaBoxes;

use WPAIL\Support\FieldDefinitions;

class ActionMetaBox extends BaseMetaBox {

	protected function post_type(): string { return 'wpail_action'; }
	protected function box_id(): string    { return 'wpail_action_details'; }
	protected function box_title(): string { return __( 'Action Details', 'ai-ready-layer' ); }

	protected function field_definitions(): array {
		return FieldDefinitions::action();
	}

	protected function field_groups(): array {
		return [
			'Action'         => [ 'action_type', 'label', 'description', 'phone', 'url', 'method', 'is_public' ],
			'Advanced'       => [ 'availability_rule' ],
			'Relationships'  => [ 'related_services', 'related_locations' ],
		];
	}
}
