<?php
defined( 'ABSPATH' ) || exit();

class Ls_License_Shipper_Public_Action {

	public static function init() {

		add_action( 'wp_ajax_ls_fetch_license', array( __CLASS__, 'ls_handle_fetch_license' ) );
		add_action( 'wp_ajax_nopriv_ls_fetch_license', array( __CLASS__, 'ls_handle_fetch_license' ) );

		add_action( 'wp_ajax_export_license_key', array( __CLASS__, 'ls_export_license_key_csv' ) );
		add_action( 'wp_ajax_nopriv_export_license_key', array( __CLASS__, 'ls_export_license_key_csv' ) );

		add_action( LS_License_Email_Service::CRON_HOOK, array( 'LS_License_Email_Service', 'send_order_email' ), 10, 2 );

		add_action( 'wp_ajax_download_activation_guide', array( __CLASS__, 'ls_download_activation_guide' ) );
		add_action( 'wp_ajax_nopriv_download_activation_guide', array( __CLASS__, 'ls_download_activation_guide' ) );
	}

	public static function ls_handle_fetch_license() {
		check_ajax_referer( 'ls_fetch_license_api', '_ajax_nonce' );

		global $wpdb;

		$order_id   = absint( $_POST['order_id'] ?? 0 );
		$product_id = absint( $_POST['product_id'] ?? 0 );
		$email      = sanitize_email( $_POST['email'] ?? '' );
		$order_key  = isset( $_POST['order_key'] ) ? wc_clean( wp_unslash( $_POST['order_key'] ) ) : '';
		$table      = $wpdb->prefix . 'ls_cached_licenses';

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'license-shipper' ) ) );
		}

		if ( ! ls_user_can_access_order_licenses( $order, array( 'email' => $email, 'order_key' => $order_key ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access to order.', 'license-shipper' ) ) );
		}

		$has_product     = false;
		$used_product_id = null;

		foreach ( $order->get_items() as $item ) {
			$item_product_id   = (int) $item->get_product_id();
			$item_variation_id = (int) $item->get_variation_id();

			if ( $item_variation_id && $item_variation_id === $product_id ) {
				$has_product     = true;
				$used_product_id = $item_variation_id;
				break;
			} elseif ( $item_product_id === $product_id ) {
				$has_product     = true;
				$used_product_id = $item_product_id;
				break;
			}
		}

		if ( ! $has_product || ! $used_product_id ) {
			wp_send_json_error( array( 'message' => __( 'Product not found in this order.', 'license-shipper' ) ) );
		}

		if ( ! $order->has_status( 'completed' ) ) {
			wp_send_json_error( array( 'message' => __( 'License can only be fetched for completed orders.', 'license-shipper' ) ) );
		}

		$expected_qty = ls_count_expected_keys_for_product_in_order( $order, $used_product_id );
		$cached_keys  = ls_get_cached_licenses_for_product( $order_id, $used_product_id );
		$cached_count = count( $cached_keys );

		if ( $cached_count >= $expected_qty && $expected_qty > 0 ) {
			wp_send_json_success( array( 'html' => self::render_license_rows_html( $cached_keys, $order ) ) );
		}

		if ( ! ls_is_license_shipper_enabled( $used_product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'License Shipper is not enabled for this product.', 'license-shipper' ) ) );
		}

		$mapped_sku = ls_get_mapped_sku( $used_product_id );
		if ( empty( $mapped_sku ) ) {
			wp_send_json_error( array( 'message' => __( 'This product does not have a mapped SKU.', 'license-shipper' ) ) );
		}

		$need = max( 1, $expected_qty - $cached_count );

		$result = License_Shipper_Api::fetch_license(
			array(
				'sku'      => $mapped_sku,
				'quantity' => $need,
				'order_id' => $order_id,
				'email'    => $email,
				'source'   => sanitize_title( get_bloginfo( 'name' ) ),
			)
		);

		if ( empty( $result['success'] ) ) {
			$msg    = $result['message'] ?? __( 'Request failed', 'license-shipper' );
			$scope  = $result['meta']['scope'] ?? '';
			$reason = $result['meta']['reason'] ?? '';

			if ( $reason ) {
				$msg .= ' ' . sprintf( __( 'Reason: %s', 'license-shipper' ), $reason );
			}
			if ( $scope ) {
				$msg .= ' ' . sprintf( __( '[%s block]', 'license-shipper' ), ucfirst( $scope ) );
			}

			wp_send_json_error(
				array(
					'message'   => $msg,
					'http_code' => $result['http_code'] ?? null,
					'meta'      => $result['meta'] ?? array(),
				)
			);
		}

		$licenses     = $result['licenses'];
		$product_info = $result['product'] ?? array();
		$links        = ls_get_license_product_links( $used_product_id );

		$final_download_link = $links['download_link'] ?: ( $product_info['download_link'] ?? '' );
		$final_guide_link    = $links['activation_guide'] ?: ( $product_info['activation_guide'] ?? '' );

		$inserted_rows = array();

		foreach ( $licenses as $index => $license ) {
			$key_val = isset( $license['key'] ) ? trim( (string) $license['key'] ) : '';
			if ( $key_val === '' ) {
				continue;
			}

			$ok = $wpdb->insert(
				$table,
				array(
					'order_id'         => $order_id,
					'product_id'       => $used_product_id,
					'sku'              => $mapped_sku,
					'email'            => $email,
					'key_value'        => $key_val,
					'download_link'    => $final_download_link,
					'activation_guide' => $final_guide_link,
					'source'           => 'api',
					'fetched'          => 1,
				)
			);

			if ( $ok === false ) {
				error_log( 'License insert failed: ' . $wpdb->last_error );
				continue;
			}

			$licenses[ $index ]['key_id'] = (int) $wpdb->insert_id;
			$inserted_rows[]              = (object) array(
				'id'               => (int) $wpdb->insert_id,
				'key_value'        => $key_val,
				'download_link'    => $final_download_link,
				'activation_guide' => $final_guide_link,
			);
		}

        LS_License_Email_Service::maybe_schedule_after_fetch( $order, $email );

		$cached_after = ls_get_cached_licenses_for_product( $order_id, $used_product_id );

		wp_send_json_success( array( 'html' => self::render_license_rows_html( $cached_after, $order ) ) );
	}

	private static function render_license_rows_html( $rows, WC_Order $order ) {
		ob_start();
		?>
		<table class="shop_table" style="width: 100%;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Key', 'license-shipper' ); ?></th>
					<th><?php esc_html_e( 'Download link', 'license-shipper' ); ?></th>
					<th><?php esc_html_e( 'Activation Process', 'license-shipper' ); ?></th>
					<th><?php esc_html_e( 'Action', 'license-shipper' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $license ) : ?>
					<?php $key_id = isset( $license->id ) ? (int) $license->id : 0; ?>
					<tr>
						<td><code class="ls-license-key"><?php echo esc_html( $license->key_value ); ?></code></td>
						<td><a href="<?php echo esc_url( $license->download_link ); ?>" class="ls-btn-download" target="_blank" rel="noopener"><?php esc_html_e( 'Click Here', 'license-shipper' ); ?></a></td>
						<td>
							<?php if ( $key_id ) : ?>
								<a href="<?php echo esc_url( ls_activation_guide_download_url( $key_id, $order ) ); ?>" class="ls-btn-guide" target="_blank" rel="noopener"><?php esc_html_e( 'Download Now', 'license-shipper' ); ?></a>
							<?php endif; ?>
						</td>
						<td><button type="button" class="button ls-copy-license-btn"><?php esc_html_e( 'Copy', 'license-shipper' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}

	public static function ls_export_license_key_csv() {
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			wp_die( esc_html__( 'Invalid context.', 'license-shipper' ), '', 400 );
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$nonce    = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $order_id || ! wp_verify_nonce( $nonce, 'export_license_nonce' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'license-shipper' ), '', 400 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'license-shipper' ), '', 404 );
		}

		$email     = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';
		$order_key = isset( $_GET['order_key'] ) ? wc_clean( wp_unslash( $_GET['order_key'] ) ) : '';

		if ( ! ls_user_can_access_order_licenses( $order, array( 'email' => $email, 'order_key' => $order_key ) ) ) {
			wp_die( esc_html__( 'Unauthorized', 'license-shipper' ), '', 403 );
		}

		if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
			wp_die( esc_html__( 'Order not paid/ready.', 'license-shipper' ), '', 403 );
		}

		global $wpdb;
		$table    = $wpdb->prefix . 'ls_cached_licenses';
		$licenses = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE order_id = %d", $order_id )
		);

		if ( empty( $licenses ) ) {
			wp_die( esc_html__( 'No license keys found for this order.', 'license-shipper' ), '', 404 );
		}

		nocache_headers();
		$filename = "license-order-{$order_id}.csv";
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Order ID', 'Product Name', 'License Key' ) );

		foreach ( $licenses as $license ) {
			$product      = wc_get_product( (int) $license->product_id );
			$product_name = $product ? $product->get_name() : 'Unknown';
			fputcsv(
				$out,
				array(
					$license->order_id,
					$product_name,
					$license->key_value,
				)
			);
		}

		fclose( $out );
		exit;
	}

	public static function ls_download_activation_guide() {
		$key_id = isset( $_GET['key_id'] ) ? absint( $_GET['key_id'] ) : 0;
		$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $key_id || ! wp_verify_nonce( $nonce, 'dl_guide_' . $key_id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'license-shipper' ) );
		}

		global $wpdb;
		$table   = $wpdb->prefix . 'ls_cached_licenses';
		$license = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $key_id )
		);

		if ( ! $license || empty( $license->activation_guide ) ) {
			wp_die( esc_html__( 'Activation guide not found.', 'license-shipper' ) );
		}

		$order = wc_get_order( (int) $license->order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'license-shipper' ) );
		}

		$email     = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';
		$order_key = isset( $_GET['order_key'] ) ? wc_clean( wp_unslash( $_GET['order_key'] ) ) : '';

		if ( ! ls_user_can_access_order_licenses( $order, array( 'email' => $email, 'order_key' => $order_key ) ) ) {
			wp_die( esc_html__( 'Unauthorized', 'license-shipper' ), '', 403 );
		}

		$url = esc_url_raw( trim( $license->activation_guide ) );
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			wp_die( esc_html__( 'Invalid activation guide URL.', 'license-shipper' ) );
		}

		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['host'] ) ) {
			wp_die( esc_html__( 'Invalid activation guide URL.', 'license-shipper' ) );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 30,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_die( esc_html__( 'Could not access activation guide file.', 'license-shipper' ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			wp_die( esc_html__( 'Activation guide is empty.', 'license-shipper' ) );
		}

		$filename = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		if ( ! $filename ) {
			$filename = 'activation-guide.pdf';
		}

		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $body ) );
		header( 'Cache-Control: no-cache' );

		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}

Ls_License_Shipper_Public_Action::init();
