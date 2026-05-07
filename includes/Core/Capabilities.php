<?php
/**
 * Custom capability registration.
 *
 * Defines the wpail_manage_content capability and grants it automatically to
 * any user who has manage_options (WordPress Administrators). Other roles can
 * be granted the capability via a plugin such as User Role Editor.
 *
 * @package WPAIL\Core
 */

declare(strict_types=1);

namespace WPAIL\Core;

class Capabilities {

	public function register(): void {
		add_filter( 'user_has_cap', [ $this, 'grant_to_admins' ], 10, 3 );
	}

	/**
	 * Grant wpail_manage_content to any user who has manage_options.
	 *
	 * @param array<string, bool> $allcaps All capabilities the user currently has.
	 * @param array<int, string>  $caps    The required capabilities being checked.
	 * @param array<int, mixed>   $args    Arguments passed to current_user_can().
	 * @return array<string, bool>
	 */
	public function grant_to_admins( array $allcaps, array $caps, array $args ): array {
		if ( in_array( WPAIL_CAP_WRITE, $caps, true ) && ! empty( $allcaps['manage_options'] ) ) {
			$allcaps[ WPAIL_CAP_WRITE ] = true;
		}
		return $allcaps;
	}
}
