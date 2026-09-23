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
		$connector_key = ConnectorBridge::get_api_key( $provider );
		if ( '' !== $connector_key ) {
			return $connector_key;
		}

		return AiKeyEncryption::decrypt( (string) self::get( 'api_key_' . $provider, '' ) );
	}

	public static function get_local_api_key( string $provider ): string {
		return AiKeyEncryption::decrypt( (string) self::get( 'api_key_' . $provider, '' ) );
	}

	public static function is_provider_configured( string $provider ): bool {
		return '' !== self::get_api_key( $provider );
	}

	public static function is_selected_provider_configured(): bool {
		$model    = self::get_selected_model();
		$info     = self::get_model_info( $model );
		$provider = $info['provider'] ?? 'openai';

		return self::is_provider_configured( $provider );
	}

	/** @return list<string> Provider slugs with a configured API key. */
	public static function get_configured_providers(): array {
		$configured = [];
		foreach ( array_keys( self::PROVIDER_LABELS ) as $provider ) {
			if ( self::is_provider_configured( $provider ) ) {
				$configured[] = $provider;
			}
		}
		return $configured;
	}

	/**
	 * Models the user can select — limited to providers with API keys when any are set.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function get_available_models(): array {
		$configured = self::get_configured_providers();

		if ( empty( $configured ) ) {
			return self::MODELS;
		}

		return array_filter(
			self::MODELS,
			static fn( array $info ): bool => in_array( $info['provider'], $configured, true )
		);
	}

	/**
	 * Echo <option>/<optgroup> markup for the model selector.
	 */
	public static function render_model_options( string $selected ): void {
		$available        = self::get_available_models();
		$current_provider = '';

		foreach ( $available as $model_id => $info ) {
			if ( $info['provider'] !== $current_provider ) {
				if ( '' !== $current_provider ) {
					echo '</optgroup>';
				}
				$current_provider = $info['provider'];
				$group_label      = self::PROVIDER_LABELS[ $current_provider ] ?? $current_provider;
				echo '<optgroup label="' . esc_attr( $group_label ) . '">';
			}
			printf(
				'<option value="%s" %s>%s (%s)</option>',
				esc_attr( $model_id ),
				selected( $selected, $model_id, false ),
				esc_html( $info['name'] ),
				esc_html( $info['speed'] )
			);
		}

		if ( '' !== $current_provider ) {
			echo '</optgroup>';
		}
	}

	/**
	 * Persist model and optional plugin-local API keys (used when Connectors API is unavailable).
	 *
	 * @param array<string, mixed> $raw_post Unslashed POST data.
	 */
	public static function save_from_request( array $raw_post ): void {
		$current = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $current ) ) {
			$current = [];
		}

		$model     = sanitize_text_field( $raw_post['wpail_ai_model'] ?? '' );
		$available = self::get_available_models();

		if ( isset( $available[ $model ] ) ) {
			$current['model'] = $model;
		} elseif ( ! empty( $available ) ) {
			$current['model'] = (string) array_key_first( $available );
		} else {
			$current['model'] = isset( self::MODELS[ $model ] ) ? $model : self::DEFAULT_MODEL;
		}

		$encrypt_failed = false;

		foreach ( array_keys( self::PROVIDER_LABELS ) as $provider ) {
			$raw_key = sanitize_text_field( $raw_post[ 'wpail_ai_key_' . $provider ] ?? '' );
			if ( '' === $raw_key ) {
				continue;
			}

			$encrypted = AiKeyEncryption::encrypt( $raw_key );
			if ( '' === $encrypted ) {
				$encrypt_failed = true;
				continue;
			}

			$current[ 'api_key_' . $provider ] = $encrypted;
		}

		update_option( self::OPTION_KEY, $current );

		if ( $encrypt_failed ) {
			set_transient( 'wpail_ai_key_encrypt_failed', 1, MINUTE_IN_SECONDS );
		}
	}

	public static function get_selected_model(): string {
		$saved = (string) self::get( 'model', self::DEFAULT_MODEL );
		if ( ! isset( self::MODELS[ $saved ] ) ) {
			$saved = self::DEFAULT_MODEL;
		}

		$available = self::get_available_models();
		if ( isset( $available[ $saved ] ) ) {
			return $saved;
		}

		if ( ! empty( $available ) ) {
			return (string) array_key_first( $available );
		}

		return $saved;
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

		self::save_from_request( (array) wp_unslash( $_POST ) );

		add_action( 'admin_notices', static function () {
			if ( get_transient( 'wpail_ai_key_encrypt_failed' ) ) {
				delete_transient( 'wpail_ai_key_encrypt_failed' );
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'AI settings saved, but one or more API keys could not be encrypted on this server. Keys were not updated.', 'ai-layer' ) . '</p></div>';
			}
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'AI settings saved.', 'ai-layer' ) . '</p></div>';
		} );
	}
}
