<?php
/**
 * Instantiates the correct AI provider based on current settings.
 *
 * @package WPAIL\AI
 */

declare(strict_types=1);

namespace WPAIL\AI;

use WPAIL\AI\Contracts\ProviderInterface;
use WPAIL\AI\ConnectorBridge;
use WPAIL\AI\Providers\AnthropicProvider;
use WPAIL\AI\Providers\OpenAiProvider;
use WPAIL\AI\Providers\GoogleProvider;

class ProviderFactory {

	public static function make(): ProviderInterface|\WP_Error {
		$model    = AiSettings::get_selected_model();
		$info     = AiSettings::get_model_info( $model );
		$provider = $info['provider'] ?? '';
		$api_key  = AiSettings::get_api_key( $provider );

		if ( '' === $api_key ) {
			$label = AiSettings::PROVIDER_LABELS[ $provider ] ?? $provider;
			if ( ConnectorBridge::is_available() && '' !== ConnectorBridge::get_admin_url() ) {
				$message = sprintf(
					/* translators: 1: provider name, 2: Settings → Connectors URL */
					__( 'No API key configured for %1$s. Add it under %2$s.', 'ai-layer' ),
					$label,
					__( 'Settings → Connectors', 'ai-layer' )
				);
			} else {
				$message = sprintf(
					/* translators: %s: provider name */
					__( 'No API key configured for %s. Add it in AI Layer → Setup Wizard or AI Import.', 'ai-layer' ),
					$label
				);
			}
			return new \WP_Error( 'wpail_no_api_key', $message );
		}

		return match ( $provider ) {
			'anthropic' => new AnthropicProvider( $api_key, $model ),
			'openai'    => new OpenAiProvider( $api_key, $model ),
			'google'    => new GoogleProvider( $api_key, $model ),
			default     => new \WP_Error( 'wpail_unknown_provider', "Unknown provider: {$provider}." ),
		};
	}
}
