<?php

/**
 * Fired during plugin deactivation
 */
class License_Shipper_Deactivator {

	public static function deactivate() {
		require_once plugin_dir_path( __FILE__ ) . 'class-ls-my-keys-endpoint.php';
		if ( class_exists( 'LS_My_Keys_Endpoint' ) ) {
			LS_My_Keys_Endpoint::deactivate();
		}

		flush_rewrite_rules();
	}
}
