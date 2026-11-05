<?php

namespace LicenseSender\Wholesale;

use LicenseSender\Core\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Wholesale cart lines.
 *
 * A wholesale line is a normal mapped WooCommerce product added with a wholesale flag, so
 * the same product can sit in a retail cart at its retail price. Only flagged lines are
 * repriced, from a LicenseSender quote for the buyer's group; nothing else in the store
 * changes. LicenseSender is the stock authority for these lines, so WooCommerce stock
 * checks are skipped for them (keys are held at checkout instead).
 */
class Cart {

	const QUOTE_SESSION = 'ls_wholesale_quote';

	/** Seconds a quote is reused before asking LicenseSender again. */
	const QUOTE_TTL = 120;

	/**
	 * Product objects that belong to wholesale cart lines (WooCommerce gives each line its
	 * own product instance, so the retail product elsewhere is unaffected).
	 *
	 * @var \SplObjectStorage<\WC_Product, true>|null
	 */
	private static $line_products = null;

	/** @var bool True while our AJAX handler adds a line (product object not in a line yet). */
	private static $adding = false;

	/** @var array<string, mixed>|null Quote memo for this request. */
	private static $quote = null;

	public static function init() {
		add_filter( 'woocommerce_add_cart_item', array( __CLASS__, 'track_new_line' ), 10, 1 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'restore_cart_item_from_session' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'persist_order_line_meta' ), 10, 4 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_cart_pricing' ), 20 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'validate_cart_items' ) );
		add_filter( 'woocommerce_store_api_cart_errors', array( __CLASS__, 'validate_cart_items_blocks' ), 10, 2 );

		add_action( 'wp_ajax_ls_wholesale_add_to_cart', array( __CLASS__, 'ajax_add_to_cart' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'cart_count_fragment' ) );

		// Stock: LicenseSender decides for wholesale lines.
		add_filter( 'woocommerce_product_is_in_stock', array( __CLASS__, 'filter_is_in_stock' ), 99, 2 );
		add_filter( 'woocommerce_product_has_enough_stock', array( __CLASS__, 'filter_is_in_stock' ), 99, 2 );
		add_filter( 'woocommerce_product_get_manage_stock', array( __CLASS__, 'filter_manage_stock' ), 99, 2 );
		add_filter( 'woocommerce_product_variation_get_manage_stock', array( __CLASS__, 'filter_manage_stock' ), 99, 2 );

		// Wholesale prices are already the deal: no coupons on top.
		add_filter( 'woocommerce_coupons_enabled', array( __CLASS__, 'filter_coupons_enabled' ), 99 );
		add_filter( 'woocommerce_coupon_is_valid', array( __CLASS__, 'block_coupon_on_wholesale_cart' ), 99, 2 );

		// Block cart: keep quantity steppers within each product's limits and stock.
		add_filter( 'woocommerce_store_api_product_quantity_minimum', array( __CLASS__, 'filter_store_api_min_qty' ), 20, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_maximum', array( __CLASS__, 'filter_store_api_max_qty' ), 20, 3 );
	}

	/**
	 * Coupons on a wholesale cart (off unless a site opts in).
	 */
	private static function allows_coupons() {
		/**
		 * Whether coupons can be used on carts with wholesale lines.
		 *
		 * @param bool $allowed Default false.
		 */
		return (bool) apply_filters( 'ls_wholesale_allow_coupons', false );
	}

	public static function filter_coupons_enabled( $enabled ) {
		if ( ! $enabled || self::allows_coupons() || ! did_action( 'woocommerce_cart_loaded_from_session' ) ) {
			return $enabled;
		}

		return Catalog::cart_has_wholesale_product() ? false : $enabled;
	}

	/**
	 * @param bool       $valid  Whether the coupon is valid.
	 * @param \WC_Coupon $coupon Coupon.
	 * @throws \Exception When the cart has wholesale lines.
	 */
	public static function block_coupon_on_wholesale_cart( $valid, $coupon ) {
		unset( $coupon );
		if ( $valid && ! self::allows_coupons() && Catalog::cart_has_wholesale_product() ) {
			throw new \Exception( esc_html__( 'Coupons cannot be used with wholesale prices.', 'licensesender' ) );
		}

		return $valid;
	}

	/**
	 * Catalog entry behind a block-cart line, or null for retail lines.
	 *
	 * @param array|null $cart_item Cart item.
	 * @return array<string, mixed>|null
	 */
	private static function catalog_item_for_line( $cart_item ) {
		if ( ! is_array( $cart_item ) || ! Catalog::is_wholesale_cart_item( $cart_item ) ) {
			return null;
		}
		$sku = Catalog::get_cart_item_wholesale_sku( $cart_item );

		return $sku ? Wholesale::get_catalog_product_by_sku( $sku ) : null;
	}

	public static function filter_store_api_min_qty( $value, $product, $cart_item = null ) {
		unset( $product );
		$item = self::catalog_item_for_line( $cart_item );

		return $item ? max( (int) $value, (int) ( $item['min_qty'] ?? 1 ) ) : $value;
	}

	public static function filter_store_api_max_qty( $value, $product, $cart_item = null ) {
		unset( $product );
		$item = self::catalog_item_for_line( $cart_item );
		if ( ! $item ) {
			return $value;
		}

		$caps = array( (int) $value );
		if ( ! empty( $item['max_qty'] ) ) {
			$caps[] = (int) $item['max_qty'];
		}
		if ( Wholesale::enforces_stock() ) {
			$caps[] = max( 1, (int) $item['available_stock'] );
		}

		return min( array_filter( $caps ) ?: array( $value ) );
	}

	/* ---------------------------------------------------------------------
	 * Line tracking and stock
	 * ------------------------------------------------------------------ */

	private static function track( $cart_item ) {
		if ( ! Catalog::is_wholesale_cart_item( $cart_item ) || empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof \WC_Product ) {
			return;
		}
		if ( null === self::$line_products ) {
			self::$line_products = new \SplObjectStorage();
		}
		self::$line_products[ $cart_item['data'] ] = true;
	}

	private static function is_line_product( $product ) {
		return self::$adding || ( null !== self::$line_products && is_object( $product ) && self::$line_products->contains( $product ) );
	}

	/**
	 * @param array $cart_item New cart item.
	 * @return array
	 */
	public static function track_new_line( $cart_item ) {
		self::track( $cart_item );
		return $cart_item;
	}

	/**
	 * Keep the wholesale flag across sessions and track the restored line.
	 *
	 * @param array $cart_item Cart item.
	 * @param array $values    Session values.
	 * @return array
	 */
	public static function restore_cart_item_from_session( $cart_item, $values ) {
		if ( ! empty( $values[ Catalog::CART_FLAG ] ) ) {
			$cart_item[ Catalog::CART_FLAG ] = $values[ Catalog::CART_FLAG ];
		}
		if ( ! empty( $values[ Catalog::CART_SKU ] ) ) {
			$cart_item[ Catalog::CART_SKU ] = $values[ Catalog::CART_SKU ];
		}

		self::track( $cart_item );

		return $cart_item;
	}

	/**
	 * @param bool       $in_stock Whether in stock / has enough stock.
	 * @param WC_Product $product  Product.
	 * @return bool
	 */
	public static function filter_is_in_stock( $in_stock, $product ) {
		return self::is_line_product( $product ) ? true : $in_stock;
	}

	/**
	 * @param bool       $manage  Whether WooCommerce manages stock.
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public static function filter_manage_stock( $manage, $product ) {
		return self::is_line_product( $product ) ? false : $manage;
	}

	/* ---------------------------------------------------------------------
	 * Quote and pricing
	 * ------------------------------------------------------------------ */

	/**
	 * Wholesale quantities in the cart per SKU.
	 *
	 * @return array<string, int>
	 */
	public static function get_cart_wholesale_items() {
		$items = array();
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $items;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! Catalog::is_wholesale_cart_item( $cart_item ) ) {
				continue;
			}
			$sku = Catalog::get_cart_item_wholesale_sku( $cart_item );
			if ( $sku !== '' ) {
				$items[ $sku ] = ( $items[ $sku ] ?? 0 ) + max( 0, (int) ( $cart_item['quantity'] ?? 0 ) );
			}
		}

		return array_filter( $items );
	}

	/**
	 * LicenseSender's quote for the current wholesale cart: prices for the buyer's group,
	 * stock and minimum-order checks. Cached in the session for QUOTE_TTL seconds.
	 *
	 * @return array<string, mixed>|null Null when the cart has no wholesale lines.
	 */
	public static function get_quote( $force = false ) {
		$items = self::get_cart_wholesale_items();
		if ( empty( $items ) ) {
			return null;
		}

		$group   = Wholesale::get_user_group();
		$session = WC()->session ?? null;
		// An unpaid order from an earlier attempt holds keys for this same cart: count them as ours.
		$reference = $session ? (string) ( $session->get( 'order_awaiting_payment' ) ?: $session->get( 'store_api_draft_order' ) ?: '' ) : '';
		$key       = md5( wp_json_encode( array( $items, $group, $reference, (int) get_option( 'ls_wholesale_catalog_version', 1 ) ) ) );

		if ( ! $force && is_array( self::$quote ) && self::$quote['key'] === $key ) {
			return self::$quote['quote'];
		}

		$cached = $session ? $session->get( self::QUOTE_SESSION ) : null;
		if ( ! $force && is_array( $cached ) && ( $cached['key'] ?? '' ) === $key && (int) ( $cached['at'] ?? 0 ) > time() - self::QUOTE_TTL ) {
			self::$quote = $cached;
			return $cached['quote'];
		}

		$lines = array();
		foreach ( $items as $sku => $qty ) {
			$lines[] = array( 'sku' => (string) $sku, 'quantity' => (int) $qty );
		}

		$quote       = Api::quote_wholesale_cart( $lines, $group, $reference );
		self::$quote = array( 'key' => $key, 'at' => time(), 'quote' => $quote );
		if ( $session && ! empty( $quote['success'] ) ) {
			$session->set( self::QUOTE_SESSION, self::$quote );
		}

		return $quote;
	}

	/**
	 * Quote lines by SKU.
	 *
	 * @param array<string, mixed>|null $quote Quote.
	 * @return array<string, array<string, mixed>>
	 */
	public static function quote_lines( $quote ) {
		$lines = array();
		foreach ( (array) ( $quote['lines'] ?? array() ) as $line ) {
			if ( is_array( $line ) && isset( $line['sku'] ) ) {
				$lines[ (string) $line['sku'] ] = $line;
			}
		}

		return $lines;
	}

	/**
	 * Set each wholesale line's unit price. Uses the quote; if LicenseSender can't be
	 * reached, falls back to the cached catalog tiers for the buyer's group.
	 *
	 * @param \WC_Cart $cart Cart.
	 */
	public static function apply_cart_pricing( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! $cart instanceof \WC_Cart || ! Wholesale::is_enabled() || ! Wholesale::user_is_wholesale() ) {
			return;
		}

		$quote   = self::get_quote();
		$lines   = ! empty( $quote['success'] ) ? self::quote_lines( $quote ) : array();
		$items   = self::get_cart_wholesale_items();
		$catalog = null;

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! Catalog::is_wholesale_cart_item( $cart_item ) || empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof \WC_Product ) {
				continue;
			}

			self::track( $cart_item );
			$sku  = Catalog::get_cart_item_wholesale_sku( $cart_item );
			$unit = isset( $lines[ $sku ]['unit_price'] ) ? (float) $lines[ $sku ]['unit_price'] : null;

			if ( null === $unit ) {
				$catalog = $catalog ?? Wholesale::get_catalog();
				$item    = $sku ? Wholesale::get_catalog_product_by_sku( $sku, $catalog ) : null;
				if ( ! $item ) {
					continue;
				}
				// Tier by the SKU's total quantity, like the quote does.
				$unit = Wholesale::get_price_for_quantity( $item, (int) ( $items[ $sku ] ?? $cart_item['quantity'] ) );
			}

			// Plain price: no "was $10, save $8" against the retail price.
			$cart_item['data']->set_price( $unit );
			$cart_item['data']->set_regular_price( $unit );
			$cart_item['data']->set_sale_price( '' );
		}
	}

	/* ---------------------------------------------------------------------
	 * Validation
	 * ------------------------------------------------------------------ */

	/**
	 * Validate wholesale cart items on classic cart/checkout.
	 */
	public static function validate_cart_items() {
		foreach ( self::get_cart_validation_errors() as $message ) {
			wc_add_notice( $message, 'error' );
		}
	}

	/**
	 * Validate wholesale cart items for block cart and checkout.
	 *
	 * @param WP_Error $errors Cart errors.
	 * @param WC_Cart  $cart   Cart object.
	 * @return WP_Error
	 */
	public static function validate_cart_items_blocks( $errors, $cart ) {
		unset( $cart );

		foreach ( self::get_cart_validation_errors() as $index => $message ) {
			$errors->add( 'ls_wholesale_cart_' . $index, $message );
		}

		return $errors;
	}

	/**
	 * Problems that block checkout of a wholesale cart.
	 *
	 * @param bool $force_refresh Ask LicenseSender for a fresh quote.
	 * @return string[]
	 */
	public static function get_cart_validation_errors( $force_refresh = false ) {
		$messages = array();

		if ( ! Wholesale::is_enabled() || ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() || ! Catalog::cart_has_wholesale_product() ) {
			return $messages;
		}

		if ( ! is_user_logged_in() || ! Wholesale::user_is_wholesale() ) {
			$messages[] = __( 'Wholesale access is required to purchase wholesale products in your cart.', 'licensesender' );
			return $messages;
		}

		/**
		 * Whether mixed wholesale + retail carts are allowed.
		 *
		 * @param bool $allowed Default false.
		 */
		$allow_mixed = (bool) apply_filters( 'ls_wholesale_allow_mixed_cart', false );
		if ( ! $allow_mixed && ! Catalog::cart_has_only_wholesale_products() ) {
			$messages[] = __( 'Wholesale products cannot be checked out with regular products. Please remove one type and try again.', 'licensesender' );
		}

		$catalog = Wholesale::get_catalog();
		$names   = self::cart_line_names();
		$quote   = self::get_quote( $force_refresh );

		if ( empty( $quote['success'] ) ) {
			// Older LicenseSender without quotes (or a blip): check against the catalog instead.
			if ( empty( $catalog['success'] ) ) {
				$messages[] = __( 'Wholesale pricing is temporarily unavailable. Please try again in a moment.', 'licensesender' );
				return $messages;
			}
			$quote = self::quote_from_catalog( $catalog );
		}

		foreach ( self::quote_lines( $quote ) as $sku => $line ) {
			if ( ! empty( $line['ok'] ) ) {
				continue;
			}

			$name    = $names[ $sku ] ?? (string) ( $line['name'] ?? $sku );
			$message = self::line_problem_message( $line, $name, Wholesale::get_catalog_product_by_sku( $sku, $catalog ) );
			if ( $message !== '' ) {
				$messages[] = $message;
			}
		}

		$messages = array_merge( $messages, self::minimum_messages( $quote ) );

		$min_message = self::get_minimum_order_quantity_error();
		if ( $min_message ) {
			$messages[] = $min_message;
		}

		return array_values( array_unique( array_filter( $messages ) ) );
	}

	/**
	 * Why a quoted line can't be ordered ('' when it can, or when stock isn't enforced).
	 *
	 * @param array<string, mixed>      $line Quote line.
	 * @param string                    $name Product name.
	 * @param array<string, mixed>|null $item Catalog entry.
	 */
	public static function line_problem_message( array $line, $name, $item ) {
		switch ( (string) ( $line['error'] ?? '' ) ) {
			case '':
				return '';
			case 'below_minimum':
				/* translators: 1: product name, 2: minimum keys */
				return sprintf( __( '"%1$s" needs at least %2$d keys per order.', 'licensesender' ), $name, (int) ( $item['min_qty'] ?? 1 ) );
			case 'above_maximum':
				/* translators: 1: product name, 2: maximum keys */
				return sprintf( __( 'You can order up to %2$d keys of "%1$s" per order.', 'licensesender' ), $name, (int) ( $item['max_qty'] ?? 0 ) );
			case 'insufficient_stock':
				if ( ! Wholesale::enforces_stock() ) {
					return '';
				}
				$available = (int) ( $line['available'] ?? 0 );
				return $available > 0
					/* translators: 1: product name, 2: keys available */
					? sprintf( __( '"%1$s" only has %2$d keys available. Please reduce the quantity.', 'licensesender' ), $name, $available )
					/* translators: %s: product name */
					: sprintf( __( '"%s" is out of stock.', 'licensesender' ), $name );
			default:
				/* translators: %s: product name */
				return sprintf( __( '"%s" is no longer available in the wholesale catalog.', 'licensesender' ), $name );
		}
	}

	/**
	 * Group minimum-order problems from a quote.
	 *
	 * @param array<string, mixed> $quote Quote.
	 * @return string[]
	 */
	public static function minimum_messages( array $quote ) {
		$messages = array();
		$minimum  = (array) ( $quote['minimum'] ?? array() );
		if ( ! isset( $minimum['met'] ) || $minimum['met'] ) {
			return $messages;
		}

		$keys = (int) ( $quote['totals']['quantity'] ?? 0 );
		if ( ! empty( $minimum['quantity'] ) && $keys < (int) $minimum['quantity'] ) {
			/* translators: 1: minimum keys, 2: keys in the order */
			$messages[] = sprintf( __( 'Your wholesale orders need at least %1$d keys in total. This order has %2$d.', 'licensesender' ), (int) $minimum['quantity'], $keys );
		}
		if ( isset( $minimum['value'] ) && null !== $minimum['value'] && (float) ( $quote['totals']['subtotal'] ?? 0 ) < (float) $minimum['value'] ) {
			/* translators: %s: minimum order value */
			$messages[] = sprintf( __( 'Your wholesale orders need a subtotal of at least %s.', 'licensesender' ), wp_strip_all_tags( Currency::format_price( (float) $minimum['value'] ) ) );
		}

		return $messages;
	}

	/**
	 * Quote-shaped check built from the cached catalog (no group minimums, no holds).
	 *
	 * @param array<string, mixed> $catalog Catalog.
	 * @return array<string, mixed>
	 */
	public static function quote_from_catalog( array $catalog, $items = null ) {
		$lines = array();
		foreach ( null === $items ? self::get_cart_wholesale_items() : $items as $sku => $qty ) {
			$item  = Wholesale::get_catalog_product_by_sku( $sku, $catalog );
			$error = null;
			if ( ! $item ) {
				$error = 'not_available';
			} elseif ( $qty < (int) ( $item['min_qty'] ?? 1 ) ) {
				$error = 'below_minimum';
			} elseif ( ! empty( $item['max_qty'] ) && $qty > (int) $item['max_qty'] ) {
				$error = 'above_maximum';
			} elseif ( $qty > (int) ( $item['available_stock'] ?? 0 ) ) {
				$error = 'insufficient_stock';
			}

			$lines[] = array(
				'sku'        => (string) $sku,
				'name'       => (string) ( $item['name'] ?? $sku ),
				'quantity'   => $qty,
				'unit_price' => $item ? Wholesale::get_price_for_quantity( $item, $qty ) : null,
				'available'  => (int) ( $item['available_stock'] ?? 0 ),
				'ok'         => null === $error,
				'error'      => $error,
			);
		}

		return array( 'success' => true, 'lines' => $lines, 'minimum' => array( 'met' => true ) );
	}

	/**
	 * @return array<string, string> SKU => product name in the cart.
	 */
	private static function cart_line_names() {
		$names = array();
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( Catalog::is_wholesale_cart_item( $cart_item ) && isset( $cart_item['data'] ) && $cart_item['data'] instanceof \WC_Product ) {
				$names[ Catalog::get_cart_item_wholesale_sku( $cart_item ) ] = $cart_item['data']->get_name();
			}
		}

		return $names;
	}

	/**
	 * Store-wide minimum wholesale units (plugin setting).
	 */
	private static function get_minimum_order_quantity_error() {
		$minimum = Wholesale::get_min_order_quantity();
		if ( $minimum <= 0 ) {
			return '';
		}

		$current = Catalog::get_cart_wholesale_quantity();
		if ( $current >= $minimum ) {
			return '';
		}

		return sprintf(
			/* translators: 1: minimum units required, 2: current wholesale units in cart */
			__( 'Wholesale orders require a minimum of %1$d units. Your cart currently has %2$d wholesale units.', 'licensesender' ),
			$minimum,
			$current
		);
	}

	/* ---------------------------------------------------------------------
	 * Orders
	 * ------------------------------------------------------------------ */

	/**
	 * Persist wholesale line markers onto the order item.
	 *
	 * @param WC_Order_Item_Product $item          Order item.
	 * @param string                $cart_item_key Cart key.
	 * @param array                 $values        Cart item values.
	 * @param WC_Order              $order         Order.
	 */
	public static function persist_order_line_meta( $item, $cart_item_key, $values, $order ) {
		unset( $cart_item_key, $order );

		if ( empty( $values[ Catalog::CART_FLAG ] ) || $values[ Catalog::CART_FLAG ] !== 'yes' ) {
			return;
		}

		$item->add_meta_data( '_ls_wholesale_line', 'yes', true );
		if ( ! empty( $values[ Catalog::CART_SKU ] ) ) {
			$item->add_meta_data( '_ls_wholesale_sku', sanitize_text_field( (string) $values[ Catalog::CART_SKU ] ), true );
		}
		if ( isset( $values['data'] ) && $values['data'] instanceof \WC_Product ) {
			$item->add_meta_data( '_ls_wholesale_unit_price', wc_format_decimal( $values['data']->get_price() ), true );
		}
	}

	/* ---------------------------------------------------------------------
	 * Add to cart
	 * ------------------------------------------------------------------ */

	/**
	 * Remove non-wholesale products from the cart.
	 *
	 * @return int Number of retail line items removed.
	 */
	public static function remove_retail_products_from_cart() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return 0;
		}

		$removed = 0;
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( Catalog::is_wholesale_cart_item( $cart_item ) ) {
				continue;
			}

			WC()->cart->remove_cart_item( $cart_item_key );
			$removed++;
		}

		if ( $removed > 0 ) {
			wc_add_notice(
				__( 'Regular products were removed from your cart so you can check out wholesale items separately.', 'licensesender' ),
				'notice'
			);
		}

		return $removed;
	}

	/**
	 * Why the current user can't order wholesale right now, or null when they can.
	 *
	 * @return \WP_Error|null
	 */
	public static function access_error() {
		if ( ! Wholesale::is_enabled() ) {
			return new \WP_Error( 'disabled', __( 'Wholesale ordering is currently disabled.', 'licensesender' ), array( 'status' => 403 ) );
		}
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'login', __( 'Please log in to order wholesale products.', 'licensesender' ), array( 'status' => 401 ) );
		}
		if ( ! Wholesale::user_is_wholesale() ) {
			return new \WP_Error( 'access', __( 'Wholesale access is required to purchase these products.', 'licensesender' ), array( 'status' => 403 ) );
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return new \WP_Error( 'cart', __( 'Cart is unavailable.', 'licensesender' ), array( 'status' => 500 ) );
		}

		return null;
	}

	/**
	 * Stock problems for the totals a cart would have after adding (one LicenseSender call).
	 *
	 * @param array<string, int>   $totals  SKU => total quantity after adding.
	 * @param array<string, mixed> $catalog Catalog (fallback stock figures).
	 * @return array<string, \WP_Error> SKU => problem.
	 */
	public static function stock_problems( array $totals, array $catalog ) {
		if ( empty( $totals ) || ! Wholesale::enforces_stock() ) {
			return array();
		}

		$items = array();
		foreach ( $totals as $sku => $qty ) {
			$items[] = array( 'sku' => (string) $sku, 'quantity' => (int) $qty );
		}
		$check = Api::quote_wholesale_cart( $items, Wholesale::get_user_group() );
		$lines = ! empty( $check['success'] ) ? self::quote_lines( $check ) : array();
		$cart  = self::get_cart_wholesale_items();

		$problems = array();
		foreach ( $totals as $sku => $qty ) {
			$line = $lines[ $sku ] ?? null;
			if ( null === $line ) {
				// No quote endpoint: fall back to the catalog's stock figure.
				$item = Wholesale::get_catalog_product_by_sku( $sku, $catalog );
				$line = $item && $qty > (int) $item['available_stock'] ? array( 'error' => 'insufficient_stock', 'available' => (int) $item['available_stock'] ) : null;
			}

			$error = (string) ( $line['error'] ?? '' );
			if ( 'insufficient_stock' === $error ) {
				$available        = max( 0, (int) ( $line['available'] ?? 0 ) - (int) ( $cart[ $sku ] ?? 0 ) );
				$problems[ $sku ] = new \WP_Error(
					'stock',
					$available > 0
						/* translators: %d: keys available */
						? sprintf( __( 'Only %d more keys are available.', 'licensesender' ), $available )
						: __( 'This product is out of stock.', 'licensesender' )
				);
			} elseif ( 'not_available' === $error ) {
				$problems[ $sku ] = new \WP_Error( 'gone', __( 'This wholesale product is no longer available.', 'licensesender' ) );
			}
		}

		return $problems;
	}

	/**
	 * Add one wholesale line (stock already checked by the caller).
	 *
	 * @param string               $sku      Catalog SKU.
	 * @param int                  $quantity Keys to add.
	 * @param array<string, mixed> $catalog  Catalog for the buyer's group.
	 * @return true|\WP_Error
	 */
	public static function add_item( $sku, $quantity, array $catalog ) {
		$item = Wholesale::get_catalog_product_by_sku( $sku, $catalog );
		if ( ! $item ) {
			return new \WP_Error( 'missing', __( 'Product not found in wholesale catalog.', 'licensesender' ) );
		}

		$total = (int) ( self::get_cart_wholesale_items()[ $sku ] ?? 0 ) + (int) $quantity;
		if ( ! empty( $item['max_qty'] ) && $total > (int) $item['max_qty'] ) {
			/* translators: %d: maximum keys per order */
			return new \WP_Error( 'max', sprintf( __( 'You can order up to %d keys of this product per order.', 'licensesender' ), (int) $item['max_qty'] ) );
		}

		$resolved = Catalog::resolve_wc_product( $item );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		$ids = Catalog::get_add_to_cart_ids( $resolved );
		if ( is_wp_error( $ids ) ) {
			return $ids;
		}

		self::$adding = true;
		try {
			$cart_key = WC()->cart->add_to_cart( $ids['product_id'], (int) $quantity, $ids['variation_id'], array(), Catalog::wholesale_cart_item_data( $sku ) );
		} finally {
			self::$adding = false;
		}

		if ( ! $cart_key ) {
			$notices = wc_get_notices( 'error' );
			wc_clear_notices();
			return new \WP_Error(
				'add',
				! empty( $notices[0]['notice'] ) ? wp_strip_all_tags( (string) $notices[0]['notice'] ) : __( 'Could not add product to cart.', 'licensesender' )
			);
		}

		return true;
	}

	/**
	 * Cart state the storefront needs after a change.
	 *
	 * @param string $message Message to show.
	 * @return array<string, mixed>
	 */
	public static function cart_response( $message ) {
		return array(
			'message'            => $message,
			'cart_url'           => wc_get_cart_url(),
			'cart_count'         => WC()->cart->get_cart_contents_count(),
			'cart_wholesale_qty' => Catalog::get_cart_wholesale_quantity(),
			'cart_items'         => (object) self::get_cart_wholesale_items(),
			'cart_hash'          => WC()->cart->get_cart_hash(),
			'fragments'          => self::get_cart_fragments(),
		);
	}

	public static function ajax_add_to_cart() {
		check_ajax_referer( 'ls_wholesale_cart', 'nonce' );

		$denied = self::access_error();
		if ( $denied ) {
			wp_send_json_error( array( 'message' => $denied->get_error_message() ), (int) $denied->get_error_data()['status'] );
		}

		$sku      = sanitize_text_field( wp_unslash( $_POST['sku'] ?? '' ) );
		$quantity = max( 1, (int) ( $_POST['quantity'] ?? 1 ) );
		if ( $sku === '' ) {
			wp_send_json_error( array( 'message' => __( 'Missing product SKU.', 'licensesender' ) ), 400 );
		}

		$catalog = Wholesale::get_catalog();
		if ( empty( $catalog['success'] ) ) {
			wp_send_json_error( array( 'message' => $catalog['message'] ?? __( 'Catalog unavailable.', 'licensesender' ) ), 400 );
		}

		$problems = self::stock_problems( array( $sku => (int) ( self::get_cart_wholesale_items()[ $sku ] ?? 0 ) + $quantity ), $catalog );
		if ( isset( $problems[ $sku ] ) ) {
			wp_send_json_error( array( 'message' => $problems[ $sku ]->get_error_message() ), 400 );
		}

		$removed_retail = self::remove_retail_products_from_cart();
		$added          = self::add_item( $sku, $quantity, $catalog );
		if ( is_wp_error( $added ) ) {
			wp_send_json_error( array( 'message' => $added->get_error_message() ), 400 );
		}

		WC()->cart->calculate_totals();

		wp_send_json_success(
			self::cart_response(
				$removed_retail > 0
					? __( 'Regular products were removed from your cart. Wholesale product added.', 'licensesender' )
					: __( 'Product added to cart.', 'licensesender' )
			)
		);
	}

	/**
	 * Build WooCommerce cart fragments for AJAX mini-cart updates.
	 *
	 * @return array<string, string>
	 */
	private static function get_cart_fragments() {
		ob_start();
		woocommerce_mini_cart();
		$mini_cart = ob_get_clean();

		$fragments = array(
			'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
		);

		return apply_filters( 'woocommerce_add_to_cart_fragments', $fragments );
	}

	/**
	 * Keep the wholesale catalog "View cart" count in sync with the cart.
	 *
	 * @param array<string, string> $fragments Cart fragments.
	 * @return array<string, string>
	 */
	public static function cart_count_fragment( $fragments ) {
		$count = ( function_exists( 'WC' ) && WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0;
		$label = $count > 0 ? '(' . number_format_i18n( $count ) . ')' : '';
		$attr  = $count > 0 ? '' : ' hidden';

		$fragments['span.ls-wholesale-cart-count'] = '<span class="ls-wholesale-cart-count"' . $attr . '>' . esc_html( $label ) . '</span>';

		return $fragments;
	}
}
