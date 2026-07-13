<?php
/**
 * Wholesale customers: role, applications, helpers.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Wholesale {

	const ROLE = 'ls_wholesale';
	const TABLE_OPTION = 'ls_wholesale_db_version';
	const TABLE_VERSION = '1.2.0';

	const PAYMENT_MODE_ALL    = 'all';
	const PAYMENT_MODE_WALLET = 'wallet';
	const PAYMENT_MODE_CUSTOM = 'custom';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_role' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ) );
	}

	public static function register_role() {
		if ( get_role( self::ROLE ) ) {
			return;
		}

		$customer = get_role( 'customer' );
		$caps     = $customer ? $customer->capabilities : array( 'read' => true );

		add_role(
			self::ROLE,
			__( 'Wholesale Customer', 'licensesender' ),
			$caps
		);
	}

	public static function maybe_upgrade() {
		$installed = get_option( self::TABLE_OPTION, '' );

		if ( $installed !== self::TABLE_VERSION ) {
			self::create_applications_table();
			self::upgrade_applications_table( $installed );
			self::register_role();
			update_option( self::TABLE_OPTION, self::TABLE_VERSION );
		}
	}

	private static function upgrade_applications_table( $installed_version ) {
		global $wpdb;

		$table = self::table_name();

		if ( version_compare( (string) $installed_version, '1.1.0', '<' ) ) {
			$column = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'messenger_link' ) );
			if ( empty( $column ) ) {
				$wpdb->query( "ALTER TABLE {$table} ADD COLUMN messenger_link VARCHAR(255) NOT NULL DEFAULT '' AFTER phone" );
			}
		}

		if ( version_compare( (string) $installed_version, '1.2.0', '<' ) ) {
			$column = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'website' ) );
			if ( empty( $column ) ) {
				$wpdb->query( "ALTER TABLE {$table} ADD COLUMN website VARCHAR(255) NOT NULL DEFAULT '' AFTER messenger_link" );
			}
		}
	}

	public static function create_applications_table() {
		global $wpdb;

		$table           = $wpdb->prefix . 'ls_wholesale_applications';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			company_name VARCHAR(255) NOT NULL DEFAULT '',
			business_email VARCHAR(255) NOT NULL DEFAULT '',
			phone VARCHAR(100) NOT NULL DEFAULT '',
			messenger_link VARCHAR(255) NOT NULL DEFAULT '',
			website VARCHAR(255) NOT NULL DEFAULT '',
			tax_id VARCHAR(100) NOT NULL DEFAULT '',
			message TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			admin_note TEXT NULL,
			reviewed_by BIGINT UNSIGNED NULL,
			reviewed_at DATETIME NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'ls_wholesale_applications';
	}

	public static function user_is_wholesale( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		return in_array( self::ROLE, (array) $user->roles, true ) || user_can( $user_id, 'manage_options' );
	}

	/**
	 * Whether the wholesale module is enabled in settings.
	 */
	public static function is_enabled() {
		return get_option( 'lship_wholesale_enabled', 'yes' ) === 'yes';
	}

	/**
	 * Catalog products shown per page.
	 */
	public static function get_catalog_per_page() {
		$per_page = (int) get_option( 'lship_wholesale_catalog_per_page', 10 );
		return max( 1, min( 100, $per_page ) );
	}

	/**
	 * Stock level below which the catalog shows a warning badge.
	 */
	public static function get_low_stock_threshold() {
		$threshold = (int) get_option( 'lship_wholesale_low_stock_threshold', 10 );
		return max( 1, $threshold );
	}

	/**
	 * Minimum total wholesale units required to place an order.
	 */
	public static function get_min_order_quantity() {
		return max( 0, (int) get_option( 'lship_wholesale_min_order_quantity', 0 ) );
	}

	/**
	 * Whether customers can order when key inventory is zero or short.
	 * You can restock keys after checkout and deliver when ready.
	 */
	public static function allows_backorders() {
		return get_option( 'lship_wholesale_allow_backorders', 'no' ) === 'yes';
	}

	/**
	 * Whether live SaaS stock must block add-to-cart / checkout.
	 */
	public static function enforces_stock() {
		return ! self::allows_backorders();
	}

	/**
	 * Configured apply page ID.
	 */
	public static function get_apply_page_id() {
		return absint( get_option( 'lship_wholesale_apply_page_id', 0 ) );
	}

	/**
	 * Configured catalog page ID.
	 */
	public static function get_catalog_page_id() {
		return absint( get_option( 'lship_wholesale_catalog_page_id', 0 ) );
	}

	/**
	 * Email address for new wholesale application notifications.
	 */
	public static function get_notify_email() {
		$email = get_option( 'lship_wholesale_notify_email', '' );
		if ( is_email( $email ) ) {
			return sanitize_email( $email );
		}

		$admin_email = get_option( 'admin_email', '' );
		return is_email( $admin_email ) ? sanitize_email( $admin_email ) : '';
	}

	/**
	 * Wholesale checkout payment restriction mode.
	 */
	public static function get_payment_mode() {
		$mode = sanitize_key( get_option( 'lship_wholesale_payment_mode', self::PAYMENT_MODE_ALL ) );

		if ( ! in_array( $mode, array( self::PAYMENT_MODE_ALL, self::PAYMENT_MODE_WALLET, self::PAYMENT_MODE_CUSTOM ), true ) ) {
			return self::PAYMENT_MODE_ALL;
		}

		return $mode;
	}

	/**
	 * Admin-selected payment gateway IDs for custom wholesale checkout mode.
	 *
	 * @return string[]
	 */
	public static function get_custom_payment_gateways() {
		$gateways = get_option( 'lship_wholesale_payment_gateways', array() );

		if ( ! is_array( $gateways ) ) {
			$gateways = array_filter( array_map( 'trim', explode( ',', (string) $gateways ) ) );
		}

		$gateways = array_map( 'sanitize_key', $gateways );

		return array_values( array_unique( array_filter( $gateways ) ) );
	}

	/**
	 * Whether TeraWallet (WooWallet) is available.
	 */
	public static function is_terawallet_active() {
		return function_exists( 'woo_wallet' )
			|| class_exists( 'Woo_Wallet' )
			|| class_exists( 'WooWallet' )
			|| class_exists( 'Woo_Wallet_Wallet' );
	}

	/**
	 * Current user's TeraWallet balance, when the plugin is active.
	 *
	 * @param int $user_id Optional user ID. Defaults to current user.
	 * @return array{amount:float,formatted:string}|null
	 */
	public static function get_user_wallet_balance( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $user_id || ! self::is_terawallet_active() ) {
			return null;
		}

		$amount = null;

		if ( function_exists( 'woo_wallet' ) ) {
			$wallet = woo_wallet();
			if ( is_object( $wallet ) && isset( $wallet->wallet ) && is_object( $wallet->wallet ) && method_exists( $wallet->wallet, 'get_wallet_balance' ) ) {
				$amount = $wallet->wallet->get_wallet_balance( $user_id, 'edit' );
			}
		}

		if ( null === $amount && class_exists( 'Woo_Wallet_Wallet' ) ) {
			$wallet = new Woo_Wallet_Wallet();
			if ( method_exists( $wallet, 'get_wallet_balance' ) ) {
				$amount = $wallet->get_wallet_balance( $user_id, 'edit' );
			}
		}

		if ( null === $amount || false === $amount || '' === $amount ) {
			return null;
		}

		$amount = (float) $amount;

		/**
		 * Filter the wholesale catalog wallet balance payload.
		 *
		 * @param array{amount:float,formatted:string} $balance Balance data.
		 * @param int                                   $user_id User ID.
		 */
		return apply_filters(
			'ls_wholesale_user_wallet_balance',
			array(
				'amount'    => $amount,
				'formatted' => function_exists( 'wc_price' ) ? wc_price( $amount ) : (string) $amount,
			),
			$user_id
		);
	}

	/**
	 * Known TeraWallet and compatible wallet gateway IDs.
	 *
	 * @param array<string, WC_Payment_Gateway>|null $gateways Optional available gateways.
	 * @return string[]
	 */
	public static function get_wallet_gateway_ids( $gateways = null ) {
		$ids = array( 'wallet', 'woo_wallet' );

		if ( is_array( $gateways ) ) {
			foreach ( $gateways as $gateway_id => $gateway ) {
				if ( ! is_object( $gateway ) ) {
					continue;
				}

				$class = get_class( $gateway );
				if ( false !== stripos( $class, 'wallet' ) && false !== stripos( $class, 'gateway' ) ) {
					$ids[] = sanitize_key( (string) $gateway_id );
				}
			}
		}

		/**
		 * Filter wallet gateway IDs used for wholesale wallet-only checkout.
		 *
		 * @param string[]                               $ids      Gateway IDs.
		 * @param array<string, WC_Payment_Gateway>|null $gateways Available gateways.
		 */
		$ids = apply_filters( 'ls_wholesale_wallet_gateway_ids', $ids, $gateways );

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	public static function get_user_application( $user_id = 0 ) {
		global $wpdb;

		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table_name() . ' WHERE user_id = %d ORDER BY id DESC LIMIT 1',
				$user_id
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	public static function submit_application( $user_id, $data ) {
		global $wpdb;

		if ( ! self::is_enabled() ) {
			return new WP_Error( 'disabled', __( 'Wholesale applications are currently closed.', 'licensesender' ) );
		}

		$user_id = (int) $user_id;
		if ( ! $user_id ) {
			return new WP_Error( 'not_logged_in', __( 'You must be logged in to apply.', 'licensesender' ) );
		}

		if ( self::user_is_wholesale( $user_id ) ) {
			return new WP_Error( 'already_wholesale', __( 'Your account already has wholesale access.', 'licensesender' ) );
		}

		$existing = self::get_user_application( $user_id );
		if ( $existing && $existing['status'] === 'pending' ) {
			return new WP_Error( 'pending', __( 'You already have a pending application.', 'licensesender' ) );
		}

		$company         = sanitize_text_field( $data['company_name'] ?? '' );
		$email           = sanitize_email( $data['business_email'] ?? '' );
		$phone           = sanitize_text_field( $data['phone'] ?? '' );
		$messenger_link  = sanitize_text_field( $data['messenger_link'] ?? '' );
		$website         = esc_url_raw( $data['website'] ?? '' );
		$tax_id          = sanitize_text_field( $data['tax_id'] ?? '' );
		$message         = sanitize_textarea_field( $data['message'] ?? '' );

		if ( $company === '' || ! is_email( $email ) ) {
			return new WP_Error( 'validation', __( 'Company name and a valid business email are required.', 'licensesender' ) );
		}

		if ( $message === '' ) {
			return new WP_Error( 'validation', __( 'Please tell us about your business.', 'licensesender' ) );
		}

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'user_id'        => $user_id,
				'company_name'   => $company,
				'business_email' => $email,
				'phone'          => $phone,
				'messenger_link' => $messenger_link,
				'website'        => $website,
				'tax_id'         => $tax_id,
				'message'        => $message,
				'status'         => 'pending',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', __( 'Could not save your application. Please try again.', 'licensesender' ) );
		}

		$application_id = (int) $wpdb->insert_id;
		self::notify_new_application( $application_id );

		return $application_id;
	}

	/**
	 * Send admin notification for a new wholesale application.
	 *
	 * @param int $application_id Application row ID.
	 */
	private static function notify_new_application( $application_id ) {
		if ( class_exists( 'LS_Wholesale_Emails' ) ) {
			LS_Wholesale_Emails::send_new_application( (int) $application_id );
		}
	}

	public static function list_applications( $status = '' ) {
		global $wpdb;

		$table = self::table_name();
		if ( $status !== '' ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC", $status ),
				ARRAY_A
			);
		}

		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );
	}

	public static function get_application( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', $id ),
			ARRAY_A
		);
	}

	public static function delete_application( $id ) {
		global $wpdb;

		$id = (int) $id;
		$application = self::get_application( $id );
		if ( ! $application ) {
			return new WP_Error( 'not_found', __( 'Application not found.', 'licensesender' ) );
		}

		$deleted = $wpdb->delete(
			self::table_name(),
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( ! $deleted ) {
			return new WP_Error( 'db_error', __( 'Could not delete application.', 'licensesender' ) );
		}

		if ( (string) ( $application['status'] ?? '' ) === 'approved' ) {
			self::maybe_revoke_wholesale_role( (int) $application['user_id'] );
		}

		return true;
	}

	/**
	 * Remove wholesale role when a user no longer has approved applications.
	 *
	 * @param int $user_id User ID.
	 */
	private static function maybe_revoke_wholesale_role( $user_id ) {
		global $wpdb;

		$user_id = (int) $user_id;
		if ( ! $user_id ) {
			return;
		}

		$approved_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE user_id = %d AND status = %s',
				$user_id,
				'approved'
			)
		);

		if ( $approved_count > 0 ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( $user ) {
			$user->remove_role( self::ROLE );
		}
	}

	public static function delete_applications( $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
		if ( empty( $ids ) ) {
			return new WP_Error( 'invalid_ids', __( 'No applications selected.', 'licensesender' ) );
		}

		$deleted = 0;
		foreach ( $ids as $id ) {
			$result = self::delete_application( $id );
			if ( ! is_wp_error( $result ) ) {
				++$deleted;
			}
		}

		if ( 0 === $deleted ) {
			return new WP_Error( 'delete_failed', __( 'Could not delete selected applications.', 'licensesender' ) );
		}

		return $deleted;
	}

	public static function review_application( $id, $status, $admin_note = '', $reviewer_id = 0 ) {
		global $wpdb;

		$application = self::get_application( $id );
		if ( ! $application ) {
			return new WP_Error( 'not_found', __( 'Application not found.', 'licensesender' ) );
		}

		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( 'approved', 'rejected' ), true ) ) {
			return new WP_Error( 'invalid_status', __( 'Invalid review status.', 'licensesender' ) );
		}

		if ( $application['status'] === $status ) {
			return true;
		}

		$reviewer_id = $reviewer_id ? (int) $reviewer_id : get_current_user_id();

		$updated = $wpdb->update(
			self::table_name(),
			array(
				'status'      => $status,
				'admin_note'  => sanitize_textarea_field( $admin_note ),
				'reviewed_by' => $reviewer_id,
				'reviewed_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'db_error', __( 'Could not update application.', 'licensesender' ) );
		}

		$user = get_userdata( (int) $application['user_id'] );
		if ( ! $user ) {
			return true;
		}

		if ( $status === 'approved' ) {
			$user->add_role( self::ROLE );
			if ( ! in_array( 'customer', (array) $user->roles, true ) ) {
				$user->add_role( 'customer' );
			}
			if ( class_exists( 'LS_Wholesale_Emails' ) ) {
				LS_Wholesale_Emails::send_application_approved( (int) $id );
			}
		} elseif ( $status === 'rejected' ) {
			$user->remove_role( self::ROLE );
			if ( class_exists( 'LS_Wholesale_Emails' ) ) {
				LS_Wholesale_Emails::send_application_rejected( (int) $id );
			}
		}

		return true;
	}

	public static function get_catalog( $force_refresh = false ) {
		$cache_key = 'ls_wholesale_catalog_v2';
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$result = Licensesender_Api::fetch_wholesale_catalog();
		if ( ! empty( $result['success'] ) ) {
			$ttl = empty( $result['products'] ) ? MINUTE_IN_SECONDS : 2 * MINUTE_IN_SECONDS;
			set_transient( $cache_key, $result, $ttl );
		}

		return $result;
	}

	/**
	 * Clear cached wholesale catalog data.
	 */
	public static function clear_catalog_cache() {
		delete_transient( 'ls_wholesale_catalog' );
		delete_transient( 'ls_wholesale_catalog_v2' );
	}

	/**
	 * Normalize a wholesale catalog product from the API.
	 *
	 * @param array<string, mixed> $product Raw API product row.
	 * @return array<string, mixed>
	 */
	public static function normalize_catalog_product( array $product ) {
		$sku   = (string) ( $product['sku'] ?? '' );
		$tiers = self::parse_wholesale_prices( $product );
		$base  = isset( $product['wholesale_price'] ) ? (float) $product['wholesale_price'] : 0.0;

		if ( $base <= 0 && ! empty( $tiers ) ) {
			$base = (float) $tiers[0]['price'];
		}

		return array(
			'id'               => isset( $product['id'] ) ? (int) $product['id'] : 0,
			'name'             => (string) ( $product['name'] ?? $sku ),
			'sku'              => $sku,
			'wholesale_price'  => $base,
			'wholesale_prices' => $tiers,
			'available_stock'  => isset( $product['available_stock'] ) ? (int) $product['available_stock'] : 0,
			'download_link'    => (string) ( $product['download_link'] ?? '' ),
		);
	}

	/**
	 * Parse quantity-based wholesale price tiers from API product data.
	 *
	 * @param array<string, mixed> $product Raw API product row.
	 * @return array<int, array{min_qty:int, price:float}>
	 */
	public static function parse_wholesale_prices( array $product ) {
		$by_qty = array();

		if ( ! empty( $product['wholesale_prices'] ) && is_array( $product['wholesale_prices'] ) ) {
			foreach ( $product['wholesale_prices'] as $tier ) {
				if ( ! is_array( $tier ) ) {
					continue;
				}

				$min_qty = isset( $tier['min_qty'] ) ? (int) $tier['min_qty'] : 0;
				$price   = isset( $tier['price'] ) ? (float) $tier['price'] : -1;

				if ( $min_qty > 0 && $price >= 0 ) {
					$by_qty[ $min_qty ] = $price;
				}
			}
		}

		foreach ( $product as $key => $value ) {
			if ( ! is_string( $key ) || ! preg_match( '/^wholesale_price_(\d+)$/', $key, $matches ) ) {
				continue;
			}

			$min_qty = (int) $matches[1];
			$price   = (float) $value;

			if ( $min_qty > 0 && $price >= 0 ) {
				$by_qty[ $min_qty ] = $price;
			}
		}

		ksort( $by_qty, SORT_NUMERIC );

		$tiers = array();
		foreach ( $by_qty as $min_qty => $price ) {
			$tiers[] = array(
				'min_qty' => (int) $min_qty,
				'price'   => (float) $price,
			);
		}

		return $tiers;
	}

	/**
	 * Resolve the unit price for a wholesale product at a given quantity.
	 *
	 * @param array<string, mixed> $product  Normalized catalog product.
	 * @param int                  $quantity Requested quantity.
	 */
	public static function get_price_for_quantity( array $product, $quantity ) {
		$qty   = max( 1, (int) $quantity );
		$base  = isset( $product['wholesale_price'] ) ? (float) $product['wholesale_price'] : 0.0;
		$tiers = $product['wholesale_prices'] ?? array();

		if ( empty( $tiers ) ) {
			return $base;
		}

		usort(
			$tiers,
			static function ( $left, $right ) {
				return (int) ( $left['min_qty'] ?? 0 ) <=> (int) ( $right['min_qty'] ?? 0 );
			}
		);

		$matched = null;
		foreach ( $tiers as $tier ) {
			if ( $qty >= (int) $tier['min_qty'] ) {
				$matched = (float) $tier['price'];
			}
		}

		return null !== $matched ? $matched : $base;
	}

	/**
	 * Find a normalized catalog product by SKU.
	 *
	 * @param string              $sku     Product SKU.
	 * @param array<string,mixed>|null $catalog Optional catalog payload.
	 */
	public static function get_catalog_product_by_sku( $sku, $catalog = null ) {
		if ( null === $catalog ) {
			$catalog = self::get_catalog();
		}

		if ( empty( $catalog['success'] ) || empty( $catalog['products'] ) ) {
			return null;
		}

		foreach ( $catalog['products'] as $product ) {
			if ( (string) ( $product['sku'] ?? '' ) === (string) $sku ) {
				return $product;
			}
		}

		return null;
	}

	/**
	 * Build a human-readable tier pricing label.
	 *
	 * @param array<string, mixed> $product Normalized catalog product.
	 */
	public static function format_price_tiers_label( array $product ) {
		$tiers = $product['wholesale_prices'] ?? array();
		if ( empty( $tiers ) ) {
			return '';
		}

		$parts = array();
		foreach ( $tiers as $tier ) {
			$parts[] = sprintf(
				'%d+: %s',
				(int) $tier['min_qty'],
				wp_strip_all_tags( LS_Wholesale_Currency::format_price( $tier['price'] ) )
			);
		}

		return implode( ' · ', $parts );
	}

	/**
	 * Collect unique tier quantity columns across catalog products.
	 *
	 * @param array<int, array<string, mixed>> $products Normalized catalog products.
	 * @return int[]
	 */
	public static function collect_catalog_tier_columns( array $products ) {
		$columns = array();

		foreach ( $products as $product ) {
			foreach ( $product['wholesale_prices'] ?? array() as $tier ) {
				$columns[ (int) $tier['min_qty'] ] = true;
			}
		}

		$columns = array_keys( $columns );
		sort( $columns, SORT_NUMERIC );

		return $columns;
	}

	/**
	 * Map tier minimum quantities to prices for a product.
	 *
	 * @param array<string, mixed> $product Normalized catalog product.
	 * @return array<int, float>
	 */
	public static function get_tier_price_map( array $product ) {
		$map = array();

		foreach ( $product['wholesale_prices'] ?? array() as $tier ) {
			$map[ (int) $tier['min_qty'] ] = (float) $tier['price'];
		}

		return $map;
	}

	/**
	 * Build a short display tag for a catalog product.
	 *
	 * @param array<string, mixed> $product Normalized catalog product.
	 */
	public static function get_product_display_tag( array $product ) {
		$name = (string) ( $product['name'] ?? '' );
		$sku  = (string) ( $product['sku'] ?? '' );

		if ( stripos( $name, 'windows' ) !== false || stripos( $sku, 'win' ) !== false ) {
			return 'WINDOWS';
		}

		if ( stripos( $name, 'office' ) !== false ) {
			return 'OFFICE';
		}

		$words = preg_split( '/\s+/', trim( $name ) );
		if ( ! empty( $words[0] ) ) {
			return strtoupper( preg_replace( '/[^a-z0-9]/i', '', $words[0] ) );
		}

		return strtoupper( $sku );
	}

	/**
	 * Build a storefront-style SKU slug for display.
	 *
	 * @param array<string, mixed> $product Normalized catalog product.
	 */
	public static function get_product_display_slug( array $product ) {
		$sku = strtolower( (string) ( $product['sku'] ?? '' ) );
		if ( $sku !== '' ) {
			return $sku;
		}

		$slug = sanitize_title( (string) ( $product['name'] ?? '' ) );
		return str_replace( '-', '_', $slug );
	}

	/**
	 * Build a normalized product array from WooCommerce product meta.
	 *
	 * @param int $product_id WooCommerce product ID.
	 */
	public static function get_catalog_product_from_meta( $product_id ) {
		$sku = (string) get_post_meta( $product_id, '_ls_wholesale_sku', true );
		if ( $sku === '' ) {
			$sku = (string) get_post_meta( $product_id, '_ls_mapped_product', true );
		}
		if ( $sku === '' ) {
			return null;
		}

		$tiers_json = get_post_meta( $product_id, '_ls_wholesale_prices', true );
		$tiers      = array();

		if ( is_string( $tiers_json ) && $tiers_json !== '' ) {
			$decoded = json_decode( $tiers_json, true );
			if ( is_array( $decoded ) ) {
				$tiers = self::parse_wholesale_prices( array( 'wholesale_prices' => $decoded ) );
			}
		}

		$base_price = get_post_meta( $product_id, '_ls_wholesale_price', true );

		return array(
			'sku'              => $sku,
			'wholesale_price'  => is_numeric( $base_price ) ? (float) $base_price : 0.0,
			'wholesale_prices' => $tiers,
		);
	}
}

LS_Wholesale::init();
