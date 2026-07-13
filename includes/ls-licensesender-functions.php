<?php

/**
 * Resolve WooCommerce product ID used for licensesender meta lookups.
 * When variation support is off, meta is stored on the parent product.
 */
function ls_resolve_license_product_id( $product_id ) {
	$product_id = absint( $product_id );
	if ( ! $product_id ) {
		return 0;
	}

	$product = wc_get_product( $product_id );
	if ( ! $product || ! $product->is_type( 'variation' ) ) {
		return $product_id;
	}

	if ( get_option( 'lship_enable_variation_support', 'no' ) !== 'yes' ) {
		$parent_id = $product->get_parent_id();
		return $parent_id ? $parent_id : $product_id;
	}

	return $product_id;
}

function ls_is_licensesender_enabled( $product_id ) {
	$resolved = ls_resolve_license_product_id( $product_id );
	return $resolved && get_post_meta( $resolved, '_ls_enabled', true ) === 'yes';
}

function ls_get_mapped_sku( $product_id ) {
	$resolved = ls_resolve_license_product_id( $product_id );
	if ( ! $resolved ) {
		return '';
	}
	return (string) get_post_meta( $resolved, '_ls_mapped_product', true );
}

/**
 * Whether an order contains at least one enabled + mapped licensesender product.
 */
function ls_order_has_licensesender_product( WC_Order $order ): bool {
	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();
		if ( ! $product ) {
			continue;
		}

		if ( ls_is_licensesender_enabled( $product->get_id() ) && ls_get_mapped_sku( $product->get_id() ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Whether an order contains at least one wholesale catalog product.
 */
function ls_order_has_wholesale_product( WC_Order $order ): bool {
	return LS_Wholesale_Orders::order_has_wholesale_product( $order );
}

/**
 * Whether an order is a wholesale order.
 */
function ls_order_is_wholesale( WC_Order $order ): bool {
	return LS_Wholesale_Orders::is_wholesale_order( $order );
}

/**
 * Count line items in an order that require licensesender delivery.
 */
function ls_count_licensesender_order_items( WC_Order $order ): int {
	$count = 0;

	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();
		if ( ! $product ) {
			continue;
		}

		if ( ls_is_licensesender_enabled( $product->get_id() ) && ls_get_mapped_sku( $product->get_id() ) ) {
			$count++;
		}
	}

	return $count;
}

/**
 * Sum of quantities for licensable line items (LS enabled + mapped SKU).
 */
function ls_count_expected_license_keys( WC_Order $order ): int {
	$count = 0;

	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();
		if ( ! $product ) {
			continue;
		}

		if ( ls_is_licensesender_enabled( $product->get_id() ) && ls_get_mapped_sku( $product->get_id() ) ) {
			$count += max( 1, (int) $item->get_quantity() );
		}
	}

	return $count;
}

/**
 * Count cached license rows fetched for an order.
 */
function ls_count_fetched_license_keys( int $order_id ): int {
	global $wpdb;

	$table = $wpdb->prefix . 'ls_cached_licenses';

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE order_id = %d AND fetched = 1",
			$order_id
		)
	);
}

/**
 * Expected key count for a specific product within an order (sums duplicate line items).
 */
function ls_count_expected_keys_for_product_in_order( WC_Order $order, int $product_id ): int {
	$count = 0;

	foreach ( $order->get_items() as $item ) {
		$pid = (int) ( $item->get_variation_id() ?: $item->get_product_id() );
		if ( $pid !== $product_id ) {
			continue;
		}

		if ( ls_is_licensesender_enabled( $pid ) && ls_get_mapped_sku( $pid ) ) {
			$count += max( 1, (int) $item->get_quantity() );
		}
	}

	return $count;
}

/**
 * Cached license rows for a product on an order (capped to expected quantity, newest kept).
 *
 * @return object[]
 */
function ls_get_cached_licenses_for_product( int $order_id, int $product_id ): array {
	global $wpdb;
	$table = $wpdb->prefix . 'ls_cached_licenses';

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE order_id = %d AND product_id = %d AND fetched = 1 ORDER BY id DESC",
			$order_id,
			$product_id
		)
	);

	if ( ! is_array( $rows ) || empty( $rows ) ) {
		return array();
	}

	$order = wc_get_order( $order_id );
	if ( $order ) {
		$expected = ls_count_expected_keys_for_product_in_order( $order, $product_id );
		if ( $expected > 0 && count( $rows ) > $expected ) {
			$rows = array_slice( $rows, 0, $expected );
		}
	}

	// Oldest-first for stable customer/admin display.
	return array_reverse( $rows );
}

/**
 * Admin order metabox: keys list with per-key Report action.
 */
function ls_render_admin_order_license_keys_html( array $licenses, WC_Order $order ): string {
	if ( empty( $licenses ) ) {
		return '<em class="ls-muted">' . esc_html__( 'Not fetched yet', 'licensesender' ) . '</em>';
	}

	$order_id = $order->get_id();
	$items    = array();

	foreach ( $licenses as $license ) {
		$key = trim( (string) ( $license->key_value ?? '' ) );
		if ( $key === '' ) {
			continue;
		}

		$product_id   = absint( $license->product_id ?? 0 );
		$product_name = '';
		if ( $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$product_name = $product->get_name();
			}
		}

		$items[] = array(
			'id'           => absint( $license->id ?? 0 ),
			'key'          => $key,
			'product_id'   => $product_id,
			'product_name' => $product_name,
		);
	}

	if ( empty( $items ) ) {
		return '<em class="ls-muted">' . esc_html__( 'Not fetched yet', 'licensesender' ) . '</em>';
	}

	$html = '<div class="ls-admin-key-list">';
	foreach ( $items as $item ) {
		$html .= '<div class="ls-admin-key-row">';
		$html .= '<code class="ls-admin-key-value">' . esc_html( $item['key'] ) . '</code>';
		if ( $item['id'] > 0 ) {
			$html .= sprintf(
				'<button type="button" class="button button-small ls-report-key-btn" data-license-id="%1$d" data-license-key="%2$s" data-order-id="%3$d" data-product-id="%4$d" data-product-name="%5$s">%6$s</button>',
				$item['id'],
				esc_attr( $item['key'] ),
				$order_id,
				$item['product_id'],
				esc_attr( $item['product_name'] ),
				esc_html__( 'Report Key', 'licensesender' )
			);
		}
		$html .= '</div>';
	}
	$html .= '</div>';

	return $html;
}

/**
 * Whether an order has any licensable products.
 */
function ls_order_has_licensable_products( WC_Order $order ): bool {
	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();
		if ( ! $product ) {
			continue;
		}
		$pid = $product->get_id();
		if ( ls_is_licensesender_enabled( $pid ) && ls_get_mapped_sku( $pid ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Whether license fetch/display is allowed for this order (HPOS-safe).
 */
function ls_is_order_license_ready( $order ) {
	if ( ! $order instanceof WC_Order ) {
		$order = wc_get_order( $order );
	}

	if ( ! $order ) {
		return false;
	}

	if ( $order->get_meta( '_ls_completed_licensesender', true ) === 'yes' ) {
		return true;
	}

	// Legacy fallback: completed orders with LS products before meta existed.
	if ( $order->has_status( 'completed' ) && ls_order_has_licensesender_product( $order ) ) {
		return true;
	}

	return false;
}

/**
 * Long-lived HMAC token for activation-guide downloads (email-safe; ~30 days).
 *
 * @param int         $key_id Cached license row ID.
 * @param WC_Order    $order  Order.
 * @param int         $ttl    Seconds until expiry.
 * @return string
 */
function ls_create_guide_download_token( $key_id, WC_Order $order, $ttl = MONTH_IN_SECONDS ) {
	$key_id  = absint( $key_id );
	$expires = time() + max( HOUR_IN_SECONDS, (int) $ttl );
	$email   = strtolower( sanitize_email( (string) $order->get_billing_email() ) );
	$payload = $key_id . '|' . (int) $order->get_id() . '|' . $email . '|' . $expires;
	$sig     = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );

	return base64_encode( $payload . '|' . $sig );
}

/**
 * Verify a guide download token.
 *
 * @param string   $token Encoded token.
 * @param int      $key_id Expected key ID.
 * @param WC_Order $order Expected order.
 * @return bool
 */
function ls_verify_guide_download_token( $token, $key_id, WC_Order $order ) {
	$token = (string) $token;
	$raw   = base64_decode( $token, true );
	if ( ! is_string( $raw ) || $raw === '' ) {
		return false;
	}

	$parts = explode( '|', $raw );
	if ( count( $parts ) !== 5 ) {
		return false;
	}

	list( $tok_key_id, $tok_order_id, $tok_email, $tok_expires, $tok_sig ) = $parts;
	$payload = $tok_key_id . '|' . $tok_order_id . '|' . $tok_email . '|' . $tok_expires;
	$expect  = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );

	if ( ! hash_equals( $expect, $tok_sig ) ) {
		return false;
	}
	if ( (int) $tok_key_id !== (int) $key_id || (int) $tok_order_id !== (int) $order->get_id() ) {
		return false;
	}
	if ( (int) $tok_expires < time() ) {
		return false;
	}

	$email = strtolower( sanitize_email( (string) $order->get_billing_email() ) );
	if ( $email === '' || strcasecmp( $tok_email, $email ) !== 0 ) {
		return false;
	}

	return true;
}

/**
 * Whether a remote URL is safe to proxy for activation-guide download.
 *
 * Allows same-host, uploads CDN, and licensesender.com. Blocks private IPs.
 *
 * @param string $url URL to fetch.
 * @return bool
 */
function ls_is_safe_activation_guide_url( $url ) {
	$url = esc_url_raw( trim( (string) $url ) );
	if ( $url === '' || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
		return false;
	}

	$parsed = wp_parse_url( $url );
	if ( empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
		return false;
	}

	$scheme = strtolower( (string) $parsed['scheme'] );
	if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
		return false;
	}

	$host = strtolower( (string) $parsed['host'] );
	if ( $host === 'localhost' || $host === '127.0.0.1' || $host === '::1' ) {
		return false;
	}

	// Resolve host and reject private / reserved ranges (SSRF).
	$ips = array();
	if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
		$ips[] = $host;
	} else {
		$records = @dns_get_record( $host, DNS_A + DNS_AAAA ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( is_array( $records ) ) {
			foreach ( $records as $record ) {
				if ( ! empty( $record['ip'] ) ) {
					$ips[] = $record['ip'];
				}
				if ( ! empty( $record['ipv6'] ) ) {
					$ips[] = $record['ipv6'];
				}
			}
		}
	}

	foreach ( $ips as $ip ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return false;
		}
	}

	$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
	$site_host = is_string( $site_host ) ? strtolower( $site_host ) : '';

	$upload     = wp_upload_dir();
	$upload_host = ! empty( $upload['baseurl'] ) ? wp_parse_url( $upload['baseurl'], PHP_URL_HOST ) : '';
	$upload_host = is_string( $upload_host ) ? strtolower( $upload_host ) : '';

	$allowed_hosts = array_filter(
		array_unique(
			array(
				$site_host,
				$upload_host,
				'licensesender.com',
				'www.licensesender.com',
			)
		)
	);

	foreach ( $allowed_hosts as $allowed ) {
		if ( $host === $allowed || substr( $host, -strlen( '.' . $allowed ) ) === ( '.' . $allowed ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Signed URL for downloading an activation guide PDF.
 */
function ls_activation_guide_download_url( $key_id, $order = null ) {
	$key_id = absint( $key_id );
	if ( ! $key_id || ! ( $order instanceof WC_Order ) ) {
		return '';
	}

	$args = array(
		'action'    => 'download_activation_guide',
		'key_id'    => $key_id,
		'order_key' => $order->get_order_key(),
		'email'     => $order->get_billing_email(),
		'token'     => ls_create_guide_download_token( $key_id, $order ),
	);

	return add_query_arg( $args, admin_url( 'admin-ajax.php' ) );
}

/**
 * Resolve download + activation guide URLs for a product (plugin settings first).
 */
function ls_get_license_product_links( $product_id ) {
	$resolved = ls_resolve_license_product_id( $product_id );

	$enable_downloads = get_option( 'lship_enable_manage_downloads' ) === 'yes';
	$enable_guides    = get_option( 'lship_enable_manage_activation_guides' ) === 'yes';

	$download_link = ( $enable_downloads && $resolved ) ? ls_get_download_link( $resolved ) : '';
	$guide_link    = ( $enable_guides && $resolved ) ? ls_get_activation_guide_pdf_link( $resolved ) : '';

	return array(
		'download_link'    => $download_link ?: '',
		'activation_guide' => $guide_link ?: '',
	);
}

/**
 * Serialize concurrent Get License requests for the same order/product.
 * Uses add_option() for atomic acquire (unique option_name).
 *
 * @return true|WP_Error
 */
function ls_acquire_fetch_lock( int $order_id, int $product_id ) {
	$option = 'ls_fetch_lock_' . $order_id . '_' . $product_id;
	$now    = time();

	if ( add_option( $option, (string) $now, '', 'no' ) ) {
		return true;
	}

	$existing = (int) get_option( $option, 0 );
	if ( $existing > 0 && ( $now - $existing ) > 45 ) {
		// Stale lock — take over.
		update_option( $option, (string) $now, false );
		return true;
	}

	return new WP_Error(
		'fetch_in_progress',
		__( 'A license fetch is already in progress for this product. Please wait a moment and try again.', 'licensesender' )
	);
}

function ls_release_fetch_lock( int $order_id, int $product_id ): void {
	delete_option( 'ls_fetch_lock_' . $order_id . '_' . $product_id );
}

function ls_user_can_access_order_licenses( WC_Order $order, array $args = array() ): bool {
	if ( current_user_can( 'manage_woocommerce' ) ) {
		return true;
	}

	$user_id = get_current_user_id();
	if ( $user_id && (int) $order->get_user_id() === (int) $user_id ) {
		return true;
	}

	$email     = isset( $args['email'] ) ? sanitize_email( $args['email'] ) : '';
	$order_key = isset( $args['order_key'] ) ? wc_clean( wp_unslash( $args['order_key'] ) ) : '';

	if ( ! $order_key || ! hash_equals( (string) $order->get_order_key(), (string) $order_key ) ) {
		return false;
	}

	// Guests must also prove billing email (order key alone is not enough).
	if ( $email === '' || strcasecmp( $email, (string) $order->get_billing_email() ) !== 0 ) {
		return false;
	}

	return true;
}

function ls_render_license_table( $order_id, $return = false ) {
	if ( empty( $order_id ) ) {
		return;
	}

	$order = wc_get_order( $order_id );

	if ( ! $order || $order->get_status() !== 'completed' ) {
		return;
	}

	if ( ! ls_is_order_license_ready( $order ) ) {
		return;
	}

	global $wpdb;
	$table = $wpdb->prefix . 'ls_cached_licenses';

	ob_start();

	?>
	<h2 class="woocommerce-order-downloads__title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
		<span><?php _e( 'License Keys', 'licensesender' ); ?></span>

		<div class="ls-header-buttons" style="display: flex; gap: 10px;">
			<?php
			$order_id = $order->get_id();
			$url      = add_query_arg(
				array(
					'action'    => 'export_license_key',
					'order_id'  => $order_id,
					'order_key' => $order->get_order_key(),
					'email'     => $order->get_billing_email(),
					'_wpnonce'  => wp_create_nonce( 'export_license_nonce' ),
				),
				admin_url( 'admin-ajax.php' )
			);
			?>
			<a href="<?php echo esc_url( $url ); ?>" class="button ls-export-all-btn">Download All</a>

			<a href="#"
			   class="button wp-element-button ls-get-all-keys-btn"
			   data-order-id="<?php echo esc_attr( $order_id ); ?>"
			   title="<?php esc_attr_e( 'Retrieve all license keys step by step', 'licensesender' ); ?>">
				<span class="ls-btn-text"><?php _e( 'Get All Keys', 'licensesender' ); ?></span>
			</a>
		</div>
	</h2>

	<table class="woocommerce-table woocommerce-table--order-details shop_table order_details" cellspacing="0" cellpadding="6" border="1" style="width:100%;margin-bottom:40px;">
		<thead>
			<tr>
				<th><?php _e( 'Product', 'licensesender' ); ?></th>
				<th><?php _e( 'Email', 'licensesender' ); ?></th>
				<th><?php _e( 'Quantity', 'licensesender' ); ?></th>
				<th><?php _e( 'Action', 'licensesender' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $order->get_items() as $item_id => $item ) :
				$product = $item->get_product();
				if ( ! $product ) {
					continue;
				}

				$product_id = $product->get_id();

				if ( ! ls_is_licensesender_enabled( $product_id ) ) {
					continue;
				}

				$product_name = $product->get_name();
				$quantity     = ls_count_expected_keys_for_product_in_order( $order, $product_id );
				$email        = $order->get_billing_email();

				$cached_keys = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM $table WHERE order_id = %d AND product_id = %d AND fetched = 1",
						$order_id,
						$product_id
					)
				);

				$cached_count = is_array( $cached_keys ) ? count( $cached_keys ) : 0;
				$is_complete  = $quantity > 0 && $cached_count >= $quantity;
				$has_partial  = $cached_count > 0 && ! $is_complete;
				?>
				<tr>
					<td>
						<a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>">
							<?php echo esc_html( $product_name ); ?>
						</a>
					</td>
					<td><?php echo esc_html( $email ); ?></td>
					<td><?php echo esc_html( (string) $quantity ); ?><?php echo $has_partial ? esc_html( sprintf( ' (%d/%d)', $cached_count, $quantity ) ) : ''; ?></td>
					<td>
						<?php if ( $is_complete ) : ?>
							<button class="button ls-toggle-license-btn">
								<?php _e( 'View Key', 'licensesender' ); ?>
							</button>
						<?php else : ?>
							<button class="button ls-view-license-btn"
							   data-product-name="<?php echo esc_attr( $product_name ); ?>"
							   data-product-id="<?php echo esc_attr( $product_id ); ?>"
							   data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
							   data-order-qnty="<?php echo esc_attr( $quantity ); ?>"
							   data-email="<?php echo esc_attr( $email ); ?>"
							   data-order-key="<?php echo esc_attr( $order->get_order_key() ); ?>">
								<?php _e( 'Get Key', 'licensesender' ); ?>
							</button>
						<?php endif; ?>
					</td>
				</tr>

				<tr class="ls-license-details-row" style="display: none;">
					<td colspan="4">
						<div class="ls-license-details-content">
							<?php if ( $cached_count > 0 ) : ?>
								<table class="shop_table" style="width: 100%;">
									<thead>
										<tr>
											<th class="ls-key-table-header"><?php _e( 'License Key', 'licensesender' ); ?></th>
											<th><?php _e( 'Download Link', 'licensesender' ); ?></th>
											<th><?php _e( 'Activation Guide', 'licensesender' ); ?></th>
											<th><?php _e( 'Action', 'licensesender' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $cached_keys as $index => $license ) : ?>
											<tr class="ls-license-row <?php echo $index >= 10 ? 'ls-hidden-row' : ''; ?>">
												<td><code class="ls-license-key"><?php echo esc_html( $license->key_value ); ?></code></td>
												<td>
													<a href="<?php echo esc_url( $license->download_link ); ?>"
													   target="_blank" rel="noopener"
													   class="ls-btn-download">
													  <?php _e( 'Click Here', 'licensesender' ); ?>
													</a>
												</td>
												<td>
													<a href="<?php echo esc_url( ls_activation_guide_download_url( $license->id, $order ) ); ?>"
													   target="_blank" rel="noopener"
													   class="ls-btn-guide">
													  <?php _e( 'Download Now', 'licensesender' ); ?>
													</a>
												</td>
												<td><button class="button ls-copy-license-btn"><?php _e( 'Copy', 'licensesender' ); ?></button></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
									<tfoot>
										<tr>
											<td colspan="4" class="ls-show-toggle-wrapper" style="text-align: center;">
												<button class="button ls-show-more-btn wp-element-button" style="display: <?php echo count( $cached_keys ) > 10 ? 'inline-block' : 'none'; ?>">Show More Keys</button>
												<button class="button ls-show-less-btn wp-element-button" style="display: none;">Show Less Key</button>
											</td>
										</tr>
									</tfoot>
								</table>
							<?php else : ?>
								<p class="ls-loading"><?php _e( 'Preparing to load license...', 'licensesender' ); ?></p>
							<?php endif; ?>
						</div>
					</td>
				</tr>
				<?php
			endforeach;
			?>
		</tbody>
	</table>
	<?php

	$output = ob_get_clean();

	if ( $return ) {
		return $output;
	}

	echo $output;
}

function ls_customizeEmailTemplate( $template, $data = array() ) {
	return strtr( $template, $data );
}

function ls_get_activation_guide_pdf_link( $product_id ) {
	global $wpdb;

	$product_id = ls_resolve_license_product_id( $product_id );
	$table      = $wpdb->prefix . 'ls_activation_guides';

	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT content FROM $table WHERE product_id = %d LIMIT 1",
			$product_id
		)
	);

	if ( ! $row ) {
		return false;
	}

	$data = maybe_unserialize( $row->content );

	if ( ! empty( $data['pdf'] ) ) {
		return esc_url( $data['pdf'] );
	}

	return false;
}

function ls_get_download_link( $product_id ) {
	global $wpdb;

	$product_id = ls_resolve_license_product_id( $product_id );
	if ( empty( $product_id ) ) {
		return false;
	}

	$table = $wpdb->prefix . 'ls_download_links';

	$link = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT link FROM {$table} WHERE product_id = %d LIMIT 1",
			$product_id
		)
	);

	if ( ! empty( $link ) && filter_var( $link, FILTER_VALIDATE_URL ) ) {
		return esc_url( $link );
	}

	return false;
}

/**
 * Run database migrations when the plugin version changes.
 * Also self-heals if core tables are missing.
 */
function ls_maybe_upgrade_database() {
	global $wpdb;

	$stored = get_option( 'licensesender_db_version', '' );
	$table  = $wpdb->prefix . 'ls_cached_licenses';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;

	if ( $stored === LICENSESENDER_VERSION && $exists ) {
		return;
	}

	require_once plugin_dir_path( __FILE__ ) . 'class-licensesender-activator.php';

	if ( class_exists( 'Licensesender_Activator' ) ) {
		Licensesender_Activator::ls_create_license_cache_table();
		Licensesender_Activator::ls_create_download_links_table();
		Licensesender_Activator::ls_create_activation_guides_table();
	}

	update_option( 'licensesender_db_version', LICENSESENDER_VERSION );
}

add_action( 'plugins_loaded', 'ls_maybe_upgrade_database', 5 );
