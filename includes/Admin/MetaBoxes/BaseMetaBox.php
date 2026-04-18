<?php
/**
 * Abstract base meta box.
 *
 * All CPT meta boxes extend this. Field rendering and save logic are shared.
 * Each sub-class only needs to declare its post type, field definitions,
 * and optional field groups.
 *
 * @package WPAIL\Admin\MetaBoxes
 */

declare(strict_types=1);

namespace WPAIL\Admin\MetaBoxes;

use WPAIL\Admin\FieldRenderer;
use WPAIL\Support\FieldDefinitions;
use WPAIL\Support\RelationshipHelper;
use WPAIL\Support\Sanitizer;

abstract class BaseMetaBox {

	abstract protected function post_type(): string;
	abstract protected function box_id(): string;
	abstract protected function box_title(): string;
	/** @return array<string, array<string, mixed>> */
	abstract protected function field_definitions(): array;

	/**
	 * Override in sub-classes to group fields into sections.
	 * Returns [ 'Group Label' => [ 'field_key', ... ], ... ]
	 *
	 * @return array<string, array<string>>
	 */
	protected function field_groups(): array {
		return [ '' => array_keys( $this->field_definitions() ) ];
	}

	public function register(): void {
		add_action( 'add_meta_boxes',           [ $this, 'add_meta_box' ] );
		add_action( 'save_post_' . $this->post_type(), [ $this, 'save_meta' ], 10, 2 );
		// Also hook generic save_post for autosave guard.
		add_action( 'save_post', [ $this, 'save_meta' ], 10, 2 );
	}

	public function add_meta_box(): void {
		add_meta_box(
			$this->box_id(),
			$this->box_title(),
			[ $this, 'render' ],
			$this->post_type(),
			'normal',
			'high'
		);
	}

	/**
	 * Render the meta box HTML.
	 *
	 * @param \WP_Post $post
	 */
	public function render( \WP_Post $post ): void {
		$data   = RelationshipHelper::get_meta( $post->ID );
		$defs   = $this->field_definitions();
		$groups = $this->field_groups();

		wp_nonce_field( 'wpail_save_' . $this->box_id(), 'wpail_' . $this->box_id() . '_nonce' );

		echo '<div class="wpail-meta-box">';

		foreach ( $groups as $group_label => $keys ) {
			if ( '' !== $group_label ) {
				echo '<h3 class="wpail-meta-box__group-title">' . esc_html( $group_label ) . '</h3>';
			}

			echo '<table class="form-table wpail-meta-box__table"><tbody>';

			foreach ( $keys as $key ) {
				if ( ! isset( $defs[ $key ] ) ) {
					continue;
				}

				$def        = $defs[ $key ];
				$value      = $data[ $key ] ?? ( $def['default'] ?? '' );
				$is_private = ( $def['visibility'] ?? '' ) === FieldDefinitions::VISIBILITY_PRIVATE;

				echo '<tr class="wpail-field ' . ( $is_private ? 'wpail-field--private' : '' ) . '">';
				echo '<th scope="row">';
				echo '<label for="wpail_' . esc_attr( $key ) . '">';
				echo esc_html( $def['label'] );
				if ( $is_private ) {
					echo ' <span class="wpail-badge wpail-badge--private">' . esc_html__( 'Internal', 'ai-ready-layer' ) . '</span>';
				}
				if ( ! empty( $def['required'] ) ) {
					echo ' <span class="required">*</span>';
				}
				echo '</label></th>';
				echo '<td>';
				FieldRenderer::render( $key, $def, $value );
				if ( ! empty( $def['help'] ) ) {
					echo '<p class="description">' . esc_html( $def['help'] ) . '</p>';
				}
				echo '</td></tr>';
			}

			echo '</tbody></table>';
		}

		echo '</div>';
	}

	/**
	 * Save meta when the post is saved.
	 *
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function save_meta( int $post_id, \WP_Post $post ): void {
		$nonce_name = 'wpail_' . $this->box_id() . '_nonce';

		// Skip autosave, revisions, other post types, and permission failures.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( 'revision' === $post->post_type ) {
			return;
		}

		if ( $post->post_type !== $this->post_type() ) {
			return;
		}

		if ( ! isset( $_POST[ $nonce_name ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) ), 'wpail_save_' . $this->box_id() ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw  = wp_unslash( $_POST );
		$data = Sanitizer::sanitize_fields( $raw, $this->field_definitions() );

		RelationshipHelper::save_meta( $post_id, $data );
	}
}
