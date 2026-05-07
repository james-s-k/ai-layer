<?php
/**
 * Anthropic (Claude) completion provider.
 *
 * @package WPAIL\AI\Providers
 */

declare(strict_types=1);

namespace WPAIL\AI\Providers;

use WPAIL\AI\Contracts\ProviderInterface;

class AnthropicProvider implements ProviderInterface {

	public function __construct(
		private readonly string $api_key,
		private readonly string $model
	) {}

	public function complete( string $system, string $user ): string|\WP_Error {
		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			[
				'headers' => [
					'x-api-key'         => $this->api_key,
					'anthropic-version' => '2023-06-01',
					'content-type'      => 'application/json',
				],
				'body'    => wp_json_encode( [
					'model'      => $this->model,
					'max_tokens' => 4096,
					'system'     => $system,
					'messages'   => [ [ 'role' => 'user', 'content' => $user ] ],
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
			$message = $body['error']['message'] ?? "Anthropic API returned HTTP {$code}.";
			return new \WP_Error( 'wpail_ai_error', $message );
		}

		return (string) ( $body['content'][0]['text'] ?? '' );
	}
}
