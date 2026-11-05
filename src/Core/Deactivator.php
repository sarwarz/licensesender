<?php

namespace LicenseSender\Core;

use LicenseSender\Support\AccountEndpoint;
use LicenseSender\Licenses\LicenseEmailService;
use LicenseSender\Licenses\MyKeysEndpoint;

defined( 'ABSPATH' ) || exit;

/**
 * Fired during plugin deactivation
 */
class Deactivator {

	public static function deactivate() {
		if ( class_exists( MyKeysEndpoint::class ) ) {
			MyKeysEndpoint::deactivate();
		}

		if ( class_exists( AccountEndpoint::class ) ) {
			AccountEndpoint::deactivate();
		}

		// wp_unschedule_hook() removes every event of a hook; wp_clear_scheduled_hook() without
		// args would miss events scheduled with args (the email and push events have them).
		foreach ( array( 'ls_send_license_email_event', 'ls_push_order_to_saas', 'ls_backfill_orders_to_saas' ) as $hook ) {
			wp_unschedule_hook( $hook );
		}

		flush_rewrite_rules();
	}
}
