<?php
/**
 * Redirect to Setup Wizard after first activation.
 *
 * @package WPAIL\Admin
 */

declare(strict_types=1);

namespace WPAIL\Admin;

class ActivationRedirect {

	private const TRANSIENT = 'wpail_activation_redirect';

	public function register(): void {
		add_action( 'admin_init', [ $this, 'maybe_redirect' ] );
	}

	public static function flag(): void {
		set_transient( self::TRANSIENT, '1', MINUTE_IN_SECONDS );
	}

	public function maybe_redirect(): void {
		if ( ! get_transient( self::TRANSIENT ) ) {
			return;
		}

		delete_transient( self::TRANSIENT );

		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['activate-multi'] ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wpail_setup_wizard' ) );
		exit;
	}
}
