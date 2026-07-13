<?php
defined( 'ABSPATH' ) || exit;

class Licensesender_Admin_Fetch_License {

	public function __construct() {
		add_action( 'wp_ajax_ls_admin_fetch_license', array( $this, 'handle' ) );
	}

	public function handle() {
		check_ajax_referer( 'ls_fetch_license_api', '_ajax_nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'licensesender' ) ) );
		}

		global $wpdb;

		$order_id   = absint( $_POST['order_id'] ?? 0 );
		$product_id = absint( $_POST['product_id'] ?? 0 );

		if ( ! $order_id || ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request data.', 'licensesender' ) ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'licensesender' ) ) );
		}

		if ( $order->get_status() !== 'completed' ) {
			wp_send_json_error( array( 'message' => __( 'Order must be completed.', 'licensesender' ) ) );
		}

		$quantity = ls_count_expected_keys_for_product_in_order( $order, $product_id );
		if ( $quantity <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Product not found in order.', 'licensesender' ) ) );
		}

		if ( ! ls_is_licensesender_enabled( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'licensesender is disabled for this product.', 'licensesender' ) ) );
		}

		$mapped_sku = ls_get_mapped_sku( $product_id );
		if ( ! $mapped_sku ) {
			wp_send_json_error( array( 'message' => __( 'No mapped SKU found for this product.', 'licensesender' ) ) );
		}

		$lock = ls_acquire_fetch_lock( $order_id, $product_id );
		if ( is_wp_error( $lock ) ) {
			wp_send_json_error( array( 'message' => $lock->get_error_message() ) );
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
			ls_release_fetch_lock( $order_id, $product_id );
			wp_send_json_error( array( 'message' => __( 'License already fetched for this product.', 'licensesender' ) ) );
		}

		$need = $quantity - $existing;

		$api = Licensesender_Api::fetch_license(
			array(
				'sku'      => $mapped_sku,
				'quantity' => $need,
				'order_id' => $order_id,
				'email'    => $order->get_billing_email(),
				'source'   => sanitize_title( get_bloginfo( 'name' ) ),
			)
		);

		if ( empty( $api['success'] ) ) {
			ls_release_fetch_lock( $order_id, $product_id );
			wp_send_json_error(
				array(
					'message' => $api['message'] ?? __( 'API request failed.', 'licensesender' ),
					'meta'    => $api['meta'] ?? array(),
				)
			);
		}

		$licenses     = $api['licenses'] ?? array();
		$product_info = $api['product'] ?? array();

		if ( empty( $licenses ) ) {
			ls_release_fetch_lock( $order_id, $product_id );
			wp_send_json_error( array( 'message' => __( 'API returned no licenses.', 'licensesender' ) ) );
		}

		$links               = ls_get_license_product_links( $product_id );
		$final_download_link = $links['download_link'] ?: ( $product_info['download_link'] ?? '' );
		$final_guide_link    = $links['activation_guide'] ?: ( $product_info['activation_guide'] ?? '' );

		LS_License_Cache::save_fetched_licenses(
			$order_id,
			$product_id,
			$mapped_sku,
			$order->get_billing_email(),
			$licenses,
			$final_download_link,
			$final_guide_link,
			'admin'
		);

		LS_License_Email_Service::maybe_schedule_after_fetch( $order, (string) $order->get_billing_email() );

		$cached      = ls_get_cached_licenses_for_product( $order_id, $product_id );
		$fetched_qty = count( $cached );
		$is_complete = $fetched_qty >= $quantity;
		$progress_cls = $is_complete ? 'is-complete' : ( $fetched_qty > 0 ? 'is-partial' : 'is-empty' );

		$expected_total = ls_count_expected_license_keys( $order );
		$fetched_total  = ls_count_fetched_license_keys( $order_id );
		$all_complete   = $expected_total > 0 && $fetched_total >= $expected_total;

		ls_release_fetch_lock( $order_id, $product_id );

		wp_send_json_success(
			array(
				'message'       => __( 'License fetched successfully.', 'licensesender' ),
				'html'          => ls_render_admin_order_license_keys_html( $cached, $order ),
				'progress_html' => sprintf(
					'<span class="ls-license-progress %s ls-product-progress">%s</span>',
					esc_attr( $progress_cls ),
					esc_html( sprintf( '%d/%d', $fetched_qty, $quantity ) )
				),
				'action_html'   => $is_complete
					? '<span class="ls-status ls-status-success" title="' . esc_attr__( 'All keys fetched', 'licensesender' ) . '">✔</span>'
					: '',
				'keys_progress' => sprintf( '%d / %d', $fetched_total, $expected_total ),
				'email_status'  => LS_Admin_Order_Actions::get_email_status_html( $order ),
				'all_complete'  => $all_complete,
				'can_resend'    => LS_License_Email_Service::is_enabled() && $all_complete,
				'count'         => count( $licenses ),
			)
		);
	}
}

new Licensesender_Admin_Fetch_License();
