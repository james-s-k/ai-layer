<?php
/**
 * Base REST controller.
 *
 * Provides shared response helpers. All controllers extend this.
 *
 * @package WPAIL\Rest
 */

declare(strict_types=1);

namespace WPAIL\Rest;

use WPAIL\Support\Sanitizer;

abstract class BaseController extends \WP_REST_Controller {

	// $namespace is declared (untyped) in WP_REST_Controller — we set it in __construct
	// to avoid the "type must not be defined" fatal when redeclaring a parent property.
	public function __construct() {
		$this->namespace = WPAIL_REST_NS;
		$this->rest_base = '';
	}

	/**
	 * Wrap data in a consistent envelope.
	 *
	 * @param mixed $data
	 * @param array<string, mixed> $meta Optional meta fields (count, etc.)
	 * @return \WP_REST_Response
	 */
	protected function success( mixed $data, array $meta = [] ): \WP_REST_Response {
		$body = [ 'data' => $data ];

		if ( ! empty( $meta ) ) {
			$body['meta'] = $meta;
		}

		return new \WP_REST_Response( $body, 200 );
	}

	protected function created( mixed $data ): \WP_REST_Response {
		return new \WP_REST_Response( [ 'data' => $data ], 201 );
	}

	protected function not_found( string $message = 'Not found.' ): \WP_Error {
		return new \WP_Error( 'wpail_not_found', $message, [ 'status' => 404 ] );
	}

	protected function bad_request( string $message ): \WP_Error {
		return new \WP_Error( 'wpail_bad_request', $message, [ 'status' => 400 ] );
	}

	public function get_items_permissions_check( $request ) {
		return true;
	}

	public function get_item_permissions_check( $request ) {
		return true;
	}

	/**
	 * Require an authenticated user with edit_posts capability.
	 * Used as the permission_callback for all write endpoints.
	 */
	public function write_permissions_check( $request ): bool|\WP_Error {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'wpail_unauthorized', 'Authentication required.', [ 'status' => 401 ] );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'wpail_forbidden', 'You do not have permission to perform this action.', [ 'status' => 403 ] );
		}
		return true;
	}

	/**
	 * Meta-cap check for creating a post of a registered post type (respects roles / map_meta_cap).
	 *
	 * @param string $post_type Post type slug.
	 * @return true|\WP_Error
	 */
	protected function assert_can_create_post_type( string $post_type ): bool|\WP_Error {
		$pto = get_post_type_object( $post_type );
		if ( ! $pto || ! $pto->cap->create_posts ) {
			return new \WP_Error( 'wpail_forbidden', 'Invalid post type.', [ 'status' => 403 ] );
		}
		if ( ! current_user_can( $pto->cap->create_posts ) ) {
			return new \WP_Error( 'wpail_forbidden', 'You do not have permission to perform this action.', [ 'status' => 403 ] );
		}
		return true;
	}

	/**
	 * Meta-cap check for editing an existing post.
	 *
	 * @param int $post_id Post ID.
	 * @return true|\WP_Error
	 */
	protected function assert_can_edit_post( int $post_id ): bool|\WP_Error {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'wpail_forbidden', 'You do not have permission to perform this action.', [ 'status' => 403 ] );
		}
		return true;
	}

	/**
	 * Meta-cap check for deleting/trashing a post.
	 *
	 * @param int $post_id Post ID.
	 * @return true|\WP_Error
	 */
	protected function assert_can_delete_post( int $post_id ): bool|\WP_Error {
		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			return new \WP_Error( 'wpail_forbidden', 'You do not have permission to perform this action.', [ 'status' => 403 ] );
		}
		return true;
	}

	/**
	 * Sanitize only the fields that are explicitly present in $data.
	 * Used by PATCH endpoints to avoid overwriting omitted fields with defaults.
	 *
	 * @param array<string, mixed>                $data        Raw request params.
	 * @param array<string, array<string, mixed>> $definitions FieldDefinitions map.
	 * @return array<string, mixed>
	 */
	protected function sanitize_partial( array $data, array $definitions ): array {
		$clean = [];
		foreach ( $definitions as $key => $def ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$clean[ $key ] = Sanitizer::sanitize_by_type( $data[ $key ], $def['type'] ?? 'text' );
		}
		return $clean;
	}
}
