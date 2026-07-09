<?php

/**
 * Resolve WooCommerce product ID used for License Shipper meta lookups.
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

function ls_is_license_shipper_enabled( $product_id ) {
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
 * Whether an order contains at least one enabled + mapped License Shipper product.
 */
function ls_order_has_license_shipper_product( WC_Order $order ): bool {
	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();
		if ( ! $product ) {
			continue;
		}

		if ( ls_is_license_shipper_enabled( $product->get_id() ) && ls_get_mapped_sku( $product->get_id() ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Count line items in an order that require License Shipper delivery.
 */
function ls_count_license_shipper_order_items( WC_Order $order ): int {
	$count = 0;

	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();
		if ( ! $product ) {
			continue;
		}

		if ( ls_is_license_shipper_enabled( $product->get_id() ) && ls_get_mapped_sku( $product->get_id() ) ) {
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

		if ( ls_is_license_shipper_enabled( $product->get_id() ) && ls_get_mapped_sku( $product->get_id() ) ) {
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

		if ( ls_is_license_shipper_enabled( $pid ) && ls_get_mapped_sku( $pid ) ) {
			$count += max( 1, (int) $item->get_quantity() );
		}
	}

	return $count;
}

/**
 * Cached license rows for a product on an order.
 *
 * @return object[]
 */
function ls_get_cached_licenses_for_product( int $order_id, int $product_id ): array {
	global $wpdb;
	$table = $wpdb->prefix . 'ls_cached_licenses';

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE order_id = %d AND product_id = %d AND fetched = 1 ORDER BY id ASC",
			$order_id,
			$product_id
		)
	);
}

/**
 * Admin order metabox: all keys for a product in one code block.
 */
function ls_render_admin_order_license_keys_html( array $licenses, WC_Order $order ): string {
	unset( $order );

	if ( empty( $licenses ) ) {
		return '<em class="ls-muted">' . esc_html__( 'Not fetched yet', 'license-shipper' ) . '</em>';
	}

	$keys = array();
	foreach ( $licenses as $license ) {
		$key = trim( (string) ( $license->key_value ?? '' ) );
		if ( $key !== '' ) {
			$keys[] = $key;
		}
	}

	if ( empty( $keys ) ) {
		return '<em class="ls-muted">' . esc_html__( 'Not fetched yet', 'license-shipper' ) . '</em>';
	}

	return '<code class="ls-admin-key-value">' . esc_html( implode( "\n", $keys ) ) . '</code>';
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
		if ( ls_is_license_shipper_enabled( $pid ) && ls_get_mapped_sku( $pid ) ) {
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

	if ( $order->get_meta( '_ls_completed_license_shipper', true ) === 'yes' ) {
		return true;
	}

	// Legacy fallback: completed orders with LS products before meta existed.
	if ( $order->has_status( 'completed' ) && ls_order_has_license_shipper_product( $order ) ) {
		return true;
	}

	return false;
}

/**
 * Signed URL for downloading an activation guide PDF.
 */
function ls_activation_guide_download_url( $key_id, $order = null ) {
	$key_id = absint( $key_id );
	if ( ! $key_id ) {
		return '';
	}

	$args = array(
		'action' => 'download_activation_guide',
		'key_id' => $key_id,
	);

	if ( $order instanceof WC_Order ) {
		$args['order_key'] = $order->get_order_key();
		$args['email']     = $order->get_billing_email();
	}

	return wp_nonce_url(
		add_query_arg( $args, admin_url( 'admin-ajax.php' ) ),
		'dl_guide_' . $key_id
	);
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
 * Verify the current user/guest may access licenses for an order.
 */
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

	if ( $email && strcasecmp( $email, (string) $order->get_billing_email() ) !== 0 ) {
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
		<span><?php _e( 'License Keys', 'license-shipper' ); ?></span>

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
			   title="<?php esc_attr_e( 'Retrieve all license keys step by step', 'license-shipper' ); ?>">
				<span class="ls-btn-text"><?php _e( 'Get All Keys', 'license-shipper' ); ?></span>
			</a>
		</div>
	</h2>

	<table class="woocommerce-table woocommerce-table--order-details shop_table order_details" cellspacing="0" cellpadding="6" border="1" style="width:100%;margin-bottom:40px;">
		<thead>
			<tr>
				<th><?php _e( 'Product', 'license-shipper' ); ?></th>
				<th><?php _e( 'Email', 'license-shipper' ); ?></th>
				<th><?php _e( 'Quantity', 'license-shipper' ); ?></th>
				<th><?php _e( 'Action', 'license-shipper' ); ?></th>
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

				if ( ! ls_is_license_shipper_enabled( $product_id ) ) {
					continue;
				}

				$product_name = $product->get_name();
				$quantity     = $item->get_quantity();
				$email        = $order->get_billing_email();

				$cached_keys = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM $table WHERE order_id = %d AND product_id = %d",
						$order_id,
						$product_id
					)
				);

				$has_cached = ! empty( $cached_keys );
				?>
				<tr>
					<td>
						<a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>">
							<?php echo esc_html( $product_name ); ?>
						</a>
					</td>
					<td><?php echo esc_html( $email ); ?></td>
					<td><?php echo esc_html( $quantity ); ?></td>
					<td>
						<?php if ( $has_cached ) : ?>
							<button class="button ls-toggle-license-btn">
								<?php _e( 'View Key', 'license-shipper' ); ?>
							</button>
						<?php else : ?>
							<button class="button ls-view-license-btn"
							   data-product-name="<?php echo esc_attr( $product_name ); ?>"
							   data-product-id="<?php echo esc_attr( $product_id ); ?>"
							   data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
							   data-order-qnty="<?php echo esc_attr( $quantity ); ?>"
							   data-email="<?php echo esc_attr( $email ); ?>"
							   data-order-key="<?php echo esc_attr( $order->get_order_key() ); ?>">
								<?php _e( 'Get Key', 'license-shipper' ); ?>
							</button>
						<?php endif; ?>
					</td>
				</tr>

				<tr class="ls-license-details-row" style="display: none;">
					<td colspan="4">
						<div class="ls-license-details-content">
							<?php if ( $has_cached ) : ?>
								<table class="shop_table" style="width: 100%;">
									<thead>
										<tr>
											<th class="ls-key-table-header"><?php _e( 'License Key', 'license-shipper' ); ?></th>
											<th><?php _e( 'Download Link', 'license-shipper' ); ?></th>
											<th><?php _e( 'Activation Guide', 'license-shipper' ); ?></th>
											<th><?php _e( 'Action', 'license-shipper' ); ?></th>
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
													  <?php _e( 'Click Here', 'license-shipper' ); ?>
													</a>
												</td>
												<td>
													<a href="<?php echo esc_url( ls_activation_guide_download_url( $license->id, $order ) ); ?>"
													   target="_blank" rel="noopener"
													   class="ls-btn-guide">
													  <?php _e( 'Download Now', 'license-shipper' ); ?>
													</a>
												</td>
												<td><button class="button ls-copy-license-btn"><?php _e( 'Copy', 'license-shipper' ); ?></button></td>
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
								<p class="ls-loading"><?php _e( 'Preparing to load license...', 'license-shipper' ); ?></p>
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
 */
function ls_maybe_upgrade_database() {
	$stored = get_option( 'license_shipper_db_version', '' );
	if ( $stored === LICENSE_SHIPPER_VERSION ) {
		return;
	}

	require_once plugin_dir_path( __FILE__ ) . 'class-license-shipper-activator.php';

	if ( class_exists( 'License_Shipper_Activator' ) ) {
		License_Shipper_Activator::ls_create_license_cache_table();
		License_Shipper_Activator::ls_create_download_links_table();
		License_Shipper_Activator::ls_create_activation_guides_table();
	}

	update_option( 'license_shipper_db_version', LICENSE_SHIPPER_VERSION );
}

add_action( 'plugins_loaded', 'ls_maybe_upgrade_database', 5 );
