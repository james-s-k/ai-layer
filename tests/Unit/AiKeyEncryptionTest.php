<?php

declare(strict_types=1);

namespace WPAIL\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPAIL\AI\AiKeyEncryption;

final class AiKeyEncryptionTest extends TestCase {

	public function test_round_trip_encryption(): void {
		if ( ! AiKeyEncryption::can_encrypt() ) {
			$this->markTestSkipped( 'No encryption extension available.' );
		}

		$plain  = 'sk-test-key-12345';
		$stored = AiKeyEncryption::encrypt( $plain );

		$this->assertNotSame( '', $stored );
		$this->assertNotSame( $plain, $stored );
		$this->assertSame( $plain, AiKeyEncryption::decrypt( $stored ) );
	}

	public function test_legacy_plain_text_passthrough(): void {
		$plain = 'legacy-plain-key';
		$this->assertSame( $plain, AiKeyEncryption::decrypt( $plain ) );
	}

	public function test_empty_plaintext(): void {
		$this->assertSame( '', AiKeyEncryption::encrypt( '' ) );
		$this->assertSame( '', AiKeyEncryption::decrypt( '' ) );
	}
}
