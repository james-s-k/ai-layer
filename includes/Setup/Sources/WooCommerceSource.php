<?php
/**
 * Detects WooCommerce availability for the Setup Wizard.
 *
 * @package WPAIL\Setup\Sources
 */

declare(strict_types=1);

namespace WPAIL\Setup\Sources;

class WooCommerceSource {

	public function is_available(): bool {
		return class_exists( 'WooCommerce' );
	}

	public function label(): string {
		return 'WooCommerce';
	}

	public function description(): string {
		return 'Enables the AI Layer /products endpoint.';
	}
}
