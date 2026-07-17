<?php
/**
 * The plugin bootstrap file
 *
 * @link              https://licensesender.com
 * @since             1.0.0
 * @package           Licensesender
 *
 * @wordpress-plugin
 * Plugin Name:       LicenseSender
 * Plugin URI:        https://licensesender.com
 * Description:       Deliver license keys for digital products via your LicenseSender App (on Get Key click).
 * Version:           1.1.4
 * Author:            LicenseSender
 * Author URI:        https://licensesender.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       licensesender
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin version (must match header)
 */
define( 'LICENSESENDER_VERSION', '1.1.4' );

/**
 * Fixed licensesender API base URL (not configurable in settings).
 */
define( 'LICENSESENDER_API_BASE_URL', 'https://licensesender.com/api/' );

/**
 * ----------------------------------------------------
 * LOAD THIRD-PARTY LIBRARIES (NO COMPOSER)
 * ----------------------------------------------------
 */

/**
 * Plugin Update Checker (GitHub Releases)
 */
$ls_updater_file = plugin_dir_path( __FILE__ ) . 'libs/plugin-update-checker/plugin-update-checker.php';
if ( file_exists( $ls_updater_file ) ) {
	require_once $ls_updater_file;
}

/**
 * Dompdf (v0.8.x – non-Composer)
 */
$ls_dompdf_loader = plugin_dir_path( __FILE__ ) . 'libs/dompdf/autoload.inc.php';
if ( file_exists( $ls_dompdf_loader ) ) {
	require_once $ls_dompdf_loader;
}

/**
 * Namespaces
 */
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
use Dompdf\Dompdf;

/**
 * ----------------------------------------------------
 * GITHUB PLUGIN UPDATE CHECKER
 * ----------------------------------------------------
 *
 * Updates are delivered via GitHub Releases.
 * ZIP must be uploaded manually in each release.
 */
if ( class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {

	$ls_update_checker = PucFactory::buildUpdateChecker(
		'https://github.com/sarwarz/licensesender',
		__FILE__,
		'licensesender'
	);

	// Use GitHub Release assets (recommended)
	$ls_update_checker->getVcsApi()->enableReleaseAssets();
}

/**
 * ----------------------------------------------------
 * ACTIVATION / DEACTIVATION
 * ----------------------------------------------------
 */
function activate_licensesender() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-licensesender-activator.php';
	Licensesender_Activator::activate();
}

function deactivate_licensesender() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-licensesender-deactivator.php';
	Licensesender_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_licensesender' );
register_deactivation_hook( __FILE__, 'deactivate_licensesender' );

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * ----------------------------------------------------
 * CORE PLUGIN CLASS
 * ----------------------------------------------------
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-licensesender.php';

/**
 * ----------------------------------------------------
 * RUN PLUGIN
 * ----------------------------------------------------
 */
function run_licensesender() {
	$plugin = new Licensesender();
	$plugin->run();
}

run_licensesender();
