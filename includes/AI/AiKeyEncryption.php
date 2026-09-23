<?php
/**
 * Encrypt plugin-local AI provider API keys at rest.
 *
 * @package WPAIL\AI
 */

declare(strict_types=1);

namespace WPAIL\AI;

class AiKeyEncryption {

	private const PREFIX = 'enc:v1:';

	public static function encrypt( string $plain ): string {
		$plain = trim( $plain );
		if ( '' === $plain ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return self::PREFIX . base64_encode( $plain );
		}

		$key       = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv        = random_bytes( 16 );
		$encrypted = openssl_encrypt( $plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $encrypted ) {
			return $plain;
		}

		return self::PREFIX . base64_encode( $iv . $encrypted );
	}

	public static function decrypt( string $stored ): string {
		$stored = trim( $stored );
		if ( '' === $stored ) {
			return '';
		}

		if ( ! str_starts_with( $stored, self::PREFIX ) ) {
			return $stored;
		}

		$payload = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true );
		if ( false === $payload ) {
			return '';
		}

		if ( ! function_exists( 'openssl_decrypt' ) || strlen( $payload ) <= 16 ) {
			return $payload;
		}

		$iv        = substr( $payload, 0, 16 );
		$cipher    = substr( $payload, 16 );
		$key       = hash( 'sha256', wp_salt( 'auth' ), true );
		$decrypted = openssl_decrypt( $cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		return false === $decrypted ? '' : $decrypted;
	}
}
