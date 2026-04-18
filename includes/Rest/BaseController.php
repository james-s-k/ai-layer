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

	protected function not_found( string $message = 'Not found.' ): \WP_Error {
		return new \WP_Error( 'wpail_not_found', $message, [ 'status' => 404 ] );
	}

	protected function bad_request( string $message ): \WP_Error {
		return new \WP_Error( 'wpail_bad_request', $message, [ 'status' => 400 ] );
	}

	/**
	 * All endpoints are public (read-only) in v1.
	 * Future: add authenticated write endpoints here.
	 */
	public function get_items_permissions_check( $request ) {
		return true;
	}

	public function get_item_permissions_check( $request ) {
		return true;
	}
}
