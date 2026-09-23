<?php
/**
 * Bridge to WordPress 7.0+ Connectors API for AI provider credentials.
 *
 * When Settings → Connectors is available, API keys entered there are used
 * for AI Import. Falls back to plugin-local keys in wpail_ai_settings.
 *
 * @see https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/
 * @package WPAIL\AI
 */

declare(strict_types=1);

namespace WPAIL\AI;

class ConnectorBridge {

	/** @var array<string, string> Plugin provider slug → connector ID. */
	private const PROVIDER_MAP = [
		'openai'    => 'openai',
		'anthropic' => 'anthropic',
		'google'    => 'google',
	];

	public static function is_available(): bool {
		return function_exists( 'wp_get_connector' );
	}

	public static function get_admin_url(): string {
		if ( ! self::is_available() ) {
			return '';
		}

		return admin_url( 'options-connectors.php' );
	}

	/**
	 * Resolve an API key from the Connectors API (env → constant → database).
	 */
	public static function get_api_key( string $provider ): string {
		if ( ! self::is_available() ) {
			return '';
		}

		$connector_id = self::PROVIDER_MAP[ $provider ] ?? $provider;

		if ( function_exists( 'wp_is_connector_registered' ) && ! wp_is_connector_registered( $connector_id ) ) {
			return self::read_database_key( 'connectors_ai_' . $connector_id . '_api_key', $connector_id );
		}

		$connector = wp_get_connector( $connector_id );
		if ( ! is_array( $connector ) ) {
			return '';
		}

		$auth = $connector['authentication'] ?? [];
		if ( ( $auth['method'] ?? '' ) !== 'api_key' ) {
			return '';
		}

		$setting_name = (string) ( $auth['setting_name'] ?? 'connectors_ai_' . $connector_id . '_api_key' );

		return self::read_database_key( $setting_name, $connector_id );
	}

	/**
	 * @return array<string, bool> Provider slug => configured.
	 */
	public static function get_provider_status(): array {
		$status = [];
		foreach ( array_keys( AiSettings::PROVIDER_LABELS ) as $provider ) {
			$status[ $provider ] = AiSettings::is_provider_configured( $provider );
		}
		return $status;
	}

	private static function read_database_key( string $setting_name, string $connector_id ): string {
		$env_name = strtoupper( $connector_id ) . '_API_KEY';

		$env = getenv( $env_name );
		if ( is_string( $env ) && '' !== $env ) {
			return $env;
		}

		if ( defined( $env_name ) ) {
			$constant = constant( $env_name );
			if ( is_string( $constant ) && '' !== $constant ) {
				return $constant;
			}
		}

		return (string) get_option( $setting_name, '' );
	}
}
