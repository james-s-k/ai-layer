<?php
/**
 * Shortcode: [wpail_answer_console]
 *
 * Embeds the answer engine query console on any frontend page or post.
 * The REST endpoint is publicly accessible when Pro is enabled, so no
 * authentication is required — a nonce is only included for logged-in users.
 *
 * @package WPAIL\Shortcodes
 */

declare(strict_types=1);

namespace WPAIL\Shortcodes;

use WPAIL\Frontend\AnswerConsole;

class AnswerConsoleShortcode {

	public function register(): void {
		add_shortcode( 'wpail_answer_console', [ $this, 'render' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_styles' ] );
	}

	/**
	 * Enqueue plugin styles on singular pages that contain the shortcode.
	 */
	public function maybe_enqueue_styles(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( $post && has_shortcode( $post->post_content, 'wpail_answer_console' ) ) {
			wp_enqueue_style(
				'wpail-admin',
				WPAIL_PLUGIN_URL . 'assets/css/admin.css',
				[],
				WPAIL_VERSION
			);
		}
	}

	/**
	 * Render the shortcode output.
	 *
	 * @param array<string,string>|string $atts Shortcode attributes (unused).
	 * @return string
	 */
	public function render( $atts ): string {
		// Enqueue styles as a fallback for block themes / widget areas where
		// has_shortcode() on the post content may not catch the shortcode.
		wp_enqueue_style(
			'wpail-admin',
			WPAIL_PLUGIN_URL . 'assets/css/admin.css',
			[],
			WPAIL_VERSION
		);

		ob_start();
		echo '<div class="wpail-answer-console-wrap">';
		// Pass true for logged-in users so authenticated REST requests include
		// a nonce; false for guests since the endpoint is public.
		AnswerConsole::render_card( is_user_logged_in() );
		echo '</div>';
		return (string) ob_get_clean();
	}
}
