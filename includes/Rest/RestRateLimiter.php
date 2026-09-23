<?php
/**
 * Rate limits unauthenticated GET traffic to the ai-layer REST namespace.
 *
 * @package WPAIL\Rest
 */

declare(strict_types=1);

namespace WPAIL\Rest;

class RestRateLimiter {

	private const WINDOW_SECONDS = 60;
	private const LIMIT_READ     = 120;
	private const LIMIT_QUERY    = 30;

	public function register(): void {
		add_filter( 'rest_pre_dispatch', [ $this, 'maybe_throttle' ], 5, 3 );
	}

	/**
	 * @param mixed             $result
	 * @param \WP_REST_Server   $server
	 * @param \WP_REST_Request  $request
	 * @return mixed
	 */
	public function maybe_throttle( $result, $server, $request ) {
		if ( null !== $result ) {
			return $result;
		}

		$route = (string) $request->get_route();
		if ( ! str_starts_with( $route, '/ai-layer/v1/' ) ) {
			return $result;
		}

		if ( 'GET' !== $request->get_method() ) {
			return $result;
		}

		if ( is_user_logged_in() && current_user_can( WPAIL_CAP_WRITE ) ) {
			return $result;
		}

		$query_param = str_starts_with( $route, '/ai-layer/v1/answers' )
			? trim( (string) $request->get_param( 'query' ) )
			: '';
		$is_query    = str_starts_with( $route, '/ai-layer/v1/answers' ) && '' !== $query_param;
		$limit    = $is_query ? self::LIMIT_QUERY : self::LIMIT_READ;
		$bucket   = $is_query ? 'query' : 'read';
		$key      = 'wpail_rl_' . md5( $this->client_ip() . '|' . $bucket );

		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return new \WP_Error(
				'wpail_rate_limited',
				__( 'Too many requests. Please try again shortly.', 'ai-layer' ),
				[ 'status' => 429 ]
			);
		}

		set_transient( $key, $count + 1, self::WINDOW_SECONDS );

		return $result;
	}

	private function client_ip(): string {
		return sanitize_text_field( wp_unslash( (string) ( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) ) );
	}
}
