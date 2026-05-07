<?php
/**
 * Hooks into the WP REST API and logs endpoint hits and query data.
 *
 * @package WPAIL\Analytics
 */

declare(strict_types=1);

namespace WPAIL\Analytics;

class AnalyticsLogger {

	public function register(): void {
		add_filter( 'rest_post_dispatch', [ $this, 'log_request' ], 999, 3 );
	}

	/**
	 * @param \WP_HTTP_Response $result
	 * @param \WP_REST_Server   $server
	 * @param \WP_REST_Request  $request
	 * @return \WP_HTTP_Response
	 */
	public function log_request( $result, $server, $request ) {
		$route = (string) $request->get_route();

		if ( 0 !== strpos( $route, '/ai-layer/v1/' ) ) {
			return $result;
		}
		if ( 'GET' !== $request->get_method() ) {
			return $result;
		}

		$endpoint   = $this->extract_endpoint( $route );
		$query_text = null;
		$matched    = 1;
		$confidence = null;
		$source     = null;

		$query_param = trim( (string) $request->get_param( 'query' ) );
		if ( 'answers' === $endpoint && '' !== $query_param ) {
			$query_text = mb_substr( sanitize_text_field( $query_param ), 0, 500 );
			$status     = method_exists( $result, 'get_status' ) ? (int) $result->get_status() : 200;
			$matched    = ( 200 === $status ) ? 1 : 0;

			if ( 1 === $matched ) {
				$data = method_exists( $result, 'get_data' ) ? (array) $result->get_data() : [];
				if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
					$confidence = isset( $data['data']['confidence'] ) ? (string) $data['data']['confidence'] : null;
					$source     = isset( $data['data']['source'] ) ? (string) $data['data']['source'] : null;
				}
			}
		}

		$this->insert( $endpoint, $query_text, $matched, $confidence, $source );

		return $result;
	}

	private function extract_endpoint( string $route ): string {
		$stripped = ltrim( str_replace( '/ai-layer/v1/', '', $route ), '/' );
		$parts    = explode( '/', $stripped );
		return sanitize_key( $parts[0] ?? 'unknown' );
	}

	private function insert(
		string $endpoint,
		?string $query_text,
		int $matched,
		?string $confidence,
		?string $source
	): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			AnalyticsTable::table_name(),
			[
				'endpoint'   => $endpoint,
				'query_text' => $query_text,
				'matched'    => $matched,
				'confidence' => $confidence,
				'source'     => $source,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			],
			[ '%s', '%s', '%d', '%s', '%s', '%s' ]
		);
	}
}
