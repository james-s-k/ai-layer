<?php
/**
 * Shared permission callbacks for WordPress Abilities (MCP tools).
 *
 * Aligns with REST write checks: wpail_manage_content.
 *
 * @package WPAIL\Abilities
 */

declare(strict_types=1);

namespace WPAIL\Abilities;

use WPAIL\Licensing\Features;

class AbilityPermissions {

	public static function can_read(): bool {
		return true;
	}

	public static function can_write(): bool {
		return current_user_can( WPAIL_CAP_WRITE );
	}

	public static function can_delete(): bool {
		return current_user_can( WPAIL_CAP_WRITE );
	}

	public static function can_query_answers(): bool {
		return Features::answers_enabled();
	}
}
