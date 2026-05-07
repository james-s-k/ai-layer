<?php
/**
 * OpenAI (GPT) completion provider.
 *
 * @package WPAIL\AI\Providers
 */

declare(strict_types=1);

namespace WPAIL\AI\Providers;

use WPAIL\AI\Contracts\ProviderInterface;

class OpenAiProvider implements ProviderInterface {

	public function __construct(
		private readonly string $api_key,
		private readonly string $model
	) {}

	public function complete( string $system, string $user ): string|\WP_Error {
		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( [
					'model'           => $this->model,
					'messages'        => [
						[ 'role' => 'system', 'content' => $system ],
						[ 'role' => 'user',   'content' => $user ],
					],
					'max_tokens'      => 4096,
					'temperature'     => 0.2,
					'response_format' => [ 'type' => 'json_object' ],
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
			$message = $body['error']['message'] ?? "OpenAI API returned HTTP {$code}.";
			return new \WP_Error( 'wpail_ai_error', $message );
		}

		return (string) ( $body['choices'][0]['message']['content'] ?? '' );
	}
}
