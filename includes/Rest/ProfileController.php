<?php
/**
 * REST endpoint: /profile
 *
 * GET /wp-json/ai-layer/v1/profile
 *
 * @package WPAIL\Rest
 */

declare(strict_types=1);

namespace WPAIL\Rest;

use WPAIL\Repositories\BusinessRepository;

class ProfileController extends BaseController {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/profile', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'get_item_permissions_check' ],
			],
		] );
	}

	public function get_item( $request ) {
		$repo    = new BusinessRepository();
		$model   = $repo->get();

		return $this->success( $model->to_public_array() );
	}
}
