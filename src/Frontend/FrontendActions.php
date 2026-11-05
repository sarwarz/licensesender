<?php

namespace LicenseSender\Frontend;

use LicenseSender\Licenses\KeyDelivery;
use LicenseSender\Licenses\LicenseEmailService;

defined( 'ABSPATH' ) || exit;

class FrontendActions {

	public static function init() {
		// React License Keys section (order details + thank-you); guests prove order key + email.
		add_action( 'wp_ajax_ls_order_keys_view', array( __CLASS__, 'ajax_order_keys_view' ) );
		add_action( 'wp_ajax_nopriv_ls_order_keys_view', array( __CLASS__, 'ajax_order_keys_view' ) );
		add_action( 'wp_ajax_ls_order_keys_get', array( __CLASS__, 'ajax_order_keys_get' ) );
		add_action( 'wp_ajax_nopriv_ls_order_keys_get', array( __CLASS__, 'ajax_order_keys_get' ) );

		add_action( 'wp_ajax_export_license_key', array( __CLASS__, 'ls_export_license_key_csv' ) );
		add_action( 'wp_ajax_nopriv_export_license_key', array( __CLASS__, 'ls_export_license_key_csv' ) );

		add_action( 'wp_ajax_download_activation_guide', array( __CLASS__, 'ls_download_activation_guide' ) );
		add_action( 'wp_ajax_nopriv_download_activation_guide', array( __CLASS__, 'ls_download_activation_guide' ) );
	}

	/** Order page / thank-you License Keys section: view saved keys (owner or guest with key + email). */
	public static function ajax_order_keys_view() {
		check_ajax_referer( 'ls_order_keys', 'nonce' );
		KeyDelivery::send( KeyDelivery::view( self::accessible_order(), absint( $_POST['product_id'] ?? 0 ) ) );
	}

	/** Order page / thank-you License Keys section: get keys (fetches missing ones). */
	public static function ajax_order_keys_get() {
		check_ajax_referer( 'ls_order_keys', 'nonce' );
		$order = self::accessible_order();
		KeyDelivery::send( KeyDelivery::fetch( $order, absint( $_POST['product_id'] ?? 0 ), (string) $order->get_billing_email() ) );
	}

	/**
	 * The posted order when the visitor may see its keys: the logged-in owner, a store
	 * manager, or a guest who proves the order key AND billing email. Ends the request otherwise.
	 */
	private static function accessible_order(): \WC_Order {
		$order = wc_get_order( absint( $_POST['order_id'] ?? 0 ) );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'licensesender' ) ), 404 );
		}

		$email     = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$order_key = isset( $_POST['order_key'] ) ? wc_clean( wp_unslash( $_POST['order_key'] ) ) : '';
		if ( ! ls_user_can_access_order_licenses( $order, array( 'email' => $email, 'order_key' => $order_key ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access to order.', 'licensesender' ) ), 403 );
		}

		return $order;
	}

	public static function ls_export_license_key_csv() {
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			wp_die( esc_html__( 'Invalid context.', 'licensesender' ), '', 400 );
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$nonce    = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $order_id || ! wp_verify_nonce( $nonce, 'export_license_nonce' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'licensesender' ), '', 400 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'licensesender' ), '', 404 );
		}

		$email     = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';
		$order_key = isset( $_GET['order_key'] ) ? wc_clean( wp_unslash( $_GET['order_key'] ) ) : '';

		if ( ! ls_user_can_access_order_licenses( $order, array( 'email' => $email, 'order_key' => $order_key ) ) ) {
			wp_die( esc_html__( 'Unauthorized', 'licensesender' ), '', 403 );
		}

		if ( ! $order->has_status( 'completed' ) ) {
			wp_die( esc_html__( 'Order not paid/ready.', 'licensesender' ), '', 403 );
		}

		// Export uses cached keys only; allow when any cache exists even if new fetch is blocked.
		global $wpdb;
		$table    = $wpdb->prefix . 'ls_cached_licenses';
		$licenses = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE order_id = %d", $order_id )
		);

		if ( empty( $licenses ) && ! ls_order_can_fetch_new_keys( $order ) ) {
			wp_die( esc_html( ls_order_delivery_block_message( $order ) ), '', 403 );
		}

		if ( empty( $licenses ) ) {
			wp_die( esc_html__( 'No license keys found for this order.', 'licensesender' ), '', 404 );
		}

		nocache_headers();
		$filename = "license-order-{$order_id}.csv";
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Order ID', 'Product Name', 'License Key' ) );

		foreach ( $licenses as $license ) {
			$product      = wc_get_product( (int) $license->product_id );
			$product_name = $product ? $product->get_name() : 'Unknown';
			fputcsv(
				$out,
				array(
					(int) $license->order_id,
					ls_csv_safe_field( $product_name ),
					ls_csv_safe_field( $license->key_value ),
				)
			);
		}

		fclose( $out );
		exit;
	}

	public static function ls_download_activation_guide() {
		$key_id = isset( $_GET['key_id'] ) ? absint( $_GET['key_id'] ) : 0;
		// Allowlist only — sanitize_text_field / +→space corruption breaks base64 tokens.
		$token_raw = isset( $_GET['token'] ) ? wp_unslash( $_GET['token'] ) : '';
		$token     = is_string( $token_raw ) ? preg_replace( '/[^A-Za-z0-9\-_=+\/]/', '', $token_raw ) : '';
		$nonce     = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $key_id ) {
			wp_die( esc_html__( 'Invalid request.', 'licensesender' ) );
		}

		global $wpdb;
		$table   = $wpdb->prefix . 'ls_cached_licenses';
		$license = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $key_id )
		);

		if ( ! $license || empty( $license->activation_guide ) ) {
			wp_die( esc_html__( 'Activation guide not found.', 'licensesender' ) );
		}

		$order = wc_get_order( (int) $license->order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'licensesender' ) );
		}

		$email     = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';
		$order_key = isset( $_GET['order_key'] ) ? wc_clean( wp_unslash( $_GET['order_key'] ) ) : '';

		$access_ok = ls_user_can_access_order_licenses(
			$order,
			array(
				'email'     => $email,
				'order_key' => $order_key,
			)
		);
		$token_ok = ( $token !== '' && ls_verify_guide_download_token( $token, $key_id, $order ) );
		$nonce_ok = ( $nonce !== '' && wp_verify_nonce( $nonce, 'dl_guide_' . $key_id ) );

		// Order key + billing email (or owner/admin) is sufficient — same trust model as key export.
		// Token/nonce remain accepted for older links and edge cases.
		if ( ! $access_ok && ! $token_ok && ! $nonce_ok ) {
			wp_die( esc_html__( 'Invalid or expired download link.', 'licensesender' ) );
		}

		$url = esc_url_raw( trim( (string) $license->activation_guide ) );
		if ( ! ls_is_safe_activation_guide_url( $url ) ) {
			wp_die( esc_html__( 'Invalid activation guide URL.', 'licensesender' ) );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'   => 30,
				'sslverify' => true,
				'redirection' => 3,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_die( esc_html__( 'Could not access activation guide file.', 'licensesender' ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			wp_die( esc_html__( 'Could not access activation guide file.', 'licensesender' ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			wp_die( esc_html__( 'Activation guide is empty.', 'licensesender' ) );
		}

		$filename = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$filename = preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $filename );
		if ( ! $filename || strlen( $filename ) > 180 ) {
			$filename = 'activation-guide.pdf';
		}

		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $body ) );
		header( 'Cache-Control: no-cache' );

		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
