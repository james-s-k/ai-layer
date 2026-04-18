<?php
/**
 * Settings storage for the llms.txt feature.
 *
 * @package WPAIL\LLMsTxt
 */

declare(strict_types=1);

namespace WPAIL\LLMsTxt;

class LLMsTxtSettings {

	private const OPT_KEY = 'wpail_llmstxt';

	public static function get( string $key, mixed $default = null ): mixed {
		$opts = (array) get_option( self::OPT_KEY, [] );
		return $opts[ $key ] ?? $default;
	}

	public static function get_all(): array {
		return wp_parse_args( (array) get_option( self::OPT_KEY, [] ), self::defaults() );
	}

	public static function save( array $data ): void {
		update_option( self::OPT_KEY, $data );
	}

	private static function defaults(): array {
		return [
			'enabled'           => false,
			'include_endpoints' => true,
			'include_answers'   => true,
			'include_pages'     => false,
			'custom_intro'      => '',
			'custom_pages'      => '',
		];
	}
}
