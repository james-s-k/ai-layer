<?php
/**
 * Registers all AI Layer WordPress Abilities for MCP exposure.
 *
 * Abilities are only registered when the WordPress Abilities API is available
 * (WordPress 6.9+ core). The WordPress MCP Adapter plugin then picks them up
 * and exposes them as MCP tools automatically.
 *
 * @package WPAIL\Abilities
 */

declare(strict_types=1);

namespace WPAIL\Abilities;

class AbilitiesRegistrar {

	public function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	public function register_abilities(): void {
		( new ProfileAbilities() )->register();
		( new ServicesAbilities() )->register();
		( new LocationsAbilities() )->register();
		( new FaqsAbilities() )->register();
		( new ProofAbilities() )->register();
		( new ActionsAbilities() )->register();
		( new AnswersAbilities() )->register();
	}
}
