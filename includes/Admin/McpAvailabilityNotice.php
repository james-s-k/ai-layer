<?php
/**
 * Admin notice when WordPress Abilities API (6.9+) is unavailable.
 *
 * @package WPAIL\Admin
 */

declare(strict_types=1);

namespace WPAIL\Admin;

class McpAvailabilityNotice {

	public function register(): void {
		add_action( 'admin_notices', [ $this, 'render' ] );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! str_contains( $screen->id ?? '', 'wpail' ) ) {
			return;
		}

		echo '<div class="notice notice-info"><p>';
		printf(
			/* translators: 1: WordPress version, 2: MCP Adapter link */
			esc_html__( 'MCP tools require WordPress %1$s or later (Abilities API). Install the %2$s when your site is upgraded to expose AI Layer tools to agents.', 'ai-layer' ),
			'6.9',
			'<a href="https://github.com/wordpress/mcp-adapter" target="_blank" rel="noopener noreferrer">' . esc_html__( 'WordPress MCP Adapter', 'ai-layer' ) . '</a>'
		);
		echo '</p></div>';
	}
}
