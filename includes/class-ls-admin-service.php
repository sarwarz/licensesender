<?php
/**
 * Shared admin business logic for REST and legacy AJAX handlers.
 *
 * @package License_Shipper
 */

defined( 'ABSPATH' ) || exit;

class LS_Admin_Service {

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
			'products' => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT product_id) FROM {$table}" ),
			'emails'   => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT email) FROM {$table} WHERE email IS NOT NULL AND email <> ''" ),
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

	public static function change_license( $id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_cached_licenses';

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'License not found.', 'license-shipper' ), array( 'status' => 404 ) );
		}

		$new_key     = isset( $data['new_key_value'] ) ? sanitize_text_field( $data['new_key_value'] ) : '';
		$new_link    = isset( $data['new_download_link'] ) ? esc_url_raw( $data['new_download_link'] ) : '';
		$new_guide   = isset( $data['new_activation_guide'] ) ? wp_kses_post( $data['new_activation_guide'] ) : '';
		$notify_user = ! empty( $data['notify_user'] );

		$wpdb->update(
			$table,
			array(
				'key_value'        => $new_key ?: $row->key_value,
				'download_link'    => $new_link,
				'activation_guide' => $new_guide,
				'source'           => 'changed',
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( $notify_user && ! empty( $row->email ) && is_email( $row->email ) ) {
			$to      = $row->email;
			$subject = sprintf( __( 'Your license for Order #%d has been updated', 'license-shipper' ), (int) $row->order_id );
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			$body    = '<p>' . esc_html__( 'Hello,', 'license-shipper' ) . '</p>' .
				'<p>' . esc_html__( 'We updated your license key. Here are the latest details:', 'license-shipper' ) . '</p>' .
				'<p><strong>' . esc_html__( 'License key:', 'license-shipper' ) . '</strong> ' . esc_html( $new_key ?: $row->key_value ) . '</p>' .
				( $new_link ? '<p><strong>' . esc_html__( 'Download:', 'license-shipper' ) . '</strong> <a href="' . esc_url( $new_link ) . '">' . esc_html( $new_link ) . '</a></p>' : '' ) .
				( $new_guide ? '<p><strong>' . esc_html__( 'Activation guide:', 'license-shipper' ) . '</strong></p><div>' . $new_guide . '</div>' : '' ) .
				'<p>' . esc_html__( 'Thank you!', 'license-shipper' ) . '</p>';

			if ( function_exists( 'wc_mail' ) ) {
				wc_mail( $to, $subject, $body, $headers );
			} else {
				wp_mail( $to, $subject, $body, $headers );
			}
		}

		return self::get_license( $id );
	}

	public static function fetch_license_by_sku( $sku, $options = array() ) {
		$sku = sanitize_text_field( $sku );
		if ( $sku === '' ) {
			return new WP_Error( 'missing_sku', __( 'Missing SKU', 'license-shipper' ), array( 'status' => 400 ) );
		}

		$sort    = isset( $options['sort'] ) ? sanitize_text_field( $options['sort'] ) : 'id,asc';
		$timeout = isset( $options['timeout'] ) ? (int) $options['timeout'] : 30;

		$api_res = License_Shipper_Api::fetch_one_available_license_by_sku(
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
				! empty( $api_res['message'] ) ? (string) $api_res['message'] : __( 'Failed to fetch license.', 'license-shipper' ),
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
			return new WP_Error( 'not_found', __( 'No license found for this SKU', 'license-shipper' ), array( 'status' => 404 ) );
		}

		return array(
			'key_value'        => $key_value,
			'download_link'    => isset( $license['download_link'] ) ? (string) $license['download_link'] : '',
			'activation_guide' => isset( $license['activation_guide'] ) ? (string) $license['activation_guide'] : '',
			'license'          => $license,
			'meta'             => $api_res['meta'] ?? array(),
		);
	}

	public static function get_settings( $tab = 'general' ) {
		$tab = sanitize_text_field( $tab );

		switch ( $tab ) {
			case 'api':
				return array(
					'lship_api_key'      => get_option( 'lship_api_key', '' ),
					'lship_api_base_url' => get_option( 'lship_api_base_url', 'https://app.licenseshipper.com/api/' ),
				);
			case 'email':
				return array(
					'lship_email_sender_name'   => get_option( 'lship_email_sender_name', '' ),
					'lship_email_sender_email'  => get_option( 'lship_email_sender_email', '' ),
					'lship_email_subject'       => get_option( 'lship_email_subject', '' ),
					'lship_email_subject_single'=> get_option( 'lship_email_subject_single', '' ),
					'lship_email_subject_bulk'  => get_option( 'lship_email_subject_bulk', '' ),
					'lship_email_preheader'     => get_option( 'lship_email_preheader', '' ),
					'lship_email_intro_single'  => get_option( 'lship_email_intro_single', '' ),
					'lship_email_intro_bulk'    => get_option( 'lship_email_intro_bulk', '' ),
					'lship_support_email'       => get_option( 'lship_support_email', '' ),
					'admin_email'               => get_option( 'admin_email', '' ),
				);
			case 'design':
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
				return array(
					'lship_sso_enabled'    => get_option( 'lship_sso_enabled', 'no' ),
					'lship_sso_token'      => get_option( 'lship_sso_token', '' ),
					'lship_sso_user_email' => get_option( 'lship_sso_user_email', '' ),
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
				update_option( 'lship_api_base_url', esc_url_raw( $data['lship_api_base_url'] ?? '' ) );
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
			default:
				return new WP_Error( 'invalid_tab', __( 'Invalid settings tab.', 'license-shipper' ), array( 'status' => 400 ) );
		}

		return self::get_settings( $tab );
	}

	public static function ping_api() {
		$result = License_Shipper_Api::ping();
		if ( ! empty( $result['success'] ) ) {
			return $result;
		}
		return new WP_Error(
			'ping_failed',
			$result['message'] ?? __( 'Unknown error occurred.', 'license-shipper' ),
			array( 'status' => 400, 'meta' => $result['meta'] ?? array() )
		);
	}

	public static function list_download_links() {
		global $wpdb;
		$table   = $wpdb->prefix . 'ls_download_links';
		$results = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );
		$data    = array();

		foreach ( $results as $row ) {
			$product_name = get_the_title( $row['product_id'] );
			if ( empty( $product_name ) ) {
				$product_name = __( '(Product not found)', 'license-shipper' );
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
			$product_name = __( '(Product not found)', 'license-shipper' );
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
			return new WP_Error( 'validation', __( 'Please fill all fields.', 'license-shipper' ), array( 'status' => 400 ) );
		}

		$duplicate = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE product_id = %d AND id != %d",
				$product_id,
				$id
			)
		);

		if ( $duplicate > 0 ) {
			return new WP_Error( 'duplicate', __( 'A download link already exists for this product.', 'license-shipper' ), array( 'status' => 400 ) );
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
			$product_name = __( '(Product Deleted)', 'license-shipper' );
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

		$product_name = __( '(Product Deleted)', 'license-shipper' );
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
			'ls_sw_confirm_title'      => get_option( 'ls_sw_confirm_title', __( 'Get license keys?', 'license-shipper' ) ),
			'ls_sw_confirm_text'       => get_option( 'ls_sw_confirm_text', __( 'We will fetch your license keys for {product}. Continue?', 'license-shipper' ) ),
			'ls_sw_confirm_btn'        => get_option( 'ls_sw_confirm_btn', __( 'Yes, get keys', 'license-shipper' ) ),
			'ls_sw_cancel_btn'         => get_option( 'ls_sw_cancel_btn', __( 'Cancel', 'license-shipper' ) ),
			'ls_sw_confirm_color'      => get_option( 'ls_sw_confirm_color', '#4f46e5' ),
			'ls_sw_cancel_color'       => get_option( 'ls_sw_cancel_color', '#6b7280' ),
			'ls_sw_bulk_title'         => get_option( 'ls_sw_bulk_title', __( 'Fetch All Keys?', 'license-shipper' ) ),
			'ls_sw_bulk_text'          => get_option( 'ls_sw_bulk_text', __( 'This will retrieve all license keys for this order.', 'license-shipper' ) ),
			'ls_sw_bulk_confirm_btn'   => get_option( 'ls_sw_bulk_confirm_btn', __( 'Yes, fetch all', 'license-shipper' ) ),
			'ls_sw_bulk_cancel_btn'    => get_option( 'ls_sw_bulk_cancel_btn', __( 'Cancel', 'license-shipper' ) ),
			'ls_sw_bulk_done_title'    => get_option( 'ls_sw_bulk_done_title', __( 'Done!', 'license-shipper' ) ),
			'ls_sw_bulk_done_text'     => get_option( 'ls_sw_bulk_done_text', __( 'All license keys have been processed.', 'license-shipper' ) ),
			'ls_sw_view_title'         => get_option( 'ls_sw_view_title', __( 'Your License Key', 'license-shipper' ) ),
			'ls_sw_view_title_many'    => get_option( 'ls_sw_view_title_many', __( 'Your License Keys', 'license-shipper' ) ),
			'ls_sw_view_copy_all'      => get_option( 'ls_sw_view_copy_all', __( 'Copy All', 'license-shipper' ) ),
			'ls_sw_view_close'         => get_option( 'ls_sw_view_close', __( 'Close', 'license-shipper' ) ),
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
