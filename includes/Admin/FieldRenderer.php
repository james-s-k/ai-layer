<?php
/**
 * Renders admin form fields from FieldDefinitions.
 *
 * All field rendering is centralised here so meta boxes and settings pages
 * use the same markup. Fields are rendered from their definition arrays —
 * no hardcoded HTML elsewhere.
 *
 * @package WPAIL\Admin
 */

declare(strict_types=1);

namespace WPAIL\Admin;

class FieldRenderer {

	/**
	 * Render a single form field.
	 *
	 * @param string               $key   Field key.
	 * @param array<string, mixed> $def   Field definition from FieldDefinitions.
	 * @param mixed                $value Current value.
	 * @param string               $prefix Optional prefix for the field name attribute.
	 */
	public static function render( string $key, array $def, mixed $value, string $prefix = '' ): void {
		$name = $prefix ? "{$prefix}[{$key}]" : $key;
		$id   = 'wpail_' . str_replace( [ '[', ']' ], '_', $name );
		$type = $def['type'] ?? 'text';

		match ( $type ) {
			'textarea'   => self::textarea( $name, $id, (string) $value ),
			'select'     => self::select( $name, $id, $def['options'] ?? [], (string) $value ),
			'checkboxes' => self::checkboxes( $name, $id, $def['options'] ?? [], (array) $value ),
			'checkbox'   => self::checkbox( $name, $id, (bool) $value ),
			'post_ids'   => self::post_ids( $name, $id, $def['post_type'] ?? '', (array) $value ),
			'number'     => self::input( $name, $id, 'number', (string) ( $value ?? '' ) ),
			'url'        => self::input( $name, $id, 'url', (string) $value ),
			'email'      => self::input( $name, $id, 'email', (string) $value ),
			'tel'        => self::input( $name, $id, 'tel', (string) $value ),
			default      => self::input( $name, $id, 'text', (string) $value ),
		};
	}

	private static function input( string $name, string $id, string $type, string $value ): void {
		printf(
			'<input type="%s" id="%s" name="%s" value="%s" class="regular-text">',
			esc_attr( $type ),
			esc_attr( $id ),
			esc_attr( $name ),
			esc_attr( $value )
		);
	}

	private static function textarea( string $name, string $id, string $value ): void {
		printf(
			'<textarea id="%s" name="%s" rows="4" class="large-text">%s</textarea>',
			esc_attr( $id ),
			esc_attr( $name ),
			esc_textarea( $value )
		);
	}

	/**
	 * @param array<string, string> $options
	 */
	private static function select( string $name, string $id, array $options, string $value ): void {
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $options as $option_value => $option_label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( (string) $option_value ),
				selected( $value, (string) $option_value, false ),
				esc_html( $option_label )
			);
		}
		echo '</select>';
	}

	/**
	 * @param array<string, string> $options
	 * @param array<string>         $value
	 */
	private static function checkboxes( string $name, string $id, array $options, array $value ): void {
		echo '<fieldset>';
		foreach ( $options as $option_value => $option_label ) {
			$checked = in_array( $option_value, $value, true );
			printf(
				'<label><input type="checkbox" name="%s[]" value="%s" %s> %s</label><br>',
				esc_attr( $name ),
				esc_attr( $option_value ),
				checked( $checked, true, false ),
				esc_html( $option_label )
			);
		}
		echo '</fieldset>';
	}

	private static function checkbox( string $name, string $id, bool $value ): void {
		printf(
			'<input type="checkbox" id="%s" name="%s" value="1" %s>',
			esc_attr( $id ),
			esc_attr( $name ),
			checked( $value, true, false )
		);
	}

	/**
	 * Render a multi-select for related post IDs.
	 * Shows all published posts of the given type with checkboxes.
	 *
	 * @param array<int> $value Currently selected IDs.
	 */
	private static function post_ids( string $name, string $id, string $post_type, array $value ): void {
		if ( '' === $post_type ) {
			return;
		}

		$posts = get_posts( [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		if ( empty( $posts ) ) {
			$label = str_replace( 'wpail_', '', $post_type );
			printf(
				'<p class="description">%s</p>',
				esc_html( sprintf(
					/* translators: %s: post type label */
					__( 'No %s found. Create some first.', 'ai-ready-layer' ),
					$label
				) )
			);
			return;
		}

		echo '<div class="wpail-post-checklist">';
		foreach ( $posts as $post ) {
			$checked = in_array( $post->ID, $value, true );
			printf(
				'<label class="wpail-post-check"><input type="checkbox" name="%s[]" value="%d" %s> %s</label>',
				esc_attr( $name ),
				$post->ID,
				checked( $checked, true, false ),
				esc_html( $post->post_title )
			);
		}
		echo '</div>';
	}
}
