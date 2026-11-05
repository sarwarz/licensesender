<?php

namespace LicenseSender\Wholesale;

defined( 'ABSPATH' ) || exit;

class Orders {

	const ORDER_META = '_ls_wholesale_order';
	const TIER_META  = '_ls_wholesale_tier';
	const GROUP_META = '_ls_wholesale_group';
	const HOLD_META  = '_ls_wholesale_hold';

	/** Hold length once an order is paid or awaiting manual payment. */
	const PAID_HOLD_MINUTES = 1440;

	public static function init() {
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'mark_order_on_checkout' ), 20, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'mark_order_on_block_checkout' ), 20, 1 );
		add_filter( 'woocommerce_can_reduce_order_stock', array( __CLASS__, 'filter_can_reduce_order_stock' ), 10, 2 );

		// Hold keys before payment so nobody else can take them; a shortage stops checkout.
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'hold_on_checkout' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'hold_on_block_checkout' ), 25, 1 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'extend_hold' ), 10, 1 );
		add_action( 'woocommerce_order_status_on-hold', array( __CLASS__, 'extend_hold' ), 10, 1 );
		foreach ( array( 'cancelled', 'failed', 'refunded' ) as $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( __CLASS__, 'release_hold' ), 10, 1 );
		}

		// Unpaid wholesale orders are cancelled when their key hold ends (WooCommerce only does
		// this for its own checkout orders, and only with stock management on).
		add_action( self::CANCEL_HOOK, array( __CLASS__, 'cancel_if_unpaid' ), 10, 1 );
	}

	const CANCEL_HOOK = 'ls_wholesale_cancel_unpaid_order';

	/**
	 * Cancel an unpaid order once its hold has run out (plus a short grace period).
	 *
	 * @param \WC_Order $order   Order.
	 * @param int       $minutes Hold length.
	 */
	private static function schedule_cancel( \WC_Order $order, $minutes ) {
		/**
		 * Whether unpaid wholesale orders are cancelled when their key hold ends.
		 *
		 * @param bool      $cancel Default true.
		 * @param \WC_Order $order  Order.
		 */
		if ( ! $order->has_status( 'pending' ) || ! apply_filters( 'ls_wholesale_cancel_unpaid_orders', true, $order ) ) {
			return;
		}

		$args = array( 'order_id' => (int) $order->get_id() );
		$when = time() + ( (int) $minutes + 2 ) * MINUTE_IN_SECONDS;

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_unschedule_all_actions( self::CANCEL_HOOK, $args, 'licensesender' );
			as_schedule_single_action( $when, self::CANCEL_HOOK, $args, 'licensesender' );
			return;
		}

		wp_clear_scheduled_hook( self::CANCEL_HOOK, array( $args['order_id'] ) );
		wp_schedule_single_event( $when, self::CANCEL_HOOK, array( $args['order_id'] ) );
	}

	/**
	 * @param int $order_id Order ID.
	 */
	public static function cancel_if_unpaid( $order_id ) {
		$order = wc_get_order( absint( $order_id ) );
		if ( ! $order || ! $order->has_status( 'pending' ) || $order->is_paid() || $order->get_meta( self::ORDER_META, true ) !== 'yes' ) {
			return;
		}

		// Cancelling also frees any keys still held (release_hold).
		$order->update_status( 'cancelled', __( 'Unpaid wholesale order cancelled: the key hold ran out before payment.', 'licensesender' ) );
	}

	/**
	 * Wholesale quantities per SKU in an order.
	 *
	 * @return array<int, array{sku:string, quantity:int}>
	 */
	public static function hold_items( \WC_Order $order ) {
		$by_sku = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product || $item->get_meta( '_ls_wholesale_line', true ) !== 'yes' ) {
				continue;
			}
			$sku = (string) $item->get_meta( '_ls_wholesale_sku', true );
			if ( $sku === '' ) {
				$product = $item->get_product();
				$sku     = $product ? (string) get_post_meta( $product->get_id(), '_ls_mapped_product', true ) : '';
			}
			if ( $sku !== '' ) {
				$by_sku[ $sku ] = ( $by_sku[ $sku ] ?? 0 ) + (int) $item->get_quantity();
			}
		}

		$items = array();
		foreach ( $by_sku as $sku => $qty ) {
			$items[] = array( 'sku' => (string) $sku, 'quantity' => (int) $qty );
		}

		return $items;
	}

	/**
	 * Hold the order's keys in LicenseSender.
	 *
	 * @param \WC_Order $order   Order.
	 * @param int       $minutes Hold length.
	 * @return true|\WP_Error WP_Error only when keys ran out (checkout should stop).
	 */
	public static function hold_stock( \WC_Order $order, $minutes ) {
		$items = self::hold_items( $order );
		if ( empty( $items ) || ! Wholesale::is_enabled() ) {
			return true;
		}

		self::schedule_cancel( $order, $minutes );

		$result = \LicenseSender\Core\Api::reserve_wholesale_stock( (string) $order->get_id(), $items, $minutes );

		if ( ! empty( $result['success'] ) ) {
			$order->update_meta_data( self::HOLD_META, 'held' );
			$order->save_meta_data();
			return true;
		}

		if ( 409 === (int) $result['http_code'] && Wholesale::allows_backorders() ) {
			// Backorders allowed: take the order anyway; keys go out once restocked.
			$order->add_order_note( __( 'Not enough wholesale keys in stock to hold this order. It was accepted as a backorder (allowed in wholesale settings).', 'licensesender' ) );
			return true;
		}

		if ( 409 === (int) $result['http_code'] ) {
			$short = array();
			foreach ( $result['lines'] as $line ) {
				if ( empty( $line['ok'] ) ) {
					$short[] = sprintf(
						/* translators: 1: SKU, 2: keys available */
						__( '%1$s (%2$d available)', 'licensesender' ),
						(string) ( $line['sku'] ?? '' ),
						(int) ( $line['available'] ?? 0 )
					);
				}
			}

			return new \WP_Error(
				'ls_wholesale_out_of_stock',
				sprintf(
					/* translators: %s: products with too few keys */
					__( 'Some wholesale products just ran low on stock: %s. Please update your cart and try again.', 'licensesender' ),
					implode( ', ', $short )
				)
			);
		}

		// LicenseSender unreachable or the plan lacks holds: don't block the sale, just note it.
		$order->add_order_note(
			sprintf(
				/* translators: %s: error message */
				__( 'LicenseSender could not hold wholesale keys for this order: %s', 'licensesender' ),
				$result['message'] !== '' ? $result['message'] : __( 'no response', 'licensesender' )
			)
		);

		return true;
	}

	/**
	 * Classic checkout: the order exists but payment hasn't run. Throwing here makes
	 * WooCommerce show the message and stop.
	 *
	 * @param \WC_Order $order Order.
	 * @throws \Exception When keys ran out.
	 */
	public static function hold_on_checkout( $order ) {
		if ( ! $order instanceof \WC_Order || ! self::order_qualifies_as_wholesale( $order ) ) {
			return;
		}

		$held = self::hold_stock( $order, Wholesale::get_hold_minutes() );
		if ( is_wp_error( $held ) ) {
			throw new \Exception( esc_html( $held->get_error_message() ) );
		}
	}

	/**
	 * Block checkout: same as classic, reported through the Store API.
	 *
	 * @param \WC_Order $order Order.
	 * @throws \Exception When keys ran out.
	 */
	public static function hold_on_block_checkout( $order ) {
		if ( ! $order instanceof \WC_Order || ! self::order_qualifies_as_wholesale( $order ) ) {
			return;
		}

		$held = self::hold_stock( $order, Wholesale::get_hold_minutes() );
		if ( ! is_wp_error( $held ) ) {
			return;
		}

		if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'ls_wholesale_out_of_stock', esc_html( $held->get_error_message() ), 409 );
		}
		throw new \Exception( esc_html( $held->get_error_message() ) );
	}

	/**
	 * Paid (or awaiting a manual payment): keep the keys until they are delivered.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function extend_hold( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( self::HOLD_META, true ) !== 'held' ) {
			return;
		}

		$held = self::hold_stock( $order, self::PAID_HOLD_MINUTES );
		if ( is_wp_error( $held ) ) {
			$order->add_order_note( __( 'Wholesale keys could not be held after payment because stock ran low. Check stock before completing this order.', 'licensesender' ) );
		}
	}

	/**
	 * Cancelled, failed or refunded: free the keys for other buyers.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function release_hold( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( self::HOLD_META, true ) !== 'held' ) {
			return;
		}

		$result = \LicenseSender\Core\Api::release_wholesale_stock( (string) $order->get_id() );
		if ( ! empty( $result['success'] ) ) {
			$order->update_meta_data( self::HOLD_META, 'released' );
			$order->save_meta_data();
		}
	}

	/**
	 * Wholesale inventory lives on LicenseSender — do not deplete the mapped retail WC stock.
	 *
	 * @param bool     $reduce_stock Whether to reduce.
	 * @param WC_Order $order        Order.
	 * @return bool
	 */
	public static function filter_can_reduce_order_stock( $reduce_stock, $order ) {
		if ( $order instanceof \WC_Order && self::is_wholesale_order( $order ) ) {
			return false;
		}

		return $reduce_stock;
	}

	/**
	 * Whether an order contains at least one wholesale catalog product.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public static function order_has_wholesale_product( \WC_Order $order ) {
		foreach ( $order->get_items() as $item ) {
			if ( $item->get_meta( '_ls_wholesale_line', true ) === 'yes' ) {
				return true;
			}

			$product = $item->get_product();
			if ( $product && Catalog::is_wholesale_product( $product ) ) {
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
	public static function order_qualifies_as_wholesale( \WC_Order $order ) {
		$customer_id = (int) $order->get_customer_id();
		if ( ! $customer_id || ! Wholesale::user_is_wholesale( $customer_id ) ) {
			return false;
		}

		return self::order_has_wholesale_product( $order );
	}

	/**
	 * Whether an order is a wholesale order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public static function is_wholesale_order( \WC_Order $order ) {
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
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		if ( ! self::order_qualifies_as_wholesale( $order ) ) {
			return;
		}

		$order->update_meta_data( self::ORDER_META, 'yes' );
		$order->update_meta_data( self::GROUP_META, Wholesale::get_user_group( (int) $order->get_customer_id() ) );

		$catalog = Wholesale::get_catalog();
		if ( ! empty( $catalog['tier'] ) ) {
			$order->update_meta_data( self::TIER_META, sanitize_text_field( (string) $catalog['tier'] ) );
		}

		$order->save();
	}
}
