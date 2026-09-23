<?php
/**
 * Wrapper for wp_register_ability() — MCP requires WordPress 6.9+.
 *
 * Core plugin supports WP 6.0+; AbilitiesRegistrar exits early when the API is unavailable.
 *
 * @package WPAIL\Abilities
 */

declare(strict_types=1);

namespace WPAIL\Abilities;

class AbilityRegistry {

	/**
	 * @param array<string, mixed> $args Ability registration arguments.
	 */
	public static function register( string $name, array $args ): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// phpcs:ignore PluginCheck.CodeAnalysis.WPFunctionCompatibilities.wp_function_not_compatible_with_requires_wp -- WP 6.9+ only; guarded above.
		wp_register_ability( $name, $args );
	}

	/**
	 * MCP ability errors are API payloads, not HTML output.
	 */
	public static function throwError( string $message ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- API error payload, not HTML.
		AbilityRegistry::throwError( $message );
	}

	public static function throwNotFoundId( string $entity, int $id ): void {
		self::throwError( $entity . ' not found: ' . max( 0, $id ) );
	}

	public static function throwNotFoundSlug( string $entity, string $slug ): void {
		self::throwError( $entity . ' not found: ' . sanitize_title( $slug ) );
	}

	public static function throwFromWpError( \WP_Error $error ): void {
		self::throwError( sanitize_text_field( $error->get_error_message() ) );
	}

	public static function throwQueryNotFound( string $query ): void {
		self::throwError( 'No matching answer found for: ' . sanitize_text_field( $query ) );
	}
}
