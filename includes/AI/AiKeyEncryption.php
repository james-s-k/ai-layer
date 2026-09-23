<?php
/**
 * Encrypt plugin-local AI provider API keys at rest.
 *
 * @package WPAIL\AI
 */

declare(strict_types=1);

namespace WPAIL\AI;

class AiKeyEncryption {

	private const PREFIX_OPENSSL = 'enc:v1:';
	private const PREFIX_SODIUM  = 'enc:v2:';

	public static function encrypt( string $plain ): string {
		$plain = trim( $plain );
		if ( '' === $plain ) {
			return '';
		}

		$openssl = self::encrypt_openssl( $plain );
		if ( '' !== $openssl ) {
			return $openssl;
		}

		$sodium = self::encrypt_sodium( $plain );
		if ( '' !== $sodium ) {
			return $sodium;
		}

		return '';
	}

	public static function decrypt( string $stored ): string {
		$stored = trim( $stored );
		if ( '' === $stored ) {
			return '';
		}

		if ( str_starts_with( $stored, self::PREFIX_OPENSSL ) ) {
			return self::decrypt_openssl( $stored );
		}

		if ( str_starts_with( $stored, self::PREFIX_SODIUM ) ) {
			return self::decrypt_sodium( $stored );
		}

		// Legacy plain-text keys (migrated on next save).
		return $stored;
	}

	public static function can_encrypt(): bool {
		return function_exists( 'openssl_encrypt' ) || function_exists( 'sodium_crypto_secretbox' );
	}

	private static function derive_key(): string {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}

	private static function encrypt_openssl( string $plain ): string {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return '';
		}

		$key       = self::derive_key();
		$iv        = random_bytes( 16 );
		$encrypted = openssl_encrypt( $plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $encrypted ) {
			return '';
		}

		return self::PREFIX_OPENSSL . base64_encode( $iv . $encrypted );
	}

	private static function decrypt_openssl( string $stored ): string {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$payload = base64_decode( substr( $stored, strlen( self::PREFIX_OPENSSL ) ), true );
		if ( false === $payload || strlen( $payload ) <= 16 ) {
			return '';
		}

		$iv        = substr( $payload, 0, 16 );
		$cipher    = substr( $payload, 16 );
		$key       = self::derive_key();
		$decrypted = openssl_decrypt( $cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		return false === $decrypted ? '' : $decrypted;
	}

	private static function encrypt_sodium( string $plain ): string {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return '';
		}

		$key   = self::derive_key();
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$box   = sodium_crypto_secretbox( $plain, $nonce, $key );

		return self::PREFIX_SODIUM . base64_encode( $nonce . $box );
	}

	private static function decrypt_sodium( string $stored ): string {
		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return '';
		}

		$payload = base64_decode( substr( $stored, strlen( self::PREFIX_SODIUM ) ), true );
		if ( false === $payload || strlen( $payload ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}

		$nonce  = substr( $payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$key    = self::derive_key();
		$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $key );

		return false === $plain ? '' : $plain;
	}
}
