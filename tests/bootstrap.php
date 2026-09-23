<?php

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/includes/AI/AiKeyEncryption.php';

if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( string $scheme ): string {
		return 'test-salt-' . $scheme;
	}
}
