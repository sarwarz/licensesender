<?php

namespace LicenseSender\Orders;

defined( 'ABSPATH' ) || exit;

class OrderHandler {

	public static function init() {

		add_action(
			'woocommerce_order_status_processing',
			array( __CLASS__, 'force_complete_order' ),
			9999,
			2
		);

		add_action(
			'woocommerce_email_after_order_table',
			array( __CLASS__, 'ls_add_license_keys_link_to_email' ),
			10,
			4
		);

		// An order auto-completed while turning "processing" gets the Completed email; skip the
		// Processing one WooCommerce would send right after it.
		add_filter( 'woocommerce_email_enabled_customer_processing_order', array( __CLASS__, 'skip_processing_email' ), 10, 2 );

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

		if ( ! $order instanceof \WC_Order ) {
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

		// Physical items still need shipping: leave the order for the merchant to complete.
		if ( self::has_items_to_ship( $order ) ) {
			if ( $order->get_meta( '_ls_autocomplete_skipped' ) !== 'yes' ) {
				$order->add_order_note( __( 'Not auto-completed by LicenseSender: this order also has items to ship. Complete it once shipped; license keys become available then.', 'licensesender' ) );
				$order->update_meta_data( '_ls_autocomplete_skipped', 'yes' );
				$order->save();
			}
			return;
		}

		$allowed_statuses = apply_filters(
			'licensesender_autocomplete_statuses',
			array( 'processing' )
		);

		if ( ! in_array( $order->get_status(), $allowed_statuses, true ) ) {
			return;
		}

		self::$auto_completed[ (int) $order->get_id() ] = true;

		$order->update_status(
			'completed',
			__( 'Order auto-completed by licensesender.', 'licensesender' )
		);

		$order->update_meta_data( '_ls_completed_licensesender', 'yes' );
		$order->save();
	}

	/** @var array<int, bool> Orders auto-completed in this request. */
	private static $auto_completed = array();

	/**
	 * @param bool           $enabled Whether the Processing email is on.
	 * @param \WC_Order|null $order   Order.
	 */
	public static function skip_processing_email( $enabled, $order ) {
		if ( $order instanceof \WC_Order && isset( self::$auto_completed[ (int) $order->get_id() ] ) ) {
			return false;
		}

		return $enabled;
	}

	/**
	 * Whether the order has lines that ship and aren't LicenseSender products.
	 */
	private static function has_items_to_ship( \WC_Order $order ) {
		foreach ( $order->get_items() as $item ) {
			$product = $item instanceof \WC_Order_Item_Product ? $item->get_product() : null;
			if ( ! $product || ( ls_is_licensesender_enabled( $product->get_id() ) && ls_get_mapped_sku( $product->get_id() ) ) ) {
				continue;
			}
			if ( $product->needs_shipping() ) {
				return true;
			}
		}

		return false;
	}

	public static function order_print_items( $order ) {

		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		ls_render_license_table( $order->get_id() );
	}

	/**
	 * "View your keys" links in the customer's Completed order (and invoice) email, once keys
	 * can be fetched. Not in admin emails or other statuses.
	 *
	 * @param \WC_Order     $order         Order.
	 * @param bool          $sent_to_admin Admin copy.
	 * @param bool          $plain_text    Plain-text email.
	 * @param \WC_Email|null $email        Email being sent.
	 */
	public static function ls_add_license_keys_link_to_email( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {

		if ( ! $order instanceof \WC_Order || $sent_to_admin ) {
			return;
		}

		$email_id = $email instanceof \WC_Email ? $email->id : '';
		if ( ! in_array( $email_id, array( 'customer_completed_order', 'customer_invoice' ), true ) ) {
			return;
		}

		if ( ! ls_order_has_licensesender_product( $order ) || ! ls_order_can_fetch_new_keys( $order ) ) {
			return;
		}

		if ( $plain_text ) {
			$view = add_query_arg( 'key', $order->get_order_key(), wc_get_endpoint_url( 'order-received', $order->get_id(), wc_get_checkout_url() ) );
			echo "\n" . esc_html__( 'Your license keys:', 'licensesender' ) . ' ' . esc_url_raw( $view ) . "\n\n";
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
