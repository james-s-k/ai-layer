<?php
/**
 * Admin page: llms.txt settings, conflict detection, and preview.
 *
 * @package WPAIL\Admin
 */

declare(strict_types=1);

namespace WPAIL\Admin;

use WPAIL\LLMsTxt\ConflictDetector;
use WPAIL\LLMsTxt\Generator;
use WPAIL\LLMsTxt\LLMsTxtSettings;
use WPAIL\LLMsTxt\LLMsTxtController;
use WPAIL\Licensing\Features;

class LLMsTxtPage {

	public function register(): void {
		// Rendering called directly from AdminMenu — no hooks needed here.
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle POST save. wp_safe_redirect() cannot be used here — headers are
		// already sent by the time an admin menu page callback fires. Process
		// inline and show the notice directly.
		$saved = false;
		if (
			isset( $_POST['_wpnonce'] ) &&
			wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'wpail_llmstxt_save' )
		) {
			self::handle_save();
			$saved = true;
		}

		$settings   = LLMsTxtSettings::get_all();
		$detector   = new ConflictDetector();
		$conflicts  = $detector->get_conflicts();
		$generator  = new Generator();
		$preview    = $generator->generate();

		$is_enabled   = (bool) $settings['enabled'];
		$has_error    = $detector->has_physical_file() || $detector->has_plain_permalinks();
		$llms_url     = home_url( '/llms.txt' );

		if ( $is_enabled && ! $has_error ) {
			$status_class = 'wpail-status--on';
			$status_label = __( 'Active', 'ai-ready-layer' );
		} elseif ( $is_enabled && $has_error ) {
			$status_class = 'wpail-status--warn';
			$status_label = __( 'Conflict', 'ai-ready-layer' );
		} else {
			$status_class = 'wpail-status--off';
			$status_label = __( 'Disabled', 'ai-ready-layer' );
		}
		?>
		<div class="wrap wpail-admin wpail-llmstxt">

			<?php if ( $saved ): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'ai-ready-layer' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="wpail-overview__header">
				<span class="dashicons dashicons-text-page wpail-overview__icon"></span>
				<div>
					<h1>
						<?php esc_html_e( 'llms.txt', 'ai-ready-layer' ); ?>
						<span class="wpail-status <?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $status_label ); ?>
						</span>
					</h1>
					<p class="wpail-overview__tagline">
						<?php esc_html_e( 'Help AI systems and agents discover your structured data by exposing a standardised llms.txt file at your site root.', 'ai-ready-layer' ); ?>
					</p>
				</div>
			</div>

			<?php foreach ( $conflicts as $conflict ): ?>
				<div class="notice notice-<?php echo esc_attr( $conflict['severity'] === 'error' ? 'error' : 'warning' ); ?>">
					<p><?php echo wp_kses_post( $conflict['message'] ); ?></p>
				</div>
			<?php endforeach; ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'wpail_llmstxt_save' ); ?>

				<div class="wpail-llmstxt__layout">

					<div class="wpail-llmstxt__settings">

						<div class="wpail-field-group">
							<h2><?php esc_html_e( 'General', 'ai-ready-layer' ); ?></h2>
							<table class="form-table wpail-meta-box__table">
								<tr>
									<th>
										<label for="wpail_llmstxt_enabled">
											<?php esc_html_e( 'Enable llms.txt', 'ai-ready-layer' ); ?>
										</label>
									</th>
									<td>
										<label>
											<input type="checkbox" name="wpail_llmstxt[enabled]" id="wpail_llmstxt_enabled" value="1"
												<?php checked( $settings['enabled'] ); ?>>
											<?php esc_html_e( 'Serve a generated llms.txt at', 'ai-ready-layer' ); ?>
											<a href="<?php echo esc_url( $llms_url ); ?>" target="_blank"><?php echo esc_html( $llms_url ); ?></a>
										</label>
										<p class="description">
											<?php esc_html_e( 'When enabled, your site responds to requests for /llms.txt with a generated file pointing AI systems to your structured data endpoints.', 'ai-ready-layer' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th>
										<label for="wpail_llmstxt_custom_intro">
											<?php esc_html_e( 'Custom introduction', 'ai-ready-layer' ); ?>
										</label>
									</th>
									<td>
										<textarea name="wpail_llmstxt[custom_intro]" id="wpail_llmstxt_custom_intro"
											rows="3" class="large-text"><?php echo esc_textarea( $settings['custom_intro'] ); ?></textarea>
										<p class="description">
											<?php esc_html_e( 'Optional paragraph inserted after the auto-generated header. Plain text or basic markdown. Leave blank to omit.', 'ai-ready-layer' ); ?>
										</p>
									</td>
								</tr>
							</table>
						</div>

						<div class="wpail-field-group">
							<h2><?php esc_html_e( 'Content', 'ai-ready-layer' ); ?></h2>
							<table class="form-table wpail-meta-box__table">
								<tr>
									<th><?php esc_html_e( 'AI Layer endpoints', 'ai-ready-layer' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="wpail_llmstxt[include_endpoints]" value="1"
												<?php checked( $settings['include_endpoints'] ); ?>>
											<?php esc_html_e( 'Include the AI Layer structured data endpoints section', 'ai-ready-layer' ); ?>
										</label>
									</td>
								</tr>
								<?php if ( Features::answers_enabled() ): ?>
								<tr>
									<th><?php esc_html_e( '/answers endpoint', 'ai-ready-layer' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="wpail_llmstxt[include_answers]" value="1"
												<?php checked( $settings['include_answers'] ); ?>>
											<?php esc_html_e( 'Include the /answers natural language endpoint', 'ai-ready-layer' ); ?>
										</label>
									</td>
								</tr>
								<?php endif; ?>
								<tr>
									<th><?php esc_html_e( 'Key pages', 'ai-ready-layer' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="wpail_llmstxt[include_pages]" id="wpail_llmstxt_include_pages" value="1"
												<?php checked( $settings['include_pages'] ); ?>>
											<?php esc_html_e( 'Include a Key Pages section', 'ai-ready-layer' ); ?>
										</label>
									</td>
								</tr>
								<tr id="wpail_llmstxt_pages_row" <?php echo $settings['include_pages'] ? '' : 'style="display:none"'; ?>>
									<th>
										<label for="wpail_llmstxt_custom_pages">
											<?php esc_html_e( 'Page URLs', 'ai-ready-layer' ); ?>
										</label>
									</th>
									<td>
										<textarea name="wpail_llmstxt[custom_pages]" id="wpail_llmstxt_custom_pages"
											rows="5" class="large-text"><?php echo esc_textarea( $settings['custom_pages'] ); ?></textarea>
										<p class="description">
											<?php esc_html_e( 'One entry per line. Use markdown link format: [Page Title](https://example.com/page)', 'ai-ready-layer' ); ?>
										</p>
									</td>
								</tr>
							</table>
						</div>

						<?php submit_button( __( 'Save Settings', 'ai-ready-layer' ) ); ?>

					</div><!-- /.wpail-llmstxt__settings -->

					<div class="wpail-llmstxt__preview">
						<div class="wpail-field-group">
							<h2><?php esc_html_e( 'Preview', 'ai-ready-layer' ); ?></h2>
							<textarea class="wpail-llmstxt__preview-area" id="wpail-llmstxt-preview" readonly><?php echo esc_textarea( $preview ); ?></textarea>
							<div class="wpail-llmstxt__preview-actions">
								<button type="button" class="button" id="wpail-llmstxt-copy">
									<?php esc_html_e( 'Copy to clipboard', 'ai-ready-layer' ); ?>
								</button>
								<?php if ( $is_enabled && ! $has_error ): ?>
									<a href="<?php echo esc_url( $llms_url ); ?>" target="_blank" class="button">
										<?php esc_html_e( 'View live', 'ai-ready-layer' ); ?>
									</a>
								<?php endif; ?>
							</div>
							<p class="description" style="margin-top: 8px;">
								<?php esc_html_e( 'Preview reflects your current business profile and settings. Save to update after changes.', 'ai-ready-layer' ); ?>
							</p>
						</div>
					</div><!-- /.wpail-llmstxt__preview -->

				</div><!-- /.wpail-llmstxt__layout -->

			</form>

		</div>
		<?php
	}

	private static function handle_save(): void {
		$raw = isset( $_POST['wpail_llmstxt'] ) && is_array( $_POST['wpail_llmstxt'] )
			? $_POST['wpail_llmstxt']
			: [];

		$data = [
			'enabled'           => ! empty( $raw['enabled'] ),
			'include_endpoints' => ! empty( $raw['include_endpoints'] ),
			'include_answers'   => ! empty( $raw['include_answers'] ),
			'include_pages'     => ! empty( $raw['include_pages'] ),
			'custom_intro'      => sanitize_textarea_field( $raw['custom_intro'] ?? '' ),
			'custom_pages'      => sanitize_textarea_field( $raw['custom_pages'] ?? '' ),
		];

		LLMsTxtSettings::save( $data );
		LLMsTxtController::flush_cache();

		// Ensure rewrite rules include the llms.txt route immediately.
		flush_rewrite_rules();
	}
}
