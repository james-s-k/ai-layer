<?php
/**
 * Admin page: ai.txt settings and live preview.
 *
 * @package WPAIL\Admin
 */

declare(strict_types=1);

namespace WPAIL\Admin;

use WPAIL\AiTxt\AiTxtSettings;
use WPAIL\AiTxt\AiTxtController;
use WPAIL\AiTxt\AiTxtGenerator;

class AiTxtPage {

	public function register(): void {
		// Rendering is called directly from AdminMenu — no hooks needed here.
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$saved = false;
		if (
			isset( $_POST['_wpnonce'] ) &&
			wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'wpail_aitxt_save' )
		) {
			self::handle_save();
			$saved = true;
		}

		$settings        = AiTxtSettings::get_all();
		$generator       = new AiTxtGenerator();
		$preview         = $generator->generate();
		$is_enabled      = (bool) $settings['enabled'];
		$has_plain_perms = get_option( 'permalink_structure', '' ) === '';
		$has_phys_file   = AiTxtController::has_physical_file();
		$aitxt_url       = home_url( '/ai.txt' );

		if ( $is_enabled && ! $has_phys_file && ! $has_plain_perms ) {
			$status_class = 'wpail-status--on';
			$status_label = __( 'Active', 'ai-ready-layer' );
		} elseif ( $is_enabled && ( $has_phys_file || $has_plain_perms ) ) {
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

			<?php if ( $has_phys_file ): ?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e( 'Physical file conflict.', 'ai-ready-layer' ); ?></strong>
						<?php esc_html_e( 'A physical ai.txt file exists at your site root. Your web server will serve that file instead of this generated version. Remove or rename the file to use AI Layer\'s dynamic ai.txt.', 'ai-ready-layer' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( $has_plain_perms ): ?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e( 'Plain permalink structure detected.', 'ai-ready-layer' ); ?></strong>
						<?php
						printf(
							/* translators: %s: permalink settings URL */
							esc_html__( 'WordPress rewrites cannot intercept /ai.txt with plain permalinks. Switch to a named permalink structure in %s.', 'ai-ready-layer' ),
							'<a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">' . esc_html__( 'Settings → Permalinks', 'ai-ready-layer' ) . '</a>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<div class="wpail-overview__header">
				<span class="dashicons dashicons-shield wpail-overview__icon"></span>
				<div>
					<h1>
						<?php esc_html_e( 'AI.txt', 'ai-ready-layer' ); ?>
						<span class="wpail-status <?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $status_label ); ?>
						</span>
						<span class="wpail-badge wpail-badge--beta">
							<?php esc_html_e( 'Beta', 'ai-ready-layer' ); ?>
						</span>
					</h1>
					<p class="wpail-overview__tagline">
						<?php esc_html_e( 'Control how AI systems are permitted to crawl, train on, and attribute content from your site.', 'ai-ready-layer' ); ?>
					</p>
				</div>
			</div>

			<div class="notice notice-warning wpail-aitxt__beta-notice">
				<p>
					<strong><?php esc_html_e( '⚠ This feature is experimental.', 'ai-ready-layer' ); ?></strong>
					<?php esc_html_e( 'The AI.txt standard is still evolving and has not been formally adopted by major AI providers. Settings here may have no effect on some systems. Use with caution and review as the standard matures.', 'ai-ready-layer' ); ?>
				</p>
			</div>

			<form method="post" action="" id="wpail-aitxt-form">
				<?php wp_nonce_field( 'wpail_aitxt_save' ); ?>

				<div class="wpail-llmstxt__layout">

					<div class="wpail-llmstxt__settings">

						<div class="wpail-field-group">
							<h2><?php esc_html_e( 'General', 'ai-ready-layer' ); ?></h2>
							<table class="form-table wpail-meta-box__table">
								<tr>
									<th>
										<label for="wpail_aitxt_enabled">
											<?php esc_html_e( 'Enable AI.txt', 'ai-ready-layer' ); ?>
										</label>
									</th>
									<td>
										<label>
											<input type="checkbox" name="wpail_aitxt[enabled]" id="wpail_aitxt_enabled" value="1"
												<?php checked( $settings['enabled'] ); ?>>
											<?php esc_html_e( 'Serve a generated ai.txt at', 'ai-ready-layer' ); ?>
											<a href="<?php echo esc_url( $aitxt_url ); ?>" target="_blank"><?php echo esc_html( $aitxt_url ); ?></a>
										</label>
										<p class="description">
											<?php esc_html_e( 'When disabled, /ai.txt returns 404.', 'ai-ready-layer' ); ?>
										</p>
									</td>
								</tr>
							</table>
						</div>

						<div class="wpail-field-group">
							<h2><?php esc_html_e( 'Global Rules', 'ai-ready-layer' ); ?></h2>
							<table class="form-table wpail-meta-box__table">
								<tr>
									<th>
										<label for="wpail_aitxt_allow_crawling">
											<?php esc_html_e( 'Allow AI crawling', 'ai-ready-layer' ); ?>
										</label>
									</th>
									<td>
										<label>
											<input type="checkbox" name="wpail_aitxt[allow_crawling]" id="wpail_aitxt_allow_crawling" value="1"
												<?php checked( $settings['allow_crawling'] ); ?>>
											<?php esc_html_e( 'Permit AI crawlers to access this site', 'ai-ready-layer' ); ?>
										</label>
										<p class="description">
											<?php esc_html_e( 'On: outputs Allow: / — Off: outputs Disallow: /. Defaults to on.', 'ai-ready-layer' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th>
										<label for="wpail_aitxt_allow_training">
											<?php esc_html_e( 'Allow AI training', 'ai-ready-layer' ); ?>
										</label>
									</th>
									<td>
										<label>
											<input type="checkbox" name="wpail_aitxt[allow_training]" id="wpail_aitxt_allow_training" value="1"
												<?php checked( $settings['allow_training'] ); ?>>
											<?php esc_html_e( 'Permit AI systems to use this content for model training', 'ai-ready-layer' ); ?>
										</label>
										<p class="description">
											<?php esc_html_e( 'Outputs Training: allow or Training: disallow. Defaults to disallow.', 'ai-ready-layer' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th>
										<label for="wpail_aitxt_require_attribution">
											<?php esc_html_e( 'Require attribution', 'ai-ready-layer' ); ?>
										</label>
									</th>
									<td>
										<label>
											<input type="checkbox" name="wpail_aitxt[require_attribution]" id="wpail_aitxt_require_attribution" value="1"
												<?php checked( $settings['require_attribution'] ); ?>>
											<?php esc_html_e( 'Request that AI systems attribute content to this site', 'ai-ready-layer' ); ?>
										</label>
										<p class="description">
											<?php esc_html_e( 'Outputs Attribution: required when enabled. Defaults to off.', 'ai-ready-layer' ); ?>
										</p>
									</td>
								</tr>
							</table>
						</div>

						<div class="wpail-field-group">
							<h2><?php esc_html_e( 'Agent-Specific Rules', 'ai-ready-layer' ); ?></h2>
							<p class="description" style="padding: 0 0 12px;">
								<?php esc_html_e( 'Optionally define rules for individual AI agents. These override the global rules for the specified agent.', 'ai-ready-layer' ); ?>
							</p>

							<div id="wpail-aitxt-agents" class="wpail-aitxt__agents">
								<?php foreach ( $settings['agents'] as $i => $agent ) :
									$idx         = esc_attr( (string) $i );
									$agent_name  = esc_attr( sanitize_text_field( $agent['name'] ?? '' ) );
									$agent_allow = (bool) ( $agent['allow'] ?? true );
									$agent_train = (bool) ( $agent['allow_training'] ?? false );
									$agent_attr  = (bool) ( $agent['require_attribution'] ?? false );
								?>
								<div class="wpail-aitxt__agent-row">
									<div class="wpail-aitxt__agent-header">
										<input type="text"
										       name="wpail_aitxt[agents][<?php echo $idx; ?>][name]"
										       class="wpail-aitxt__agent-name"
										       value="<?php echo $agent_name; ?>"
										       placeholder="<?php esc_attr_e( 'e.g. GPTBot', 'ai-ready-layer' ); ?>">
										<button type="button" class="wpail-aitxt__agent-remove button-link">
											<?php esc_html_e( '✕ Remove', 'ai-ready-layer' ); ?>
										</button>
									</div>
									<div class="wpail-aitxt__agent-controls">
										<label>
											<input type="checkbox"
											       name="wpail_aitxt[agents][<?php echo $idx; ?>][allow]"
											       class="wpail-aitxt__agent-allow"
											       value="1" <?php checked( $agent_allow ); ?>>
											<?php esc_html_e( 'Allow crawling', 'ai-ready-layer' ); ?>
										</label>
										<label>
											<input type="checkbox"
											       name="wpail_aitxt[agents][<?php echo $idx; ?>][allow_training]"
											       class="wpail-aitxt__agent-training"
											       value="1" <?php checked( $agent_train ); ?>>
											<?php esc_html_e( 'Allow training', 'ai-ready-layer' ); ?>
										</label>
										<label>
											<input type="checkbox"
											       name="wpail_aitxt[agents][<?php echo $idx; ?>][require_attribution]"
											       class="wpail-aitxt__agent-attribution"
											       value="1" <?php checked( $agent_attr ); ?>>
											<?php esc_html_e( 'Require attribution', 'ai-ready-layer' ); ?>
										</label>
									</div>
								</div>
								<?php endforeach; ?>
							</div>

							<button type="button" id="wpail-aitxt-add-agent" class="button" style="margin-top: 10px;">
								<?php esc_html_e( '+ Add Agent', 'ai-ready-layer' ); ?>
							</button>

							<p class="description" style="margin-top: 8px;">
								<?php esc_html_e( 'Known agent names: GPTBot (OpenAI), ClaudeBot (Anthropic), Google-Extended (Google), CCBot (Common Crawl), FacebookBot (Meta).', 'ai-ready-layer' ); ?>
							</p>
						</div>

						<?php submit_button( __( 'Save Settings', 'ai-ready-layer' ) ); ?>

					</div><!-- /.wpail-llmstxt__settings -->

					<div class="wpail-llmstxt__preview">
						<div class="wpail-field-group">
							<h2><?php esc_html_e( 'Preview', 'ai-ready-layer' ); ?></h2>
							<textarea class="wpail-llmstxt__preview-area" id="wpail-aitxt-preview" readonly><?php echo esc_textarea( $preview ); ?></textarea>
							<div class="wpail-llmstxt__preview-actions">
								<button type="button" class="button" id="wpail-aitxt-copy">
									<?php esc_html_e( 'Copy to clipboard', 'ai-ready-layer' ); ?>
								</button>
								<?php if ( $is_enabled && ! $has_phys_file && ! $has_plain_perms ) : ?>
									<a href="<?php echo esc_url( $aitxt_url ); ?>" target="_blank" class="button">
										<?php esc_html_e( 'View live', 'ai-ready-layer' ); ?>
									</a>
								<?php endif; ?>
							</div>
							<p class="description" style="margin-top: 8px;">
								<?php esc_html_e( 'Updates live as you change settings. Save to persist.', 'ai-ready-layer' ); ?>
							</p>
						</div>
					</div><!-- /.wpail-llmstxt__preview -->

				</div><!-- /.wpail-llmstxt__layout -->

				<?php // Row template — cloned by JS, never submitted directly. ?>
				<template id="wpail-aitxt-agent-template">
					<div class="wpail-aitxt__agent-row">
						<div class="wpail-aitxt__agent-header">
							<input type="text"
							       class="wpail-aitxt__agent-name"
							       placeholder="<?php esc_attr_e( 'e.g. GPTBot', 'ai-ready-layer' ); ?>">
							<button type="button" class="wpail-aitxt__agent-remove button-link">
								<?php esc_html_e( '✕ Remove', 'ai-ready-layer' ); ?>
							</button>
						</div>
						<div class="wpail-aitxt__agent-controls">
							<label>
								<input type="checkbox" class="wpail-aitxt__agent-allow" value="1" checked>
								<?php esc_html_e( 'Allow crawling', 'ai-ready-layer' ); ?>
							</label>
							<label>
								<input type="checkbox" class="wpail-aitxt__agent-training" value="1">
								<?php esc_html_e( 'Allow training', 'ai-ready-layer' ); ?>
							</label>
							<label>
								<input type="checkbox" class="wpail-aitxt__agent-attribution" value="1">
								<?php esc_html_e( 'Require attribution', 'ai-ready-layer' ); ?>
							</label>
						</div>
					</div>
				</template>

			</form>

		</div>
		<?php
		// Pass existing row count to JS so the counter starts in the right place.
		$agent_count = count( $settings['agents'] );
		?>
		<script>
		window.wpailAiTxtAgentCount = <?php echo (int) $agent_count; ?>;
		</script>
		<?php
	}

	private static function handle_save(): void {
		$raw = isset( $_POST['wpail_aitxt'] ) && is_array( $_POST['wpail_aitxt'] )
			? $_POST['wpail_aitxt']
			: [];

		// Sanitize agent rows.
		$agents = [];
		if ( isset( $raw['agents'] ) && is_array( $raw['agents'] ) ) {
			foreach ( $raw['agents'] as $agent ) {
				$name = sanitize_text_field( wp_unslash( $agent['name'] ?? '' ) );
				if ( $name === '' ) {
					continue;
				}
				$agents[] = [
					'name'                => $name,
					'allow'               => ! empty( $agent['allow'] ),
					'allow_training'      => ! empty( $agent['allow_training'] ),
					'require_attribution' => ! empty( $agent['require_attribution'] ),
				];
			}
		}

		$data = [
			'enabled'             => ! empty( $raw['enabled'] ),
			'allow_crawling'      => ! empty( $raw['allow_crawling'] ),
			'allow_training'      => ! empty( $raw['allow_training'] ),
			'require_attribution' => ! empty( $raw['require_attribution'] ),
			'agents'              => $agents,
		];

		AiTxtSettings::save( $data );
		AiTxtController::flush_cache();
		flush_rewrite_rules();
	}
}
