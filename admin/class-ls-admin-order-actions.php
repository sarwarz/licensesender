<?php
defined( 'ABSPATH' ) || exit;

class LS_Admin_Order_Actions {

	public static function init() {
		add_action( 'wp_ajax_ls_admin_resend_license_email', array( __CLASS__, 'resend_license_email' ) );
	}

	public static function resend_license_email() {
		check_ajax_referer( 'ls_fetch_license_api', '_ajax_nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'license-shipper' ) ) );
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'license-shipper' ) ) );
		}

		if ( ! LS_License_Email_Service::is_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'License emails are disabled in settings.', 'license-shipper' ) ) );
		}

		if ( ! LS_License_Email_Service::is_order_ready_for_email( $order_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Not all license keys have been fetched yet.', 'license-shipper' ) ) );
		}

		$email = sanitize_email( $order->get_billing_email() );
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Order has no valid billing email.', 'license-shipper' ) ) );
		}

		$sent = LS_License_Email_Service::send_order_email( $order_id, $email, true );

		if ( ! $sent ) {
			wp_send_json_error( array( 'message' => __( 'Failed to send license email.', 'license-shipper' ) ) );
		}

		wp_send_json_success(
			array(
				'message'      => __( 'License email resent successfully.', 'license-shipper' ),
				'email_status' => self::get_email_status_html( $order ),
			)
		);
	}

	public static function get_email_status_html( WC_Order $order ): string {
		$order_id = $order->get_id();

		if ( ! LS_License_Email_Service::is_enabled() ) {
			return '<span class="ls-status ls-status-muted">' . esc_html__( 'Email on redeem: disabled', 'license-shipper' ) . '</span>';
		}

		if ( LS_License_Email_Service::is_order_email_sent( $order_id ) ) {
			return '<span class="ls-status ls-status-success">' . esc_html__( 'License email: sent', 'license-shipper' ) . '</span>';
		}

		if ( LS_License_Email_Service::is_order_ready_for_email( $order_id ) ) {
			return '<span class="ls-status ls-status-warning">' . esc_html__( 'License email: pending', 'license-shipper' ) . '</span>';
		}

		return '<span class="ls-status ls-status-muted">' . esc_html__( 'License email: waiting for keys', 'license-shipper' ) . '</span>';
	}
}

LS_Admin_Order_Actions::init();
