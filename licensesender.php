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
 * Version:           1.3.8
 * Author:            LicenseSender
 * Author URI:        https://licensesender.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       licensesender
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'LICENSESENDER_VERSION', '1.3.8' );
define( 'LICENSESENDER_FILE', __FILE__ );
define( 'LICENSESENDER_PATH', plugin_dir_path( __FILE__ ) );
define( 'LICENSESENDER_URL', plugin_dir_url( __FILE__ ) );

// LicenseSender app API. Point a local or staging site elsewhere from wp-config.php.
if ( ! defined( 'LICENSESENDER_API_BASE_URL' ) ) {
	define( 'LICENSESENDER_API_BASE_URL', 'https://licensesender.com/api/' );
}

require_once LICENSESENDER_PATH . 'src/autoload.php';

// Third-party libraries kept in libs/ (not managed by Composer).
if ( is_readable( LICENSESENDER_PATH . 'libs/plugin-update-checker/plugin-update-checker.php' ) ) {
	require_once LICENSESENDER_PATH . 'libs/plugin-update-checker/plugin-update-checker.php';
}
if ( is_readable( LICENSESENDER_PATH . 'libs/dompdf/autoload.inc.php' ) ) {
	require_once LICENSESENDER_PATH . 'libs/dompdf/autoload.inc.php';
}

// Updates come from GitHub releases; the release ZIP asset (npm run build:zip) is preferred over the source zip.
if ( class_exists( \YahnisElsts\PluginUpdateChecker\v5\PucFactory::class ) ) {
	$ls_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/sarwarz/licensesender',
		__FILE__,
		'licensesender'
	);
	$ls_update_checker->getVcsApi()->enableReleaseAssets();
}

register_activation_hook( __FILE__, array( \LicenseSender\Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \LicenseSender\Core\Deactivator::class, 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

\LicenseSender\Plugin::instance()->boot();
