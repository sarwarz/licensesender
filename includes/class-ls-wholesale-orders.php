<?php
/**
 * Wholesale order identification and checkout metadata.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Wholesale_Orders {

	const ORDER_META = '_ls_wholesale_order';
	const TIER_META  = '_ls_wholesale_tier';

	public static function init() {
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'mark_order_on_checkout' ), 20, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'mark_order_on_block_checkout' ), 20, 1 );
		add_filter( 'woocommerce_can_reduce_order_stock', array( __CLASS__, 'filter_can_reduce_order_stock' ), 10, 2 );
	}

	/**
	 * Wholesale inventory lives on LicenseSender — do not deplete the mapped retail WC stock.
	 *
	 * @param bool     $reduce_stock Whether to reduce.
	 * @param WC_Order $order        Order.
	 * @return bool
	 */
	public static function filter_can_reduce_order_stock( $reduce_stock, $order ) {
		if ( $order instanceof WC_Order && self::is_wholesale_order( $order ) ) {
			return false;
		}

		return $reduce_stock;
	}

	/**
	 * Whether an order contains at least one wholesale catalog product.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public static function order_has_wholesale_product( WC_Order $order ) {
		foreach ( $order->get_items() as $item ) {
			if ( $item->get_meta( '_ls_wholesale_line', true ) === 'yes' ) {
				return true;
			}

			$product = $item->get_product();
			if ( $product && LS_Wholesale_Catalog::is_wholesale_product( $product ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether an order qualifies as wholesale at checkout time.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public static function order_qualifies_as_wholesale( WC_Order $order ) {
		$customer_id = (int) $order->get_customer_id();
		if ( ! $customer_id || ! LS_Wholesale::user_is_wholesale( $customer_id ) ) {
			return false;
		}

		return self::order_has_wholesale_product( $order );
	}

	/**
	 * Whether an order is a wholesale order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public static function is_wholesale_order( WC_Order $order ) {
		if ( $order->get_meta( self::ORDER_META, true ) === 'yes' ) {
			return true;
		}

		return self::order_qualifies_as_wholesale( $order );
	}

	/**
	 * Persist wholesale metadata on classic checkout orders.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $data  Checkout data.
	 */
	public static function mark_order_on_checkout( $order, $data ) {
		unset( $data );

		self::maybe_mark_order( $order );
	}

	/**
	 * Persist wholesale metadata on block checkout orders.
	 *
	 * @param WC_Order $order Order object.
	 */
	public static function mark_order_on_block_checkout( $order ) {
		self::maybe_mark_order( $order );
	}

	/**
	 * @param WC_Order $order Order object.
	 */
	private static function maybe_mark_order( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( ! self::order_qualifies_as_wholesale( $order ) ) {
			return;
		}

		$order->update_meta_data( self::ORDER_META, 'yes' );

		$catalog = LS_Wholesale::get_catalog();
		if ( ! empty( $catalog['tier'] ) ) {
			$order->update_meta_data( self::TIER_META, sanitize_text_field( (string) $catalog['tier'] ) );
		}

		$order->save();
	}
}

LS_Wholesale_Orders::init();
