<?php

namespace LicenseSender\Admin;

use LicenseSender\Licenses\LicenseEmailService;
use LicenseSender\Support\AccountEndpoint;
use LicenseSender\Core\Api;
use LicenseSender\Core\DesignSystem;
use LicenseSender\Licenses\LicenseCache;
use LicenseSender\Orders\OrderPush;
use LicenseSender\Support\Support;
use LicenseSender\Orders\WebhookReceiver;
use LicenseSender\Wholesale\Wholesale;

defined( 'ABSPATH' ) || exit;

class AdminService {

	/**
	 * licensesender admin page slugs.
	 *
	 * @return string[]
	 */
	public static function get_admin_page_slugs() {
		return array(
			'ls-dashboard',
			'ls-licensesender',
			'ls-licensesender-edit',
			'ls-licensesender-report',
			'ls-licensesender-settings',
			'ls-licensesender-download-links',
			'ls-activation-guides',
			'ls-wholesale-applications',
			'ls-setup',
		);
	}

	/**
	 * Whether the current request is a licensesender admin screen.
	 *
	 * @param string|null $page Optional page slug override.
	 */
	public static function is_plugin_admin_page( $page = null ) {
		if ( $page === null ) {
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		}

		return in_array( $page, self::get_admin_page_slugs(), true );
	}




	/**
	 * Permission check matching legacy AJAX handlers.
	 */
	public static function can_manage() {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
	}

	/**
	 * License table stats.
	 */
	public static function get_license_stats() {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_cached_licenses';

		return array(
			'total'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
			'orders'   => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT order_id) FROM {$table}" ),
			'products' => self::count_licensesender_wc_products(),
			'emails'   => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT email) FROM {$table} WHERE email IS NOT NULL AND email <> ''" ),
		);
	}

	/**
	 * Count published WooCommerce products with LicenseSender enabled.
	 */
	public static function count_licensesender_wc_products() {
		$query = new \WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					'relation' => 'AND',
					array(
						'key'   => '_ls_enabled',
						'value' => 'yes',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => '_ls_wholesale_product',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => '_ls_wholesale_product',
							'value'   => 'yes',
							'compare' => '!=',
						),
					),
				),
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Aggregated dashboard payload for the React/legacy dashboard page.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_dashboard_data() {
		global $wpdb;

		$table = $wpdb->prefix . 'ls_cached_licenses';
		$stats = self::get_license_stats();

		$today_start = current_time( 'Y-m-d 00:00:00' );
		$week_start  = gmdate( 'Y-m-d 00:00:00', strtotime( '-6 days', current_time( 'timestamp' ) ) );

		$stats['today'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
				$today_start
			)
		);
		$stats['week'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
				$week_start
			)
		);
		$stats['emails_sent'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE email_sent = 1"
		);
		$stats['emails_pending'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE email_sent = 0 OR email_sent IS NULL"
		);

		$wholesale_pending = 0;
		if ( class_exists( Wholesale::class ) ) {
			$apps = Wholesale::list_applications( 'pending' );
			$wholesale_pending = is_array( $apps ) ? count( $apps ) : 0;
		}
		$stats['wholesale_pending'] = $wholesale_pending;

		$api_key = (string) get_option( 'lship_api_key', '' );
		$stats['api_connected'] = $api_key !== '';

		// Keys delivered in the last 30 days vs the 30 days before, for the trend badge.
		$day       = DAY_IN_SECONDS;
		$now       = current_time( 'timestamp' );
		$start_30  = gmdate( 'Y-m-d 00:00:00', $now - 29 * $day );
		$start_60  = gmdate( 'Y-m-d 00:00:00', $now - 59 * $day );
		$stats['month'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $start_30 ) );
		$stats['previous_month'] = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND created_at < %s", $start_60, $start_30 )
		);
		$stats['month_orders'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT order_id) FROM {$table} WHERE created_at >= %s", $start_30 ) );

		return array(
			'stats'        => $stats,
			'trend'        => self::get_delivery_trend( $start_30, 30 ),
			'recent'       => self::get_recent_deliveries( 8 ),
			'top_products' => self::get_top_products( $start_30, 5 ),
			'setup'        => array(
				'complete'           => get_option( 'ls_setup_complete', 'no' ) === 'yes',
				'downloads_enabled'  => get_option( 'lship_enable_manage_downloads' ) === 'yes',
				'guides_enabled'     => get_option( 'lship_enable_manage_activation_guides' ) === 'yes',
				'autocomplete'       => get_option( 'lship_autocomplete_order' ) === 'yes',
			),
			'links'        => array(
				'licenses'       => admin_url( 'admin.php?page=ls-licensesender' ),
				'wholesale'      => admin_url( 'admin.php?page=ls-wholesale-applications' ),
				'settings'       => admin_url( 'admin.php?page=ls-licensesender-settings' ),
				'api_settings'   => admin_url( 'admin.php?page=ls-licensesender-settings&tab=api' ),
				'setup'          => admin_url( 'admin.php?page=ls-setup' ),
				'download_links' => admin_url( 'admin.php?page=ls-licensesender-download-links' ),
				'guides'         => admin_url( 'admin.php?page=ls-activation-guides' ),
				'products'       => admin_url( 'edit.php?post_type=product' ),
				'orders'         => function_exists( 'wc_get_container' ) && class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
					? admin_url( 'admin.php?page=wc-orders' )
					: admin_url( 'edit.php?post_type=shop_order' ),
			),
		);
	}

	/**
	 * Keys delivered per day, oldest first, one entry per day (zero-filled).
	 *
	 * @return array<int, array{date: string, count: int}>
	 */
	private static function get_delivery_trend( $start, $days ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_cached_licenses';
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM {$table} WHERE created_at >= %s GROUP BY DATE(created_at)", $start ),
			ARRAY_A
		);
		$by_day = array();
		foreach ( (array) $rows as $row ) {
			$by_day[ $row['d'] ] = (int) $row['c'];
		}

		$trend = array();
		$first = strtotime( $start );
		for ( $i = 0; $i < $days; $i++ ) {
			$date    = gmdate( 'Y-m-d', $first + $i * DAY_IN_SECONDS );
			$trend[] = array(
				'date'  => $date,
				'count' => $by_day[ $date ] ?? 0,
			);
		}
		return $trend;
	}

	/**
	 * Latest delivered keys, key masked, with product and order links.
	 */
	private static function get_recent_deliveries( $limit ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_cached_licenses';
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, order_id, product_id, email, key_value, email_sent, created_at FROM {$table} ORDER BY id DESC LIMIT %d", $limit ),
			ARRAY_A
		);

		$items = array();
		foreach ( (array) $rows as $row ) {
			$key   = (string) $row['key_value'];
			$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $row['order_id'] ) : null;
			$items[] = array(
				'id'          => (int) $row['id'],
				'order_id'    => (int) $row['order_id'],
				'order_url'   => $order ? $order->get_edit_order_url() : '',
				'product'     => get_the_title( (int) $row['product_id'] ) ?: '#' . (int) $row['product_id'],
				'email'       => (string) $row['email'],
				'key_masked'  => strlen( $key ) > 8 ? substr( $key, 0, 4 ) . str_repeat( '•', 4 ) . substr( $key, -4 ) : str_repeat( '•', max( 4, strlen( $key ) ) ),
				'email_sent'  => (int) $row['email_sent'] === 1,
				'created_at'  => (string) $row['created_at'],
				'license_url' => admin_url( 'admin.php?page=ls-licensesender-edit&id=' . (int) $row['id'] ),
			);
		}
		return $items;
	}

	/**
	 * Products with the most keys delivered since $start.
	 */
	private static function get_top_products( $start, $limit ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_cached_licenses';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id, COUNT(*) AS c FROM {$table} WHERE created_at >= %s GROUP BY product_id ORDER BY c DESC LIMIT %d",
				$start,
				$limit
			),
			ARRAY_A
		);

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = array(
				'product_id' => (int) $row['product_id'],
				'name'       => get_the_title( (int) $row['product_id'] ) ?: '#' . (int) $row['product_id'],
				'count'      => (int) $row['c'],
				'edit_url'   => get_edit_post_link( (int) $row['product_id'], 'raw' ) ?: '',
			);
		}
		return $items;
	}

	/**
	 * Builds the WHERE clause shared by the license list, counts and export.
	 *
	 * @param array<string, mixed> $args search, status (sent|pending), product_id, date_from, date_to (Y-m-d).
	 * @param bool                 $with_status Apply the status filter (off for the per-status counts).
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	private static function license_where( $args, $with_status = true ) {
		global $wpdb;
		$where = 'WHERE 1=1';
		$vals  = array();

		$search = trim( (string) ( $args['search'] ?? '' ) );
		if ( $search !== '' ) {
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= ' AND (key_value LIKE %s OR sku LIKE %s OR email LIKE %s OR CAST(order_id AS CHAR) LIKE %s OR CAST(product_id AS CHAR) LIKE %s)';
			array_push( $vals, $like, $like, $like, $like, $like );
		}

		$product_id = (int) ( $args['product_id'] ?? 0 );
		if ( $product_id > 0 ) {
			$where .= ' AND product_id = %d';
			$vals[] = $product_id;
		}

		foreach ( array( 'date_from' => '>=', 'date_to' => '<' ) as $key => $op ) {
			$date = (string) ( $args[ $key ] ?? '' );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				// date_to is inclusive for the user, so compare against the next day.
				$value  = $key === 'date_to' ? gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) ) : $date;
				$where .= " AND created_at {$op} %s";
				$vals[] = $value . ' 00:00:00';
			}
		}

		if ( $with_status ) {
			$status = (string) ( $args['status'] ?? '' );
			if ( $status === 'sent' ) {
				$where .= ' AND email_sent = 1';
			} elseif ( $status === 'pending' ) {
				$where .= ' AND (email_sent = 0 OR email_sent IS NULL)';
			}
		}

		return array( $where, $vals );
	}

	private static function prepare_if( $sql, $vals ) {
		global $wpdb;
		return $vals ? $wpdb->prepare( $sql, $vals ) : $sql;
	}

	/**
	 * Paginated, filtered license list plus per-status counts for the same filters.
	 */
	public static function list_licenses( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_cached_licenses';

		$args = wp_parse_args(
			$args,
			array(
				'page'     => 1,
				'per_page' => 25,
				'orderby'  => 'id',
				'order'    => 'DESC',
			)
		);

		$page     = max( 1, (int) $args['page'] );
		$per_page = max( 1, min( 500, (int) $args['per_page'] ) );
		$offset   = ( $page - 1 ) * $per_page;

		$allowed_orderby = array( 'id', 'key_value', 'order_id', 'product_id', 'sku', 'email', 'created_at', 'email_sent' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'id';
		$order           = strtoupper( (string) $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		list( $where, $vals ) = self::license_where( $args );
		$total = (int) $wpdb->get_var( self::prepare_if( "SELECT COUNT(*) FROM {$table} {$where}", $vals ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, key_value, order_id, product_id, sku, email, email_sent, source, created_at FROM {$table} {$where} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d",
				array_merge( $vals, array( $per_page, $offset ) )
			),
			ARRAY_A
		);

		list( $count_where, $count_vals ) = self::license_where( $args, false );
		$counts = $wpdb->get_row(
			self::prepare_if( "SELECT COUNT(*) AS all_count, SUM(email_sent = 1) AS sent FROM {$table} {$count_where}", $count_vals ),
			ARRAY_A
		);
		$all  = (int) ( $counts['all_count'] ?? 0 );
		$sent = (int) ( $counts['sent'] ?? 0 );

		return array(
			'items'    => array_map( array( __CLASS__, 'format_license_row' ), (array) $rows ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'counts'   => array(
				'all'     => $all,
				'sent'    => $sent,
				'pending' => $all - $sent,
			),
		);
	}

	/**
	 * Every license matching the filters (for CSV export), capped at $limit rows.
	 */
	public static function export_licenses( $args, $limit = 20000 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_cached_licenses';
		list( $where, $vals ) = self::license_where( $args );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, key_value, order_id, product_id, sku, email, email_sent, source, created_at FROM {$table} {$where} ORDER BY id DESC LIMIT %d",
				array_merge( $vals, array( (int) $limit ) )
			),
			ARRAY_A
		);
		return array_map( array( __CLASS__, 'format_license_row' ), (array) $rows );
	}

	/**
	 * Products that have keys, for the product filter.
	 *
	 * @return array<int, array{id:int,name:string,count:int}>
	 */
	public static function license_products() {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_cached_licenses';
		$rows  = $wpdb->get_results( "SELECT product_id, COUNT(*) AS c FROM {$table} GROUP BY product_id ORDER BY c DESC LIMIT 500", ARRAY_A );

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = array(
				'id'    => (int) $row['product_id'],
				'name'  => self::product_name( (int) $row['product_id'] ),
				'count' => (int) $row['c'],
			);
		}
		return $items;
	}

	/**
	 * Resend key emails or re-sync keys from the API for the orders behind $ids.
	 *
	 * @param string          $action resend_email|sync.
	 * @param array<int, int> $ids    License row IDs.
	 * @return array{done:int,failed:int,errors:array<int,string>}
	 */
	public static function bulk_license_action( $action, $ids ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_cached_licenses';
		$ids   = array_slice( array_values( array_filter( array_map( 'absint', (array) $ids ) ) ), 0, 500 );
		if ( ! $ids ) {
			return array( 'done' => 0, 'failed' => 0, 'errors' => array() );
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$orders       = $wpdb->get_results(
			$wpdb->prepare( "SELECT order_id, MAX(email) AS email FROM {$table} WHERE id IN ({$placeholders}) GROUP BY order_id", $ids ),
			ARRAY_A
		);

		$done   = 0;
		$errors = array();
		foreach ( (array) $orders as $row ) {
			$order_id = (int) $row['order_id'];
			$result   = $action === 'sync'
				? self::sync_order_licenses( $order_id, true )
				: self::resend_order_email( $order_id, (string) $row['email'] );

			if ( is_wp_error( $result ) ) {
				/* translators: 1: order number, 2: error message */
				$errors[] = sprintf( __( 'Order #%1$d: %2$s', 'licensesender' ), $order_id, $result->get_error_message() );
			} else {
				$done++;
			}
		}

		return array(
			'done'   => $done,
			'failed' => count( $errors ),
			'errors' => array_slice( $errors, 0, 10 ),
		);
	}

	/**
	 * Sends the order's license email again (same checks as the order screen's resend button).
	 *
	 * @return true|\WP_Error
	 */
	public static function resend_order_email( $order_id, $fallback_email = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new \WP_Error( 'not_found', __( 'Order not found.', 'licensesender' ) );
		}
		// A manual send works even when automatic license emails are off.
		if ( ! LicenseEmailService::is_order_ready_for_email( $order_id ) ) {
			return new \WP_Error( 'not_ready', __( 'Not all license keys have been fetched yet.', 'licensesender' ) );
		}
		$email = sanitize_email( $order->get_billing_email() ) ?: sanitize_email( $fallback_email );
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'no_email', __( 'Order has no valid billing email.', 'licensesender' ) );
		}
		if ( ! LicenseEmailService::send_order_email( (int) $order_id, $email, true ) ) {
			return new \WP_Error( 'send_failed', LicenseEmailService::$last_message ?: __( 'Failed to send license email.', 'licensesender' ) );
		}
		return true;
	}

	private static function product_name( $product_id ) {
		static $names = array();
		if ( ! isset( $names[ $product_id ] ) ) {
			$product               = $product_id ? wc_get_product( $product_id ) : null;
			$names[ $product_id ] = $product ? $product->get_name() : '';
		}
		return $names[ $product_id ];
	}



	private static function format_license_row( $r ) {
		static $orders = array();
		$order_id   = (int) $r['order_id'];
		$product_id = (int) $r['product_id'];

		if ( $order_id && ! array_key_exists( $order_id, $orders ) ) {
			$order               = wc_get_order( $order_id );
			$orders[ $order_id ] = $order
				? array(
					'url'    => $order->get_edit_order_url(),
					'status' => $order->get_status(),
					'number' => (string) $order->get_order_number(),
				)
				: null;
		}
		$order = $order_id ? $orders[ $order_id ] : null;

		return array(
			'id'            => (int) $r['id'],
			'license_key'   => (string) $r['key_value'],
			'order_id'      => $order_id,
			'order_number'  => $order['number'] ?? (string) $order_id,
			'order_link'    => $order['url'] ?? '',
			'order_status'  => $order['status'] ?? '',
			'product_id'    => $product_id,
			'product_name'  => self::product_name( $product_id ),
			'product_link'  => $product_id ? (string) get_edit_post_link( $product_id, 'raw' ) : '',
			'sku'           => (string) $r['sku'],
			'email'         => (string) $r['email'],
			'email_sent'    => (int) ( $r['email_sent'] ?? 0 ) === 1,
			'source'        => (string) ( $r['source'] ?? '' ),
			'sold_at'       => (string) $r['created_at'],
		);
	}

	public static function get_license( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_cached_licenses';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		if ( ! $row ) {
			return null;
		}

		$formatted = self::format_license_row( $row );
		$formatted['download_link']    = (string) ( $row['download_link'] ?? '' );
		$formatted['activation_guide'] = (string) ( $row['activation_guide'] ?? '' );
		$formatted['source']           = (string) ( $row['source'] ?? '' );
		$formatted['remote_license_id'] = (int) ( $row['remote_license_id'] ?? 0 );
		$formatted['last_synced_at']    = (string) ( $row['last_synced_at'] ?? '' );

		return $formatted;
	}

	public static function update_license( $id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_cached_licenses';

		$update = array();
		$format = array();

		if ( isset( $data['key_value'] ) ) {
			$update['key_value'] = sanitize_text_field( $data['key_value'] );
			$format[] = '%s';
		}
		if ( isset( $data['email'] ) ) {
			$update['email'] = sanitize_email( $data['email'] );
			$format[] = '%s';
		}
		if ( isset( $data['sku'] ) ) {
			$update['sku'] = sanitize_text_field( $data['sku'] );
			$format[] = '%s';
		}
		if ( isset( $data['download_link'] ) ) {
			$update['download_link'] = esc_url_raw( $data['download_link'] );
			$format[] = '%s';
		}
		if ( isset( $data['activation_guide'] ) ) {
			$update['activation_guide'] = wp_kses_post( $data['activation_guide'] );
			$format[] = '%s';
		}
		if ( isset( $data['source'] ) ) {
			$update['source'] = sanitize_text_field( $data['source'] );
			$format[] = '%s';
		}

		if ( empty( $update ) ) {
			return false;
		}

		return false !== $wpdb->update( $table, $update, array( 'id' => $id ), $format, array( '%d' ) );
	}

	/**
	 * Report a dead/issue key via API and update local cache.
	 *
	 * @param int                  $id     Local cache row ID.
	 * @param array<string, mixed> $params Report payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function report_license( $id, $params = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_cached_licenses';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		if ( ! $row ) {
			return new \WP_Error( 'not_found', __( 'License not found.', 'licensesender' ), array( 'status' => 404 ) );
		}

		$api_res = Api::report_license(
			array(
				'key'      => (string) $row->key_value,
				'order_id' => (string) $row->order_id,
				'reason'   => (string) ( $params['reason'] ?? 'dead_key' ),
				'mode'     => (string) ( $params['mode'] ?? 'auto' ),
				'notes'    => (string) ( $params['notes'] ?? '' ),
			)
		);

		if ( empty( $api_res['success'] ) ) {
			return new \WP_Error(
				'report_failed',
				(string) ( $api_res['message'] ?? __( 'Could not report license.', 'licensesender' ) ),
				array(
					'status' => ! empty( $api_res['http_code'] ) ? (int) $api_res['http_code'] : 400,
				)
			);
		}

		$report          = is_array( $api_res['report'] ?? null ) ? $api_res['report'] : array();
		$replacement_key = trim( (string) ( $report['replacement_key'] ?? '' ) );
		// Prefer a real license id if the API ever sends one; never use report ticket id.
		$new_remote_id = (int) ( $report['replacement_license_id'] ?? $report['new_license_id'] ?? 0 );

		if ( $replacement_key !== '' && class_exists( LicenseCache::class ) ) {
			LicenseCache::apply_replacement(
				(int) $row->order_id,
				(string) $row->key_value,
				$replacement_key,
				$new_remote_id
			);
		}

		return array(
			'message' => (string) ( $api_res['message'] ?? __( 'License reported successfully.', 'licensesender' ) ),
			'license' => self::get_license( $id ),
			'report'  => $report,
		);
	}

	/**
	 * Pull licenses from API for an order and update local cache.
	 *
	 * @param int  $order_id WooCommerce order ID.
	 * @param bool $force    Skip debounce.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function sync_order_licenses( $order_id, $force = false ) {
		if ( ! class_exists( LicenseCache::class ) ) {
			return new \WP_Error( 'unavailable', __( 'License cache is not available.', 'licensesender' ), array( 'status' => 500 ) );
		}

		$result = LicenseCache::sync_order_licenses( (int) $order_id, (bool) $force );
		if ( empty( $result['success'] ) ) {
			return new \WP_Error(
				'sync_failed',
				(string) ( $result['message'] ?? __( 'Could not sync licenses.', 'licensesender' ) ),
				array( 'status' => 400 )
			);
		}

		return $result;
	}

	public static function fetch_license_by_sku( $sku, $options = array() ) {
		$sku = sanitize_text_field( $sku );
		if ( $sku === '' ) {
			return new \WP_Error( 'missing_sku', __( 'Missing SKU', 'licensesender' ), array( 'status' => 400 ) );
		}

		$sort    = isset( $options['sort'] ) ? sanitize_text_field( $options['sort'] ) : 'id,asc';
		$timeout = isset( $options['timeout'] ) ? (int) $options['timeout'] : 30;

		$api_res = Api::fetch_one_available_license_by_sku(
			$sku,
			array(
				'sort'    => $sort,
				'timeout' => $timeout,
			)
		);

		if ( empty( $api_res['success'] ) ) {
			$code = ! empty( $api_res['http_code'] ) ? (int) $api_res['http_code'] : 500;
			return new \WP_Error(
				'api_error',
				! empty( $api_res['message'] ) ? (string) $api_res['message'] : __( 'Failed to fetch license.', 'licensesender' ),
				array( 'status' => $code, 'meta' => $api_res['meta'] ?? array() )
			);
		}

		$license = isset( $api_res['license'] ) && is_array( $api_res['license'] ) ? $api_res['license'] : array();

		$key_value = '';
		if ( isset( $license['key_value'] ) ) {
			$key_value = (string) $license['key_value'];
		} elseif ( isset( $license['key'] ) ) {
			$key_value = (string) $license['key'];
		} elseif ( isset( $license['masked_key'] ) ) {
			$key_value = (string) $license['masked_key'];
		}

		if ( $key_value === '' ) {
			return new \WP_Error( 'not_found', __( 'No license found for this SKU', 'licensesender' ), array( 'status' => 404 ) );
		}

		return array(
			'key_value'        => $key_value,
			'download_link'    => isset( $license['download_link'] ) ? (string) $license['download_link'] : '',
			'activation_guide' => isset( $license['activation_guide'] ) ? (string) $license['activation_guide'] : '',
			'license'          => $license,
			'meta'             => $api_res['meta'] ?? array(),
		);
	}

	/**
	 * Page choices for wholesale settings dropdowns.
	 *
	 * @return array<int, array{id:string,title:string}>
	 */
	public static function get_page_choices() {
		$choices = array(
			array(
				'id'    => '0',
				'title' => __( '— Select a page —', 'licensesender' ),
			),
		);

		$pages = get_pages(
			array(
				'sort_column' => 'post_title',
				'sort_order'  => 'ASC',
			)
		);

		foreach ( $pages as $page ) {
			$choices[] = array(
				'id'    => (string) $page->ID,
				'title' => $page->post_title,
			);
		}

		return $choices;
	}

	/**
	 * WooCommerce payment gateway choices for wholesale settings.
	 *
	 * @return array<int, array{id:string,title:string,enabled:bool,is_wallet:bool}>
	 */
	public static function get_payment_gateway_choices() {
		if ( ! function_exists( 'WC' ) ) {
			return array();
		}

		if ( is_null( WC()->payment_gateways ) ) {
			WC()->payment_gateways();
		}

		$gateways = WC()->payment_gateways()->payment_gateways();
		$choices  = array();

		foreach ( $gateways as $gateway_id => $gateway ) {
			if ( ! is_object( $gateway ) ) {
				continue;
			}

			$title = method_exists( $gateway, 'get_method_title' ) ? $gateway->get_method_title() : '';
			if ( $title === '' && method_exists( $gateway, 'get_title' ) ) {
				$title = $gateway->get_title();
			}

			$choices[] = array(
				'id'        => sanitize_key( (string) $gateway_id ),
				'title'     => $title !== '' ? $title : sanitize_key( (string) $gateway_id ),
				'enabled'   => isset( $gateway->enabled ) && 'yes' === $gateway->enabled,
				'is_wallet' => in_array( sanitize_key( (string) $gateway_id ), Wholesale::get_wallet_gateway_ids( $gateways ), true ),
			);
		}

		usort(
			$choices,
			static function ( $left, $right ) {
				return strcasecmp( (string) $left['title'], (string) $right['title'] );
			}
		);

		return $choices;
	}

	/**
	 * Normalize saved wholesale payment gateway IDs.
	 *
	 * @param mixed $raw Raw settings value.
	 * @return string[]
	 */
	private static function normalize_wholesale_payment_gateways( $raw ) {
		if ( is_array( $raw ) ) {
			$gateway_ids = $raw;
		} else {
			$gateway_ids = array_filter( array_map( 'trim', explode( ',', (string) $raw ) ) );
		}

		$gateway_ids  = array_map( 'sanitize_key', $gateway_ids );
		$available    = self::get_payment_gateway_choices();
		$available_ids = wp_list_pluck( $available, 'id' );
		$valid        = array();

		foreach ( $gateway_ids as $gateway_id ) {
			if ( in_array( $gateway_id, $available_ids, true ) ) {
				$valid[] = $gateway_id;
			}
		}

		return array_values( array_unique( $valid ) );
	}

	/**
	 * Create or update wholesale storefront pages and link them in settings.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function generate_wholesale_pages() {
		if ( ! current_user_can( 'publish_pages' ) ) {
			return new \WP_Error(
				'cannot_publish_pages',
				__( 'You do not have permission to create pages.', 'licensesender' ),
				array( 'status' => 403 )
			);
		}

		$apply_id = self::ensure_shortcode_page(
			'wholesale-apply',
			__( 'Wholesale Application', 'licensesender' ),
			'[ls_wholesale_apply]',
			Wholesale::get_apply_page_id()
		);

		if ( is_wp_error( $apply_id ) ) {
			return $apply_id;
		}

		$catalog_id = self::ensure_shortcode_page(
			'wholesale',
			__( 'Wholesale', 'licensesender' ),
			'[ls_wholesale_catalog]',
			Wholesale::get_catalog_page_id()
		);

		if ( is_wp_error( $catalog_id ) ) {
			return $catalog_id;
		}

		if ( ! $apply_id || ! $catalog_id ) {
			return new \WP_Error(
				'page_save_failed',
				__( 'Could not create one or more wholesale pages.', 'licensesender' ),
				array( 'status' => 500 )
			);
		}

		update_option( 'lship_wholesale_apply_page_id', (int) $apply_id );
		update_option( 'lship_wholesale_catalog_page_id', (int) $catalog_id );

		clean_post_cache( (int) $apply_id );
		clean_post_cache( (int) $catalog_id );

		return array(
			'message'          => __( 'Wholesale pages generated successfully.', 'licensesender' ),
			'apply_page_id'    => (int) $apply_id,
			'catalog_page_id'  => (int) $catalog_id,
			'apply_page_url'   => get_permalink( $apply_id ),
			'catalog_page_url' => get_permalink( $catalog_id ),
			'apply_edit_url'   => get_edit_post_link( $apply_id, 'raw' ),
			'catalog_edit_url' => get_edit_post_link( $catalog_id, 'raw' ),
			'pages'            => self::get_page_choices(),
		);
	}

	/**
	 * Create or update customer support pages and link them in settings.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function generate_support_pages() {
		if ( ! current_user_can( 'publish_pages' ) ) {
			return new \WP_Error(
				'cannot_publish_pages',
				__( 'You do not have permission to create pages.', 'licensesender' ),
				array( 'status' => 403 )
			);
		}

		$open_id = self::ensure_shortcode_page(
			'support-open-ticket',
			__( 'Open Support Ticket', 'licensesender' ),
			'[ls_support_open]',
			Support::get_open_page_id()
		);

		if ( is_wp_error( $open_id ) ) {
			return $open_id;
		}

		$manage_id = self::ensure_shortcode_page(
			'my-support-tickets',
			__( 'My Support Tickets', 'licensesender' ),
			'[ls_support_manage]',
			Support::get_manage_page_id()
		);

		if ( is_wp_error( $manage_id ) ) {
			return $manage_id;
		}

		if ( ! $open_id || ! $manage_id ) {
			return new \WP_Error(
				'page_save_failed',
				__( 'Could not create one or more support pages.', 'licensesender' ),
				array( 'status' => 500 )
			);
		}

		update_option( 'lship_support_open_page_id', (int) $open_id );
		update_option( 'lship_support_manage_page_id', (int) $manage_id );

		clean_post_cache( (int) $open_id );
		clean_post_cache( (int) $manage_id );

		return array(
			'message'           => __( 'Support pages generated successfully.', 'licensesender' ),
			'open_page_id'      => (int) $open_id,
			'manage_page_id'    => (int) $manage_id,
			'open_page_url'     => get_permalink( $open_id ),
			'manage_page_url'   => get_permalink( $manage_id ),
			'open_edit_url'     => get_edit_post_link( $open_id, 'raw' ),
			'manage_edit_url'   => get_edit_post_link( $manage_id, 'raw' ),
			'pages'             => self::get_page_choices(),
		);
	}

	/**
	 * Find a page by slug.
	 *
	 * @param string $slug Page slug.
	 */
	private static function find_page_by_slug( $slug ) {
		$slug = sanitize_title( $slug );
		if ( $slug === '' ) {
			return 0;
		}

		$posts = get_posts(
			array(
				'post_type'              => 'page',
				'name'                   => $slug,
				'post_status'            => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page'         => 1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'suppress_filters'       => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( ! empty( $posts[0] ) && $posts[0] instanceof \WP_Post ) {
			return (int) $posts[0]->ID;
		}

		$by_path = get_page_by_path( $slug );
		if ( $by_path instanceof \WP_Post ) {
			return (int) $by_path->ID;
		}

		return 0;
	}

	/**
	 * Insert or update a page while preserving shortcode markup.
	 *
	 * @param array<string, mixed> $args Post args.
	 * @return int|WP_Error
	 */
	private static function save_page_record( array $args ) {
		$args = wp_slash( $args );

		$unfiltered = current_user_can( 'unfiltered_html' );
		if ( ! $unfiltered ) {
			kses_remove_filters();
		}

		if ( ! empty( $args['ID'] ) ) {
			$result = wp_update_post( $args, true );
		} else {
			if ( empty( $args['post_author'] ) ) {
				$args['post_author'] = get_current_user_id() ?: 1;
			}
			$result = wp_insert_post( $args, true );
		}

		if ( ! $unfiltered ) {
			kses_init_filters();
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! $result ) {
			return new \WP_Error(
				'page_save_failed',
				__( 'Could not save the page.', 'licensesender' ),
				array( 'status' => 500 )
			);
		}

		return (int) $result;
	}

	/**
	 * Create or update a storefront page with the required shortcode.
	 *
	 * @param string $slug             Page slug.
	 * @param string $title            Page title.
	 * @param string $shortcode_markup Shortcode content.
	 * @param int    $configured_id    Saved page ID from settings.
	 * @return int|WP_Error
	 */
	private static function ensure_shortcode_page( $slug, $title, $shortcode_markup, $configured_id = 0 ) {
		$page_id = 0;

		if ( $configured_id ) {
			$configured_post = get_post( $configured_id );
			if ( $configured_post instanceof \WP_Post && $configured_post->post_type === 'page' ) {
				$page_id = (int) $configured_id;
			}
		}

		if ( ! $page_id ) {
			$page_id = self::find_page_by_slug( $slug );
		}

		$post_args = array(
			'post_title'   => $title,
			'post_name'    => sanitize_title( $slug ),
			'post_content' => $shortcode_markup,
			'post_status'  => 'publish',
			'post_type'    => 'page',
		);

		if ( $page_id ) {
			$post_args['ID'] = $page_id;
		}

		return self::save_page_record( $post_args );
	}

	/**
	 * @deprecated Use ensure_shortcode_page().
	 */
	private static function ensure_wholesale_page( $slug, $title, $shortcode_tag, $shortcode_markup, $configured_id = 0 ) {
		unset( $shortcode_tag );
		return self::ensure_shortcode_page( $slug, $title, $shortcode_markup, $configured_id );
	}

	public static function get_settings( $tab = 'general' ) {
		$tab = sanitize_text_field( $tab );

		switch ( $tab ) {
			case 'api':
				return array(
					'lship_api_key' => get_option( 'lship_api_key', '' ),
				);
			case 'email':
				return array(
					'lship_send_email_after_redeem' => LicenseEmailService::is_enabled() ? 'yes' : 'no',
					'lship_email_sender'            => LicenseEmailService::sender(),
					// Read-only context for the tab.
					'wc_email_url'                  => LicenseEmailService::wc_settings_url(),
					'wc_from_name'                  => (string) get_option( 'woocommerce_email_from_name', get_bloginfo( 'name' ) ),
					'wc_from_address'               => (string) get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) ),
					'wc_sender_url'                 => admin_url( 'admin.php?page=wc-settings&tab=email' ),
					'app_template_url'              => \LicenseSender\Core\Api::panel_url( 'app/email-customize/order_delivery' ),
					'app_shops_url'                 => \LicenseSender\Core\Api::panel_url( 'app/shops' ),
					'app_log_url'                   => \LicenseSender\Core\Api::panel_url( 'app/email-logs' ),
					'admin_email'                   => (string) get_option( 'admin_email', '' ),
				);
			case 'design':
				if ( class_exists( DesignSystem::class ) ) {
					return DesignSystem::get_settings_payload();
				}

				return array(
					'ls_brand'          => get_option( 'ls_brand', '#4f46e5' ),
					'ls_brand_2'        => get_option( 'ls_brand_2', '#6366f1' ),
					'ls_ring'           => get_option( 'ls_ring', '#6366f1' ),
					'ls_success'        => get_option( 'ls_success', '#059669' ),
					'ls_success_2'      => get_option( 'ls_success_2', '#10b981' ),
					'ls_blue_600'       => get_option( 'ls_blue_600', '#2563eb' ),
					'ls_blue_500'       => get_option( 'ls_blue_500', '#3b82f6' ),
					'ls_amber_500'      => get_option( 'ls_amber_500', '#f59e0b' ),
					'ls_amber_400'      => get_option( 'ls_amber_400', '#fbbf24' ),
					'ls_code_bg'        => get_option( 'ls_code_bg', '#1e1e2e' ),
					'ls_code_fg'        => get_option( 'ls_code_fg', '#cdd6f4' ),
					'ls_code_border'    => get_option( 'ls_code_border', '#313244' ),
					'ls_code_accent'    => get_option( 'ls_code_accent', '#89b4fa' ),
					'lship_brand_color' => get_option( 'lship_brand_color', '#4F46E5' ),
					'lship_accent_color'=> get_option( 'lship_accent_color', '#0EA5E9' ),
					'lship_email_logo'  => get_option( 'lship_email_logo', '' ),
				);
			case 'popup':
				return self::get_popup_settings();
			case 'advance':
				$webhook_secret = '';
				$webhook_url    = '';
				if ( class_exists( WebhookReceiver::class ) ) {
					$webhook_secret = WebhookReceiver::ensure_secret();
					$webhook_url    = WebhookReceiver::get_webhook_url();
				}

				return array(
					'lship_webhook_url'     => $webhook_url,
					'lship_webhook_secret'  => $webhook_secret,
					'order_backfill'        => class_exists( OrderPush::class ) ? OrderPush::get_backfill_status() : array(),
				);
			case 'wholesale':
				$payment_gateways = self::normalize_wholesale_payment_gateways( get_option( 'lship_wholesale_payment_gateways', array() ) );

				return array(
					'lship_wholesale_enabled'              => get_option( 'lship_wholesale_enabled', 'yes' ),
					'lship_wholesale_catalog_per_page'     => (string) get_option( 'lship_wholesale_catalog_per_page', 10 ),
					'lship_wholesale_low_stock_threshold'  => (string) get_option( 'lship_wholesale_low_stock_threshold', 10 ),
					'lship_wholesale_min_order_quantity'  => (string) get_option( 'lship_wholesale_min_order_quantity', 0 ),
					'lship_wholesale_allow_backorders'    => get_option( 'lship_wholesale_allow_backorders', 'no' ),
					'lship_wholesale_apply_page_id'        => (string) get_option( 'lship_wholesale_apply_page_id', '' ),
					'lship_wholesale_catalog_page_id'      => (string) get_option( 'lship_wholesale_catalog_page_id', '' ),
					'lship_wholesale_notify_email'         => get_option( 'lship_wholesale_notify_email', '' ),
					'lship_wholesale_payment_mode'         => Wholesale::get_payment_mode(),
					'lship_wholesale_payment_gateways'     => implode( ',', $payment_gateways ),
					'lship_wholesale_auto_approve'         => Wholesale::auto_approves() ? 'yes' : 'no',
					'lship_wholesale_auto_approve_group'   => Wholesale::get_auto_approve_group(),
					'lship_wholesale_hold_minutes'         => (string) Wholesale::get_hold_minutes(),
					'group_choices'                        => Wholesale::get_groups(),
					'admin_email'                          => get_option( 'admin_email', '' ),
					'pages'                                => self::get_page_choices(),
					'payment_gateway_choices'              => self::get_payment_gateway_choices(),
				);
			case 'support':
				return array(
					'lship_support_enabled'           => get_option( 'lship_support_enabled', 'yes' ),
					'lship_support_open_page_id'      => (string) get_option( 'lship_support_open_page_id', '' ),
					'lship_support_manage_page_id'    => (string) get_option( 'lship_support_manage_page_id', '' ),
					'lship_support_my_account'        => get_option( 'lship_support_my_account', 'no' ),
					'lship_support_auth_my_account'   => get_option( 'lship_support_auth_my_account', 'yes' ),
					'pages'                           => self::get_page_choices(),
				);
			case 'chat':
				return array(
					'lship_chat_enabled' => get_option( 'lship_chat_enabled', 'no' ),
					'lship_chat_color'   => get_option( 'lship_chat_color', '' ),
					// Read-only: the Design brand colour, for the "match my brand" choice.
					'design_brand'       => class_exists( DesignSystem::class ) ? DesignSystem::get_brand() : '#4f46e5',
				);
			case 'general':
			default:
				return array(
					'lship_autocomplete_order'               => get_option( 'lship_autocomplete_order', 'no' ),
					'lship_enable_variation_support'         => get_option( 'lship_enable_variation_support', 'no' ),
					'lship_enable_manage_downloads'          => get_option( 'lship_enable_manage_downloads', 'no' ),
					'lship_enable_manage_activation_guides'  => get_option( 'lship_enable_manage_activation_guides', 'no' ),
					'lship_activated_at'                     => ls_ensure_plugin_activation_date(),
					'lship_delivery_start_date'              => ls_get_delivery_start_date(),
				);
		}
	}

	public static function save_settings( $tab, $data ) {
		$tab = sanitize_text_field( $tab );

		switch ( $tab ) {
			case 'general':
				update_option( 'lship_autocomplete_order', ( $data['lship_autocomplete_order'] ?? 'no' ) === 'yes' ? 'yes' : 'no' );
				update_option( 'lship_enable_variation_support', ( $data['lship_enable_variation_support'] ?? 'no' ) === 'yes' ? 'yes' : 'no' );
				update_option( 'lship_enable_manage_downloads', ( $data['lship_enable_manage_downloads'] ?? 'no' ) === 'yes' ? 'yes' : 'no' );
				update_option( 'lship_enable_manage_activation_guides', ( $data['lship_enable_manage_activation_guides'] ?? 'no' ) === 'yes' ? 'yes' : 'no' );
				$start_date = sanitize_text_field( (string) ( $data['lship_delivery_start_date'] ?? '' ) );
				if ( $start_date !== '' && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) ) {
					update_option( 'lship_delivery_start_date', $start_date );
				} else {
					update_option( 'lship_delivery_start_date', ls_ensure_plugin_activation_date() );
				}
				break;
			case 'api':
				update_option( 'lship_api_key', sanitize_text_field( $data['lship_api_key'] ?? '' ) );
				delete_transient( 'ls_api_subscription_details' );
				break;
			case 'email':
				// Subject, heading, text and sender name are WooCommerce email settings now.
				update_option( 'lship_send_email_after_redeem', ( $data['lship_send_email_after_redeem'] ?? 'no' ) === 'yes' ? 'yes' : 'no' );
				update_option(
					LicenseEmailService::OPTION_SENDER,
					( $data['lship_email_sender'] ?? '' ) === LicenseEmailService::SENDER_APP ? LicenseEmailService::SENDER_APP : LicenseEmailService::SENDER_WORDPRESS
				);
				break;
			case 'design':
				if ( class_exists( DesignSystem::class ) ) {
					DesignSystem::save_settings( is_array( $data ) ? $data : array() );
					break;
				}

				$design_keys = array(
					'ls_brand', 'ls_brand_2', 'ls_ring', 'ls_success', 'ls_success_2',
					'ls_blue_600', 'ls_blue_500', 'ls_amber_500', 'ls_amber_400',
					'ls_code_bg', 'ls_code_fg', 'ls_code_border', 'ls_code_accent',
					'lship_brand_color', 'lship_accent_color',
				);
				foreach ( $design_keys as $key ) {
					$raw = isset( $data[ $key ] ) ? wp_unslash( $data[ $key ] ) : '';
					$hex = sanitize_hex_color( $raw );
					update_option( $key, $hex ? $hex : '' );
				}
				if ( isset( $data['lship_email_logo'] ) ) {
					$logo = esc_url_raw( wp_unslash( $data['lship_email_logo'] ) );
					update_option( 'lship_email_logo', $logo );
				}
				break;
			case 'popup':
				// Key delivery texts. Empty = use the built-in (translatable) text.
				foreach ( array_keys( self::KEY_DELIVERY_TEXTS ) as $key ) {
					$raw   = isset( $data[ $key ] ) ? wp_unslash( (string) $data[ $key ] ) : '';
					$clean = 'ls_sw_confirm_text' === $key ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
					update_option( $key, mb_substr( trim( $clean ), 0, 'ls_sw_confirm_text' === $key ? 300 : 80 ) );
				}
				break;
			case 'advance':
				// Webhook credentials are read-only; nothing to save on this tab.
				break;
			case 'wholesale':
				update_option( 'lship_wholesale_enabled', ( $data['lship_wholesale_enabled'] ?? 'no' ) === 'yes' ? 'yes' : 'no' );

				$per_page = isset( $data['lship_wholesale_catalog_per_page'] ) ? (int) $data['lship_wholesale_catalog_per_page'] : 10;
				update_option( 'lship_wholesale_catalog_per_page', max( 1, min( 100, $per_page ) ) );

				$threshold = isset( $data['lship_wholesale_low_stock_threshold'] ) ? (int) $data['lship_wholesale_low_stock_threshold'] : 10;
				update_option( 'lship_wholesale_low_stock_threshold', max( 1, $threshold ) );

				$min_order_quantity = isset( $data['lship_wholesale_min_order_quantity'] ) ? (int) $data['lship_wholesale_min_order_quantity'] : 0;
				update_option( 'lship_wholesale_min_order_quantity', max( 0, $min_order_quantity ) );

				update_option( 'lship_wholesale_allow_backorders', ( $data['lship_wholesale_allow_backorders'] ?? 'no' ) === 'yes' ? 'yes' : 'no' );

				update_option( 'lship_wholesale_apply_page_id', absint( $data['lship_wholesale_apply_page_id'] ?? 0 ) );
				update_option( 'lship_wholesale_catalog_page_id', absint( $data['lship_wholesale_catalog_page_id'] ?? 0 ) );

				if ( ! empty( $data['lship_wholesale_notify_email'] ) && is_email( $data['lship_wholesale_notify_email'] ) ) {
					update_option( 'lship_wholesale_notify_email', sanitize_email( $data['lship_wholesale_notify_email'] ) );
				} else {
					delete_option( 'lship_wholesale_notify_email' );
				}

				$payment_mode = sanitize_key( $data['lship_wholesale_payment_mode'] ?? Wholesale::PAYMENT_MODE_ALL );
				if ( ! in_array( $payment_mode, array( Wholesale::PAYMENT_MODE_ALL, Wholesale::PAYMENT_MODE_WALLET, Wholesale::PAYMENT_MODE_CUSTOM ), true ) ) {
					$payment_mode = Wholesale::PAYMENT_MODE_ALL;
				}
				update_option( 'lship_wholesale_payment_mode', $payment_mode );
				update_option( 'lship_wholesale_payment_gateways', self::normalize_wholesale_payment_gateways( $data['lship_wholesale_payment_gateways'] ?? '' ) );

				update_option( 'lship_wholesale_auto_approve', ( $data['lship_wholesale_auto_approve'] ?? 'no' ) === 'yes' ? 'yes' : 'no' );
				update_option( 'lship_wholesale_auto_approve_group', sanitize_title( (string) ( $data['lship_wholesale_auto_approve_group'] ?? '' ) ) );
				$hold_minutes = isset( $data['lship_wholesale_hold_minutes'] ) ? (int) $data['lship_wholesale_hold_minutes'] : 60;
				update_option( 'lship_wholesale_hold_minutes', max( 5, min( 1440, $hold_minutes ) ) );
				break;
			case 'support':
				update_option( 'lship_support_enabled', ( $data['lship_support_enabled'] ?? 'no' ) === 'yes' ? 'yes' : 'no' );
				update_option( 'lship_support_open_page_id', absint( $data['lship_support_open_page_id'] ?? 0 ) );
				update_option( 'lship_support_manage_page_id', absint( $data['lship_support_manage_page_id'] ?? 0 ) );
				$my_account = ( $data['lship_support_my_account'] ?? 'no' ) === 'yes' ? 'yes' : 'no';
				update_option( 'lship_support_my_account', $my_account );
				update_option(
					'lship_support_auth_my_account',
					( $data['lship_support_auth_my_account'] ?? 'yes' ) === 'yes' ? 'yes' : 'no'
				);
				if ( 'yes' === $my_account && class_exists( AccountEndpoint::class ) ) {
					AccountEndpoint::add_endpoint();
					flush_rewrite_rules( false );
				}
				break;
			case 'chat':
				update_option( 'lship_chat_enabled', ( $data['lship_chat_enabled'] ?? 'no' ) === 'yes' ? 'yes' : 'no' );
				// '' = the widget colour set in LicenseSender, 'brand' = Design brand colour, or a hex.
				$chat_raw   = (string) ( $data['lship_chat_color'] ?? '' );
				$chat_color = 'brand' === $chat_raw ? 'brand' : sanitize_hex_color( $chat_raw );
				update_option( 'lship_chat_color', $chat_color ? $chat_color : '' );
				break;
			default:
				return new \WP_Error( 'invalid_tab', __( 'Invalid settings tab.', 'licensesender' ), array( 'status' => 400 ) );
		}

		return self::get_settings( $tab );
	}

	public static function ping_api() {
		$result = Api::ping();
		if ( ! empty( $result['success'] ) ) {
			$result['subscription_details'] = Api::get_subscription_details( true );
			return $result;
		}
		return new \WP_Error(
			'ping_failed',
			$result['message'] ?? __( 'Unknown error occurred.', 'licensesender' ),
			array( 'status' => 400, 'meta' => $result['meta'] ?? array() )
		);
	}

	public static function get_subscription_details( $force_refresh = false ) {
		return Api::get_subscription_details( $force_refresh );
	}

	public static function list_download_links() {
		global $wpdb;
		$table   = $wpdb->prefix . 'ls_download_links';
		$results = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );
		$data    = array();

		foreach ( $results as $row ) {
			$product      = wc_get_product( (int) $row['product_id'] );
			$product_name = $product ? $product->get_name() : __( '(Product not found)', 'licensesender' );
			$data[] = array(
				'id'           => (int) $row['id'],
				'product_id'   => (int) $row['product_id'],
				'product_name' => $product_name,
				'product_found' => (bool) $product,
				'edit_url'     => $product ? (string) get_edit_post_link( (int) $row['product_id'], 'raw' ) : '',
				'link'         => $row['link'],
				'host'         => (string) wp_parse_url( (string) $row['link'], PHP_URL_HOST ),
				'created_at'   => $row['created_at'] ?? '',
			);
		}

		return $data;
	}

	public static function get_download_link( $id ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'ls_download_links';
		$record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		if ( ! $record ) {
			return null;
		}

		$product_name = get_the_title( $record['product_id'] );
		if ( empty( $product_name ) ) {
			$product_name = __( '(Product not found)', 'licensesender' );
		}

		return array(
			'id'           => (int) $record['id'],
			'product_id'   => (int) $record['product_id'],
			'product_name' => $product_name,
			'link'         => $record['link'],
			'created_at'   => $record['created_at'] ?? '',
		);
	}

	public static function save_download_link( $data ) {
		global $wpdb;
		$table      = $wpdb->prefix . 'ls_download_links';
		$id         = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$product_id = absint( $data['product_id'] ?? 0 );
		$link       = esc_url_raw( $data['link'] ?? '' );

		if ( ! $product_id || empty( $link ) ) {
			return new \WP_Error( 'validation', __( 'Please fill all fields.', 'licensesender' ), array( 'status' => 400 ) );
		}

		$duplicate = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE product_id = %d AND id != %d",
				$product_id,
				$id
			)
		);

		if ( $duplicate > 0 ) {
			return new \WP_Error( 'duplicate', __( 'A download link already exists for this product.', 'licensesender' ), array( 'status' => 400 ) );
		}

		if ( $id > 0 ) {
			$wpdb->update( $table, array( 'product_id' => $product_id, 'link' => $link ), array( 'id' => $id ), array( '%d', '%s' ), array( '%d' ) );
		} else {
			$wpdb->insert( $table, array( 'product_id' => $product_id, 'link' => $link, 'created_at' => current_time( 'mysql' ) ), array( '%d', '%s', '%s' ) );
			$id = (int) $wpdb->insert_id;
		}

		return self::get_download_link( $id );
	}

	public static function delete_download_link( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_download_links';
		return false !== $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Products customers can get keys for, resolved the same way the storefront looks up
	 * download links and guides (variations map to their parent unless variation support is on).
	 *
	 * @return array<int, string> product ID => name
	 */
	public static function license_enabled_products() {
		global $wpdb;
		$ids = $wpdb->get_col(
			"SELECT p.ID FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_ls_enabled' AND m.meta_value = 'yes'
			WHERE p.post_type IN ('product','product_variation') AND p.post_status IN ('publish','private')"
		);

		$products = array();
		foreach ( (array) $ids as $id ) {
			$target = (int) ls_resolve_license_product_id( (int) $id );
			if ( $target && ! isset( $products[ $target ] ) ) {
				$product               = wc_get_product( $target );
				$products[ $target ] = $product ? $product->get_name() : '#' . $target;
			}
		}
		asort( $products, SORT_NATURAL | SORT_FLAG_CASE );
		return $products;
	}

	/**
	 * Which license products have a row in $table (download links or activation guides).
	 *
	 * @return array{enabled:int,covered:int,missing:array<int,array{id:int,name:string,edit_url:string}>}
	 */
	public static function coverage( $table_suffix ) {
		global $wpdb;
		$table    = $wpdb->prefix . $table_suffix;
		$enabled  = self::license_enabled_products();
		$have     = array_map( 'intval', (array) $wpdb->get_col( "SELECT DISTINCT product_id FROM {$table}" ) );
		$missing  = array();
		foreach ( $enabled as $id => $name ) {
			if ( ! in_array( (int) $id, $have, true ) ) {
				$missing[] = array(
					'id'       => (int) $id,
					'name'     => $name,
					'edit_url' => (string) get_edit_post_link( (int) $id, 'raw' ),
				);
			}
		}

		return array(
			'enabled' => count( $enabled ),
			'covered' => count( $enabled ) - count( $missing ),
			'missing' => $missing,
		);
	}

	/**
	 * Checks that download links still answer. HEAD first; some hosts refuse HEAD, so fall back to a 1-byte GET.
	 *
	 * @param array<int, int> $ids Download link IDs (max 25).
	 * @return array<int, array{id:int,ok:bool,status:int,message:string}>
	 */
	public static function check_download_links( $ids ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_download_links';
		$ids   = array_slice( array_values( array_filter( array_map( 'absint', (array) $ids ) ) ), 0, 25 );
		if ( ! $ids ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows         = $wpdb->get_results( $wpdb->prepare( "SELECT id, link FROM {$table} WHERE id IN ({$placeholders})", $ids ), ARRAY_A );

		$results = array();
		foreach ( (array) $rows as $row ) {
			$url  = (string) $row['link'];
			$args = array(
				'timeout'     => 8,
				'redirection' => 5,
				'user-agent'  => 'LicenseSender link check; ' . home_url(),
			);
			$response = wp_safe_remote_head( $url, $args );
			$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			if ( is_wp_error( $response ) || $code === 405 || $code === 403 || $code === 501 ) {
				$response = wp_safe_remote_get( $url, $args + array( 'headers' => array( 'Range' => 'bytes=0-0' ), 'limit_response_size' => 1024 ) );
				$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			}

			$results[] = array(
				'id'      => (int) $row['id'],
				'ok'      => $code >= 200 && $code < 400,
				'status'  => $code,
				'message' => is_wp_error( $response ) ? $response->get_error_message() : (string) wp_remote_retrieve_response_message( $response ),
			);
		}
		return $results;
	}

	/**
	 * Deletes several rows from the download links or activation guides table.
	 */
	public static function bulk_delete( $table_suffix, $ids ) {
		global $wpdb;
		$ids = array_slice( array_values( array_filter( array_map( 'absint', (array) $ids ) ) ), 0, 500 );
		if ( ! $ids ) {
			return 0;
		}
		$table        = $wpdb->prefix . $table_suffix;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids ) );
	}

	public static function search_products( $query, $limit = 20 ) {
		$query = sanitize_text_field( $query );
		$args  = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			's'              => $query,
		);

		$posts = get_posts( $args );
		$items = array();

		foreach ( $posts as $post ) {
			$items[] = array(
				'id'   => $post->ID,
				'name' => $post->post_title,
			);
		}

		return $items;
	}

	/**
	 * Product search where variable products are replaced by their variations
	 * ("Windows 11 – Pro"), for mapping a SKU to the exact item sold.
	 *
	 * @return array<int, array{id:int, name:string}>
	 */
	public static function search_products_with_variations( $query, $limit = 20 ) {
		$items = array();
		foreach ( self::search_products( $query, $limit ) as $row ) {
			$product = wc_get_product( (int) $row['id'] );
			if ( ! $product ) {
				continue;
			}
			if ( ! $product->is_type( 'variable' ) ) {
				$items[] = $row;
				continue;
			}
			foreach ( $product->get_children() as $child_id ) {
				$variation = wc_get_product( $child_id );
				if ( $variation ) {
					$items[] = array(
						'id'   => (int) $child_id,
						'name' => wp_strip_all_tags( $variation->get_name() ),
					);
				}
			}
		}

		return array_slice( $items, 0, 40 );
	}

	public static function list_activation_guides() {
		global $wpdb;
		$table   = $wpdb->prefix . 'ls_activation_guides';
		$results = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );
		$data    = array();

		foreach ( $results as $row ) {
			$product_name = __( '(Product Deleted)', 'licensesender' );
			if ( ! empty( $row['product_id'] ) ) {
				$product = wc_get_product( $row['product_id'] );
				if ( $product ) {
					$product_name = $product->get_name();
				}
			}

			$content = maybe_unserialize( $row['content'] );
			$data[] = array(
				'id'           => (int) $row['id'],
				'product_id'   => (int) $row['product_id'],
				'product_name' => $product_name,
				'type'         => ucfirst( $row['type'] ?? 'text' ),
				'type_key'     => sanitize_key( $row['type'] ?? 'text' ),
				'pdf_link'     => is_array( $content ) ? esc_url_raw( (string) ( $content['pdf'] ?? '' ) ) : '',
				'has_text'     => is_array( $content ) ? trim( wp_strip_all_tags( (string) ( $content['html'] ?? '' ) ) ) !== '' : trim( wp_strip_all_tags( (string) $row['content'] ) ) !== '',
				'edit_url'     => $product_name !== __( '(Product Deleted)', 'licensesender' ) ? (string) get_edit_post_link( (int) $row['product_id'], 'raw' ) : '',
				'created_at'   => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['created_at'] ) ),
				'created_raw'  => (string) $row['created_at'],
			);
		}

		return $data;
	}

	public static function get_activation_guide( $id ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'ls_activation_guides';
		$record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		if ( ! $record ) {
			return null;
		}

		$content_data = maybe_unserialize( $record['content'] );
		$html_content = '';
		$pdf_link     = '';

		if ( is_array( $content_data ) ) {
			$html_content = $content_data['html'] ?? '';
			$pdf_link     = $content_data['pdf'] ?? '';
		} else {
			$html_content = $record['content'];
		}

		$product_name = __( '(Product Deleted)', 'licensesender' );
		if ( ! empty( $record['product_id'] ) ) {
			$product = wc_get_product( $record['product_id'] );
			if ( $product ) {
				$product_name = $product->get_name();
			}
		}

		return array(
			'id'           => (int) $record['id'],
			'product_id'   => (int) $record['product_id'],
			'product_name' => $product_name,
			'type'         => sanitize_text_field( $record['type'] ),
			'html_content' => wp_kses_post( $html_content ),
			'pdf_link'     => esc_url_raw( $pdf_link ),
			'created_at'   => $record['created_at'],
		);
	}

	public static function delete_activation_guide( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_activation_guides';
		return false !== $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Customer-facing texts in the keys section (My Keys, order and thank-you pages).
	 * Option => [ app string key, old built-in defaults that count as "not customised" ].
	 * The option names predate the redesign (they were SweetAlert pop-up texts).
	 */
	const KEY_DELIVERY_TEXTS = array(
		'ls_sw_confirm_title'   => array( 'confirmTitle', array( 'Get license keys?' ) ),
		'ls_sw_confirm_text'    => array( 'confirmText', array( 'We will fetch your license keys for {product}. Continue?' ) ),
		'ls_sw_confirm_btn'     => array( 'confirmBtn', array( 'Yes, get keys' ) ),
		'ls_sw_cancel_btn'      => array( 'cancelBtn', array( 'Cancel' ) ),
		'ls_sw_view_title'      => array( 'viewTitle', array( 'Your License Key', 'Your license key' ) ),
		'ls_sw_view_title_many' => array( 'viewTitleMany', array( 'Your License Keys', 'Your license keys' ) ),
		'ls_sw_view_copy_all'   => array( 'copyAll', array( 'Copy All', 'Copy all' ) ),
	);

	/**
	 * Settings tab values: only what the store typed ('' = built-in text).
	 *
	 * @return array<string, string>
	 */
	public static function get_popup_settings() {
		$settings = array();
		foreach ( self::KEY_DELIVERY_TEXTS as $option => $spec ) {
			$value              = trim( (string) get_option( $option, '' ) );
			// Older versions saved the English defaults on activation; those aren't customisations.
			$settings[ $option ] = in_array( $value, $spec[1], true ) ? '' : $value;
		}

		// Read-only: the preview uses the storefront's brand colour and corner radii.
		$tokens = class_exists( \LicenseSender\Core\DesignSystem::class ) ? \LicenseSender\Core\DesignSystem::resolve_tokens() : array();

		return $settings + array(
			'preview_brand'       => (string) ( $tokens['brand'] ?? '#4f46e5' ),
			'preview_radius_card' => (string) ( $tokens['radius_card'] ?? '14px' ),
			'preview_radius_ctl'  => (string) ( $tokens['radius_ctl'] ?? '10px' ),
		);
	}

	/**
	 * The customised texts as app string keys (blank ones left out, so the translatable
	 * built-in text shows instead).
	 *
	 * @return array<string, string>
	 */
	public static function get_key_delivery_texts() {
		$settings = self::get_popup_settings();
		$texts    = array();
		foreach ( self::KEY_DELIVERY_TEXTS as $option => $spec ) {
			if ( $settings[ $option ] !== '' ) {
				$texts[ $spec[0] ] = $settings[ $option ];
			}
		}

		return $texts;
	}
}
