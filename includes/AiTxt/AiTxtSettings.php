<?php
/**
 * Settings storage for the ai.txt feature.
 *
 * @package WPAIL\AiTxt
 */

declare(strict_types=1);

namespace WPAIL\AiTxt;

class AiTxtSettings {

	private const OPT_KEY = 'wpail_aitxt';

	public static function get( string $key, mixed $default = null ): mixed {
		$opts = (array) get_option( self::OPT_KEY, [] );
		return $opts[ $key ] ?? $default;
	}

	/** @return array<string, mixed> */
	public static function get_all(): array {
		$saved = (array) get_option( self::OPT_KEY, [] );
		$defaults = self::defaults();

		// Shallow merge, then handle agents separately (wp_parse_args doesn't deep-merge).
		$merged = wp_parse_args( $saved, $defaults );
		$merged['agents'] = isset( $saved['agents'] ) && is_array( $saved['agents'] )
			? array_values( $saved['agents'] )
			: [];

		return $merged;
	}

	/** @param array<string, mixed> $data */
	public static function save( array $data ): void {
		update_option( self::OPT_KEY, $data );
	}

	/** @return array<string, mixed> */
	private static function defaults(): array {
		return [
			'enabled'             => false,
			'allow_crawling'      => true,
			'allow_training'      => false,
			'require_attribution' => false,
			'agents'              => [],
		];
	}
}
