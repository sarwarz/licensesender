<?php

namespace LicenseSender\Wholesale;

use LicenseSender\Core\DesignSystem;

defined( 'ABSPATH' ) || exit;

/**
 * The reseller portal: one React app (build/public/wholesale.js) placed on pages with the
 * [ls_wholesale] / [ls_wholesale_catalog] and [ls_wholesale_apply] shortcodes. Approved
 * buyers get quick ordering at their group's prices; everyone else gets the application
 * form or its status.
 */
class Portal {

	/** Bump to re-flush rewrite rules once (v2 removed the My Account endpoint). */
	const REWRITE_VERSION = '2';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrites' ), 99 );
		add_action( 'wp_ajax_ls_wholesale_portal_apply', array( __CLASS__, 'ajax_apply' ) );
	}

	public static function maybe_flush_rewrites() {
		if ( get_option( 'ls_wholesale_portal_rewrite' ) !== self::REWRITE_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'ls_wholesale_portal_rewrite', self::REWRITE_VERSION, false );
		}
	}

	/**
	 * Mount point + settings for the app.
	 *
	 * @param string $view portal|apply|guest.
	 */
	public static function render( $view ) {
		$view = in_array( $view, array( 'apply', 'guest' ), true ) ? $view : 'portal';

		wp_enqueue_script( 'ls-wholesale-app' );
		wp_enqueue_script( 'wc-cart-fragments' );
		wp_add_inline_script( 'ls-wholesale-app', 'window.LSWholesaleApp = ' . wp_json_encode( self::config( $view ) ) . ';', 'before' );

		ob_start();
		?>
		<div class="ls-wholesale-app" data-theme="<?php echo esc_attr( DesignSystem::color_mode() ); ?>">
			<div class="ls-wholesale-loading" role="status" style="padding:48px 16px;text-align:center;color:#64748b;font:14px/1.5 system-ui,-apple-system,'Segoe UI',sans-serif;">
				<?php esc_html_e( 'Loading…', 'licensesender' ); ?>
			</div>
			<noscript><p><?php esc_html_e( 'Please enable JavaScript to use the wholesale portal.', 'licensesender' ); ?></p></noscript>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Everything the app needs on first paint (no loading round trip).
	 *
	 * @param string $view portal|apply.
	 * @return array<string, mixed>
	 */
	public static function config( $view ) {
		$tokens    = class_exists( DesignSystem::class ) ? DesignSystem::resolve_tokens() : array();
		$user      = wp_get_current_user();
		$wholesale = Wholesale::user_is_wholesale();
		$app       = Wholesale::get_user_application( $user->ID );

		$config = array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'ls_wholesale_cart' ),
			'applyNonce'    => wp_create_nonce( 'ls_wholesale_apply' ),
			'brand'         => (string) ( $tokens['brand'] ?? '#4f46e5' ),
			'radiusCard'    => (string) ( $tokens['radius_card'] ?? '14px' ),
			'radiusControl' => (string) ( $tokens['radius_ctl'] ?? '10px' ),
			'view'          => $view,
			'enabled'       => Wholesale::is_enabled(),
			'wholesale'     => $wholesale,
			'application'   => $app ? array(
				'status'    => (string) $app['status'],
				'note'      => (string) ( $app['admin_note'] ?? '' ),
				'submitted' => (string) $app['created_at'],
			) : null,
			'user'          => array(
				'name'  => $user->display_name ? $user->display_name : $user->user_login,
				'email' => $user->user_email,
			),
			'links'         => self::links(),
			'auth'          => 'guest' === $view ? self::auth_links() : null,
			'i18n'          => self::strings(),
		);

		if ( $wholesale && Wholesale::is_enabled() ) {
			$config += self::portal_data();
		}

		return $config;
	}

	/**
	 * Store login / register links that bring the visitor back to this page (the support
	 * module's return parameter handles the redirect after WooCommerce signs them in).
	 * Null when WooCommerce has no My Account page.
	 *
	 * @return array{login: string, register: string}|null
	 */
	public static function auth_links() {
		$account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';
		if ( ! $account || ! get_permalink() ) {
			return null;
		}

		// A relative path: full URLs in query strings trip some firewalls.
		$back  = wp_make_link_relative( (string) get_permalink() );
		$login = add_query_arg( 'ls_support_redirect', $back, $account );

		// Store registration off: the wholesale page still offers its own sign-up form.
		$register = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' )
			? add_query_arg( 'action', 'register', $login )
			: add_query_arg( 'ls_auth', 'register', (string) get_permalink() );

		return array( 'login' => $login, 'register' => $register );
	}

	/** @return array<string, string> */
	private static function links() {
		$catalog_page = Wholesale::get_catalog_page_id();
		$apply_page   = Wholesale::get_apply_page_id();
		// Without configured pages, stay on the page the shortcode is on.
		$here = (string) get_permalink();

		return array(
			'catalog'  => $catalog_page ? (string) get_permalink( $catalog_page ) : $here,
			'apply'    => $apply_page ? (string) get_permalink( $apply_page ) : $here,
			'cart'     => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'checkout' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
			'orders'   => function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'orders' ) : '',
		);
	}

	/**
	 * Catalog, group, cart and recent orders for an approved buyer.
	 *
	 * @return array<string, mixed>
	 */
	private static function portal_data() {
		$catalog  = Wholesale::get_catalog();
		$products = (array) ( $catalog['products'] ?? array() );
		$mapped   = Catalog::map_skus( array_column( $products, 'sku' ) );

		$items = array();
		foreach ( $products as $product ) {
			$sku        = (string) $product['sku'];
			$product_id = $mapped[ $sku ] ?? 0;
			$items[]    = array(
				'sku'     => $sku,
				'name'    => (string) $product['name'],
				'image'   => $product_id ? self::image_url( $product_id ) : '',
				'tiers'   => array_values( (array) $product['wholesale_prices'] ),
				'base'    => (float) $product['wholesale_price'],
				'minQty'  => (int) ( $product['min_qty'] ?? 1 ),
				'maxQty'  => (int) ( $product['max_qty'] ?? 0 ),
				'stock'   => (int) $product['available_stock'],
				'buyable' => $product_id > 0,
			);
		}

		return array(
			'catalog'  => array(
				'ok'       => ! empty( $catalog['success'] ),
				'message'  => (string) ( $catalog['message'] ?? '' ),
				'products' => $items,
			),
			'group'    => self::group_info(),
			'orders'   => self::recent_orders(),
			'currency' => self::currency(),
			'billing'  => self::billing_summary(),
			'wallet'   => self::wallet_info(),
			'settings' => array(
				'perPage'      => Wholesale::get_catalog_per_page(),
				'lowStock'     => Wholesale::get_low_stock_threshold(),
				'minOrderQty'  => Wholesale::get_min_order_quantity(),
				'enforceStock' => Wholesale::enforces_stock(),
			),
		);
	}

	private static function image_url( $product_id ) {
		$url = get_the_post_thumbnail_url( $product_id, 'woocommerce_thumbnail' );
		if ( ! $url ) {
			$parent = wp_get_post_parent_id( $product_id );
			$url    = $parent ? get_the_post_thumbnail_url( $parent, 'woocommerce_thumbnail' ) : '';
		}

		return (string) $url;
	}

	/** @return array<string, mixed>|null The buyer's group (or the account default). */
	private static function group_info() {
		$slug = Wholesale::get_user_group();
		foreach ( Wholesale::get_groups() as $group ) {
			if ( ( $slug !== '' && $group['slug'] === $slug ) || ( $slug === '' && ! empty( $group['is_default'] ) ) ) {
				return array(
					'name'     => (string) $group['name'],
					'discount' => (float) $group['discount_percent'],
					'minQty'   => $group['min_order_qty'] ? (int) $group['min_order_qty'] : 0,
					'minValue' => null !== $group['min_order_value'] ? (float) $group['min_order_value'] : 0,
				);
			}
		}

		return null;
	}

	/** @return array<string, mixed> Display currency (multi-currency plugins convert from the base). */
	private static function currency() {
		$code = Currency::get_active_currency();

		return array(
			'code'     => $code,
			'symbol'   => html_entity_decode( get_woocommerce_currency_symbol( $code ), ENT_QUOTES, 'UTF-8' ),
			'position' => (string) get_option( 'woocommerce_currency_pos', 'left' ),
			'decimals' => wc_get_price_decimals(),
			'decSep'   => wc_get_price_decimal_separator(),
			'thouSep'  => wc_get_price_thousand_separator(),
			'rate'     => (float) Currency::convert_price( 1.0 ),
		);
	}

	/** @return array<string, string> Who the order is billed to (from the account). */
	private static function billing_summary() {
		$customer = new \WC_Customer( get_current_user_id() );
		$name     = trim( $customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name() );
		$country  = $customer->get_billing_country();
		$countries = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_countries() : array();

		return array(
			'name'    => $name !== '' ? $name : wp_get_current_user()->display_name,
			'company' => $customer->get_billing_company(),
			'email'   => $customer->get_billing_email() ?: wp_get_current_user()->user_email,
			'country' => $country ? html_entity_decode( (string) ( $countries[ $country ] ?? $country ), ENT_QUOTES, 'UTF-8' ) : '',
			'editUrl' => function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_endpoint_url( 'edit-address', 'billing', wc_get_page_permalink( 'myaccount' ) ) : '',
		);
	}

	/** @return array{available: bool, balance: float} Wallet the buyer can pay with in one click. */
	private static function wallet_info() {
		$available = null !== Checkout::wallet_gateway();

		return array(
			'available' => $available,
			'balance'   => $available ? Payments::get_wallet_balance_amount() : 0.0,
		);
	}

	/** @return array<int, array<string, mixed>> */
	private static function recent_orders() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => get_current_user_id(),
				'limit'       => 6,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => Orders::ORDER_META,
						'value' => 'yes',
					),
				),
			)
		);

		$rows = array();
		foreach ( $orders as $order ) {
			$lines = array();
			foreach ( $order->get_items() as $item ) {
				if ( $item instanceof \WC_Order_Item_Product && $item->get_meta( '_ls_wholesale_line', true ) === 'yes' ) {
					$lines[] = array(
						'sku'  => (string) $item->get_meta( '_ls_wholesale_sku', true ),
						'name' => $item->get_name(),
						'qty'  => (int) $item->get_quantity(),
					);
				}
			}

			$rows[] = array(
				'id'     => $order->get_id(),
				'number' => $order->get_order_number(),
				'date'   => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : '',
				'status' => wc_get_order_status_name( $order->get_status() ),
				'total'  => html_entity_decode( wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ),
				'url'    => $order->get_view_order_url(),
				'payUrl' => $order->needs_payment() ? $order->get_checkout_payment_url() : '',
				'lines'  => $lines,
			);
		}

		return $rows;
	}

	/* ---------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------ */

	public static function ajax_apply() {
		check_ajax_referer( 'ls_wholesale_apply', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to apply.', 'licensesender' ) ), 401 );
		}

		$fields = array( 'company_name', 'business_email', 'phone', 'messenger_link', 'website', 'tax_id', 'message' );
		$data   = array();
		foreach ( $fields as $field ) {
			$data[ $field ] = wp_unslash( (string) ( $_POST[ $field ] ?? '' ) );
		}

		$result = Wholesale::submit_application( get_current_user_id(), $data );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		$app = Wholesale::get_user_application( get_current_user_id() );
		wp_send_json_success(
			array(
				'wholesale'   => Wholesale::user_is_wholesale(),
				'application' => $app ? array(
					'status'    => (string) $app['status'],
					'note'      => (string) ( $app['admin_note'] ?? '' ),
					'submitted' => (string) $app['created_at'],
				) : null,
			)
		);
	}

	/**
	 * UI strings (keys match t() calls in resources/public/wholesale).
	 *
	 * @return array<string, string>
	 */
	public static function strings() {
		return array(
			/* @strings-start */
			'disabledTitle' => __( 'Wholesale is paused', 'licensesender' ),
			'disabledText' => __( 'Wholesale ordering is currently unavailable. Please check back later or contact us.', 'licensesender' ),
			'stepAccount' => __( 'Your account', 'licensesender' ),
			'stepDetails' => __( 'Business details', 'licensesender' ),
			'stepReviewShort' => __( 'Review', 'licensesender' ),
			'currentStep' => __( '(current step)', 'licensesender' ),
			'eyebrow' => __( 'Wholesale', 'licensesender' ),
			'applyTitle' => __( 'Become a wholesale partner', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'stepOf' => __( 'Step %d of %d · %s', 'licensesender' ),
			'perkPricing' => __( 'Volume pricing', 'licensesender' ),
			'perkPricingText' => __( 'Lower prices per key the more you order.', 'licensesender' ),
			'perkDelivery' => __( 'Instant delivery', 'licensesender' ),
			'perkDeliveryText' => __( 'Keys arrive with your order as soon as it is paid.', 'licensesender' ),
			'perkBulk' => __( 'Bulk ordering', 'licensesender' ),
			'perkBulkText' => __( 'Order many products at once or paste a list.', 'licensesender' ),
			'applyLead' => __( 'Tell us about your business. Once approved, you can order at wholesale prices from your account.', 'licensesender' ),
			'howItWorks' => __( 'How it works', 'licensesender' ),
			'guestTitle' => __( 'Sign in to apply', 'licensesender' ),
			'guestLead' => __( 'Wholesale access is linked to your customer account. Log in, or create a free account, and we will bring you right back to the application.', 'licensesender' ),
			'logIn' => __( 'Log in', 'licensesender' ),
			'createAccount' => __( 'Create an account', 'licensesender' ),
			'guestNote' => __( 'You sign in on our secure account page. Existing customers keep their order history.', 'licensesender' ),
			'stepSubmitted' => __( 'Application submitted', 'licensesender' ),
			'stepReview' => __( 'In review', 'licensesender' ),
			'stepReviewText' => __( 'We usually reply within 1–2 business days.', 'licensesender' ),
			'stepApproved' => __( 'Approved', 'licensesender' ),
			'stepApprovedText' => __( 'You will get an email and can start ordering.', 'licensesender' ),
			'pendingTitle' => __( 'Your application is in review', 'licensesender' ),
			'pendingLead' => __( 'Thanks for applying. Nothing else is needed from you right now.', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'pendingEmail' => __( 'We will email %s when your account is approved.', 'licensesender' ),
			'approvedTitle' => __( 'You\'re a wholesale partner', 'licensesender' ),
			'approvedLead' => __( 'Your account has wholesale prices. Start an order from the catalog.', 'licensesender' ),
			'openCatalog' => __( 'Open wholesale catalog', 'licensesender' ),
			'reqCompany' => __( 'Enter your company name.', 'licensesender' ),
			'reqEmail' => __( 'Enter a valid email address.', 'licensesender' ),
			'reqWebsite' => __( 'Enter a web address like example.com.', 'licensesender' ),
			'reqMessage' => __( 'Tell us a little about your business.', 'licensesender' ),
			'formTitle' => __( 'Business details', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'formLead' => __( 'Applying as %s.', 'licensesender' ),
			'sectionCompany' => __( 'Company', 'licensesender' ),
			'company' => __( 'Company name', 'licensesender' ),
			'taxId' => __( 'Tax / VAT ID', 'licensesender' ),
			'website' => __( 'Website or store', 'licensesender' ),
			'sectionContact' => __( 'Contact', 'licensesender' ),
			'businessEmail' => __( 'Business email', 'licensesender' ),
			'phone' => __( 'Phone', 'licensesender' ),
			'messenger' => __( 'Telegram or WhatsApp', 'licensesender' ),
			'messengerHint' => __( 'The fastest way for us to reach you about orders.', 'licensesender' ),
			'about' => __( 'About your business', 'licensesender' ),
			'aboutLabel' => __( 'Where and how do you sell?', 'licensesender' ),
			'aboutHint' => __( 'Your store or channel, region, and how many keys you expect to order per month.', 'licensesender' ),
			'formFootnote' => __( 'We review every application and reply by email.', 'licensesender' ),
			'submit' => __( 'Submit application', 'licensesender' ),
			'rejectedTitle' => __( 'Your last application was not approved', 'licensesender' ),
			'rejectedText' => __( 'You are welcome to apply again with more details about your business.', 'licensesender' ),
			'notOrderable' => __( 'Not orderable yet', 'licensesender' ),
			'outOfStock' => __( 'Out of stock', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'onlyLeft' => __( 'Only %d left', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'inStock' => __( '%d in stock', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'nextTier' => __( 'Add %d more for %s per key', 'licensesender' ),
			'decrease' => __( 'Decrease', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'quantityFor' => __( 'Quantity for %s', 'licensesender' ),
			'increase' => __( 'Increase', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'minNote' => __( 'Min. %d keys', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'maxNote' => __( 'Max. %d per order', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'inCartNote' => __( '%d in cart', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'perKey' => __( '%s / key', 'licensesender' ),
			'noOrders' => __( 'No wholesale orders yet', 'licensesender' ),
			'noOrdersHint' => __( 'Your wholesale orders will appear here, ready to order again.', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'orderNumber' => __( 'Order #%s', 'licensesender' ),
			'oneKey' => __( '1 key', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'nKeys' => __( '%d keys', 'licensesender' ),
			'viewOrder' => __( 'View', 'licensesender' ),
			'payNow' => __( 'Pay now', 'licensesender' ),
			'orderAgain' => __( 'Order again', 'licensesender' ),
			'addedSomeToOrder' => __( 'Added to your order. Some products could not be added:', 'licensesender' ),
			'addedToOrder' => __( 'Added to your order.', 'licensesender' ),
			'tabCatalog' => __( 'Catalog', 'licensesender' ),
			'tabQuick' => __( 'Quick order', 'licensesender' ),
			'tabOrders' => __( 'Past orders', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'groupBadgeDiscount' => __( '%s pricing · %s off', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'groupBadge' => __( '%s pricing', 'licensesender' ),
			'portalTitle' => __( 'Order in bulk', 'licensesender' ),
			'portalLead' => __( 'Prices drop as you order more. Keys are delivered to your order as soon as it is paid.', 'licensesender' ),
			'sections' => __( 'Sections', 'licensesender' ),
			'searchPlaceholder' => __( 'Search products or SKU…', 'licensesender' ),
			'clearSearch' => __( 'Clear search', 'licensesender' ),
			'catalogErrorTitle' => __( 'The catalog could not load', 'licensesender' ),
			'catalogErrorText' => __( 'Please reload the page in a moment.', 'licensesender' ),
			'noMatches' => __( 'No products match your search', 'licensesender' ),
			'emptyCatalog' => __( 'No wholesale products yet', 'licensesender' ),
			'noMatchesHint' => __( 'Try another name or SKU.', 'licensesender' ),
			'emptyCatalogHint' => __( 'Check back soon. New products appear here as soon as they are added.', 'licensesender' ),
			'colProduct' => __( 'Product', 'licensesender' ),
			'colPrice' => __( 'Price per key', 'licensesender' ),
			'colQty' => __( 'Quantity', 'licensesender' ),
			'colTotal' => __( 'Total', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'pageOf' => __( 'Page %d of %d', 'licensesender' ),
			'prevPage' => __( 'Previous page', 'licensesender' ),
			'nextPage' => __( 'Next page', 'licensesender' ),
			'oneProduct' => __( '1 product', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'nProducts' => __( '%d products', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'minShort' => __( 'Add %d more keys to reach the %d-key minimum', 'licensesender' ),
			'minMet' => __( 'Minimum order reached', 'licensesender' ),
			'clear' => __( 'Clear', 'licensesender' ),
			'reviewOrder' => __( 'Review order', 'licensesender' ),
			'qoUnknown' => __( 'Not in the wholesale catalog', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'qoBelowMin' => __( 'Minimum is %d keys', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'qoAllInCart' => __( 'No more available: %d already in your order', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'qoTooMany' => __( 'Only %d can be added', 'licensesender' ),
			'qoTitle' => __( 'Quick order', 'licensesender' ),
			'qoLead' => __( 'One product per line: SKU and quantity, e.g. "WIN11PRO, 50". You can also load a CSV file.', 'licensesender' ),
			'qoLoadCsv' => __( 'Load CSV', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'qoInvalid' => __( 'Skipped lines without a SKU and quantity: %s', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'qoReady' => __( '%d of %d lines ready', 'licensesender' ),
			'qoAddOne' => __( 'Add 1 product to order', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'qoAddMany' => __( 'Add %d products to order', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'payWallet' => __( 'Pay %s from wallet', 'licensesender' ),
			'placeOrder' => __( 'Place order', 'licensesender' ),
			'backToCatalog' => __( 'Back to catalog', 'licensesender' ),
			'reviewTitle' => __( 'Review your order', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'removeLine' => __( 'Remove %s', 'licensesender' ),
			'subtotal' => __( 'Subtotal', 'licensesender' ),
			'taxNote' => __( 'Taxes, if any, are added when the order is created.', 'licensesender' ),
			'billedTo' => __( 'Billed to', 'licensesender' ),
			'edit' => __( 'Edit', 'licensesender' ),
			'orderNote' => __( 'Order note or PO number', 'licensesender' ),
			'payment' => __( 'Payment', 'licensesender' ),
			'payWithWallet' => __( 'Wallet balance', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'walletBalance' => __( 'Balance %s · paid instantly', 'licensesender' ),
			/* translators: placeholders follow the English text */
			'walletLow' => __( 'Balance %s is not enough for this order', 'licensesender' ),
			'payOther' => __( 'Another payment method', 'licensesender' ),
			'payOtherText' => __( 'Choose on the next page.', 'licensesender' ),
			'payNextPage' => __( 'You choose how to pay on the next page.', 'licensesender' ),
			'holdNote' => __( 'Your keys are reserved while you pay.', 'licensesender' ),
			'optional' => __( 'Optional', 'licensesender' ),
			'dismiss' => __( 'Dismiss', 'licensesender' ),
			'error' => __( 'Something went wrong. Please try again.', 'licensesender' ),
			'networkError' => __( 'Could not reach the server. Check your connection and try again.', 'licensesender' ),
			'sessionExpired' => __( 'Your session has expired. Please reload the page.', 'licensesender' ),
			/* @strings-end */
		);
	}
}
