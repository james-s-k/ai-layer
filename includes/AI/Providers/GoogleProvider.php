<?php
/**
 * Google (Gemini) completion provider.
 *
 * @package WPAIL\AI\Providers
 */

declare(strict_types=1);

namespace WPAIL\AI\Providers;

use WPAIL\AI\Contracts\ProviderInterface;

class GoogleProvider implements ProviderInterface {

	public function __construct(
		private readonly string $api_key,
		private readonly string $model
	) {}

	public function complete( string $system, string $user ): string|\WP_Error {
		$url = add_query_arg(
			'key',
			$this->api_key,
			sprintf(
				'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
				rawurlencode( $this->model )
			)
		);

		$response = wp_remote_post(
			$url,
			[
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode( [
					'system_instruction' => [
						'parts' => [ [ 'text' => $system ] ],
					],
					'contents'           => [
						[ 'parts' => [ [ 'text' => $user ] ] ],
					],
					'generationConfig'   => [
						'maxOutputTokens' => 4096,
						'temperature'     => 0.2,
					],
				] ),
				'timeout' => 60,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$message = $body['error']['message'] ?? "Google API returned HTTP {$code}.";
			return new \WP_Error( 'wpail_ai_error', $message );
		}

		return (string) ( $body['candidates'][0]['content']['parts'][0]['text'] ?? '' );
	}
}
