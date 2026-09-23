<?php
/**
 * AI Import admin page.
 *
 * Handles provider/model settings, source page selection, step-by-step
 * extraction (via admin-ajax), and results display.
 *
 * @package WPAIL\Admin
 */

declare(strict_types=1);

namespace WPAIL\Admin;

use WPAIL\AI\AiSettings;
use WPAIL\AI\ConnectorBridge;
use WPAIL\AI\ExtractionJob;
use WPAIL\AI\ImportPageSuggestions;
use WPAIL\AI\ProviderFactory;

class AiImportPage {

	public function register(): void {
		( new AiSettings() )->register();
		add_action( 'wp_ajax_wpail_ai_start',    [ $this, 'ajax_start' ] );
		add_action( 'wp_ajax_wpail_ai_run_step', [ $this, 'ajax_run_step' ] );
		add_action( 'wp_ajax_wpail_ai_resync',               [ $this, 'ajax_resync' ] );
		add_action( 'wp_ajax_wpail_ai_find_relationships',  [ $this, 'ajax_find_relationships' ] );
		add_action( 'wp_ajax_wpail_ai_rebuild_relationships', [ $this, 'ajax_rebuild_relationships' ] );
		add_action( 'wp_ajax_wpail_ai_pages_under_parent', [ $this, 'ajax_pages_under_parent' ] );
		add_action( 'wp_ajax_wpail_ai_search_pages', [ $this, 'ajax_search_pages' ] );
	}

	public function ajax_search_pages(): void {
		check_ajax_referer( 'wpail_ai_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		$term = sanitize_text_field( wp_unslash( $_POST['term'] ?? '' ) );
		$pages = ImportPageSuggestions::search_pages( $term );

		wp_send_json_success( [ 'pages' => $pages ] );
	}

	public function ajax_pages_under_parent(): void {
		check_ajax_referer( 'wpail_ai_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		$parent_id = absint( $_POST['parent_id'] ?? 0 );
		if ( $parent_id <= 0 ) {
			wp_send_json_error( [ 'message' => 'Invalid parent page.' ] );
		}

		$pages = ImportPageSuggestions::get_pages_under_parent( $parent_id );
		wp_send_json_success( [ 'pages' => $pages, 'count' => count( $pages ) ] );
	}

	// ------------------------------------------------------------------
	// AJAX handlers.
	// ------------------------------------------------------------------

	public function ajax_start(): void {
		check_ajax_referer( 'wpail_ai_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		$post_ids = array_map( 'absint', (array) ( $_POST['post_ids'] ?? [] ) );
		$post_ids = array_filter( $post_ids );

		if ( empty( $post_ids ) ) {
			wp_send_json_error( [ 'message' => 'Select at least one page or post.' ] );
		}

		$types = array_map( 'sanitize_key', (array) ( $_POST['types'] ?? [] ) );
		$types = array_filter( $types );

		if ( empty( $types ) ) {
			wp_send_json_error( [ 'message' => 'Select at least one entity type to extract.' ] );
		}

		$job = ExtractionJob::create( $post_ids, $types );
		wp_send_json_success( $job ); // Includes job_id + full types list (with automatic 'link' step).
	}

	public function ajax_resync(): void {
		check_ajax_referer( 'wpail_ai_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		$processed = ExtractionJob::resync_consistency();
		wp_send_json_success( [ 'processed' => $processed ] );
	}

	public function ajax_find_relationships(): void {
		check_ajax_referer( 'wpail_ai_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		$provider = ProviderFactory::make();
		if ( is_wp_error( $provider ) ) {
			wp_send_json_error( [ 'message' => $provider->get_error_message() ] );
		}

		$result = ExtractionJob::ai_find_relationships( $provider );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'updated' => $result ] );
	}

	public function ajax_rebuild_relationships(): void {
		check_ajax_referer( 'wpail_ai_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		$provider = ProviderFactory::make();
		if ( is_wp_error( $provider ) ) {
			wp_send_json_error( [ 'message' => $provider->get_error_message() ] );
		}

		$result = ExtractionJob::ai_rebuild_relationships( $provider );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'updated' => $result ] );
	}

	public function ajax_run_step(): void {
		check_ajax_referer( 'wpail_ai_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		$job_id = sanitize_text_field( wp_unslash( $_POST['job_id'] ?? '' ) );
		if ( '' === $job_id ) {
			wp_send_json_error( [ 'message' => 'Missing job ID.' ] );
		}

		$provider = ProviderFactory::make();
		if ( is_wp_error( $provider ) ) {
			wp_send_json_error( [ 'message' => $provider->get_error_message() ] );
		}

		$result = ExtractionJob::run_step( $job_id, $provider );

		if ( isset( $result['error'] ) ) {
			wp_send_json_error( [ 'message' => $result['error'] ] );
		}

		wp_send_json_success( $result );
	}

	// ------------------------------------------------------------------
	// Page render.
	// ------------------------------------------------------------------

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$model           = AiSettings::get_selected_model();
		$model_info      = AiSettings::get_model_info( $model );
		$provider        = $model_info['provider'] ?? 'openai';
		$has_key         = AiSettings::is_selected_provider_configured();
		$from_wizard     = isset( $_GET['from'] ) && 'wizard' === sanitize_key( wp_unslash( $_GET['from'] ) );
		$use_connectors  = ConnectorBridge::is_available();
		$connectors_url  = ConnectorBridge::get_admin_url();
		$suggestions     = ImportPageSuggestions::get_suggested_pages();
		$section_parents = ImportPageSuggestions::get_section_parents();
		$presets         = [
			ImportPageSuggestions::PRESET_ESSENTIAL => ImportPageSuggestions::get_preset_pages( ImportPageSuggestions::PRESET_ESSENTIAL ),
			ImportPageSuggestions::PRESET_TOP_LEVEL => ImportPageSuggestions::get_preset_pages( ImportPageSuggestions::PRESET_TOP_LEVEL ),
		];
		?>
		<div class="wrap wpail-admin">

			<?php if ( $from_wizard ) : ?>
				<div class="notice notice-info" style="margin-top:16px;">
					<p>
						<?php esc_html_e( 'Welcome from the Setup Wizard. Select your pages, choose what to extract, and click Start AI Extraction. All items are saved as drafts for your review.', 'ai-layer' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<div class="wpail-admin__header">
				<div>
					<h1><?php esc_html_e( 'AI Import', 'ai-layer' ); ?></h1>
					<p class="wpail-overview__tagline" style="margin-bottom:0;">
						<?php esc_html_e( 'Use AI to extract services, FAQs, locations, proof, and actions from your existing pages.', 'ai-layer' ); ?>
					</p>
				</div>
			</div>

			<div class="notice notice-warning" style="margin:20px 0 0;">
				<p>
					<strong><?php esc_html_e( 'Back up your database before importing.', 'ai-layer' ); ?></strong>
					<?php esc_html_e( 'AI Import creates new posts and modifies relationship data. Use a backup plugin or your host\'s backup tool to take a full database backup before running an import or any relationship operation.', 'ai-layer' ); ?>
				</p>
			</div>

			<?php // ── Provider & Model ──────────────────────────────────── ?>
			<div class="wpail-card" style="margin-top:24px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Provider & Model', 'ai-layer' ); ?></h2>

				<?php if ( $use_connectors && '' !== $connectors_url ) : ?>
					<p class="description" style="max-width:680px;margin-bottom:16px;">
						<?php
						printf(
							/* translators: %s: Settings → Connectors link */
							esc_html__( 'API keys are managed on %s when available. Choose your model below; update provider keys on the Connectors screen.', 'ai-layer' ),
							'<a href="' . esc_url( $connectors_url ) . '"><strong>' . esc_html__( 'Settings → Connectors', 'ai-layer' ) . '</strong></a>'
						);
						?>
					</p>
				<?php endif; ?>

				<form method="post" action="">
					<?php wp_nonce_field( AiSettings::NONCE_ACTION, AiSettings::NONCE_NAME ); ?>

					<table class="form-table" style="max-width:700px;">
						<tr>
							<th scope="row"><label for="wpail_ai_model"><?php esc_html_e( 'Model', 'ai-layer' ); ?></label></th>
							<td>
								<select name="wpail_ai_model" id="wpail_ai_model" style="min-width:280px;">
									<?php AiSettings::render_model_options( $model ); ?>
								</select>
								<p class="description"><?php esc_html_e( 'GPT-4o Mini is the recommended default — fast, cheap, and accurate for most sites. For the relationship step, a stronger model such as GPT-4.1 or Claude Sonnet 4.6 gives more accurate results.', 'ai-layer' ); ?></p>
							</td>
						</tr>

						<?php if ( ! $use_connectors ) : ?>
							<?php foreach ( AiSettings::PROVIDER_LABELS as $prov => $label ) : ?>
								<tr>
									<th scope="row">
										<label for="wpail_ai_key_<?php echo esc_attr( $prov ); ?>">
											<?php
											/* translators: %s: provider name (e.g. OpenAI) */
											printf( esc_html__( '%s API Key', 'ai-layer' ), esc_html( $label ) );
											?>
										</label>
									</th>
									<td>
										<?php $stored_key = AiSettings::get_local_api_key( $prov ); ?>
										<input type="password"
										       id="wpail_ai_key_<?php echo esc_attr( $prov ); ?>"
										       name="wpail_ai_key_<?php echo esc_attr( $prov ); ?>"
										       value=""
										       placeholder="<?php echo $stored_key ? esc_attr__( '(saved — leave blank to keep)', 'ai-layer' ) : esc_attr__( 'Paste your API key', 'ai-layer' ); ?>"
										       style="width:340px;font-family:monospace;" />
										<?php if ( AiSettings::is_provider_configured( $prov ) ) : ?>
											<span style="color:#00a32a;margin-left:8px;">&#10003; <?php esc_html_e( 'Key saved', 'ai-layer' ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</table>

					<p style="margin-top:16px;"><button type="submit" class="button button-secondary"><?php esc_html_e( 'Save Settings', 'ai-layer' ); ?></button></p>
				</form>
			</div>

			<?php // ── Import from Pages ─────────────────────────────────── ?>
			<div class="wpail-card" style="margin-top:24px;" id="wpail-ai-import-card">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Import from Pages', 'ai-layer' ); ?></h2>
				<p class="description" style="margin:0 0 20px;max-width:680px;">
					<?php esc_html_e( 'Select the pages that describe your business (e.g. Services, About, Areas). The AI will read them and create draft items for each entity type. You can review and publish the drafts afterwards.', 'ai-layer' ); ?>
				</p>

				<?php if ( ! $has_key ) : ?>
					<div class="notice notice-warning inline" style="margin:0 0 20px;">
						<p>
							<?php
							if ( $use_connectors && '' !== $connectors_url ) {
								printf(
									/* translators: 1: provider name, 2: Settings → Connectors link */
									esc_html__( 'Add an API key for %1$s on %2$s before running an import.', 'ai-layer' ),
									esc_html( AiSettings::PROVIDER_LABELS[ $provider ] ?? $provider ),
									'<a href="' . esc_url( $connectors_url ) . '">' . esc_html__( 'Settings → Connectors', 'ai-layer' ) . '</a>'
								);
							} else {
								printf(
									/* translators: %s: provider name */
									esc_html__( 'Add an API key for %s above and save before running an import.', 'ai-layer' ),
									esc_html( AiSettings::PROVIDER_LABELS[ $provider ] ?? $provider )
								);
							}
							?>
						</p>
					</div>
				<?php endif; ?>

				<?php // ── Quick page selection ────────────────────────────── ?>
				<div class="wpail-import-pages" id="wpail-import-pages">

					<h3 class="wpail-import-pages__heading"><?php esc_html_e( 'Quick start', 'ai-layer' ); ?></h3>
					<p class="description wpail-import-pages__intro">
						<?php esc_html_e( 'Use a preset or tick suggested pages below. You can fine-tune the selection before extracting.', 'ai-layer' ); ?>
					</p>

					<div class="wpail-import-pages__presets">
						<button type="button"
						        class="button button-secondary wpail-import-preset-btn"
						        data-preset="<?php echo esc_attr( ImportPageSuggestions::PRESET_ESSENTIAL ); ?>">
							<?php esc_html_e( 'Essential business pages', 'ai-layer' ); ?>
						</button>
						<button type="button"
						        class="button button-secondary wpail-import-preset-btn"
						        data-preset="<?php echo esc_attr( ImportPageSuggestions::PRESET_TOP_LEVEL ); ?>">
							<?php esc_html_e( 'All top-level pages', 'ai-layer' ); ?>
						</button>
					</div>

					<?php if ( ! empty( $suggestions ) ) : ?>
						<div class="wpail-import-pages__suggestions">
							<div class="wpail-import-pages__suggestions-head">
								<strong><?php esc_html_e( 'Suggested pages', 'ai-layer' ); ?></strong>
								<button type="button" class="button-link" id="wpail-suggest-select-all">
									<?php esc_html_e( 'Select all', 'ai-layer' ); ?>
								</button>
								<button type="button" class="button-link" id="wpail-suggest-select-none">
									<?php esc_html_e( 'Select none', 'ai-layer' ); ?>
								</button>
							</div>
							<ul class="wpail-import-pages__suggest-list">
								<?php foreach ( $suggestions as $item ) : ?>
									<li>
										<label class="wpail-import-pages__suggest-item">
											<input type="checkbox"
											       class="wpail-suggest-cb"
											       value="<?php echo esc_attr( (string) $item['id'] ); ?>"
											       data-title="<?php echo esc_attr( $item['title'] ); ?>"
											       <?php checked( $item['default_checked'] ); ?> />
											<span class="wpail-import-pages__suggest-title"><?php echo esc_html( $item['title'] ); ?></span>
											<span class="wpail-import-pages__suggest-reason"><?php echo esc_html( $item['reason'] ); ?></span>
										</label>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No published pages were found. Create pages in WordPress first, or use the advanced search below.', 'ai-layer' ); ?></p>
					<?php endif; ?>

					<div class="wpail-import-pages__selected-wrap">
						<div class="wpail-import-pages__selected-head">
							<strong>
								<?php esc_html_e( 'Selected for import', 'ai-layer' ); ?>
								<span id="wpail-selected-count" class="wpail-import-pages__count">(0)</span>
							</strong>
							<a href="#" id="wpail-clear-pages" style="display:none;">
								<?php esc_html_e( 'Clear all', 'ai-layer' ); ?>
							</a>
						</div>
						<div id="wpail-selected-pages" class="wpail-import-pages__chips"></div>
						<p id="wpail-selected-empty" class="description wpail-import-pages__empty">
							<?php esc_html_e( 'No pages selected yet. Use a preset or tick suggestions above.', 'ai-layer' ); ?>
						</p>
						<p id="wpail-selected-warn" class="wpail-import-pages__warn" style="display:none;"></p>
					</div>

					<details class="wpail-import-pages__advanced">
						<summary><?php esc_html_e( 'Add more pages…', 'ai-layer' ); ?></summary>
						<div class="wpail-import-pages__advanced-body">

							<label class="wpail-import-pages__advanced-label" for="wpail-import-search">
								<?php esc_html_e( 'Search by title', 'ai-layer' ); ?>
							</label>
							<div class="wpail-page-picker" id="wpail-import-picker">
								<div class="wpail-page-picker__search-wrap">
									<input type="text"
									       id="wpail-import-search"
									       class="wpail-page-picker__search regular-text"
									       placeholder="<?php esc_attr_e( 'Search for a page…', 'ai-layer' ); ?>"
									       autocomplete="off" />
									<div class="wpail-page-picker__dropdown"></div>
								</div>
							</div>
							<p class="description"><?php esc_html_e( 'Type at least 2 characters. Click a result to add it.', 'ai-layer' ); ?></p>

							<?php if ( ! empty( $section_parents ) ) : ?>
								<div class="wpail-import-pages__section">
									<label class="wpail-import-pages__advanced-label" for="wpail-section-parent">
										<?php esc_html_e( 'Add all pages under a section', 'ai-layer' ); ?>
									</label>
									<div class="wpail-import-pages__section-row">
										<select id="wpail-section-parent" class="regular-text">
											<option value=""><?php esc_html_e( '— Choose a top-level page —', 'ai-layer' ); ?></option>
											<?php foreach ( $section_parents as $parent ) : ?>
												<option value="<?php echo esc_attr( (string) $parent['id'] ); ?>"
												        data-child-count="<?php echo esc_attr( (string) $parent['child_count'] ); ?>">
													<?php
													echo esc_html( $parent['title'] );
													if ( $parent['child_count'] > 0 ) {
														printf(
															/* translators: %d: number of child pages */
															esc_html__( ' (%d sub-pages)', 'ai-layer' ),
															(int) $parent['child_count']
														);
													}
													?>
												</option>
											<?php endforeach; ?>
										</select>
										<button type="button" class="button button-secondary" id="wpail-section-add-btn" disabled>
											<?php esc_html_e( 'Add section', 'ai-layer' ); ?>
										</button>
									</div>
									<p id="wpail-section-hint" class="description"></p>
								</div>
							<?php endif; ?>

						</div>
					</details>
				</div>

				<?php // ── Entity type selector ──────────────────────────── ?>
				<div style="margin-bottom:20px;">
					<strong style="display:block;font-size:13px;margin-bottom:10px;"><?php esc_html_e( 'What to extract', 'ai-layer' ); ?></strong>
					<div style="display:flex;flex-wrap:wrap;gap:10px 24px;">
						<?php
						$entity_labels = [
							'services'  => __( 'Services', 'ai-layer' ),
							'faqs'      => __( 'FAQs', 'ai-layer' ),
							'locations' => __( 'Locations', 'ai-layer' ),
							'proof'     => __( 'Proof & Trust', 'ai-layer' ),
							'actions'   => __( 'Actions', 'ai-layer' ),
						];
						foreach ( $entity_labels as $type => $label ) :
						?>
							<label style="font-size:13px;">
								<input type="checkbox"
								       class="wpail-ai-type-cb"
								       value="<?php echo esc_attr( $type ); ?>"
								       checked />
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<p style="margin:0 0 4px;">
					<button id="wpail-ai-start-btn"
					        class="button button-primary"
					        <?php disabled( ! $has_key ); ?>>
						<?php esc_html_e( 'Start AI Extraction', 'ai-layer' ); ?>
					</button>
				</p>
				<p class="description" style="margin:8px 0 0;">
					<?php esc_html_e( 'All extracted items are saved as drafts. AI can make mistakes — review titles, content, and relationships before publishing.', 'ai-layer' ); ?>
				</p>

				<?php // ── Progress ──────────────────────────────────────── ?>
				<div id="wpail-ai-progress" style="display:none;margin-top:24px;padding-top:20px;border-top:1px solid #dcdcde;">
					<p style="margin:0 0 8px;font-weight:600;"><?php esc_html_e( 'Extracting…', 'ai-layer' ); ?></p>
					<div style="background:#dcdcde;border-radius:4px;height:10px;overflow:hidden;max-width:500px;">
						<div id="wpail-ai-bar" style="background:#2271b1;height:10px;width:0;transition:width .4s;"></div>
					</div>
					<p id="wpail-ai-status-text" style="margin:8px 0 0;color:#646970;font-size:13px;"></p>
				</div>

				<?php // ── Results ───────────────────────────────────────── ?>
				<div id="wpail-ai-results" style="display:none;margin-top:24px;padding-top:20px;border-top:1px solid #dcdcde;">
					<p style="margin:0 0 12px;font-weight:600;color:#00a32a;">&#10003; <?php esc_html_e( 'Extraction complete', 'ai-layer' ); ?></p>
					<table class="widefat striped" style="max-width:460px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Entity', 'ai-layer' ); ?></th>
								<th><?php esc_html_e( 'Drafts created', 'ai-layer' ); ?></th>
								<th><?php esc_html_e( 'Review', 'ai-layer' ); ?></th>
							</tr>
						</thead>
						<tbody id="wpail-ai-results-body"></tbody>
					</table>
					<p class="description" style="margin-top:12px;">
						<?php esc_html_e( 'All items were saved as drafts. Review and publish the ones you want to keep.', 'ai-layer' ); ?>
					</p>
				</div>

				<?php // ── Error ─────────────────────────────────────────── ?>
				<div id="wpail-ai-error" style="display:none;margin-top:20px;" class="notice notice-error inline">
					<p id="wpail-ai-error-text"></p>
				</div>
			</div>

			<?php // ── Relationships ─────────────────────────────────────── ?>
			<div class="wpail-card" style="margin-top:24px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Relationships', 'ai-layer' ); ?></h2>
				<p class="description" style="margin:0 0 20px;max-width:680px;">
					<?php esc_html_e( 'Manage how entities are linked to one another. All relationship operations affect published and draft posts.', 'ai-layer' ); ?>
				</p>

				<?php // ── Reference table ───────────────────────────────── ?>
				<table style="border-collapse:collapse;max-width:680px;width:100%;margin-bottom:28px;font-size:13px;">
					<thead>
						<tr style="border-bottom:2px solid #dcdcde;">
							<th style="text-align:left;padding:0 16px 8px 0;color:#3c434a;font-weight:600;width:160px;"><?php esc_html_e( 'Relationship', 'ai-layer' ); ?></th>
							<th style="text-align:left;padding:0 0 8px;color:#3c434a;font-weight:600;"><?php esc_html_e( 'Set when…', 'ai-layer' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$rel_rows = [
							[ __( 'FAQ → Service', 'ai-layer' ),      __( 'The FAQ is clearly about that specific service.', 'ai-layer' ) ],
							[ __( 'Proof → Service', 'ai-layer' ),    __( 'The testimonial, stat, or award is about a specific service — not general company-wide proof.', 'ai-layer' ) ],
							[ __( 'Proof → Location', 'ai-layer' ),   __( 'A specific city or area is explicitly named in the proof text. Never inferred — left empty if no place name appears.', 'ai-layer' ) ],
							[ __( 'Action → Service', 'ai-layer' ),   __( 'The action is the primary way to enquire about or book that service.', 'ai-layer' ) ],
							[ __( 'Location → Service', 'ai-layer' ), __( 'The service is offered at or from that location.', 'ai-layer' ) ],
						];
						foreach ( $rel_rows as $row ) :
						?>
							<tr style="border-bottom:1px solid #f0f0f1;">
								<td style="padding:10px 16px 10px 0;font-weight:600;color:#1d2327;white-space:nowrap;"><?php echo esc_html( $row[0] ); ?></td>
								<td style="padding:10px 0;color:#646970;"><?php echo esc_html( $row[1] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php // ── Three action cards ────────────────────────────── ?>
				<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">

					<?php // Card 1 — Resync (safe / green) ?>
					<div style="border:1px solid #dcdcde;border-top:3px solid #00a32a;border-radius:4px;padding:20px;display:flex;flex-direction:column;">
						<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
							<strong style="font-size:13px;"><?php esc_html_e( 'Resync All Relationships', 'ai-layer' ); ?></strong>
							<span style="font-size:11px;font-weight:600;background:#e7f6ea;color:#00a32a;padding:2px 7px;border-radius:3px;white-space:nowrap;"><?php esc_html_e( 'Safe', 'ai-layer' ); ?></span>
						</div>
						<p class="description" style="margin:0 0 12px;flex:1;font-size:13px;">
							<?php esc_html_e( 'Repairs missing inverse links using relationship data already saved on your entities. Additive only — never removes existing links.', 'ai-layer' ); ?>
						</p>
						<p style="margin:0 0 14px;font-size:12px;color:#646970;"><?php esc_html_e( 'No API key required.', 'ai-layer' ); ?></p>
						<button id="wpail-resync-btn" class="button button-secondary" style="width:100%;">
							<?php esc_html_e( 'Resync All Relationships', 'ai-layer' ); ?>
						</button>
						<p id="wpail-resync-status" style="margin:8px 0 0;font-size:13px;color:#646970;min-height:20px;"></p>
					</div>

					<?php // Card 2 — Find New (AI / amber) ?>
					<div style="border:1px solid #dcdcde;border-top:3px solid #dba617;border-radius:4px;padding:20px;display:flex;flex-direction:column;">
						<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
							<strong style="font-size:13px;"><?php esc_html_e( 'Find New Relationships', 'ai-layer' ); ?></strong>
							<span style="font-size:11px;font-weight:600;background:#fef8ee;color:#996800;padding:2px 7px;border-radius:3px;white-space:nowrap;"><?php esc_html_e( 'AI', 'ai-layer' ); ?></span>
						</div>
						<p class="description" style="margin:0 0 12px;flex:1;font-size:13px;">
							<?php esc_html_e( 'Uses AI to discover relationships not yet set and adds them. Does not remove existing links.', 'ai-layer' ); ?>
						</p>
						<p style="margin:0 0 14px;font-size:12px;color:#646970;"><?php esc_html_e( 'Requires an API key. Review results manually — AI can make mistakes.', 'ai-layer' ); ?></p>
						<button id="wpail-find-rel-btn" class="button button-secondary" style="width:100%;">
							<?php esc_html_e( 'Find New Relationships', 'ai-layer' ); ?>
						</button>
						<p id="wpail-find-rel-status" style="margin:8px 0 0;font-size:13px;color:#646970;min-height:20px;"></p>
					</div>

					<?php // Card 3 — Rebuild (destructive / red) ?>
					<div style="border:1px solid #dcdcde;border-top:3px solid #d63638;border-radius:4px;padding:20px;display:flex;flex-direction:column;">
						<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
							<strong style="font-size:13px;"><?php esc_html_e( 'Rebuild All Relationships', 'ai-layer' ); ?></strong>
							<span style="font-size:11px;font-weight:600;background:#fef0f0;color:#d63638;padding:2px 7px;border-radius:3px;white-space:nowrap;"><?php esc_html_e( 'Destructive', 'ai-layer' ); ?></span>
						</div>
						<p class="description" style="margin:0 0 12px;flex:1;font-size:13px;">
							<?php esc_html_e( 'AI sets the complete, authoritative set of relationships for every entity. Existing relationship data is replaced — links not confirmed by the AI are removed.', 'ai-layer' ); ?>
						</p>
						<p style="margin:0 0 14px;font-size:12px;color:#d63638;"><?php esc_html_e( 'Requires an API key. Overwrites all existing relationship data. Review manually afterwards.', 'ai-layer' ); ?></p>
						<button id="wpail-rebuild-rel-btn" class="button" style="background:#d63638;border-color:#b32d2e;color:#fff;width:100%;">
							<?php esc_html_e( 'Rebuild All Relationships', 'ai-layer' ); ?>
						</button>
						<p id="wpail-rebuild-rel-status" style="margin:8px 0 0;font-size:13px;color:#646970;min-height:20px;"></p>
					</div>

				</div>
			</div>

		</div>

		<script>
		(function () {
			const ajaxUrl  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			const nonce    = <?php echo wp_json_encode( wp_create_nonce( 'wpail_ai_import' ) ); ?>;
			const labels  = {
				services:  <?php echo wp_json_encode( __( 'Services', 'ai-layer' ) ); ?>,
				faqs:      <?php echo wp_json_encode( __( 'FAQs', 'ai-layer' ) ); ?>,
				locations: <?php echo wp_json_encode( __( 'Locations', 'ai-layer' ) ); ?>,
				proof:     <?php echo wp_json_encode( __( 'Proof & Trust', 'ai-layer' ) ); ?>,
				actions:   <?php echo wp_json_encode( __( 'Actions', 'ai-layer' ) ); ?>,
				link:      <?php echo wp_json_encode( __( 'Relationships', 'ai-layer' ) ); ?>,
			};
			const reviewUrls = {
				services:  <?php echo wp_json_encode( admin_url( 'edit.php?post_type=wpail_service&post_status=draft' ) ); ?>,
				faqs:      <?php echo wp_json_encode( admin_url( 'edit.php?post_type=wpail_faq&post_status=draft' ) ); ?>,
				locations: <?php echo wp_json_encode( admin_url( 'edit.php?post_type=wpail_location&post_status=draft' ) ); ?>,
				proof:     <?php echo wp_json_encode( admin_url( 'edit.php?post_type=wpail_proof&post_status=draft' ) ); ?>,
				actions:   <?php echo wp_json_encode( admin_url( 'edit.php?post_type=wpail_action&post_status=draft' ) ); ?>,
			};
			const importPresets = <?php echo wp_json_encode( $presets ); ?>;
			const recommendedMax = <?php echo (int) ImportPageSuggestions::RECOMMENDED_MAX; ?>;

			// ── Page selection (presets, suggestions, search, sections) ───
			let selectedPages = [];
			const picker           = document.getElementById('wpail-import-picker');
			const searchInput      = picker?.querySelector('.wpail-page-picker__search');
			const dropdown         = picker?.querySelector('.wpail-page-picker__dropdown');
			const selectedWrap     = document.getElementById('wpail-selected-pages');
			const clearAllLink     = document.getElementById('wpail-clear-pages');
			const selectedCountEl  = document.getElementById('wpail-selected-count');
			const selectedEmptyEl  = document.getElementById('wpail-selected-empty');
			const selectedWarnEl   = document.getElementById('wpail-selected-warn');
			const suggestCheckboxes = document.querySelectorAll('.wpail-suggest-cb');
			const sectionSelect    = document.getElementById('wpail-section-parent');
			const sectionAddBtn    = document.getElementById('wpail-section-add-btn');
			const sectionHint      = document.getElementById('wpail-section-hint');
			let searchCache = {};
			let debounce;

			function isSelected(id) {
				return selectedPages.some(p => p.id === id);
			}

			function addPages(pages) {
				pages.forEach(page => {
					const id = parseInt(page.id, 10);
					if (!id || isSelected(id)) return;
					selectedPages.push({ id, title: page.title || '' });
				});
				syncUi();
			}

			function removePage(id) {
				selectedPages = selectedPages.filter(p => p.id !== id);
				syncUi();
			}

			function syncSuggestionCheckboxes() {
				suggestCheckboxes.forEach(cb => {
					const id = parseInt(cb.value, 10);
					cb.checked = isSelected(id);
				});
			}

			function updateCountWarning() {
				const count = selectedPages.length;
				if (selectedCountEl) {
					selectedCountEl.textContent = '(' + count + ')';
				}
				if (selectedEmptyEl) {
					selectedEmptyEl.style.display = count > 0 ? 'none' : '';
				}
				if (clearAllLink) {
					clearAllLink.style.display = count > 0 ? '' : 'none';
				}
				if (!selectedWarnEl) return;
				if (count > recommendedMax) {
					selectedWarnEl.style.display = '';
					selectedWarnEl.textContent = <?php echo wp_json_encode(
						sprintf(
							/* translators: %d: recommended maximum pages per import */
							__( 'You have selected more than %d pages. Large imports take longer and cost more. Consider splitting into smaller runs.', 'ai-layer' ),
							ImportPageSuggestions::RECOMMENDED_MAX
						)
					); ?>;
				} else {
					selectedWarnEl.style.display = 'none';
					selectedWarnEl.textContent = '';
				}
			}

			function renderChips() {
				if (!selectedWrap) return;
				selectedWrap.innerHTML = '';
				selectedPages.forEach(page => {
					const chip = document.createElement('span');
					chip.className = 'wpail-import-pages__chip';
					chip.textContent = page.title;
					const rm = document.createElement('button');
					rm.type = 'button';
					rm.className = 'wpail-import-pages__chip-remove';
					rm.textContent = '×';
					rm.title = <?php echo wp_json_encode( __( 'Remove', 'ai-layer' ) ); ?>;
					rm.addEventListener('click', () => removePage(page.id));
					chip.appendChild(rm);
					selectedWrap.appendChild(chip);
				});
				updateCountWarning();
			}

			function syncUi() {
				syncSuggestionCheckboxes();
				renderChips();
			}

			function collectCheckedSuggestions() {
				const pages = [];
				suggestCheckboxes.forEach(cb => {
					if (!cb.checked) return;
					pages.push({
						id: parseInt(cb.value, 10),
						title: cb.dataset.title || '',
					});
				});
				return pages;
			}

			function applySuggestionSelection() {
				const checkedIds = new Set(
					collectCheckedSuggestions().map(p => p.id)
				);
				selectedPages = selectedPages.filter(p => {
					const cb = document.querySelector('.wpail-suggest-cb[value="' + p.id + '"]');
					return !cb || checkedIds.has(p.id);
				});
				collectCheckedSuggestions().forEach(page => {
					if (!isSelected(page.id)) {
						selectedPages.push(page);
					}
				});
				syncUi();
			}

			suggestCheckboxes.forEach(cb => {
				cb.addEventListener('change', applySuggestionSelection);
			});

			document.getElementById('wpail-suggest-select-all')?.addEventListener('click', function (e) {
				e.preventDefault();
				suggestCheckboxes.forEach(cb => { cb.checked = true; });
				applySuggestionSelection();
			});

			document.getElementById('wpail-suggest-select-none')?.addEventListener('click', function (e) {
				e.preventDefault();
				suggestCheckboxes.forEach(cb => { cb.checked = false; });
				applySuggestionSelection();
			});

			document.querySelectorAll('.wpail-import-preset-btn').forEach(btn => {
				btn.addEventListener('click', function () {
					const preset = btn.dataset.preset;
					const pages = importPresets[preset] || [];
					if (!pages.length) {
						alert(<?php echo wp_json_encode( __( 'No matching pages were found for this preset.', 'ai-layer' ) ); ?>);
						return;
					}
					addPages(pages);
					suggestCheckboxes.forEach(cb => {
						const id = parseInt(cb.value, 10);
						cb.checked = isSelected(id);
					});
				});
			});

			clearAllLink?.addEventListener('click', function (e) {
				e.preventDefault();
				selectedPages = [];
				suggestCheckboxes.forEach(cb => { cb.checked = false; });
				syncUi();
			});

			sectionSelect?.addEventListener('change', function () {
				const opt = sectionSelect.options[sectionSelect.selectedIndex];
				const childCount = parseInt(opt?.dataset.childCount || '0', 10);
				if (sectionAddBtn) {
					sectionAddBtn.disabled = !sectionSelect.value;
				}
				if (!sectionHint) return;
				if (!sectionSelect.value) {
					sectionHint.textContent = '';
					return;
				}
				const total = childCount + 1;
				sectionHint.textContent = total === 1
					? <?php echo wp_json_encode( __( 'Adds 1 page (this page has no sub-pages).', 'ai-layer' ) ); ?>
					: <?php echo wp_json_encode( __( 'Adds this page plus all published sub-pages underneath it.', 'ai-layer' ) ); ?>;
			});

			sectionAddBtn?.addEventListener('click', async function () {
				const parentId = parseInt(sectionSelect.value, 10);
				if (!parentId) return;
				sectionAddBtn.disabled = true;
				const body = new FormData();
				body.append('action', 'wpail_ai_pages_under_parent');
				body.append('nonce', nonce);
				body.append('parent_id', String(parentId));
				try {
					const res = await fetch(ajaxUrl, { method: 'POST', body });
					const json = await res.json();
					if (!json.success) {
						alert(json.data?.message || <?php echo wp_json_encode( __( 'Could not load pages for that section.', 'ai-layer' ) ); ?>);
						return;
					}
					addPages(json.data.pages || []);
				} catch (err) {
					alert(<?php echo wp_json_encode( __( 'Could not load pages for that section.', 'ai-layer' ) ); ?>);
				} finally {
					sectionAddBtn.disabled = !sectionSelect.value;
				}
			});

			function searchPages(term, callback) {
				if (searchCache[term]) { callback(searchCache[term]); return; }
				const body = new FormData();
				body.append('action', 'wpail_ai_search_pages');
				body.append('nonce', nonce);
				body.append('term', term);
				fetch(ajaxUrl, { method: 'POST', body })
					.then(r => r.json())
					.then(data => {
						const results = data.success && Array.isArray(data.data?.pages)
							? data.data.pages.map(item => ({
								id: parseInt(item.id, 10),
								title: item.title || '',
							}))
							: [];
						searchCache[term] = results;
						callback(results);
					})
					.catch(() => callback([]));
			}

			function renderDropdown(results) {
				if (!dropdown) return;
				dropdown.innerHTML = '';
				if (!results.length) {
					const empty = document.createElement('div');
					empty.className = 'wpail-page-picker__option wpail-page-picker__option--empty';
					empty.textContent = <?php echo wp_json_encode( __( 'No pages found.', 'ai-layer' ) ); ?>;
					dropdown.appendChild(empty);
					dropdown.style.display = 'block';
					return;
				}
				results.forEach(page => {
					const opt = document.createElement('div');
					opt.className = 'wpail-page-picker__option';
					opt.textContent = page.title;
					if (isSelected(page.id)) {
						opt.style.opacity = '0.45';
						opt.style.cursor = 'default';
					}
					opt.addEventListener('mousedown', function (e) {
						e.preventDefault();
						if (isSelected(page.id)) return;
						addPages([page]);
						if (searchInput) {
							searchInput.value = '';
						}
						dropdown.style.display = 'none';
					});
					dropdown.appendChild(opt);
				});
				dropdown.style.display = 'block';
			}

			if (searchInput && dropdown && picker) {
				searchInput.addEventListener('input', function () {
					clearTimeout(debounce);
					const term = searchInput.value.trim();
					if (term.length < 2) {
						dropdown.innerHTML = '';
						dropdown.style.display = 'none';
						return;
					}
					debounce = setTimeout(() => searchPages(term, renderDropdown), 300);
				});

				searchInput.addEventListener('focus', function () {
					const term = searchInput.value.trim();
					if (term.length >= 2) searchPages(term, renderDropdown);
				});

				document.addEventListener('click', function (e) {
					if (!picker.contains(e.target)) dropdown.style.display = 'none';
				});
			}

			// Initialise from pre-checked suggestions (wizard / default).
			applySuggestionSelection();

			// ── Extraction ────────────────────────────────────────────────
			const btn         = document.getElementById('wpail-ai-start-btn');
			const progress    = document.getElementById('wpail-ai-progress');
			const bar         = document.getElementById('wpail-ai-bar');
			const statusTxt   = document.getElementById('wpail-ai-status-text');
			const results     = document.getElementById('wpail-ai-results');
			const resultsBody = document.getElementById('wpail-ai-results-body');
			const errorBox    = document.getElementById('wpail-ai-error');
			const errorTxt    = document.getElementById('wpail-ai-error-text');

			btn?.addEventListener('click', async function () {
				const checkedTypes = [...document.querySelectorAll('.wpail-ai-type-cb:checked')].map(cb => cb.value);

				if (!selectedPages.length) {
					alert(<?php echo wp_json_encode( __( 'Select at least one page using the suggestions, a preset, or advanced search.', 'ai-layer' ) ); ?>);
					return;
				}
				if (selectedPages.length > recommendedMax) {
					const ok = confirm(<?php echo wp_json_encode( __( 'You selected many pages. Imports this large may be slow and costly. Continue anyway?', 'ai-layer' ) ); ?>);
					if (!ok) return;
				}
				if (!checkedTypes.length) {
					alert(<?php echo wp_json_encode( __( 'Select at least one entity type to extract.', 'ai-layer' ) ); ?>);
					return;
				}

				btn.disabled = true;
				errorBox.style.display = 'none';
				results.style.display  = 'none';
				resultsBody.innerHTML  = '';
				progress.style.display = 'block';
				bar.style.width        = '0';
				statusTxt.textContent  = <?php echo wp_json_encode( __( 'Preparing…', 'ai-layer' ) ); ?>;

				const startData = new FormData();
				startData.append('action', 'wpail_ai_start');
				startData.append('nonce', nonce);
				selectedPages.forEach(p => startData.append('post_ids[]', p.id));
				checkedTypes.forEach(t => startData.append('types[]', t));

				let jobId, activeTypes;
				try {
					const startRes  = await fetch(ajaxUrl, { method: 'POST', body: startData });
					const startJson = await startRes.json();
					if (!startJson.success) throw new Error(startJson.data?.message || 'Failed to start job.');
					jobId       = startJson.data.job_id;
					activeTypes = startJson.data.types;
				} catch (err) {
					showError(err.message);
					return;
				}

				for (let i = 0; i < activeTypes.length; i++) {
					bar.style.width       = Math.round((i / activeTypes.length) * 100) + '%';
					statusTxt.textContent = activeTypes[i] === 'link'
						? <?php echo wp_json_encode( __( 'Linking relationships…', 'ai-layer' ) ); ?>
						: <?php echo wp_json_encode( __( 'Extracting', 'ai-layer' ) ); ?> + ' ' + (labels[activeTypes[i]] || activeTypes[i]) + '…';

					const stepData = new FormData();
					stepData.append('action', 'wpail_ai_run_step');
					stepData.append('nonce', nonce);
					stepData.append('job_id', jobId);

					try {
						const stepRes  = await fetch(ajaxUrl, { method: 'POST', body: stepData });
						const stepJson = await stepRes.json();
						if (!stepJson.success) throw new Error(stepJson.data?.message || 'Step failed.');

						const d = stepJson.data;
						if (d.step_name) {
							const tr = document.createElement('tr');
							if (d.step_name === 'link') {
								tr.innerHTML = `<td>${labels.link}</td><td><strong>${d.created}</strong></td><td>—</td>`;
							} else {
								tr.innerHTML = `<td>${labels[d.step_name] || d.step_name}</td>` +
									`<td><strong>${d.created}</strong></td>` +
									`<td>${d.created > 0 ? '<a href="' + reviewUrls[d.step_name] + '"><?php echo esc_js( __( 'Review drafts', 'ai-layer' ) ); ?></a>' : '—'}</td>`;
							}
							resultsBody.appendChild(tr);
						}
					} catch (err) {
						showError(err.message);
						return;
					}
				}

				bar.style.width = '100%';
				progress.style.display = 'none';
				results.style.display  = 'block';
				btn.disabled = false;
			});

			function showError(msg) {
				progress.style.display = 'none';
				errorTxt.textContent   = msg;
				errorBox.style.display = 'block';
				btn.disabled           = false;
			}
		})();

		(function () {
			const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			const nonce   = <?php echo wp_json_encode( wp_create_nonce( 'wpail_ai_import' ) ); ?>;

			async function runAction( action, btn, statusEl, pendingMsg, successFn ) {
				btn.disabled         = true;
				statusEl.style.color = '#646970';
				statusEl.textContent = pendingMsg;
				const data = new FormData();
				data.append('action', action);
				data.append('nonce', nonce);
				try {
					const res  = await fetch(ajaxUrl, { method: 'POST', body: data });
					const json = await res.json();
					if (json.success) {
						statusEl.style.color = '#00a32a';
						statusEl.textContent = successFn(json.data);
					} else {
						statusEl.style.color = '#d63638';
						statusEl.textContent = json.data?.message || <?php echo wp_json_encode( __( 'Something went wrong.', 'ai-layer' ) ); ?>;
					}
				} catch (err) {
					statusEl.style.color = '#d63638';
					statusEl.textContent = err.message;
				} finally {
					btn.disabled = false;
				}
			}

			document.getElementById('wpail-resync-btn')?.addEventListener('click', function () {
				if ( ! confirm( <?php echo wp_json_encode( __( 'This will repair any missing inverse links using existing relationship data. Continue?', 'ai-layer' ) ); ?> ) ) {
					return;
				}
				runAction(
					'wpail_ai_resync',
					this,
					document.getElementById('wpail-resync-status'),
					<?php echo wp_json_encode( __( 'Resyncing…', 'ai-layer' ) ); ?>,
					data => <?php echo wp_json_encode( __( 'Done —', 'ai-layer' ) ); ?> + ' ' + data.processed + ' ' + <?php echo wp_json_encode( __( 'posts processed.', 'ai-layer' ) ); ?>
				);
			});

			document.getElementById('wpail-find-rel-btn')?.addEventListener('click', function () {
				if ( ! confirm( <?php echo wp_json_encode( __( 'This will use AI to discover and add new relationships. Existing links will not be removed. Continue?', 'ai-layer' ) ); ?> ) ) {
					return;
				}
				runAction(
					'wpail_ai_find_relationships',
					this,
					document.getElementById('wpail-find-rel-status'),
					<?php echo wp_json_encode( __( 'Asking AI to find relationships…', 'ai-layer' ) ); ?>,
					data => <?php echo wp_json_encode( __( 'Done —', 'ai-layer' ) ); ?> + ' ' + data.updated + ' ' + <?php echo wp_json_encode( __( 'entities updated.', 'ai-layer' ) ); ?>
				);
			});

			document.getElementById('wpail-rebuild-rel-btn')?.addEventListener('click', function () {
				if ( ! confirm( <?php echo wp_json_encode( __( 'This will replace all existing relationship data using AI. Any links not confirmed by the AI will be removed. Continue?', 'ai-layer' ) ); ?> ) ) {
					return;
				}
				runAction(
					'wpail_ai_rebuild_relationships',
					this,
					document.getElementById('wpail-rebuild-rel-status'),
					<?php echo wp_json_encode( __( 'Rebuilding relationships via AI — this may take a moment…', 'ai-layer' ) ); ?>,
					data => <?php echo wp_json_encode( __( 'Done —', 'ai-layer' ) ); ?> + ' ' + data.updated + ' ' + <?php echo wp_json_encode( __( 'entities updated.', 'ai-layer' ) ); ?>
				);
			});
		})();
		</script>
		<?php
	}
}
