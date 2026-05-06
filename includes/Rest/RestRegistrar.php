<?php
/**
 * Registers all REST API routes.
 *
 * @package WPAIL\Rest
 */

declare(strict_types=1);

namespace WPAIL\Rest;

use WPAIL\Admin\SettingsPage;

class RestRegistrar {

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );

		// WordPress 6.9+ requires is_ssl() || 'local' === wp_get_environment_type() for
		// Application Passwords to be available. On non-SSL localhost without WP_ENVIRONMENT_TYPE='local'
		// they are disabled. Both filters below restore auth for /wp-json/ requests.
		add_filter( 'wp_is_application_passwords_available', [ $this, 'is_rest_api_request' ] );
		add_filter( 'application_password_is_api_request', [ $this, 'is_rest_api_request' ] );
	}

	public function is_rest_api_request( bool $is_api_request ): bool {
		if ( $is_api_request ) {
			return true;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		return '' !== $uri && false !== strpos( $uri, '/wp-json/' );
	}

	public function register_routes(): void {
		( new ManifestController() )->register_routes();
		( new OpenApiController() )->register_routes();
		( new ProfileController() )->register_routes();
		( new ServicesController() )->register_routes();
		( new LocationsController() )->register_routes();
		( new FaqsController() )->register_routes();
		( new ProofController() )->register_routes();
		( new ActionsController() )->register_routes();
		( new AnswersController() )->register_routes();

		if ( SettingsPage::get( SettingsPage::SETTING_PRODUCTS_ENABLED ) && class_exists( 'WooCommerce' ) ) {
			( new ProductsController() )->register_routes();
		}
	}
}
