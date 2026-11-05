<?php

namespace LicenseSender\Wholesale;

use LicenseSender\Core\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Wholesale checkout without the WooCommerce cart.
 *
 * The portal sends the order lines; we price them with LicenseSender (the buyer's group),
 * create a normal pending WooCommerce order, hold its keys, then either pay it from the
 * wallet right away or send the buyer to WooCommerce's order-pay page, where the
 * wholesale payment rules already apply. No coupons, no shipping form, and the buyer's
 * retail cart is left alone.
 */
class Checkout {

	const CREATED_VIA = 'ls-wholesale-portal';

	public static function init() {
		add_action( 'wp_ajax_ls_wholesale_place_order', array( __CLASS__, 'ajax_place_order' ) );
	}

	/**
	 * Price and check order lines.
	 *
	 * @param array<string, int> $wanted SKU => keys.
	 * @return array{lines: array<string, array<string, mixed>>, errors: array<string, string>, notices: string[]}|\WP_Error
	 */
	public static function price_lines( array $wanted ) {
		$catalog = Wholesale::get_catalog();
		$items   = array();
		foreach ( $wanted as $sku => $qty ) {
			$items[] = array( 'sku' => (string) $sku, 'quantity' => (int) $qty );
		}

		$quote = Api::quote_wholesale_cart( $items, Wholesale::get_user_group() );
		if ( empty( $quote['success'] ) ) {
			if ( empty( $catalog['success'] ) ) {
				return new \WP_Error( 'unavailable', __( 'Wholesale pricing is temporarily unavailable. Please try again in a moment.', 'licensesender' ) );
			}
			// Older LicenseSender without quotes: price from the catalog instead.
			$quote = Cart::quote_from_catalog( $catalog, $wanted );
		}

		$lines  = array();
		$errors = array();
		foreach ( Cart::quote_lines( $quote ) as $sku => $line ) {
			$item    = Wholesale::get_catalog_product_by_sku( $sku, $catalog );
			$name    = (string) ( $item['name'] ?? $line['name'] ?? $sku );
			$problem = Cart::line_problem_message( $line, $name, $item );
			if ( $problem !== '' ) {
				$errors[ $sku ] = $problem;
				continue;
			}
			if ( ! isset( $line['unit_price'] ) || null === $line['unit_price'] ) {
				$errors[ $sku ] = __( 'This wholesale product is no longer available.', 'licensesender' );
				continue;
			}
			$lines[ $sku ] = array(
				'name'  => $name,
				'qty'   => (int) $line['quantity'],
				'price' => (float) $line['unit_price'],
			);
		}

		$notices = Cart::minimum_messages( $quote );
		$store_min = Wholesale::get_min_order_quantity();
		$keys      = array_sum( array_column( $lines, 'qty' ) );
		if ( $store_min > 0 && $keys < $store_min ) {
			/* translators: 1: minimum keys, 2: keys in the order */
			$notices[] = sprintf( __( 'Wholesale orders need at least %1$d keys. This order has %2$d.', 'licensesender' ), $store_min, $keys );
		}

		return array( 'lines' => $lines, 'errors' => $errors, 'notices' => $notices );
	}

	/**
	 * Wallet gateway the buyer may use, if any (TeraWallet active and allowed by the
	 * wholesale payment mode).
	 */
	public static function wallet_gateway() {
		if ( ! Wholesale::is_terawallet_active() || ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return null;
		}

		$gateways = WC()->payment_gateways()->payment_gateways();
		$ids      = Wholesale::get_wallet_gateway_ids( $gateways );
		if ( Wholesale::PAYMENT_MODE_CUSTOM === Wholesale::get_payment_mode() ) {
			$ids = array_intersect( $ids, Wholesale::get_custom_payment_gateways() );
		}

		foreach ( $ids as $id ) {
			if ( isset( $gateways[ $id ] ) && 'yes' === $gateways[ $id ]->enabled ) {
				return $gateways[ $id ];
			}
		}

		return null;
	}

	public static function ajax_place_order() {
		check_ajax_referer( 'ls_wholesale_cart', 'nonce' );

		$denied = Cart::access_error();
		if ( $denied ) {
			wp_send_json_error( array( 'message' => $denied->get_error_message() ), (int) $denied->get_error_data()['status'] );
		}

		$user_id = get_current_user_id();
		// One order at a time per buyer (double clicks, two tabs).
		$lock = 'ls_ws_placing_' . $user_id;
		if ( get_transient( $lock ) ) {
			wp_send_json_error( array( 'message' => __( 'Your order is already being placed. Please wait a moment.', 'licensesender' ) ), 429 );
		}
		set_transient( $lock, 1, 30 );

		try {
			$result = self::place_order( $user_id, $_POST );
		} finally {
			delete_transient( $lock );
		}

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'errors'  => (object) ( is_array( $data ) ? ( $data['errors'] ?? array() ) : array() ),
				),
				400
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * @param int                  $user_id Buyer.
	 * @param array<string, mixed> $input   Raw request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function place_order( $user_id, array $input ) {
		$raw    = json_decode( wp_unslash( (string) ( $input['items'] ?? '' ) ), true );
		$wanted = array();
		foreach ( is_array( $raw ) ? array_slice( $raw, 0, 200 ) : array() as $line ) {
			$sku = sanitize_text_field( (string) ( $line['sku'] ?? '' ) );
			$qty = (int) ( $line['qty'] ?? 0 );
			if ( $sku !== '' && $qty > 0 ) {
				$wanted[ $sku ] = ( $wanted[ $sku ] ?? 0 ) + min( $qty, 100000 );
			}
		}
		if ( empty( $wanted ) ) {
			return new \WP_Error( 'empty', __( 'Choose at least one product.', 'licensesender' ) );
		}

		$priced = self::price_lines( $wanted );
		if ( is_wp_error( $priced ) ) {
			return $priced;
		}
		if ( ! empty( $priced['errors'] ) ) {
			return new \WP_Error( 'lines', __( 'Some products need changes before you can order.', 'licensesender' ), array( 'errors' => $priced['errors'] ) );
		}
		if ( ! empty( $priced['notices'] ) ) {
			return new \WP_Error( 'minimum', implode( ' ', $priced['notices'] ) );
		}

		$products = Catalog::map_skus( array_keys( $priced['lines'] ) );
		$missing  = array_diff_key( $priced['lines'], $products );
		if ( $missing ) {
			$errors = array();
			foreach ( $missing as $sku => $line ) {
				$errors[ $sku ] = __( 'This product cannot be ordered yet.', 'licensesender' );
			}
			return new \WP_Error( 'unmapped', __( 'Some products need changes before you can order.', 'licensesender' ), array( 'errors' => $errors ) );
		}

		$order = self::create_order( $user_id, $priced['lines'], $products, sanitize_textarea_field( wp_unslash( (string) ( $input['note'] ?? '' ) ) ) );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		// Hold the keys before payment; if they ran out meanwhile, drop the order.
		$held = Orders::hold_stock( $order, Wholesale::get_hold_minutes() );
		if ( is_wp_error( $held ) ) {
			$order->delete( true );
			return $held;
		}

		$paid     = false;
		$redirect = $order->get_checkout_payment_url();

		if ( 'wallet' === ( $input['pay'] ?? '' ) ) {
			$wallet = self::wallet_gateway();
			if ( $wallet && Payments::get_wallet_balance_amount() + 0.00001 >= (float) $order->get_total() ) {
				$order->set_payment_method( $wallet );
				$order->save();
				// Wallet gateways empty the cart after paying; this order never used it.
				$saved_cart = WC()->cart ? WC()->cart->get_cart_for_session() : array();
				$result     = $wallet->process_payment( $order->get_id() );
				if ( $saved_cart && WC()->cart && WC()->cart->is_empty() && WC()->session ) {
					WC()->session->set( 'cart', $saved_cart );
					WC()->cart->get_cart_from_session();
				}
				if ( is_array( $result ) && 'success' === ( $result['result'] ?? '' ) ) {
					$paid     = true;
					$redirect = ! empty( $result['redirect'] ) ? $result['redirect'] : $order->get_checkout_order_received_url();
				}
				wc_clear_notices();
			}
		}

		return array(
			'order_id' => $order->get_id(),
			'number'   => $order->get_order_number(),
			'paid'     => $paid,
			'redirect' => $redirect,
		);
	}

	/**
	 * A pending WooCommerce order with the wholesale lines at their quoted prices.
	 *
	 * @param int                                $user_id  Buyer.
	 * @param array<string, array<string,mixed>> $lines    SKU => name/qty/price.
	 * @param array<string, int>                 $products SKU => product or variation ID.
	 * @param string                             $note     Buyer's note / PO reference.
	 * @return \WC_Order|\WP_Error
	 */
	private static function create_order( $user_id, array $lines, array $products, $note ) {
		$order = wc_create_order(
			array(
				'customer_id' => $user_id,
				'created_via' => self::CREATED_VIA,
				'status'      => 'pending',
			)
		);
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		// Wholesale prices come from LicenseSender in the store's base currency.
		$order->set_currency( Currency::get_base_currency() );

		foreach ( $lines as $sku => $line ) {
			$product = wc_get_product( $products[ $sku ] );
			if ( ! $product ) {
				$order->delete( true );
				return new \WP_Error( 'product', __( 'A product in this order could not be loaded.', 'licensesender' ) );
			}

			$total   = wc_format_decimal( $line['price'] * $line['qty'] );
			$item_id = $order->add_product( $product, $line['qty'], array( 'subtotal' => $total, 'total' => $total ) );
			$item    = $order->get_item( $item_id );
			if ( $item ) {
				$item->add_meta_data( '_ls_wholesale_line', 'yes', true );
				$item->add_meta_data( '_ls_wholesale_sku', $sku, true );
				$item->add_meta_data( '_ls_wholesale_unit_price', wc_format_decimal( $line['price'] ), true );
				$item->save();
			}
		}

		$customer = new \WC_Customer( $user_id );
		$user     = get_userdata( $user_id );
		$billing  = array(
			'first_name' => $customer->get_billing_first_name() ?: $customer->get_first_name(),
			'last_name'  => $customer->get_billing_last_name() ?: $customer->get_last_name(),
			'company'    => $customer->get_billing_company(),
			'email'      => $customer->get_billing_email() ?: ( $user ? $user->user_email : '' ),
			'phone'      => $customer->get_billing_phone(),
			'address_1'  => $customer->get_billing_address_1(),
			'address_2'  => $customer->get_billing_address_2(),
			'city'       => $customer->get_billing_city(),
			'state'      => $customer->get_billing_state(),
			'postcode'   => $customer->get_billing_postcode(),
			'country'    => $customer->get_billing_country(),
		);
		$order->set_address( $billing, 'billing' );
		if ( $note !== '' ) {
			$order->set_customer_note( $note );
		}

		$order->update_meta_data( Orders::ORDER_META, 'yes' );
		$order->update_meta_data( Orders::GROUP_META, Wholesale::get_user_group( $user_id ) );
		$order->calculate_totals( true );
		$order->add_order_note( __( 'Wholesale order placed from the wholesale portal.', 'licensesender' ) );
		$order->save();

		return $order;
	}
}
