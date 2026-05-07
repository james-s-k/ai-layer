<?php
/**
 * AI Import settings: provider API keys and model selection.
 *
 * @package WPAIL\AI
 */

declare(strict_types=1);

namespace WPAIL\AI;

class AiSettings {

	const OPTION_KEY   = 'wpail_ai_settings';
	const NONCE_ACTION = 'wpail_save_ai_settings';
	const NONCE_NAME   = 'wpail_ai_settings_nonce';
	const DEFAULT_MODEL = 'gpt-4o-mini';

	/**
	 * All supported models, ordered: OpenAI first, Anthropic second, Google third.
	 *
	 * @var array<string, array<string, string>>
	 */
	const MODELS = [
		// OpenAI.
		'gpt-4o-mini'               => [ 'provider' => 'openai',    'name' => 'GPT-4o Mini',       'speed' => 'Very Fast' ],
		'gpt-4.1-mini'              => [ 'provider' => 'openai',    'name' => 'GPT-4.1 Mini',      'speed' => 'Very Fast' ],
		'gpt-4o'                    => [ 'provider' => 'openai',    'name' => 'GPT-4o',            'speed' => 'Fast' ],
		'gpt-4.1'                   => [ 'provider' => 'openai',    'name' => 'GPT-4.1',           'speed' => 'Fast' ],
		// Anthropic.
		'claude-haiku-4-5-20251001' => [ 'provider' => 'anthropic', 'name' => 'Claude Haiku 4.5',  'speed' => 'Very Fast' ],
		'claude-sonnet-4-6'         => [ 'provider' => 'anthropic', 'name' => 'Claude Sonnet 4.6', 'speed' => 'Medium' ],
		'claude-opus-4-7'           => [ 'provider' => 'anthropic', 'name' => 'Claude Opus 4.7',   'speed' => 'Slower' ],
		// Google.
		'gemini-2.5-flash'          => [ 'provider' => 'google',    'name' => 'Gemini 2.5 Flash',  'speed' => 'Very Fast' ],
		'gemini-2.5-pro'            => [ 'provider' => 'google',    'name' => 'Gemini 2.5 Pro',    'speed' => 'Medium' ],
	];

	const PROVIDER_LABELS = [
		'openai'    => 'OpenAI',
		'anthropic' => 'Anthropic',
		'google'    => 'Google',
	];

	public static function get( string $key, mixed $default = null ): mixed {
		$settings = get_option( self::OPTION_KEY, [] );
		return $settings[ $key ] ?? $default;
	}

	public static function get_api_key( string $provider ): string {
		return (string) self::get( 'api_key_' . $provider, '' );
	}

	public static function get_selected_model(): string {
		$model = (string) self::get( 'model', self::DEFAULT_MODEL );
		return isset( self::MODELS[ $model ] ) ? $model : self::DEFAULT_MODEL;
	}

	public static function get_model_provider( string $model_id ): string {
		return self::MODELS[ $model_id ]['provider'] ?? 'openai';
	}

	/** @return array<string, string> */
	public static function get_model_info( string $model_id ): array {
		return self::MODELS[ $model_id ] ?? [];
	}

	public function register(): void {
		add_action( 'admin_init', [ $this, 'handle_save' ] );
	}

	public function handle_save(): void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'ai-layer' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ai-layer' ) );
		}

		$current  = get_option( self::OPTION_KEY, [] );
		$model    = sanitize_text_field( wp_unslash( $_POST['wpail_ai_model'] ?? '' ) );
		$current['model'] = isset( self::MODELS[ $model ] ) ? $model : self::DEFAULT_MODEL;

		foreach ( array_keys( self::PROVIDER_LABELS ) as $provider ) {
			$raw_key = sanitize_text_field( wp_unslash( $_POST[ 'wpail_ai_key_' . $provider ] ?? '' ) );
			if ( '' !== $raw_key ) {
				$current[ 'api_key_' . $provider ] = $raw_key;
			}
		}

		update_option( self::OPTION_KEY, $current );

		add_action( 'admin_notices', static function () {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'AI settings saved.', 'ai-layer' ) . '</p></div>';
		} );
	}
}
