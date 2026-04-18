<?php
/**
 * Business profile data access.
 *
 * The business profile is stored as a single JSON blob in wp_options.
 * This is the only entity that uses options rather than a CPT.
 *
 * @package WPAIL\Repositories
 */

declare(strict_types=1);

namespace WPAIL\Repositories;

use WPAIL\Models\BusinessModel;
use WPAIL\Transformers\BusinessTransformer;
use WPAIL\Support\Sanitizer;
use WPAIL\Support\FieldDefinitions;

class BusinessRepository {

	/**
	 * Retrieve the business profile as a canonical model.
	 */
	public function get(): BusinessModel {
		$raw = get_option( WPAIL_OPT_BUSINESS, [] );

		if ( ! is_array( $raw ) ) {
			$raw = [];
		}

		return BusinessTransformer::from_options( $raw );
	}

	/**
	 * Save submitted business profile data.
	 *
	 * @param array<string, mixed> $data Raw submitted data (e.g. from $_POST).
	 */
	public function save( array $data ): void {
		$clean = Sanitizer::sanitize_fields( $data, FieldDefinitions::business() );
		update_option( WPAIL_OPT_BUSINESS, $clean );
	}

	/**
	 * Return the raw options array (for admin form population).
	 *
	 * @return array<string, mixed>
	 */
	public function get_raw(): array {
		$raw = get_option( WPAIL_OPT_BUSINESS, [] );
		return is_array( $raw ) ? $raw : [];
	}
}
