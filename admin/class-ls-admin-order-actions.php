<?php
defined( 'ABSPATH' ) || exit;

class LS_Admin_Order_Actions {

	public static function init() {
		add_action( 'wp_ajax_ls_admin_resend_license_email', array( __CLASS__, 'resend_license_email' ) );
		add_action( 'wp_ajax_ls_admin_sync_order_licenses', array( __CLASS__, 'sync_order_licenses' ) );
		add_action( 'wp_ajax_ls_admin_report_license', array( __CLASS__, 'report_license' ) );
	}

	public static function resend_license_email() {
		check_ajax_referer( 'ls_fetch_license_api', '_ajax_nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'licensesender' ) ) );
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'licensesender' ) ) );
		}

		if ( ! LS_License_Email_Service::is_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'License emails are disabled in settings.', 'licensesender' ) ) );
		}

		if ( ! LS_License_Email_Service::is_order_ready_for_email( $order_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Not all license keys have been fetched yet.', 'licensesender' ) ) );
		}

		$email = sanitize_email( $order->get_billing_email() );
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Order has no valid billing email.', 'licensesender' ) ) );
		}

		$sent = LS_License_Email_Service::send_order_email( $order_id, $email, true );

		if ( ! $sent ) {
			wp_send_json_error( array( 'message' => __( 'Failed to send license email.', 'licensesender' ) ) );
		}

		wp_send_json_success(
			array(
				'message'      => __( 'License email resent successfully.', 'licensesender' ),
				'email_status' => self::get_email_status_html( $order ),
			)
		);
	}

	public static function sync_order_licenses() {
		check_ajax_referer( 'ls_fetch_license_api', '_ajax_nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'licensesender' ) ) );
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$force    = ! empty( $_POST['force'] );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'licensesender' ) ) );
		}

		if ( ! class_exists( 'LS_License_Cache' ) ) {
			wp_send_json_error( array( 'message' => __( 'License cache is not available.', 'licensesender' ) ) );
		}

		$result = LS_License_Cache::sync_order_licenses( $order_id, $force );

		if ( empty( $result['success'] ) ) {
			wp_send_json_error(
				array(
					'message' => (string) ( $result['message'] ?? __( 'Sync failed.', 'licensesender' ) ),
				)
			);
		}

		$rows_payload = array();
		$seen         = array();

		foreach ( $order->get_items() as $item ) {
			$product_id = (int) ( $item->get_variation_id() ?: $item->get_product_id() );

			if ( isset( $seen[ $product_id ] ) || ! ls_is_licensesender_enabled( $product_id ) || ! ls_get_mapped_sku( $product_id ) ) {
				continue;
			}
			$seen[ $product_id ] = true;

			$expected_qty = ls_count_expected_keys_for_product_in_order( $order, $product_id );
			$cached       = ls_get_cached_licenses_for_product( $order_id, $product_id );
			$fetched_qty  = count( $cached );
			$is_complete  = $fetched_qty >= $expected_qty;
			$progress_cls = $is_complete ? 'is-complete' : ( $fetched_qty > 0 ? 'is-partial' : 'is-empty' );

			$rows_payload[ $product_id ] = array(
				'html'          => ls_render_admin_order_license_keys_html( $cached, $order ),
				'progress_html' => sprintf(
					'<span class="ls-license-progress %s ls-product-progress">%s</span>',
					esc_attr( $progress_cls ),
					esc_html( sprintf( '%d/%d', $fetched_qty, $expected_qty ) )
				),
				'action_html'   => $is_complete
					? '<span class="ls-status ls-status-success" title="' . esc_attr__( 'All keys fetched', 'licensesender' ) . '">✔</span>'
					: '',
			);
		}

		$expected_total = ls_count_expected_license_keys( $order );
		$fetched_total  = ls_count_fetched_license_keys( $order_id );
		$all_complete   = $expected_total > 0 && $fetched_total >= $expected_total;

		wp_send_json_success(
			array(
				'message'       => (string) ( $result['message'] ?? __( 'Licenses synced.', 'licensesender' ) ),
				'updated'       => (int) ( $result['updated'] ?? 0 ),
				'inserted'      => (int) ( $result['inserted'] ?? 0 ),
				'skipped'       => ! empty( $result['skipped'] ),
				'rows'          => $rows_payload,
				'keys_progress' => sprintf( '%d / %d', $fetched_total, $expected_total ),
				'email_status'  => self::get_email_status_html( $order ),
				'all_complete'  => $all_complete,
				'can_resend'    => LS_License_Email_Service::is_enabled() && $all_complete,
			)
		);
	}

	public static function report_license() {
		check_ajax_referer( 'ls_fetch_license_api', '_ajax_nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'licensesender' ) ) );
		}

		$license_id = absint( $_POST['license_id'] ?? 0 );
		$product_id = absint( $_POST['product_id'] ?? 0 );
		$order_id   = absint( $_POST['order_id'] ?? 0 );

		$result = LS_Admin_Service::report_license(
			$license_id,
			array(
				'reason' => isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : 'dead_key',
				'mode'   => isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'auto',
				'notes'  => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$order = $order_id ? wc_get_order( $order_id ) : null;
		if ( ! $order && ! empty( $result['license']['order_id'] ) ) {
			$order    = wc_get_order( (int) $result['license']['order_id'] );
			$order_id = $order ? $order->get_id() : 0;
		}

		$response = array(
			'message' => (string) ( $result['message'] ?? __( 'License reported successfully.', 'licensesender' ) ),
			'report'  => is_array( $result['report'] ?? null ) ? $result['report'] : array(),
			'license' => $result['license'] ?? null,
		);

		if ( $order && $product_id ) {
			$expected_qty = ls_count_expected_keys_for_product_in_order( $order, $product_id );
			$cached       = ls_get_cached_licenses_for_product( $order_id, $product_id );
			$fetched_qty  = count( $cached );
			$progress_cls = $fetched_qty >= $expected_qty ? 'is-complete' : ( $fetched_qty > 0 ? 'is-partial' : 'is-empty' );

			$response['product_id']    = $product_id;
			$response['html']          = ls_render_admin_order_license_keys_html( $cached, $order );
			$response['progress_html'] = sprintf(
				'<span class="ls-license-progress %s ls-product-progress">%s</span>',
				esc_attr( $progress_cls ),
				esc_html( sprintf( '%d/%d', $fetched_qty, $expected_qty ) )
			);
			$response['keys_progress'] = sprintf(
				'%d / %d',
				ls_count_fetched_license_keys( $order_id ),
				ls_count_expected_license_keys( $order )
			);
		}

		wp_send_json_success( $response );
	}

	public static function get_email_status_html( WC_Order $order ): string {
		$order_id = $order->get_id();

		if ( ! LS_License_Email_Service::is_enabled() ) {
			return '<span class="ls-status ls-status-muted">' . esc_html__( 'Email on redeem: disabled', 'licensesender' ) . '</span>';
		}

		if ( LS_License_Email_Service::is_order_email_sent( $order_id ) ) {
			return '<span class="ls-status ls-status-success">' . esc_html__( 'License email: sent', 'licensesender' ) . '</span>';
		}

		if ( LS_License_Email_Service::is_order_ready_for_email( $order_id ) ) {
			return '<span class="ls-status ls-status-warning">' . esc_html__( 'License email: pending', 'licensesender' ) . '</span>';
		}

		return '<span class="ls-status ls-status-muted">' . esc_html__( 'License email: waiting for keys', 'licensesender' ) . '</span>';
	}
}

LS_Admin_Order_Actions::init();
