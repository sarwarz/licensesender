<?php
/**
 * WooCommerce cart and pricing for wholesale.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Wholesale_Cart {

	/**
	 * When true, WC retail stock checks are ignored (API stock is authoritative for wholesale).
	 *
	 * @var bool
	 */
	private static $bypass_retail_stock = false;

	public static function init() {
		add_filter( 'woocommerce_product_get_price', array( __CLASS__, 'filter_price' ), 99, 2 );
		add_filter( 'woocommerce_product_get_regular_price', array( __CLASS__, 'filter_price' ), 99, 2 );
		add_filter( 'woocommerce_product_get_sale_price', array( __CLASS__, 'filter_price' ), 99, 2 );
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'filter_price_html' ), 99, 2 );
		add_filter( 'woocommerce_is_purchasable', array( __CLASS__, 'filter_is_purchasable' ), 99, 2 );
		add_filter( 'woocommerce_product_is_visible', array( __CLASS__, 'filter_is_visible' ), 99, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_to_cart' ), 10, 5 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'restore_cart_item_from_session' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'persist_order_line_meta' ), 10, 4 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_cart_tier_pricing' ), 20 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'validate_cart_items' ) );
		add_filter( 'woocommerce_store_api_cart_errors', array( __CLASS__, 'validate_cart_items_blocks' ), 10, 2 );

		add_action( 'wp_ajax_ls_wholesale_add_to_cart', array( __CLASS__, 'ajax_add_to_cart' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'cart_count_fragment' ) );
		add_filter( 'woocommerce_product_get_image', array( 'LS_Wholesale_Catalog', 'filter_product_image' ), 20, 6 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'ensure_cart_placeholder_images' ), 5 );
		add_filter( 'woocommerce_product_is_in_stock', array( __CLASS__, 'filter_product_is_in_stock' ), 99, 2 );
		add_filter( 'woocommerce_product_has_enough_stock', array( __CLASS__, 'filter_product_has_enough_stock' ), 99, 3 );
	}

	/**
	 * Temporarily ignore WC stock while adding a wholesale cart line.
	 */
	private static function begin_wholesale_stock_bypass() {
		self::$bypass_retail_stock = true;
	}

	/**
	 * Restore normal WC stock checks.
	 */
	private static function end_wholesale_stock_bypass() {
		self::$bypass_retail_stock = false;
	}

	/**
	 * @param bool       $in_stock In stock.
	 * @param WC_Product $product  Product.
	 * @return bool
	 */
	public static function filter_product_is_in_stock( $in_stock, $product ) {
		unset( $product );
		return self::$bypass_retail_stock ? true : $in_stock;
	}

	/**
	 * @param bool       $enough   Enough stock.
	 * @param WC_Product $product  Product.
	 * @param int|float  $quantity Quantity.
	 * @return bool
	 */
	public static function filter_product_has_enough_stock( $enough, $product, $quantity ) {
		unset( $product, $quantity );
		return self::$bypass_retail_stock ? true : $enough;
	}

	/**
	 * Keep wholesale cart flags across sessions.
	 *
	 * @param array $cart_item Cart item.
	 * @param array $values    Session values.
	 * @return array
	 */
	public static function restore_cart_item_from_session( $cart_item, $values ) {
		if ( ! empty( $values[ LS_Wholesale_Catalog::CART_FLAG ] ) ) {
			$cart_item[ LS_Wholesale_Catalog::CART_FLAG ] = $values[ LS_Wholesale_Catalog::CART_FLAG ];
		}
		if ( ! empty( $values[ LS_Wholesale_Catalog::CART_SKU ] ) ) {
			$cart_item[ LS_Wholesale_Catalog::CART_SKU ] = $values[ LS_Wholesale_Catalog::CART_SKU ];
		}

		return $cart_item;
	}

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

		if ( empty( $values[ LS_Wholesale_Catalog::CART_FLAG ] ) || $values[ LS_Wholesale_Catalog::CART_FLAG ] !== 'yes' ) {
			return;
		}

		$item->add_meta_data( '_ls_wholesale_line', 'yes', true );
		if ( ! empty( $values[ LS_Wholesale_Catalog::CART_SKU ] ) ) {
			$item->add_meta_data( '_ls_wholesale_sku', sanitize_text_field( (string) $values[ LS_Wholesale_Catalog::CART_SKU ] ), true );
		}
	}

	/**
	 * Backfill wholesale dummy thumbnails for legacy shadow cart products missing images.
	 */
	public static function ensure_cart_placeholder_images() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! LS_Wholesale_Catalog::is_wholesale_cart_item( $cart_item ) ) {
				continue;
			}

			$product_id = isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
			if ( ! $product_id || ! LS_Wholesale_Catalog::is_wholesale_product( $product_id ) ) {
				continue;
			}
			LS_Wholesale_Catalog::maybe_assign_placeholder_thumbnail( $product_id );
		}
	}

	public static function validate_add_to_cart( $passed, $product_id, $quantity = 1, $variation_id = 0, $variations = array() ) {
		unset( $variations );

		$check_id = $variation_id ? (int) $variation_id : (int) $product_id;

		// Only legacy shadow products are sold exclusively as wholesale via the shop.
		if ( ! LS_Wholesale_Catalog::is_wholesale_product( $check_id ) ) {
			return $passed;
		}

		if ( ! LS_Wholesale::is_enabled() ) {
			wc_add_notice( __( 'Wholesale ordering is currently disabled.', 'licensesender' ), 'error' );
			return false;
		}

		if ( ! is_user_logged_in() || ! LS_Wholesale::user_is_wholesale() ) {
			wc_add_notice( __( 'Wholesale access is required to purchase this product.', 'licensesender' ), 'error' );
			return false;
		}

		if ( ! LS_Wholesale_Catalog::is_product_in_live_catalog( $check_id ) ) {
			wc_add_notice( __( 'This wholesale product is no longer available.', 'licensesender' ), 'error' );
			return false;
		}

		$message = self::get_add_to_cart_minimum_order_error( $quantity );
		if ( $message ) {
			wc_add_notice( $message, 'error' );
			return false;
		}

		// Wholesale and retail cannot share a cart — drop retail items before adding.
		self::remove_retail_products_from_cart();

		return $passed;
	}

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
			if ( LS_Wholesale_Catalog::is_wholesale_cart_item( $cart_item ) ) {
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
	 * Validate wholesale cart items on classic cart/checkout (availability, stock, MOQ, mixed cart).
	 */
	public static function validate_cart_items() {
		foreach ( self::get_cart_validation_errors( true ) as $message ) {
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

		foreach ( self::get_cart_validation_errors( true ) as $index => $message ) {
			$errors->add( 'ls_wholesale_cart_' . $index, $message );
		}

		return $errors;
	}

	/**
	 * Collect wholesale cart validation errors.
	 *
	 * @param bool $force_refresh Force a live catalog refresh.
	 * @return string[]
	 */
	public static function get_cart_validation_errors( $force_refresh = false ) {
		$messages = array();

		if ( ! LS_Wholesale::is_enabled() || ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return $messages;
		}

		if ( ! LS_Wholesale_Catalog::cart_has_wholesale_product() ) {
			return $messages;
		}

		if ( ! is_user_logged_in() || ! LS_Wholesale::user_is_wholesale() ) {
			$messages[] = __( 'Wholesale access is required to purchase wholesale products in your cart.', 'licensesender' );
			return $messages;
		}

		/**
		 * Whether mixed wholesale + retail carts are allowed.
		 *
		 * @param bool $allowed Default false.
		 */
		$allow_mixed = (bool) apply_filters( 'ls_wholesale_allow_mixed_cart', false );
		if ( ! $allow_mixed && ! LS_Wholesale_Catalog::cart_has_only_wholesale_products() ) {
			$messages[] = __( 'Wholesale products cannot be checked out with regular products. Please remove one type and try again.', 'licensesender' );
		}

		$catalog = LS_Wholesale::get_catalog( (bool) $force_refresh );
		if ( empty( $catalog['success'] ) ) {
			$messages[] = ! empty( $catalog['message'] )
				? (string) $catalog['message']
				: __( 'Wholesale catalog is temporarily unavailable. Please try again shortly.', 'licensesender' );
			return $messages;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! LS_Wholesale_Catalog::is_wholesale_cart_item( $cart_item ) ) {
				continue;
			}

			$sku  = LS_Wholesale_Catalog::get_cart_item_wholesale_sku( $cart_item );
			$item = $sku ? LS_Wholesale::get_catalog_product_by_sku( $sku, $catalog ) : null;
			$qty  = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0;
			$name = isset( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product
				? $cart_item['data']->get_name()
				: ( $sku ? $sku : __( 'Wholesale product', 'licensesender' ) );

			if ( ! $item ) {
				$messages[] = sprintf(
					/* translators: %s: product name */
					__( '"%s" is no longer available in the wholesale catalog. Please remove it from your cart.', 'licensesender' ),
					$name
				);
				continue;
			}

			$stock = (int) ( $item['available_stock'] ?? 0 );
			if ( LS_Wholesale::enforces_stock() ) {
				if ( $stock <= 0 ) {
					$messages[] = sprintf(
						/* translators: %s: product name */
						__( '"%s" is out of stock.', 'licensesender' ),
						$name
					);
					continue;
				}

				if ( $qty > $stock ) {
					$messages[] = sprintf(
						/* translators: 1: product name, 2: available stock */
						__( '"%1$s" only has %2$d units available. Please reduce the quantity.', 'licensesender' ),
						$name,
						$stock
					);
				}
			}
		}

		$min_message = self::get_minimum_order_quantity_error();
		if ( $min_message ) {
			$messages[] = $min_message;
		}

		return array_values( array_unique( array_filter( $messages ) ) );
	}

	/**
	 * Hide wholesale prices from non-wholesale visitors.
	 *
	 * @param string     $html    Price HTML.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function filter_price_html( $html, $product ) {
		if ( ! LS_Wholesale_Catalog::is_wholesale_product( $product ) ) {
			return $html;
		}

		if ( is_user_logged_in() && LS_Wholesale::user_is_wholesale() ) {
			return $html;
		}

		return '';
	}

	/**
	 * Wholesale products are only purchasable by approved wholesale customers.
	 *
	 * @param bool       $purchasable Whether purchasable.
	 * @param WC_Product $product     Product.
	 * @return bool
	 */
	public static function filter_is_purchasable( $purchasable, $product ) {
		if ( ! LS_Wholesale_Catalog::is_wholesale_product( $product ) ) {
			return $purchasable;
		}

		if ( ! LS_Wholesale::is_enabled() ) {
			return false;
		}

		return is_user_logged_in() && LS_Wholesale::user_is_wholesale();
	}

	/**
	 * Keep wholesale products out of shop/catalog loops for retail visitors.
	 *
	 * @param bool $visible Whether visible.
	 * @param int  $id      Product ID.
	 * @return bool
	 */
	public static function filter_is_visible( $visible, $id ) {
		if ( ! LS_Wholesale_Catalog::is_wholesale_product( $id ) ) {
			return $visible;
		}

		return false;
	}

	/**
	 * @deprecated 1.0.0 Use validate_cart_items().
	 */
	public static function validate_minimum_order_quantity() {
		self::validate_cart_items();
	}

	/**
	 * @deprecated 1.0.0 Use validate_cart_items_blocks().
	 *
	 * @param WP_Error $errors Cart errors.
	 * @param WC_Cart  $cart   Cart object.
	 * @return WP_Error
	 */
	public static function validate_minimum_order_quantity_blocks( $errors, $cart ) {
		return self::validate_cart_items_blocks( $errors, $cart );
	}

	/**
	 * Build minimum order quantity error message when validation fails.
	 */
	private static function get_minimum_order_quantity_error() {
		if ( ! LS_Wholesale::is_enabled() ) {
			return '';
		}

		if ( ! is_user_logged_in() || ! LS_Wholesale::user_is_wholesale() ) {
			return '';
		}

		$minimum = LS_Wholesale::get_min_order_quantity();
		if ( $minimum <= 0 ) {
			return '';
		}

		if ( ! LS_Wholesale_Catalog::cart_has_wholesale_product() ) {
			return '';
		}

		$current = LS_Wholesale_Catalog::get_cart_wholesale_quantity();
		if ( $current >= $minimum ) {
			return '';
		}

		return self::format_minimum_order_quantity_error( $minimum, $current );
	}

	/**
	 * Validate whether a wholesale add-to-cart quantity satisfies the minimum order rule.
	 *
	 * @param int $quantity Quantity being added.
	 * @return string Error message or empty string when valid.
	 */
	public static function get_add_to_cart_minimum_order_error( $quantity ) {
		if ( ! LS_Wholesale::is_enabled() ) {
			return '';
		}

		if ( ! is_user_logged_in() || ! LS_Wholesale::user_is_wholesale() ) {
			return '';
		}

		$minimum = LS_Wholesale::get_min_order_quantity();
		if ( $minimum <= 0 ) {
			return '';
		}

		$current  = LS_Wholesale_Catalog::get_cart_wholesale_quantity();
		$quantity = max( 1, (int) $quantity );

		if ( $current >= $minimum || ( $current + $quantity ) >= $minimum ) {
			return '';
		}

		return sprintf(
			/* translators: 1: minimum units required, 2: additional units needed in this add action */
			__( 'Wholesale orders require a minimum of %1$d units. Add at least %2$d more units to continue.', 'licensesender' ),
			$minimum,
			$minimum - $current
		);
	}

	/**
	 * @param int $minimum Required wholesale units.
	 * @param int $current Current wholesale units in cart.
	 */
	private static function format_minimum_order_quantity_error( $minimum, $current ) {
		return sprintf(
			/* translators: 1: minimum units required, 2: current wholesale units in cart */
			__( 'Wholesale orders require a minimum of %1$d units. Your cart currently has %2$d wholesale units.', 'licensesender' ),
			$minimum,
			$current
		);
	}

	public static function filter_price( $price, $product ) {
		if ( ! LS_Wholesale::is_enabled() || ! LS_Wholesale::user_is_wholesale() || ! LS_Wholesale_Catalog::is_wholesale_product( $product ) ) {
			return $price;
		}

		if ( self::should_use_cart_tier_price() ) {
			return $price;
		}

		$wholesale_price = get_post_meta( $product->get_id(), '_ls_wholesale_price', true );
		if ( $wholesale_price !== '' && is_numeric( $wholesale_price ) ) {
			return (string) $wholesale_price;
		}

		return $price;
	}

	/**
	 * Whether the current request should keep tier-adjusted cart prices.
	 */
	private static function should_use_cart_tier_price() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		// apply_cart_tier_pricing sets line-item prices during cart recalculation.
		if ( did_action( 'woocommerce_before_calculate_totals' ) ) {
			return true;
		}

		if ( is_cart() || is_checkout() ) {
			return true;
		}

		if ( wp_doing_ajax() ) {
			if ( ! empty( $_REQUEST['wc-ajax'] ) ) {
				return true;
			}

			$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
			if ( in_array( $action, array( 'ls_wholesale_add_to_cart', 'woocommerce_add_to_cart', 'woocommerce_get_refreshed_fragments' ), true ) ) {
				return true;
			}
		}

		return false;
	}

	public static function apply_cart_tier_pricing( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! $cart instanceof WC_Cart || ! LS_Wholesale::is_enabled() || ! LS_Wholesale::user_is_wholesale() ) {
			return;
		}

		$catalog = LS_Wholesale::get_catalog();

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			unset( $cart_item_key );

			if ( ! LS_Wholesale_Catalog::is_wholesale_cart_item( $cart_item ) ) {
				continue;
			}

			if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
				continue;
			}

			$sku  = LS_Wholesale_Catalog::get_cart_item_wholesale_sku( $cart_item );
			$item = $sku ? LS_Wholesale::get_catalog_product_by_sku( $sku, $catalog ) : null;
			if ( ! $item ) {
				$product_id = isset( $cart_item['variation_id'] ) && (int) $cart_item['variation_id'] > 0
					? (int) $cart_item['variation_id']
					: (int) ( $cart_item['product_id'] ?? 0 );
				$item = $product_id ? LS_Wholesale::get_catalog_product_from_meta( $product_id ) : null;
			}

			if ( ! $item ) {
				continue;
			}

			$unit_price = LS_Wholesale::get_price_for_quantity( $item, (int) $cart_item['quantity'] );
			$cart_item['data']->set_price( $unit_price );
		}
	}

	public static function ajax_add_to_cart() {
		check_ajax_referer( 'ls_wholesale_cart', 'nonce' );

		if ( ! LS_Wholesale::is_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Wholesale ordering is currently disabled.', 'licensesender' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to order wholesale products.', 'licensesender' ) ), 401 );
		}

		if ( ! LS_Wholesale::user_is_wholesale() ) {
			wp_send_json_error( array( 'message' => __( 'Wholesale access is required to purchase these products.', 'licensesender' ) ), 403 );
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'Cart is unavailable.', 'licensesender' ) ), 500 );
		}

		$sku      = sanitize_text_field( wp_unslash( $_POST['sku'] ?? '' ) );
		$quantity = max( 1, (int) ( $_POST['quantity'] ?? 1 ) );

		if ( $sku === '' ) {
			wp_send_json_error( array( 'message' => __( 'Missing product SKU.', 'licensesender' ) ), 400 );
		}

		$catalog = LS_Wholesale::get_catalog( true );
		if ( empty( $catalog['success'] ) ) {
			wp_send_json_error( array( 'message' => $catalog['message'] ?? __( 'Catalog unavailable.', 'licensesender' ) ), 400 );
		}

		$item = null;
		foreach ( $catalog['products'] as $product ) {
			if ( (string) $product['sku'] === $sku ) {
				$item = $product;
				break;
			}
		}

		if ( ! $item ) {
			wp_send_json_error( array( 'message' => __( 'Product not found in wholesale catalog.', 'licensesender' ) ), 404 );
		}

		if ( LS_Wholesale::enforces_stock() ) {
			if ( (int) $item['available_stock'] <= 0 ) {
				wp_send_json_error( array( 'message' => __( 'This product is out of stock.', 'licensesender' ) ), 400 );
			}

			if ( $quantity > (int) $item['available_stock'] ) {
				wp_send_json_error(
					array( 'message' => sprintf( __( 'Only %d units available.', 'licensesender' ), (int) $item['available_stock'] ) ),
					400
				);
			}
		}

		$resolved = LS_Wholesale_Catalog::resolve_wc_product( $item );
		if ( is_wp_error( $resolved ) ) {
			wp_send_json_error( array( 'message' => $resolved->get_error_message() ), 400 );
		}

		$ids = LS_Wholesale_Catalog::get_add_to_cart_ids( $resolved );
		if ( is_wp_error( $ids ) ) {
			wp_send_json_error( array( 'message' => $ids->get_error_message() ), 400 );
		}

		$minimum_error = self::get_add_to_cart_minimum_order_error( $quantity );
		if ( $minimum_error ) {
			wp_send_json_error( array( 'message' => $minimum_error ), 400 );
		}

		$removed_retail = self::remove_retail_products_from_cart();

		self::begin_wholesale_stock_bypass();
		$cart_key = WC()->cart->add_to_cart(
			$ids['product_id'],
			$quantity,
			$ids['variation_id'],
			array(),
			LS_Wholesale_Catalog::wholesale_cart_item_data( $sku )
		);
		self::end_wholesale_stock_bypass();
		if ( ! $cart_key ) {
			$notices = wc_get_notices( 'error' );
			$message = __( 'Could not add product to cart.', 'licensesender' );
			if ( ! empty( $notices[0]['notice'] ) ) {
				$message = wp_strip_all_tags( (string) $notices[0]['notice'] );
			}
			wc_clear_notices();
			wp_send_json_error( array( 'message' => $message ), 400 );
		}

		WC()->cart->calculate_totals();

		$message = __( 'Product added to cart.', 'licensesender' );
		if ( $removed_retail > 0 ) {
			$message = __( 'Regular products were removed from your cart. Wholesale product added.', 'licensesender' );
		}

		wp_send_json_success(
			array(
				'message'            => $message,
				'cart_url'           => wc_get_cart_url(),
				'cart_count'         => WC()->cart->get_cart_contents_count(),
				'cart_wholesale_qty' => LS_Wholesale_Catalog::get_cart_wholesale_quantity(),
				'cart_hash'          => WC()->cart->get_cart_hash(),
				'fragments'          => self::get_cart_fragments(),
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

LS_Wholesale_Cart::init();
