<?php
/**
 * Business Profile settings page.
 *
 * Renders and processes the business profile form.
 * Uses FieldDefinitions as the canonical field list so nothing is hardcoded here.
 *
 * @package WPAIL\Admin
 */

declare(strict_types=1);

namespace WPAIL\Admin;

use WPAIL\Repositories\BusinessRepository;
use WPAIL\Support\FieldDefinitions;

class BusinessProfilePage {

	const NONCE_ACTION = 'wpail_save_business_profile';
	const NONCE_NAME   = 'wpail_business_profile_nonce';

	public function register(): void {
		add_action( 'admin_init', [ $this, 'handle_save' ] );
	}

	/**
	 * Handle form submission.
	 */
	public function handle_save(): void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'ai-layer' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'ai-layer' ) );
		}

		$repo = new BusinessRepository();
		$repo->save( wp_unslash( $_POST ) ); // sanitize_fields handles individual sanitation.

		add_action( 'admin_notices', function (): void {
			echo '<div class="notice notice-success is-dismissible"><p>';
			esc_html_e( 'Business profile saved.', 'ai-layer' );
			echo '</p></div>';
		} );
	}

	/**
	 * Render the settings page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$repo   = new BusinessRepository();
		$data   = $repo->get_raw();
		$fields = FieldDefinitions::business();

		// Field grouping for cleaner UX.
		$groups = [
			'Identity'   => [ 'name', 'legal_name', 'business_type', 'subtype', 'short_summary', 'long_summary', 'brand_tone', 'founded_year' ],
			'Contact'    => [ 'phone', 'email', 'website' ],
			'Address'    => [ 'address_line1', 'address_line2', 'city', 'county', 'postcode', 'country' ],
			'Operations' => [ 'opening_hours', 'service_modes', 'trust_summary' ],
			'Social'     => [ 'social_facebook', 'social_twitter', 'social_linkedin', 'social_instagram', 'social_youtube' ],
		];

		?>
		<div class="wrap wpail-admin">
			<h1><?php esc_html_e( 'Business Profile', 'ai-layer' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'This is the canonical business profile. This data is exposed via the /profile endpoint and used across all AI Layer features.', 'ai-layer' ); ?>
			</p>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

				<?php foreach ( $groups as $group_label => $keys ) : ?>
					<div class="wpail-field-group">
						<h2><?php echo esc_html( $group_label ); ?></h2>
						<table class="form-table" role="presentation">
							<tbody>
							<?php foreach ( $keys as $key ) :
								if ( ! isset( $fields[ $key ] ) ) continue;
								$def   = $fields[ $key ];
								$value = $data[ $key ] ?? ( $def['default'] ?? '' );
								$is_private = ( $def['visibility'] ?? '' ) === FieldDefinitions::VISIBILITY_PRIVATE;
							?>
								<tr class="wpail-field <?php echo $is_private ? 'wpail-field--private' : ''; ?>">
									<th scope="row">
										<label for="wpail_<?php echo esc_attr( $key ); ?>">
											<?php echo esc_html( $def['label'] ); ?>
											<?php if ( $is_private ) : ?>
												<span class="wpail-badge wpail-badge--private"><?php esc_html_e( 'Internal', 'ai-layer' ); ?></span>
											<?php endif; ?>
											<?php if ( ! empty( $def['required'] ) ) : ?>
												<span class="required">*</span>
											<?php endif; ?>
										</label>
									</th>
									<td>
										<?php FieldRenderer::render( $key, $def, $value ); ?>
										<?php if ( ! empty( $def['help'] ) ) : ?>
											<p class="description"><?php echo esc_html( $def['help'] ); ?></p>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endforeach; ?>

				<p class="submit">
					<input type="submit" name="submit" class="button button-primary"
					       value="<?php esc_attr_e( 'Save Business Profile', 'ai-layer' ); ?>">
				</p>
			</form>
		</div>
		<?php
	}
}
