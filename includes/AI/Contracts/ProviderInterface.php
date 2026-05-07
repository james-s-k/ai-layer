<?php
/**
 * Contract for AI completion providers.
 *
 * @package WPAIL\AI\Contracts
 */

declare(strict_types=1);

namespace WPAIL\AI\Contracts;

interface ProviderInterface {

	/**
	 * Send a chat completion request and return the text response.
	 *
	 * @param string $system System / instruction prompt.
	 * @param string $user   User prompt containing the content to process.
	 * @return string|\WP_Error Completion text, or WP_Error on failure.
	 */
	public function complete( string $system, string $user ): string|\WP_Error;
}
