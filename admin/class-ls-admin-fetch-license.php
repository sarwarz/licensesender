<?php
defined( 'ABSPATH' ) || exit;

class License_Shipper_Admin_Fetch_License {

	public function __construct() {
		add_action( 'wp_ajax_ls_admin_fetch_license', array( $this, 'handle' ) );
	}

	public function handle() {
		check_ajax_referer( 'ls_fetch_license_api', '_ajax_nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'license-shipper' ) ) );
		}

		global $wpdb;

		$order_id   = absint( $_POST['order_id'] ?? 0 );
		$product_id = absint( $_POST['product_id'] ?? 0 );

		if ( ! $order_id || ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request data.', 'license-shipper' ) ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'license-shipper' ) ) );
		}

		if ( $order->get_status() !== 'completed' ) {
			wp_send_json_error( array( 'message' => __( 'Order must be completed.', 'license-shipper' ) ) );
		}

		$quantity = ls_count_expected_keys_for_product_in_order( $order, $product_id );
		if ( $quantity <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Product not found in order.', 'license-shipper' ) ) );
		}

		if ( ! ls_is_license_shipper_enabled( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'License Shipper is disabled for this product.', 'license-shipper' ) ) );
		}

		$mapped_sku = ls_get_mapped_sku( $product_id );
		if ( ! $mapped_sku ) {
			wp_send_json_error( array( 'message' => __( 'No mapped SKU found for this product.', 'license-shipper' ) ) );
		}

		$table = $wpdb->prefix . 'ls_cached_licenses';

		$existing = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE order_id = %d AND product_id = %d AND fetched = 1",
				$order_id,
				$product_id
			)
		);

		if ( $existing >= $quantity ) {
			wp_send_json_error( array( 'message' => __( 'License already fetched for this product.', 'license-shipper' ) ) );
		}

		$need = $quantity - $existing;

		$api = License_Shipper_Api::fetch_license(
			array(
				'sku'      => $mapped_sku,
				'quantity' => $need,
				'order_id' => $order_id,
				'email'    => $order->get_billing_email(),
				'source'   => sanitize_title( get_bloginfo( 'name' ) ) . '(admin)',
			)
		);

		if ( empty( $api['success'] ) ) {
			wp_send_json_error(
				array(
					'message' => $api['message'] ?? __( 'API request failed.', 'license-shipper' ),
					'meta'    => $api['meta'] ?? array(),
				)
			);
		}

		$licenses     = $api['licenses'] ?? array();
		$product_info = $api['product'] ?? array();

		if ( empty( $licenses ) ) {
			wp_send_json_error( array( 'message' => __( 'API returned no licenses.', 'license-shipper' ) ) );
		}

		$links               = ls_get_license_product_links( $product_id );
		$final_download_link = $links['download_link'] ?: ( $product_info['download_link'] ?? '' );
		$final_guide_link    = $links['activation_guide'] ?: ( $product_info['activation_guide'] ?? '' );

		$saved = 0;

		foreach ( $licenses as $license ) {
			$key = trim( $license['key'] ?? '' );
			if ( ! $key ) {
				continue;
			}

			$inserted = $wpdb->insert(
				$table,
				array(
					'order_id'         => $order_id,
					'product_id'       => $product_id,
					'sku'              => $mapped_sku,
					'email'            => $order->get_billing_email(),
					'key_value'        => $key,
					'download_link'    => $final_download_link,
					'activation_guide' => $final_guide_link,
					'source'           => 'admin',
					'fetched'          => 1,
				)
			);

			if ( $inserted ) {
				++$saved;
			}
		}

		if ( $saved === 0 ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save licenses.', 'license-shipper' ) ) );
		}

		LS_License_Email_Service::maybe_schedule_after_fetch( $order, (string) $order->get_billing_email() );

		$cached      = ls_get_cached_licenses_for_product( $order_id, $product_id );
		$fetched_qty = count( $cached );
		$is_complete = $fetched_qty >= $quantity;
		$progress_cls = $is_complete ? 'is-complete' : ( $fetched_qty > 0 ? 'is-partial' : 'is-empty' );

		$expected_total = ls_count_expected_license_keys( $order );
		$fetched_total  = ls_count_fetched_license_keys( $order_id );
		$all_complete   = $expected_total > 0 && $fetched_total >= $expected_total;

		wp_send_json_success(
			array(
				'message'       => __( 'License fetched successfully.', 'license-shipper' ),
				'html'          => ls_render_admin_order_license_keys_html( $cached, $order ),
				'progress_html' => sprintf(
					'<span class="ls-license-progress %s ls-product-progress">%s</span>',
					esc_attr( $progress_cls ),
					esc_html( sprintf( '%d/%d', $fetched_qty, $quantity ) )
				),
				'action_html'   => $is_complete
					? '<span class="ls-status ls-status-success" title="' . esc_attr__( 'All keys fetched', 'license-shipper' ) . '">✔</span>'
					: '',
				'keys_progress' => sprintf( '%d / %d', $fetched_total, $expected_total ),
				'email_status'  => LS_Admin_Order_Actions::get_email_status_html( $order ),
				'all_complete'  => $all_complete,
				'can_resend'    => LS_License_Email_Service::is_enabled() && $all_complete,
				'count'         => $saved,
			)
		);
	}
}

new License_Shipper_Admin_Fetch_License();
