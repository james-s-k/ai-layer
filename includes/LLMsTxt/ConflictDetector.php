<?php
/**
 * Detects potential conflicts with the llms.txt feature.
 *
 * @package WPAIL\LLMsTxt
 */

declare(strict_types=1);

namespace WPAIL\LLMsTxt;

class ConflictDetector {

	public function has_physical_file(): bool {
		return file_exists( ABSPATH . 'llms.txt' );
	}

	public function has_plain_permalinks(): bool {
		return '' === get_option( 'permalink_structure' );
	}

	public function is_yoast_active(): bool {
		return defined( 'WPSEO_VERSION' );
	}

	public function is_rank_math_active(): bool {
		return defined( 'RANK_MATH_VERSION' );
	}

	/**
	 * Returns all detected issues.
	 * Each item: [ 'type' => string, 'message' => string, 'severity' => 'error'|'warning' ]
	 *
	 * @return array<array{type: string, message: string, severity: string}>
	 */
	public function get_conflicts(): array {
		$conflicts = [];

		if ( $this->has_physical_file() ) {
			$conflicts[] = [
				'type'     => 'physical_file',
				'message'  => sprintf(
					/* translators: %s: absolute path to the existing llms.txt file */
					__( 'A physical <code>llms.txt</code> file already exists at <code>%s</code>. Your web server will serve that file directly — the AI Layer dynamic route will not be reached. Remove or rename the file to use AI Layer\'s generated version, or copy the preview below into the existing file.', 'ai-layer' ),
					esc_html( ABSPATH . 'llms.txt' )
				),
				'severity' => 'error',
			];
		}

		if ( $this->has_plain_permalinks() ) {
			$conflicts[] = [
				'type'     => 'plain_permalinks',
				'message'  => sprintf(
					/* translators: %s: URL to WordPress permalink settings */
					__( 'Your site is using plain permalinks. The AI Layer <code>llms.txt</code> dynamic route requires pretty permalinks. <a href="%s">Update your permalink structure</a>, then save these settings again to activate serving.', 'ai-layer' ),
					esc_url( admin_url( 'options-permalink.php' ) )
				),
				'severity' => 'error',
			];
		}

		if ( $this->is_yoast_active() ) {
			$conflicts[] = [
				'type'     => 'yoast',
				'message'  => __( 'Yoast SEO is active. Some versions of Yoast SEO also generate an llms.txt file. Verify that only one source is managing your llms.txt output.', 'ai-layer' ),
				'severity' => 'warning',
			];
		}

		if ( $this->is_rank_math_active() ) {
			$conflicts[] = [
				'type'     => 'rank_math',
				'message'  => __( 'Rank Math SEO is active. Verify that only one source is managing your llms.txt output.', 'ai-layer' ),
				'severity' => 'warning',
			];
		}

		return $conflicts;
	}
}
