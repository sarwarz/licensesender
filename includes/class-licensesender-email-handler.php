<?php
defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper — delegates to LS_License_Email_Service.
 */
class Ls_Licensesender_Email_Handler {

	public static function send_license_email( $order_id, $email ) {
		return LS_License_Email_Service::send_order_email( (int) $order_id, (string) $email );
	}
}
