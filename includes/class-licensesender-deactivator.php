<?php

/**
 * Fired during plugin deactivation
 */
class Licensesender_Deactivator {

	public static function deactivate() {
		require_once plugin_dir_path( __FILE__ ) . 'class-ls-my-keys-endpoint.php';
		if ( class_exists( 'LS_My_Keys_Endpoint' ) ) {
			LS_My_Keys_Endpoint::deactivate();
		}

		require_once plugin_dir_path( __FILE__ ) . 'class-ls-support-endpoint.php';
		if ( class_exists( 'LS_Support_Endpoint' ) ) {
			LS_Support_Endpoint::deactivate();
		}

		require_once plugin_dir_path( __FILE__ ) . 'class-ls-license-email-service.php';
		if ( class_exists( 'LS_License_Email_Service' ) ) {
			wp_clear_scheduled_hook( LS_License_Email_Service::CRON_HOOK );
		}

		flush_rewrite_rules();
	}
}
