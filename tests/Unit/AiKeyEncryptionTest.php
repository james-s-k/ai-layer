<?php

declare(strict_types=1);

namespace WPAIL\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPAIL\AI\AiKeyEncryption;

final class AiKeyEncryptionTest extends TestCase {

	public function test_round_trip_encryption(): void {
		$plain = 'sk-test-key-12345';
		$stored = AiKeyEncryption::encrypt( $plain );

		$this->assertNotSame( $plain, $stored );
		$this->assertSame( $plain, AiKeyEncryption::decrypt( $stored ) );
	}

	public function test_legacy_plain_text_passthrough(): void {
		$plain = 'legacy-plain-key';
		$this->assertSame( $plain, AiKeyEncryption::decrypt( $plain ) );
	}
}
