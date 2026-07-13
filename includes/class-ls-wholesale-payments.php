<?php
/**
 * Wholesale checkout payment method restrictions + TeraWallet smart routing.
 *
 * Behaviour when TeraWallet is active on a wholesale checkout:
 * - Enough balance  → wallet gateway only (full pay via wallet)
 * - Partial balance → auto-apply wallet partial + other active methods for the rest
 * - No balance      → hide wallet, show other active methods
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Wholesale_Payments {

	/** @var bool Prevent recursive cart calculate loops. */
	private static $syncing_partial = false;

	public static function init() {
		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'filter_gateways' ), 100 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'sync_wallet_partial_payment' ), 5 );
		add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'sync_wallet_partial_payment' ), 20 );
		add_action( 'woocommerce_checkout_init', array( __CLASS__, 'sync_wallet_partial_payment' ), 5 );
		add_filter( 'is_enable_wallet_partial_payment', array( __CLASS__, 'force_partial_payment_when_needed' ), 20 );
		add_filter( 'woocommerce_default_gateway', array( __CLASS__, 'prefer_wallet_gateway' ), 20 );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'maybe_render_wallet_notice' ), 5 );
	}

	/**
	 * Whether wholesale payment rules should apply on the current request.
	 */
	public static function should_filter_gateways() {
		if ( ! LS_Wholesale::is_enabled() ) {
			return false;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}

		if ( ! is_user_logged_in() || ! LS_Wholesale::user_is_wholesale() ) {
			return false;
		}

		// Apply when the cart includes wholesale items (including mixed carts).
		if ( LS_Wholesale_Catalog::cart_has_wholesale_product() ) {
			return true;
		}

		return self::is_paying_wholesale_order();
	}

	/**
	 * Whether smart TeraWallet routing should run.
	 */
	public static function should_use_smart_wallet() {
		if ( ! self::should_filter_gateways() ) {
			return false;
		}

		if ( ! LS_Wholesale::is_terawallet_active() ) {
			return false;
		}

		$mode = LS_Wholesale::get_payment_mode();
		// Smart wallet applies for all/wallet modes. Custom mode keeps explicit allow-list,
		// with wallet preferred only when wallet is among the selected gateways.
		if ( LS_Wholesale::PAYMENT_MODE_CUSTOM === $mode ) {
			$allowed = LS_Wholesale::get_custom_payment_gateways();
			$wallets = LS_Wholesale::get_wallet_gateway_ids();
			if ( empty( array_intersect( $allowed, $wallets ) ) ) {
				return false;
			}
		}

		/**
		 * Filter whether wholesale smart wallet routing is enabled.
		 *
		 * @param bool $enabled Whether smart wallet is enabled.
		 */
		return (bool) apply_filters( 'ls_wholesale_smart_wallet_enabled', true );
	}

	/**
	 * Whether the customer is paying a wholesale order (order-pay page).
	 */
	private static function is_paying_wholesale_order() {
		if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-pay' ) ) {
			return false;
		}

		$order_id = absint( get_query_var( 'order-pay' ) );
		if ( ! $order_id ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || ! LS_Wholesale_Orders::is_wholesale_order( $order ) ) {
			return false;
		}

		$customer_id = (int) $order->get_customer_id();
		if ( $customer_id && get_current_user_id() !== $customer_id && ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Cart/order total used for wallet comparisons (excludes wallet partial fee when possible).
	 *
	 * @return float
	 */
	public static function get_payable_total() {
		$order_id = absint( get_query_var( 'order-pay' ) );
		if ( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof WC_Order ) {
				$paid_via_wallet = function_exists( 'get_order_partial_payment_amount' )
					? (float) get_order_partial_payment_amount( $order_id )
					: 0.0;
				return max( 0, (float) $order->get_total( 'edit' ) + $paid_via_wallet );
			}
		}

		if ( function_exists( 'get_woowallet_cart_total' ) ) {
			return (float) get_woowallet_cart_total();
		}

		if ( function_exists( 'WC' ) && WC()->cart ) {
			return (float) WC()->cart->get_total( 'edit' );
		}

		return 0.0;
	}

	/**
	 * Raw wallet balance for the current user.
	 *
	 * @return float
	 */
	public static function get_wallet_balance_amount() {
		$balance = LS_Wholesale::get_user_wallet_balance();
		return is_array( $balance ) ? (float) $balance['amount'] : 0.0;
	}

	/**
	 * Smart wallet checkout state.
	 *
	 * @return string one of: none|full|partial|unavailable
	 */
	public static function get_wallet_payment_state() {
		if ( ! LS_Wholesale::is_terawallet_active() ) {
			return 'unavailable';
		}

		$balance = self::get_wallet_balance_amount();
		$total   = self::get_payable_total();

		if ( $balance <= 0 ) {
			return 'none';
		}

		if ( $total <= 0 ) {
			return 'full';
		}

		if ( $balance + 0.00001 >= $total ) {
			return 'full';
		}

		return 'partial';
	}

	/**
	 * Sync TeraWallet partial-payment session for wholesale checkouts.
	 */
	public static function sync_wallet_partial_payment() {
		if ( self::$syncing_partial || ! self::should_use_smart_wallet() ) {
			return;
		}

		if ( ! function_exists( 'update_wallet_partial_payment_session' ) ) {
			return;
		}

		self::$syncing_partial = true;

		$state     = self::get_wallet_payment_state();
		$balance   = self::get_wallet_balance_amount();
		$desired   = ( 'partial' === $state ) ? $balance : 0.0;
		$current   = ( ! is_null( WC()->session ) ) ? (float) WC()->session->get( 'partial_payment_amount', 0 ) : 0.0;

		if ( abs( $current - $desired ) > 0.00001 ) {
			update_wallet_partial_payment_session( $desired );
		}

		self::$syncing_partial = false;
	}

	/**
	 * Force TeraWallet partial payment on when wholesale smart routing needs it.
	 *
	 * @param bool $enabled Current TeraWallet value.
	 * @return bool
	 */
	public static function force_partial_payment_when_needed( $enabled ) {
		if ( ! self::should_use_smart_wallet() ) {
			return $enabled;
		}

		return 'partial' === self::get_wallet_payment_state() ? true : $enabled;
	}

	/**
	 * Prefer wallet as the default gateway when balance covers the order.
	 *
	 * @param string $default Default gateway ID.
	 * @return string
	 */
	public static function prefer_wallet_gateway( $default ) {
		if ( ! self::should_use_smart_wallet() || 'full' !== self::get_wallet_payment_state() ) {
			return $default;
		}

		$available = WC()->payment_gateways() ? WC()->payment_gateways()->get_available_payment_gateways() : array();
		foreach ( LS_Wholesale::get_wallet_gateway_ids( $available ) as $gateway_id ) {
			if ( isset( $available[ $gateway_id ] ) ) {
				return $gateway_id;
			}
		}

		return $default;
	}

	/**
	 * Checkout notice explaining how wallet is being applied.
	 */
	public static function maybe_render_wallet_notice() {
		if ( ! self::should_use_smart_wallet() ) {
			return;
		}

		$state   = self::get_wallet_payment_state();
		$balance = LS_Wholesale::get_user_wallet_balance();
		if ( ! is_array( $balance ) ) {
			return;
		}

		if ( 'full' === $state ) {
			wc_print_notice(
				sprintf(
					/* translators: %s: formatted wallet balance */
					__( 'Your wallet balance of %s will be used to pay this wholesale order in full.', 'licensesender' ),
					wp_strip_all_tags( $balance['formatted'] )
				),
				'notice'
			);
			return;
		}

		if ( 'partial' === $state ) {
			$total     = self::get_payable_total();
			$remaining = max( 0, $total - (float) $balance['amount'] );
			wc_print_notice(
				sprintf(
					/* translators: 1: wallet amount applied, 2: remaining amount */
					__( 'Wallet credit of %1$s will be applied. Pay the remaining %2$s with another payment method.', 'licensesender' ),
					wp_strip_all_tags( $balance['formatted'] ),
					wp_strip_all_tags( function_exists( 'wc_price' ) ? wc_price( $remaining ) : (string) $remaining )
				),
				'notice'
			);
		}
	}

	/**
	 * Gateways allowed by wholesale payment mode (before smart wallet).
	 *
	 * @param array<string, WC_Payment_Gateway> $gateways Available gateways.
	 * @return array<string, WC_Payment_Gateway>
	 */
	private static function apply_mode_filter( $gateways ) {
		$mode = LS_Wholesale::get_payment_mode();

		if ( LS_Wholesale::PAYMENT_MODE_ALL === $mode ) {
			return $gateways;
		}

		$filtered = array();

		if ( LS_Wholesale::PAYMENT_MODE_WALLET === $mode ) {
			// Wallet-preferred mode: keep the full gateway pool so partial/zero
			// balance orders can still fall back to other active methods.
			return $gateways;
		}

		if ( LS_Wholesale::PAYMENT_MODE_CUSTOM === $mode ) {
			$allowed = LS_Wholesale::get_custom_payment_gateways();
			if ( empty( $allowed ) ) {
				return array();
			}

			foreach ( $allowed as $gateway_id ) {
				if ( isset( $gateways[ $gateway_id ] ) ) {
					$filtered[ $gateway_id ] = $gateways[ $gateway_id ];
				}
			}

			return $filtered;
		}

		return $gateways;
	}

	/**
	 * Apply smart TeraWallet routing on top of the mode-filtered pool.
	 *
	 * @param array<string, WC_Payment_Gateway> $gateways Mode-filtered gateways.
	 * @param array<string, WC_Payment_Gateway> $original Original available gateways.
	 * @return array<string, WC_Payment_Gateway>
	 */
	private static function apply_smart_wallet( $gateways, $original ) {
		$pool       = ! empty( $gateways ) ? $gateways : $original;
		$wallet_ids = LS_Wholesale::get_wallet_gateway_ids( $original );
		$state      = self::get_wallet_payment_state();

		if ( 'unavailable' === $state ) {
			return $gateways;
		}

		if ( 'full' === $state ) {
			$wallet_only = array();
			foreach ( $wallet_ids as $gateway_id ) {
				// Respect mode-filtered pool when selecting wallet-only.
				if ( isset( $pool[ $gateway_id ] ) ) {
					$wallet_only[ $gateway_id ] = $pool[ $gateway_id ];
				} elseif ( isset( $original[ $gateway_id ] ) && LS_Wholesale::PAYMENT_MODE_WALLET === LS_Wholesale::get_payment_mode() ) {
					$wallet_only[ $gateway_id ] = $original[ $gateway_id ];
				}
			}

			return ! empty( $wallet_only ) ? $wallet_only : $gateways;
		}

		foreach ( $wallet_ids as $gateway_id ) {
			unset( $gateways[ $gateway_id ] );
		}

		return $gateways;
	}

	/**
	 * Limit / route payment gateways for wholesale carts.
	 *
	 * @param array<string, WC_Payment_Gateway> $gateways Available gateways.
	 * @return array<string, WC_Payment_Gateway>
	 */
	public static function filter_gateways( $gateways ) {
		if ( ! self::should_filter_gateways() || empty( $gateways ) || ! is_array( $gateways ) ) {
			return $gateways;
		}

		$mode     = LS_Wholesale::get_payment_mode();
		$original = $gateways;

		// Legacy: without TeraWallet, keep previous wallet-only behaviour.
		if ( ! LS_Wholesale::is_terawallet_active() ) {
			if ( LS_Wholesale::PAYMENT_MODE_ALL === $mode ) {
				return $gateways;
			}

			$filtered = self::apply_mode_filter( $gateways );
			if ( LS_Wholesale::PAYMENT_MODE_WALLET === $mode ) {
				$filtered = array();
				foreach ( LS_Wholesale::get_wallet_gateway_ids( $original ) as $gateway_id ) {
					if ( isset( $original[ $gateway_id ] ) ) {
						$filtered[ $gateway_id ] = $original[ $gateway_id ];
					}
				}
			}

			return apply_filters( 'ls_wholesale_filtered_payment_gateways', $filtered, $original, $mode );
		}

		self::sync_wallet_partial_payment();

		$filtered = self::apply_mode_filter( $gateways );
		$filtered = self::apply_smart_wallet( $filtered, $original );

		/**
		 * Filter wholesale checkout payment gateways after restriction.
		 *
		 * @param array<string, WC_Payment_Gateway> $filtered Restricted gateways.
		 * @param array<string, WC_Payment_Gateway> $original Original gateways.
		 * @param string                            $mode     Payment mode.
		 */
		return apply_filters( 'ls_wholesale_filtered_payment_gateways', $filtered, $original, $mode );
	}
}

LS_Wholesale_Payments::init();
