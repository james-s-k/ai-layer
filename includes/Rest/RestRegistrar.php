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
	}

	public function register_routes(): void {
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
