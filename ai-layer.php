<?php
/**
 * Plugin Name:       AI Layer
 * Plugin URI:        https://strivewp.com/ai-layer
 * Description:       Structured business knowledge layer for WordPress. Exposes canonical business data via versioned REST endpoints for AI systems, agents, and search tools.
 * Version:           1.5.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            AI Layer
 * Author URI:        https://strivewp.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-layer
 * Domain Path:       /languages
 *
 * @package WPAIL
 */

declare(strict_types=1);

namespace WPAIL;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'WPAIL_VERSION',       '1.5.0' );
define( 'WPAIL_PLUGIN_FILE',   __FILE__ );
define( 'WPAIL_PLUGIN_DIR',    plugin_dir_path( __FILE__ ) );
define( 'WPAIL_PLUGIN_URL',    plugin_dir_url( __FILE__ ) );
define( 'WPAIL_PLUGIN_BASE',   plugin_basename( __FILE__ ) );
define( 'WPAIL_REST_NS',       'ai-layer/v1' );
define( 'WPAIL_META_KEY',      '_wpail_data' );
define( 'WPAIL_OPT_BUSINESS',  'wpail_business_profile' );
define( 'WPAIL_OPT_SETTINGS',  'wpail_settings' );

// Autoloader: WPAIL\Foo\Bar  ->  includes/Foo/Bar.php
spl_autoload_register( function ( string $class ): void {
	$prefix = 'WPAIL\\';
	$base   = WPAIL_PLUGIN_DIR . 'includes/';

	if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );
	$file     = $base . str_replace( '\\', '/', $relative ) . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

// Freemius bootstrap — must run before plugins_loaded so the SDK hooks fire
// at the right time. This file defines the global wpail_fs() function and
// initialises the SDK. It is required directly (not autoloaded) because it
// intentionally lives outside the WPAIL\ namespace.
require_once WPAIL_PLUGIN_DIR . 'includes/Licensing/FreemiusBootstrap.php';

// Lifecycle hooks.
register_activation_hook( __FILE__,   [ Core\Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Core\Activator::class, 'deactivate' ] );

// Boot on plugins_loaded so all plugins are available for integration checks.
add_action( 'plugins_loaded', function (): void {
	Core\Plugin::instance()->boot();
}, 1 );
