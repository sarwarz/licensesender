<?php

namespace LicenseSender\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Licensesender
 * @subpackage Licensesender/includes
 * @author     licensesender <hello@licensesender.com>
 */
class I18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'licensesender',
			false,
			dirname( plugin_basename( LICENSESENDER_FILE ) ) . '/languages/'
		);

	}



}
