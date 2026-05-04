<?php
/**
 * Admin page: Answer Engine test console.
 *
 * @package WPAIL\Admin
 */

declare(strict_types=1);

namespace WPAIL\Admin;

use WPAIL\Frontend\AnswerConsole;

class AnswerTestPage {

	public static function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		?>
		<div class="wrap wpail-admin">

			<div class="wpail-admin__header">
				<div>
					<h1><?php esc_html_e( 'Answer Engine Test', 'ai-layer' ); ?></h1>
					<p class="wpail-overview__tagline" style="margin-bottom: 20px;">
						<?php esc_html_e( 'Ask a natural language question and see exactly what the answer engine returns.', 'ai-layer' ); ?>
					</p>
				</div>
			</div>

			<?php AnswerConsole::render_card( true ); ?>

		</div><!-- /.wrap -->
		<?php
	}
}
