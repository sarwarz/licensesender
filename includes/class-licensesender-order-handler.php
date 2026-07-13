<?php
defined( 'ABSPATH' ) || exit();

class Licensesender_Order_Handler {

	public static function init() {

		add_action(
			'woocommerce_order_status_processing',
			array( __CLASS__, 'force_complete_order' ),
			9999,
			2
		);

		add_action(
			'woocommerce_email_after_order_table',
			array( __CLASS__, 'ls_add_license_keys_link_to_email' )
		);

		add_action(
			'woocommerce_order_details_after_order_table',
			array( __CLASS__, 'order_print_items' ),
			10
		);

		add_action(
			'woocommerce_order_status_completed',
			array( __CLASS__, 'ls_update_order_meta' )
		);
	}

	public static function force_complete_order( $order_id, $order ) {

		$enable_autocomplete = get_option( 'lship_autocomplete_order' );

		if ( $enable_autocomplete !== 'yes' ) {
			return;
		}

		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		if ( $order->get_meta( '_ls_completed_licensesender' ) === 'yes' ) {
			return;
		}

		if ( ! ls_order_has_licensesender_product( $order ) ) {
			return;
		}

		$allowed_statuses = apply_filters(
			'licensesender_autocomplete_statuses',
			array( 'processing' )
		);

		if ( ! in_array( $order->get_status(), $allowed_statuses, true ) ) {
			return;
		}

		$order->update_status(
			'completed',
			__( 'Order auto-completed by licensesender.', 'licensesender' )
		);

		$order->update_meta_data( '_ls_completed_licensesender', 'yes' );
		$order->save();
	}

	public static function order_print_items( $order ) {

		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		ls_render_license_table( $order->get_id() );
	}

	public static function ls_add_license_keys_link_to_email( $order ) {

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( ! ls_order_has_licensesender_product( $order ) ) {
			return;
		}

		$order_id  = $order->get_id();
		$order_key = $order->get_order_key();

		$thankyou_url = add_query_arg(
			'key',
			$order_key,
			wc_get_endpoint_url( 'order-received', $order_id, wc_get_checkout_url() )
		);

		$myaccount_url = add_query_arg(
			'key',
			$order_key,
			wc_get_endpoint_url( 'view-order', $order_id, wc_get_page_permalink( 'myaccount' ) )
		);

		echo '<h2>' . esc_html__( 'Your Order & License Access', 'licensesender' ) . '</h2>';
		echo '<p style="margin-bottom:15px;">' . esc_html__( 'Use any of the links below to access your order and license keys:', 'licensesender' ) . '</p>';

		echo '<p>
			<a href="' . esc_url( $thankyou_url ) . '" target="_blank"
			style="background:#2271b1;color:#fff;padding:10px 20px;border-radius:5px;text-decoration:none;margin-right:10px;">
			' . esc_html__( 'View License Key', 'licensesender' ) . '
			</a>

			<a href="' . esc_url( $myaccount_url ) . '" target="_blank"
			style="background:#32ab13;color:#fff;padding:10px 20px;border-radius:5px;text-decoration:none;">
			' . esc_html__( 'View Order in My Account', 'licensesender' ) . '
			</a>
		</p>';
	}

	public static function ls_update_order_meta( $order_id ) {

		if ( empty( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! ls_order_has_licensesender_product( $order ) ) {
			return;
		}

		$order->update_meta_data( '_ls_completed_licensesender', 'yes' );
		$order->save();
	}
}

Licensesender_Order_Handler::init();
