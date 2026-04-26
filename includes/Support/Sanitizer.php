<?php
/**
 * Sanitisation helpers for plugin data.
 *
 * Wraps WordPress sanitisation functions with type-aware dispatch.
 * All data coming from $_POST or meta storage passes through here.
 *
 * @package WPAIL\Support
 */

declare(strict_types=1);

namespace WPAIL\Support;

class Sanitizer {

	/**
	 * Sanitise a single value by field type.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $type  Field type from FieldDefinitions.
	 * @return mixed Sanitised value.
	 */
	public static function sanitize_by_type( mixed $value, string $type ): mixed {
		return match ( $type ) {
			'text'        => sanitize_text_field( (string) $value ),
			'textarea'    => sanitize_textarea_field( (string) $value ),
			'url'         => esc_url_raw( (string) $value ),
			'email'       => sanitize_email( (string) $value ),
			'tel'         => preg_replace( '/[^\d\s\+\-\(\)]/', '', (string) $value ),
			'number'      => is_numeric( $value ) ? (float) $value : null,
			'checkbox'    => (bool) $value,
			'select'      => sanitize_text_field( (string) $value ),
			'checkboxes'  => self::sanitize_checkboxes( $value ),
			'post_ids'    => self::sanitize_post_ids( $value ),
			default       => sanitize_text_field( (string) $value ),
		};
	}

	/**
	 * Sanitise only the keys present in $data against the definition map.
	 *
	 * Unlike sanitize_fields(), absent keys are skipped rather than defaulted.
	 * Suitable for PATCH / JSON API inputs where every value is explicit.
	 *
	 * @param array<string, mixed> $data        Partial input (only keys to update).
	 * @param array<string, array<string, mixed>> $definitions Field definitions.
	 * @return array<string, mixed>
	 */
	public static function sanitize_partial( array $data, array $definitions ): array {
		$clean = [];
		foreach ( $definitions as $key => $def ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$clean[ $key ] = self::sanitize_by_type( $data[ $key ], $def['type'] ?? 'text' );
		}
		return $clean;
	}

	/**
	 * Sanitise an entire fields array against a definition map.
	 *
	 * @param array<string, mixed> $data   Raw submitted data.
	 * @param array<string, array<string, mixed>> $definitions Field definitions.
	 * @return array<string, mixed>
	 */
	public static function sanitize_fields( array $data, array $definitions ): array {
		$clean = [];

		foreach ( $definitions as $key => $def ) {
			$type  = $def['type'] ?? 'text';
			$value = $data[ $key ] ?? ( $def['default'] ?? null );

			if ( 'checkbox' === $type ) {
				// Unchecked checkboxes are absent from POST data.
				$value = isset( $data[ $key ] );
			} elseif ( 'checkboxes' === $type ) {
				$value = $data[ $key ] ?? [];
			} elseif ( 'post_ids' === $type ) {
				$value = $data[ $key ] ?? [];
			}

			$clean[ $key ] = self::sanitize_by_type( $value, $type );
		}

		return $clean;
	}

	/**
	 * @param mixed $value
	 * @return array<string>
	 */
	private static function sanitize_checkboxes( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}
		return array_map( 'sanitize_text_field', array_values( $value ) );
	}

	/**
	 * @param mixed $value
	 * @return array<int>
	 */
	private static function sanitize_post_ids( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}
		return array_values(
			array_filter(
				array_map( 'absint', $value ),
				fn( int $id ) => $id > 0
			)
		);
	}

	/**
	 * Convert a comma-separated string to a clean array.
	 *
	 * @param string $value
	 * @return array<string>
	 */
	public static function csv_to_array( string $value ): array {
		if ( '' === trim( $value ) ) {
			return [];
		}
		return array_values(
			array_filter(
				array_map( 'trim', explode( ',', $value ) )
			)
		);
	}

	/**
	 * Convert a newline-separated string to a clean array.
	 *
	 * @param string $value
	 * @return array<string>
	 */
	public static function lines_to_array( string $value ): array {
		if ( '' === trim( $value ) ) {
			return [];
		}
		return array_values(
			array_filter(
				array_map( 'trim', explode( "\n", $value ) )
			)
		);
	}
}
