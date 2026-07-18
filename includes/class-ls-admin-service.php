<?php
/**
 * Shared admin business logic for REST and legacy AJAX handlers.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Admin_Service {

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
	 * Whether the current screen uses the React admin shell.
	 */
	public static function is_react_admin_screen() {
		return self::uses_react_admin() && self::is_plugin_admin_page();
	}

	/**
	 * Whether the React admin UI should be used.
	 */
	public static function uses_react_admin() {
		$version = get_option( 'ls_admin_ui_version', 'react' );
		return $version === 'react' && self::build_exists();
	}

	/**
	 * Check if compiled React assets exist.
	 */
	public static function build_exists() {
		return file_exists( plugin_dir_path( dirname( __FILE__ ) ) . 'admin/build/licenses.js' );
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
		$query = new WP_Query(
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
		if ( class_exists( 'LS_Wholesale' ) ) {
			$apps = LS_Wholesale::list_applications( 'pending' );
			$wholesale_pending = is_array( $apps ) ? count( $apps ) : 0;
		}
		$stats['wholesale_pending'] = $wholesale_pending;

		$api_key = (string) get_option( 'lship_api_key', '' );
		$stats['api_connected'] = $api_key !== '';

		$links = array(
			'licenses'  => admin_url( 'admin.php?page=ls-licensesender' ),
			'wholesale' => admin_url( 'admin.php?page=ls-wholesale-applications' ),
			'settings'  => admin_url( 'admin.php?page=ls-licensesender-settings&tab=api' ),
		);

		return array(
			'stats' => $stats,
			'links' => $links,
		);
	}

	/**
	 * Paginated license list for REST (non-DataTables shape).
	 */
	public static function list_licenses( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_cached_licenses';

		$defaults = array(
			'page'     => 1,
			'per_page' => 25,
			'search'   => '',
			'orderby'  => 'id',
			'order'    => 'DESC',
		);
		$args = wp_parse_args( $args, $defaults );

		$page     = max( 1, (int) $args['page'] );
		$per_page = max( 1, min( 500, (int) $args['per_page'] ) );
		$offset   = ( $page - 1 ) * $per_page;
		$search   = trim( (string) $args['search'] );

		$allowed_orderby = array( 'id', 'key_value', 'order_id', 'product_id', 'sku', 'email', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'id';
		$order           = strtoupper( (string) $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$where = 'WHERE 1=1';
		$sql_args = array();

		if ( $search !== '' ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$where  .= ' AND (key_value LIKE %s OR sku LIKE %s OR email LIKE %s OR CAST(order_id AS CHAR) LIKE %s OR CAST(product_id AS CHAR) LIKE %s)';
			$sql_args = array( $like, $like, $like, $like, $like );
		}

		$records_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		if ( $search !== '' ) {
			$records_filtered = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where}", $sql_args ) );
		} else {
			$records_filtered = $records_total;
		}

		$sql = "SELECT id, key_value, order_id, product_id, sku, email, created_at FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$params = array_merge( $sql_args, array( $per_page, $offset ) );
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		$items = array();
		foreach ( $rows as $r ) {
			$items[] = self::format_license_row( $r );
		}

		return array(
			'items'    => $items,
			'total'    => $records_filtered,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * DataTables-compatible response (legacy).
	 */
	public static function list_licenses_datatables( $post ) {
		$draw   = isset( $post['draw'] ) ? (int) $post['draw'] : 0;
		$start  = isset( $post['start'] ) ? max( 0, (int) $post['start'] ) : 0;
		$length = isset( $post['length'] ) ? (int) $post['length'] : 25;
		if ( $length < 1 || $length > 500 ) {
			$length = 25;
		}

		$cols = array(
			0 => 'id',
			1 => 'key_value',
			2 => 'order_id',
			3 => 'product_id',
			4 => 'sku',
			5 => 'email',
			6 => 'created_at',
		);

		$search  = isset( $post['search']['value'] ) ? trim( wp_unslash( $post['search']['value'] ) ) : '';
		$orderby = 'id';
		$order   = 'DESC';

		if ( isset( $post['order'][0]['column'], $post['order'][0]['dir'] ) ) {
			$idx = (int) $post['order'][0]['column'];
			$dir = strtolower( sanitize_text_field( $post['order'][0]['dir'] ) ) === 'asc' ? 'ASC' : 'DESC';
			if ( isset( $cols[ $idx ] ) ) {
				$orderby = $cols[ $idx ];
				$order   = $dir;
			}
		}

		$result = self::list_licenses(
			array(
				'page'     => (int) floor( $start / $length ) + 1,
				'per_page' => $length,
				'search'   => $search,
				'orderby'  => $orderby,
				'order'    => $order,
			)
		);

		global $wpdb;
		$table         = $wpdb->prefix . 'ls_cached_licenses';
		$records_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$out = array();
		foreach ( $result['items'] as $item ) {
			$out[] = array(
				'id'           => $item['id'],
				'key_value'    => $item['license_key'],
				'order_id'     => $item['order_id'],
				'order_link'   => $item['order_link'],
				'product_id'   => $item['product_id'],
				'product_name' => $item['product_name'],
				'product_link' => $item['product_link'],
				'sku'          => $item['sku'],
				'email'        => $item['email'],
				'created_at'   => $item['sold_at'],
				'action'       => $item['id'],
			);
		}

		return array(
			'draw'            => $draw,
			'recordsTotal'    => $records_total,
			'recordsFiltered' => $result['total'],
			'data'            => $out,
		);
	}

	private static function format_license_row( $r ) {
		$order_id   = (int) $r['order_id'];
		$product_id = (int) $r['product_id'];
		$product_name = '';
		$product_link = '';

		if ( $product_id ) {
			$p = wc_get_product( $product_id );
			if ( $p ) {
				$product_name = $p->get_name();
				$product_link = get_edit_post_link( $product_id, 'raw' );
			}
		}

		return array(
			'id'           => (int) $r['id'],
			'license_key'  => (string) $r['key_value'],
			'order_id'     => $order_id,
			'order_link'   => $order_id ? admin_url( 'post.php?post=' . $order_id . '&action=edit' ) : '',
			'product_id'   => $product_id,
			'product_name' => $product_name,
			'product_link' => $product_link ?: '',
			'sku'          => (string) $r['sku'],
			'email'        => (string) $r['email'],
			'sold_at'      => (string) $r['created_at'],
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
			return new WP_Error( 'not_found', __( 'License not found.', 'licensesender' ), array( 'status' => 404 ) );
		}

		$api_res = Licensesender_Api::report_license(
			array(
				'key'      => (string) $row->key_value,
				'order_id' => (string) $row->order_id,
				'reason'   => (string) ( $params['reason'] ?? 'dead_key' ),
				'mode'     => (string) ( $params['mode'] ?? 'auto' ),
				'notes'    => (string) ( $params['notes'] ?? '' ),
			)
		);

		if ( empty( $api_res['success'] ) ) {
			return new WP_Error(
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

		if ( $replacement_key !== '' && class_exists( 'LS_License_Cache' ) ) {
			LS_License_Cache::apply_replacement(
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
		if ( ! class_exists( 'LS_License_Cache' ) ) {
			return new WP_Error( 'unavailable', __( 'License cache is not available.', 'licensesender' ), array( 'status' => 500 ) );
		}

		$result = LS_License_Cache::sync_order_licenses( (int) $order_id, (bool) $force );
		if ( empty( $result['success'] ) ) {
			return new WP_Error(
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
			return new WP_Error( 'missing_sku', __( 'Missing SKU', 'licensesender' ), array( 'status' => 400 ) );
		}

		$sort    = isset( $options['sort'] ) ? sanitize_text_field( $options['sort'] ) : 'id,asc';
		$timeout = isset( $options['timeout'] ) ? (int) $options['timeout'] : 30;

		$api_res = Licensesender_Api::fetch_one_available_license_by_sku(
			$sku,
			array(
				'sort'    => $sort,
				'timeout' => $timeout,
			)
		);

		if ( empty( $api_res['success'] ) ) {
			$code = ! empty( $api_res['http_code'] ) ? (int) $api_res['http_code'] : 500;
			return new WP_Error(
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
			return new WP_Error( 'not_found', __( 'No license found for this SKU', 'licensesender' ), array( 'status' => 404 ) );
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
				'is_wallet' => in_array( sanitize_key( (string) $gateway_id ), LS_Wholesale::get_wallet_gateway_ids( $gateways ), true ),
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
			return new WP_Error(
				'cannot_publish_pages',
				__( 'You do not have permission to create pages.', 'licensesender' ),
				array( 'status' => 403 )
			);
		}

		$apply_id = self::ensure_shortcode_page(
			'wholesale-apply',
			__( 'Wholesale Application', 'licensesender' ),
			'[ls_wholesale_apply]',
			LS_Wholesale::get_apply_page_id()
		);

		if ( is_wp_error( $apply_id ) ) {
			return $apply_id;
		}

		$catalog_id = self::ensure_shortcode_page(
			'wholesale',
			__( 'Wholesale', 'licensesender' ),
			'[ls_wholesale_catalog]',
			LS_Wholesale::get_catalog_page_id()
		);

		if ( is_wp_error( $catalog_id ) ) {
			return $catalog_id;
		}

		if ( ! $apply_id || ! $catalog_id ) {
			return new WP_Error(
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
			return new WP_Error(
				'cannot_publish_pages',
				__( 'You do not have permission to create pages.', 'licensesender' ),
				array( 'status' => 403 )
			);
		}

		$open_id = self::ensure_shortcode_page(
			'support-open-ticket',
			__( 'Open Support Ticket', 'licensesender' ),
			'[ls_support_open]',
			LS_Support::get_open_page_id()
		);

		if ( is_wp_error( $open_id ) ) {
			return $open_id;
		}

		$manage_id = self::ensure_shortcode_page(
			'my-support-tickets',
			__( 'My Support Tickets', 'licensesender' ),
			'[ls_support_manage]',
			LS_Support::get_manage_page_id()
		);

		if ( is_wp_error( $manage_id ) ) {
			return $manage_id;
		}

		if ( ! $open_id || ! $manage_id ) {
			return new WP_Error(
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

		if ( ! empty( $posts[0] ) && $posts[0] instanceof WP_Post ) {
			return (int) $posts[0]->ID;
		}

		$by_path = get_page_by_path( $slug );
		if ( $by_path instanceof WP_Post ) {
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
			return new WP_Error(
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
			if ( $configured_post instanceof WP_Post && $configured_post->post_type === 'page' ) {
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
					'lship_email_sender_name'    => get_option( 'lship_email_sender_name', '' ),
					'lship_email_sender_email'   => get_option( 'lship_email_sender_email', '' ),
					'lship_email_subject'        => get_option( 'lship_email_subject', '' ),
					'lship_email_subject_single' => get_option( 'lship_email_subject_single', '' ),
					'lship_email_subject_bulk'   => get_option( 'lship_email_subject_bulk', '' ),
					'lship_email_preheader'      => get_option( 'lship_email_preheader', '' ),
					'lship_email_intro_single'   => get_option( 'lship_email_intro_single', '' ),
					'lship_email_intro_bulk'     => get_option( 'lship_email_intro_bulk', '' ),
					'lship_support_email'        => get_option( 'lship_support_email', '' ),
					'admin_email'                => get_option( 'admin_email', '' ),
					'lship_email_logo'           => get_option( 'lship_email_logo', '' ),
					'ls_brand'                   => get_option( 'ls_brand', '#4f46e5' ),
					'lship_brand_color'          => get_option( 'lship_brand_color', '#4F46E5' ),
				);
			case 'design':
				if ( class_exists( 'LS_Design_System' ) ) {
					return LS_Design_System::get_settings_payload();
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
				if ( class_exists( 'LS_Webhook_Receiver' ) ) {
					$webhook_secret = LS_Webhook_Receiver::ensure_secret();
					$webhook_url    = LS_Webhook_Receiver::get_webhook_url();
				}

				return array(
					'lship_sso_enabled'     => get_option( 'lship_sso_enabled', 'no' ),
					'lship_sso_token'       => get_option( 'lship_sso_token', '' ),
					'lship_sso_user_email'  => get_option( 'lship_sso_user_email', '' ),
					'lship_webhook_url'     => $webhook_url,
					'lship_webhook_secret'  => $webhook_secret,
					'order_backfill'        => class_exists( 'LS_Order_Push' ) ? LS_Order_Push::get_backfill_status() : array(),
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
					'lship_wholesale_payment_mode'         => LS_Wholesale::get_payment_mode(),
					'lship_wholesale_payment_gateways'     => implode( ',', $payment_gateways ),
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
					'lship_chat_enabled'        => get_option( 'lship_chat_enabled', 'no' ),
					'lship_chat_require_email'  => get_option( 'lship_chat_require_email', 'no' ),
					'lship_chat_title'          => get_option( 'lship_chat_title', '' ),
					'lship_chat_welcome'        => get_option( 'lship_chat_welcome', '' ),
					'lship_chat_color'          => get_option( 'lship_chat_color', get_option( 'ls_brand', '#0f766e' ) ),
					'lship_chat_launcher_style' => get_option( 'lship_chat_launcher_style', 'icon' ),
				);
			case 'general':
			default:
				return array(
					'lship_autocomplete_order'               => get_option( 'lship_autocomplete_order', 'no' ),
					'lship_send_email_after_redeem'          => get_option( 'lship_send_email_after_redeem', 'no' ),
					'lship_enable_variation_support'         => get_option( 'lship_enable_variation_support', 'no' ),
					'lship_enable_manage_downloads'          => get_option( 'lship_enable_manage_downloads', 'no' ),
					'lship_enable_manage_activation_guides'  => get_option( 'lship_enable_manage_activation_guides', 'no' ),
				);
		}
	}

	public static function save_settings( $tab, $data ) {
		$tab = sanitize_text_field( $tab );

		switch ( $tab ) {
			case 'general':
				update_option( 'lship_autocomplete_order', ! empty( $data['lship_autocomplete_order'] ) ? 'yes' : 'no' );
				update_option( 'lship_send_email_after_redeem', ! empty( $data['lship_send_email_after_redeem'] ) ? 'yes' : 'no' );
				update_option( 'lship_enable_variation_support', ( $data['lship_enable_variation_support'] ?? 'no' ) === 'yes' ? 'yes' : 'no' );
				update_option( 'lship_enable_manage_downloads', ( $data['lship_enable_manage_downloads'] ?? 'no' ) === 'yes' ? 'yes' : 'no' );
				update_option( 'lship_enable_manage_activation_guides', ( $data['lship_enable_manage_activation_guides'] ?? 'no' ) === 'yes' ? 'yes' : 'no' );
				break;
			case 'api':
				update_option( 'lship_api_key', sanitize_text_field( $data['lship_api_key'] ?? '' ) );
				delete_transient( 'ls_api_subscription_details' );
				break;
			case 'email':
				if ( isset( $data['lship_email_sender_name'] ) ) {
					update_option( 'lship_email_sender_name', sanitize_text_field( $data['lship_email_sender_name'] ) );
				}
				if ( isset( $data['lship_email_sender_email'] ) && is_email( $data['lship_email_sender_email'] ) ) {
					update_option( 'lship_email_sender_email', sanitize_email( $data['lship_email_sender_email'] ) );
				} else {
					delete_option( 'lship_email_sender_email' );
				}
				$text_fields = array(
					'lship_email_subject',
					'lship_email_subject_single',
					'lship_email_subject_bulk',
					'lship_email_preheader',
					'lship_support_email',
				);
				foreach ( $text_fields as $key ) {
					if ( isset( $data[ $key ] ) ) {
						update_option( $key, sanitize_text_field( $data[ $key ] ) );
					}
				}
				$textarea_fields = array( 'lship_email_intro_single', 'lship_email_intro_bulk' );
				foreach ( $textarea_fields as $key ) {
					if ( isset( $data[ $key ] ) ) {
						update_option( $key, sanitize_textarea_field( $data[ $key ] ) );
					}
				}
				break;
			case 'design':
				if ( class_exists( 'LS_Design_System' ) ) {
					LS_Design_System::save_settings( is_array( $data ) ? $data : array() );
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
				$popup_text_keys = array(
					'ls_sw_confirm_title', 'ls_sw_confirm_text', 'ls_sw_confirm_btn', 'ls_sw_cancel_btn',
					'ls_sw_bulk_title', 'ls_sw_bulk_text', 'ls_sw_bulk_confirm_btn', 'ls_sw_bulk_cancel_btn',
					'ls_sw_bulk_done_title', 'ls_sw_bulk_done_text',
					'ls_sw_view_title', 'ls_sw_view_title_many', 'ls_sw_view_copy_all', 'ls_sw_view_close',
				);
				foreach ( $popup_text_keys as $key ) {
					$raw = isset( $data[ $key ] ) ? wp_unslash( $data[ $key ] ) : '';
					update_option( $key, in_array( $key, array( 'ls_sw_confirm_text', 'ls_sw_bulk_text', 'ls_sw_bulk_done_text' ), true )
						? sanitize_textarea_field( $raw )
						: sanitize_text_field( $raw ) );
				}
				foreach ( array( 'ls_sw_confirm_color', 'ls_sw_cancel_color' ) as $key ) {
					$raw = isset( $data[ $key ] ) ? wp_unslash( $data[ $key ] ) : '';
					$hex = sanitize_hex_color( $raw );
					update_option( $key, $hex ? $hex : '' );
				}
				break;
			case 'advance':
				update_option( 'lship_sso_enabled', ( $data['lship_sso_enabled'] ?? 'no' ) === 'yes' ? 'yes' : 'no' );
				if ( ! empty( $data['lship_sso_token'] ) ) {
					update_option( 'lship_sso_token', sanitize_text_field( $data['lship_sso_token'] ) );
				}
				if ( ! empty( $data['lship_sso_user_email'] ) ) {
					update_option( 'lship_sso_user_email', sanitize_email( $data['lship_sso_user_email'] ) );
				}
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

				$payment_mode = sanitize_key( $data['lship_wholesale_payment_mode'] ?? LS_Wholesale::PAYMENT_MODE_ALL );
				if ( ! in_array( $payment_mode, array( LS_Wholesale::PAYMENT_MODE_ALL, LS_Wholesale::PAYMENT_MODE_WALLET, LS_Wholesale::PAYMENT_MODE_CUSTOM ), true ) ) {
					$payment_mode = LS_Wholesale::PAYMENT_MODE_ALL;
				}
				update_option( 'lship_wholesale_payment_mode', $payment_mode );
				update_option( 'lship_wholesale_payment_gateways', self::normalize_wholesale_payment_gateways( $data['lship_wholesale_payment_gateways'] ?? '' ) );
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
				if ( 'yes' === $my_account && class_exists( 'LS_Support_Endpoint' ) ) {
					LS_Support_Endpoint::add_endpoint();
					flush_rewrite_rules( false );
				}
				break;
			case 'chat':
				update_option( 'lship_chat_enabled', ( $data['lship_chat_enabled'] ?? 'no' ) === 'yes' ? 'yes' : 'no' );
				update_option( 'lship_chat_require_email', ( $data['lship_chat_require_email'] ?? 'no' ) === 'yes' ? 'yes' : 'no' );
				update_option( 'lship_chat_title', sanitize_text_field( (string) ( $data['lship_chat_title'] ?? '' ) ) );
				update_option( 'lship_chat_welcome', sanitize_textarea_field( (string) ( $data['lship_chat_welcome'] ?? '' ) ) );
				$chat_color = sanitize_hex_color( (string) ( $data['lship_chat_color'] ?? '' ) );
				update_option( 'lship_chat_color', $chat_color ? $chat_color : '' );
				$launcher_style = sanitize_key( (string) ( $data['lship_chat_launcher_style'] ?? 'icon' ) );
				update_option( 'lship_chat_launcher_style', in_array( $launcher_style, array( 'icon', 'label' ), true ) ? $launcher_style : 'icon' );
				break;
			default:
				return new WP_Error( 'invalid_tab', __( 'Invalid settings tab.', 'licensesender' ), array( 'status' => 400 ) );
		}

		return self::get_settings( $tab );
	}

	public static function ping_api() {
		$result = Licensesender_Api::ping();
		if ( ! empty( $result['success'] ) ) {
			$result['subscription_details'] = Licensesender_Api::get_subscription_details( true );
			return $result;
		}
		return new WP_Error(
			'ping_failed',
			$result['message'] ?? __( 'Unknown error occurred.', 'licensesender' ),
			array( 'status' => 400, 'meta' => $result['meta'] ?? array() )
		);
	}

	public static function get_subscription_details( $force_refresh = false ) {
		return Licensesender_Api::get_subscription_details( $force_refresh );
	}

	public static function list_download_links() {
		global $wpdb;
		$table   = $wpdb->prefix . 'ls_download_links';
		$results = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );
		$data    = array();

		foreach ( $results as $row ) {
			$product_name = get_the_title( $row['product_id'] );
			if ( empty( $product_name ) ) {
				$product_name = __( '(Product not found)', 'licensesender' );
			}
			$data[] = array(
				'id'           => (int) $row['id'],
				'product_id'   => (int) $row['product_id'],
				'product_name' => $product_name,
				'link'         => $row['link'],
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
			return new WP_Error( 'validation', __( 'Please fill all fields.', 'licensesender' ), array( 'status' => 400 ) );
		}

		$duplicate = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE product_id = %d AND id != %d",
				$product_id,
				$id
			)
		);

		if ( $duplicate > 0 ) {
			return new WP_Error( 'duplicate', __( 'A download link already exists for this product.', 'licensesender' ), array( 'status' => 400 ) );
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

			$data[] = array(
				'id'           => (int) $row['id'],
				'product_id'   => (int) $row['product_id'],
				'product_name' => $product_name,
				'type'         => ucfirst( $row['type'] ?? 'text' ),
				'created_at'   => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['created_at'] ) ),
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

	public static function get_popup_settings() {
		return array(
			'ls_sw_confirm_title'      => get_option( 'ls_sw_confirm_title', __( 'Get license keys?', 'licensesender' ) ),
			'ls_sw_confirm_text'       => get_option( 'ls_sw_confirm_text', __( 'We will fetch your license keys for {product}. Continue?', 'licensesender' ) ),
			'ls_sw_confirm_btn'        => get_option( 'ls_sw_confirm_btn', __( 'Yes, get keys', 'licensesender' ) ),
			'ls_sw_cancel_btn'         => get_option( 'ls_sw_cancel_btn', __( 'Cancel', 'licensesender' ) ),
			'ls_sw_confirm_color'      => get_option( 'ls_sw_confirm_color', '#4f46e5' ),
			'ls_sw_cancel_color'       => get_option( 'ls_sw_cancel_color', '#6b7280' ),
			'ls_sw_bulk_title'         => get_option( 'ls_sw_bulk_title', __( 'Fetch All Keys?', 'licensesender' ) ),
			'ls_sw_bulk_text'          => get_option( 'ls_sw_bulk_text', __( 'This will retrieve all license keys for this order.', 'licensesender' ) ),
			'ls_sw_bulk_confirm_btn'   => get_option( 'ls_sw_bulk_confirm_btn', __( 'Yes, fetch all', 'licensesender' ) ),
			'ls_sw_bulk_cancel_btn'    => get_option( 'ls_sw_bulk_cancel_btn', __( 'Cancel', 'licensesender' ) ),
			'ls_sw_bulk_done_title'    => get_option( 'ls_sw_bulk_done_title', __( 'Done!', 'licensesender' ) ),
			'ls_sw_bulk_done_text'     => get_option( 'ls_sw_bulk_done_text', __( 'All license keys have been processed.', 'licensesender' ) ),
			'ls_sw_view_title'         => get_option( 'ls_sw_view_title', __( 'Your License Key', 'licensesender' ) ),
			'ls_sw_view_title_many'    => get_option( 'ls_sw_view_title_many', __( 'Your License Keys', 'licensesender' ) ),
			'ls_sw_view_copy_all'      => get_option( 'ls_sw_view_copy_all', __( 'Copy All', 'licensesender' ) ),
			'ls_sw_view_close'         => get_option( 'ls_sw_view_close', __( 'Close', 'licensesender' ) ),
			// Read-only brand reference for "Match brand" in the admin UI.
			'ls_brand'                 => get_option( 'ls_brand', '#4f46e5' ),
		);
	}

	public static function get_popup_i18n() {
		$settings = self::get_popup_settings();
		return array(
			'confirmTitle'     => $settings['ls_sw_confirm_title'],
			'confirmText'      => $settings['ls_sw_confirm_text'],
			'confirmBtn'       => $settings['ls_sw_confirm_btn'],
			'cancelBtn'        => $settings['ls_sw_cancel_btn'],
			'confirmColor'     => $settings['ls_sw_confirm_color'],
			'cancelColor'      => $settings['ls_sw_cancel_color'],
			'bulkTitle'        => $settings['ls_sw_bulk_title'],
			'bulkText'         => $settings['ls_sw_bulk_text'],
			'bulkConfirmBtn'   => $settings['ls_sw_bulk_confirm_btn'],
			'bulkCancelBtn'    => $settings['ls_sw_bulk_cancel_btn'],
			'bulkDoneTitle'    => $settings['ls_sw_bulk_done_title'],
			'bulkDoneText'     => $settings['ls_sw_bulk_done_text'],
			'viewTitle'        => $settings['ls_sw_view_title'],
			'viewTitleMany'    => $settings['ls_sw_view_title_many'],
			'copyAll'          => $settings['ls_sw_view_copy_all'],
			'close'            => $settings['ls_sw_view_close'],
		);
	}
}
