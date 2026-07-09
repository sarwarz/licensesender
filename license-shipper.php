<?php
/**
 * The plugin bootstrap file
 *
 * @link              https://licenseshipper.com
 * @since             1.0.0
 * @package           License_Shipper
 *
 * @wordpress-plugin
 * Plugin Name:       License Shipper
 * Plugin URI:        https://github.com/sarwarz/license-shipper
 * Description:       Automatically deliver license keys for digital products via your LicenseShipper App.
 * Version:           1.1.0
 * Author:            License Shipper
 * Author URI:        https://licenseshipper.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       license-shipper
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin version (must match header)
 */
define( 'LICENSE_SHIPPER_VERSION', '1.1.0' );

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
		'https://github.com/sarwarz/license-shipper',
		__FILE__,
		'license-shipper'
	);

	// Use GitHub Release assets (recommended)
	$ls_update_checker->getVcsApi()->enableReleaseAssets();
}

/**
 * ----------------------------------------------------
 * ACTIVATION / DEACTIVATION
 * ----------------------------------------------------
 */
function activate_license_shipper() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-shipper-activator.php';
	License_Shipper_Activator::activate();
}

function deactivate_license_shipper() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-shipper-deactivator.php';
	License_Shipper_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_license_shipper' );
register_deactivation_hook( __FILE__, 'deactivate_license_shipper' );

/**
 * ----------------------------------------------------
 * CORE PLUGIN CLASS
 * ----------------------------------------------------
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-shipper.php';

/**
 * ----------------------------------------------------
 * RUN PLUGIN
 * ----------------------------------------------------
 */
function run_license_shipper() {
	$plugin = new License_Shipper();
	$plugin->run();
}

run_license_shipper();
