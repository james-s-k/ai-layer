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
use WPAIL\WellKnown\AiLayerController;
use WPAIL\Licensing\Features;
use WPAIL\Admin\SettingsPage;

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
		$pages_data = $settings['pages'];
		$common_labels = [
			'about'   => __( 'About', 'ai-layer' ),
			'contact' => __( 'Contact', 'ai-layer' ),
			'privacy' => __( 'Privacy policy', 'ai-layer' ),
			'terms'   => __( 'Terms', 'ai-layer' ),
			'blog'    => __( 'Blog', 'ai-layer' ),
		];
		$detector   = new ConflictDetector();
		$conflicts  = $detector->get_conflicts();
		$generator  = new Generator();
		$preview    = $generator->generate();

		$is_enabled        = (bool) $settings['enabled'];
		$has_error         = $detector->has_physical_file() || $detector->has_plain_permalinks();
		$llms_url          = home_url( '/llms.txt' );
		$discovery_mode    = SettingsPage::get( SettingsPage::SETTING_AI_DISCOVERY_MODE, SettingsPage::AI_DISCOVERY_WELL_KNOWN );
		$is_well_known_mode = $discovery_mode === SettingsPage::AI_DISCOVERY_WELL_KNOWN;

		if ( $is_enabled && ! $has_error ) {
			$status_class = 'wpail-status--on';
			$status_label = __( 'Active', 'ai-layer' );
		} elseif ( $is_enabled && $has_error ) {
			$status_class = 'wpail-status--warn';
			$status_label = __( 'Conflict', 'ai-layer' );
		} else {
			$status_class = 'wpail-status--off';
			$status_label = __( 'Disabled', 'ai-layer' );
		}
		?>
		<div class="wrap wpail-admin wpail-llmstxt">

			<?php if ( $saved ): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'ai-layer' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="wpail-overview__header">
				<span class="dashicons dashicons-text-page wpail-overview__icon"></span>
				<div>
					<h1>
						<?php esc_html_e( 'llms.txt', 'ai-layer' ); ?>
						<span class="wpail-status <?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $status_label ); ?>
						</span>
					</h1>
					<p class="wpail-overview__tagline">
						<?php esc_html_e( 'Help AI systems and agents discover your structured data by exposing a standardised llms.txt file at your site root.', 'ai-layer' ); ?>
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
							<h2><?php esc_html_e( 'General', 'ai-layer' ); ?></h2>
							<table class="form-table wpail-meta-box__table">
								<tr>
									<th>
										<label for="wpail_llmstxt_enabled">
											<?php esc_html_e( 'Enable llms.txt', 'ai-layer' ); ?>
										</label>
									</th>
									<td>
										<label>
											<input type="checkbox" name="wpail_llmstxt[enabled]" id="wpail_llmstxt_enabled" value="1"
												<?php checked( $settings['enabled'] ); ?>>
											<?php esc_html_e( 'Serve a generated llms.txt at', 'ai-layer' ); ?>
											<a href="<?php echo esc_url( $llms_url ); ?>" target="_blank"><?php echo esc_html( $llms_url ); ?></a>
										</label>
										<p class="description">
											<?php esc_html_e( 'When enabled, your site responds to requests for /llms.txt with a generated file pointing AI systems to your structured data endpoints.', 'ai-layer' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th>
										<label for="wpail_llmstxt_custom_intro">
											<?php esc_html_e( 'Custom introduction', 'ai-layer' ); ?>
										</label>
									</th>
									<td>
										<textarea name="wpail_llmstxt[custom_intro]" id="wpail_llmstxt_custom_intro"
											rows="3" class="large-text"><?php echo esc_textarea( $settings['custom_intro'] ); ?></textarea>
										<p class="description">
											<?php esc_html_e( 'Optional paragraph inserted after the auto-generated header. Plain text or basic markdown. Leave blank to omit.', 'ai-layer' ); ?>
										</p>
									</td>
								</tr>
							</table>
						</div>

						<div class="wpail-field-group">
							<h2><?php esc_html_e( 'Content', 'ai-layer' ); ?></h2>
							<table class="form-table wpail-meta-box__table">
								<tr>
									<th><?php esc_html_e( 'AI Layer endpoints', 'ai-layer' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="wpail_llmstxt[include_endpoints]" value="1"
												<?php checked( $settings['include_endpoints'] ); ?>>
											<?php esc_html_e( 'Include the AI Layer endpoints section', 'ai-layer' ); ?>
										</label>
										<p class="description">
											<?php if ( $is_well_known_mode ) : ?>
												<?php
												printf(
													/* translators: %s: well-known URL */
													esc_html__( 'Inserts a single line pointing to %s (the machine-readable source of truth). Discovery mode is set to /.well-known/ai-layer — change it in Settings to list endpoints directly here instead.', 'ai-layer' ),
													'<code>' . esc_html( home_url( '/.well-known/ai-layer' ) ) . '</code>'
												);
												?>
											<?php else : ?>
												<?php esc_html_e( 'Lists all active AI Layer endpoints directly in llms.txt. Products appear automatically when the Products endpoint is enabled in Settings and WooCommerce is active.', 'ai-layer' ); ?>
											<?php endif; ?>
										</p>
									</td>
								</tr>
								<?php if ( ! $is_well_known_mode && Features::answers_enabled() ): ?>
								<tr>
									<th><?php esc_html_e( '/answers endpoint', 'ai-layer' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="wpail_llmstxt[include_answers]" value="1"
												<?php checked( $settings['include_answers'] ); ?>>
											<?php esc_html_e( 'Include the /answers natural language endpoint', 'ai-layer' ); ?>
										</label>
									</td>
								</tr>
								<?php endif; ?>
								<tr>
									<th><?php esc_html_e( 'Key pages', 'ai-layer' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="wpail_llmstxt[include_pages]" id="wpail_llmstxt_include_pages" value="1"
												<?php checked( $settings['include_pages'] ); ?>>
											<?php esc_html_e( 'Include a Key Pages section', 'ai-layer' ); ?>
										</label>
									</td>
								</tr>
							</table>

							<div id="wpail_llmstxt_pages_section" <?php echo $settings['include_pages'] ? '' : 'style="display:none"'; ?>>

								<table class="form-table wpail-meta-box__table wpail-pages-table">
									<?php foreach ( $common_labels as $key => $label ) :
										$page_id    = (int) ( $pages_data['common'][ $key ] ?? 0 );
										$page_title = $page_id > 0 ? get_the_title( $page_id ) : '';
									?>
									<tr>
										<th><?php echo esc_html( $label ); ?></th>
										<td>
											<div class="wpail-page-picker">
												<div class="wpail-page-picker__search-wrap">
													<input type="text"
														class="wpail-page-picker__search regular-text"
														placeholder="<?php esc_attr_e( 'Search to select a page…', 'ai-layer' ); ?>"
														value="<?php echo esc_attr( $page_title ); ?>"
														autocomplete="off">
													<div class="wpail-page-picker__dropdown"></div>
												</div>
												<input type="hidden"
													name="wpail_llmstxt[pages][common][<?php echo esc_attr( $key ); ?>]"
													class="wpail-page-picker__id"
													value="<?php echo esc_attr( $page_id ); ?>">
												<button type="button" class="wpail-page-picker__clear button-link"
													<?php echo $page_id > 0 ? '' : 'style="display:none"'; ?>>
													<?php esc_html_e( 'Clear', 'ai-layer' ); ?>
												</button>
											</div>
										</td>
									</tr>
									<?php endforeach; ?>
								</table>

								<div class="wpail-pages-custom">
									<div class="wpail-pages-section__header">
										<p class="wpail-pages-section__label">
											<?php esc_html_e( 'Custom pages', 'ai-layer' ); ?>
										</p>
									</div>
									<div class="wpail-page-repeater" id="wpail-page-repeater">
										<?php foreach ( $pages_data['custom'] as $i => $row ) :
											$page_id    = (int) ( $row['id'] ?? 0 );
											$page_title = $page_id > 0 ? get_the_title( $page_id ) : '';
										?>
										<div class="wpail-page-repeater__row">
											<div class="wpail-page-picker">
												<div class="wpail-page-picker__search-wrap">
													<input type="text"
														class="wpail-page-picker__search regular-text"
														placeholder="<?php esc_attr_e( 'Search to select a page…', 'ai-layer' ); ?>"
														value="<?php echo esc_attr( $page_title ); ?>"
														autocomplete="off">
													<div class="wpail-page-picker__dropdown"></div>
												</div>
												<input type="hidden"
													name="wpail_llmstxt[pages][custom][<?php echo (int) $i; ?>][id]"
													class="wpail-page-picker__id"
													value="<?php echo esc_attr( $page_id ); ?>">
												<button type="button" class="wpail-page-picker__clear button-link"
													<?php echo $page_id > 0 ? '' : 'style="display:none"'; ?>>
													<?php esc_html_e( 'Clear', 'ai-layer' ); ?>
												</button>
											</div>
											<button type="button" class="wpail-page-repeater__remove button-link">
												<?php esc_html_e( 'Remove', 'ai-layer' ); ?>
											</button>
										</div>
										<?php endforeach; ?>
									</div>
									<button type="button" id="wpail-add-custom-page" class="button">
										<?php esc_html_e( '+ Add page', 'ai-layer' ); ?>
									</button>
								</div>

								<template id="wpail-custom-page-template">
									<div class="wpail-page-repeater__row">
										<div class="wpail-page-picker">
											<div class="wpail-page-picker__search-wrap">
												<input type="text"
													class="wpail-page-picker__search regular-text"
													placeholder="Search to select a page&hellip;"
													autocomplete="off">
												<div class="wpail-page-picker__dropdown"></div>
											</div>
											<input type="hidden" class="wpail-page-picker__id" name="" value="0">
											<button type="button" class="wpail-page-picker__clear button-link" style="display:none">Clear</button>
										</div>
										<button type="button" class="wpail-page-repeater__remove button-link">Remove</button>
									</div>
								</template>

							</div><!-- /#wpail_llmstxt_pages_section -->

						</div>

						<?php submit_button( __( 'Save Settings', 'ai-layer' ) ); ?>

					</div><!-- /.wpail-llmstxt__settings -->

					<div class="wpail-llmstxt__preview">
						<div class="wpail-field-group">
							<h2><?php esc_html_e( 'Preview', 'ai-layer' ); ?></h2>
							<textarea class="wpail-llmstxt__preview-area" id="wpail-llmstxt-preview" readonly><?php echo esc_textarea( $preview ); ?></textarea>
							<div class="wpail-llmstxt__preview-actions">
								<button type="button" class="button" id="wpail-llmstxt-copy">
									<?php esc_html_e( 'Copy to clipboard', 'ai-layer' ); ?>
								</button>
								<?php if ( $is_enabled && ! $has_error ): ?>
									<a href="<?php echo esc_url( $llms_url ); ?>" target="_blank" class="button">
										<?php esc_html_e( 'View live', 'ai-layer' ); ?>
									</a>
								<?php endif; ?>
							</div>
							<p class="description" style="margin-top: 8px;">
								<?php esc_html_e( 'Preview reflects your current business profile and settings. Save to update after changes.', 'ai-layer' ); ?>
							</p>
						</div>
					</div><!-- /.wpail-llmstxt__preview -->

				</div><!-- /.wpail-llmstxt__layout -->

			</form>

			<script>
			window.wpailLlmsTxt = {
				nonce:   <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>,
				restUrl: <?php echo wp_json_encode( rest_url() ); ?>
			};
			</script>

		</div>
		<?php
	}

	private static function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$raw = isset( $_POST['wpail_llmstxt'] ) && is_array( $_POST['wpail_llmstxt'] )
			? (array) wp_unslash( $_POST['wpail_llmstxt'] )
			: [];

		$common = [];
		foreach ( [ 'about', 'contact', 'privacy', 'terms', 'blog' ] as $key ) {
			$common[ $key ] = absint( $raw['pages']['common'][ $key ] ?? 0 );
		}

		$custom = [];
		if ( isset( $raw['pages']['custom'] ) && is_array( $raw['pages']['custom'] ) ) {
			foreach ( $raw['pages']['custom'] as $row ) {
				$id = absint( $row['id'] ?? 0 );
				if ( $id > 0 ) {
					$custom[] = [ 'id' => $id ];
				}
			}
		}

		$data = [
			'enabled'           => ! empty( $raw['enabled'] ),
			'include_endpoints' => ! empty( $raw['include_endpoints'] ),
			'include_answers'   => ! empty( $raw['include_answers'] ),
			'include_pages'     => ! empty( $raw['include_pages'] ),
			'custom_intro'      => sanitize_textarea_field( $raw['custom_intro'] ?? '' ),
			'pages'             => [
				'common' => $common,
				'custom' => $custom,
			],
		];

		LLMsTxtSettings::save( $data );
		LLMsTxtController::flush_cache();
		AiLayerController::flush_cache();

		// Ensure rewrite rules include the llms.txt route immediately.
		flush_rewrite_rules();
	}
}
