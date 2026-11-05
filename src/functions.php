<?php

defined( 'ABSPATH' ) || exit;

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

/**
 * Whether the page being requested contains one of the given shortcodes.
 *
 * Works before the main query runs (WooCommerce handles login/registration on
 * wp_loaded), by resolving the post from the request URL.
 *
 * @param string[] $tags Shortcode tags.
 */
function ls_request_page_has_shortcode( array $tags ): bool {
	static $cache = array();

	// Rewrite rules are not ready before init; treat as "not a shortcode page".
	if ( ! did_action( 'init' ) ) {
		return false;
	}

	$post =( did_action( 'wp' ) && is_singular() ) ? get_post() : null;
	if ( ! $post && ! empty( $_SERVER['REQUEST_URI'] ) && ! empty( $_SERVER['HTTP_HOST'] ) ) {
		$url     = ( is_ssl() ? 'https://' : 'http://' ) . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) . esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$post_id = url_to_postid( $url );
		$post    = $post_id ? get_post( $post_id ) : null;
	}

	if ( ! $post ) {
		return false;
	}

	$key = $post->ID . ':' . implode( ',', $tags );
	if ( ! isset( $cache[ $key ] ) ) {
		$cache[ $key ] = false;
		foreach ( $tags as $tag ) {
			if ( has_shortcode( (string) $post->post_content, $tag ) ) {
				$cache[ $key ] = true;
				break;
			}
		}
	}

	return $cache[ $key ];
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
	return \LicenseSender\Wholesale\Orders::order_has_wholesale_product( $order );
}

/**
 * Whether an order is a wholesale order.
 */
function ls_order_is_wholesale( WC_Order $order ): bool {
	return \LicenseSender\Wholesale\Orders::is_wholesale_order( $order );
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
 * Expected key count for all order lines that map to the same SaaS SKU.
 * Used when multiple WooCommerce products share one SaaS inventory product.
 */
function ls_count_expected_keys_for_sku_in_order( WC_Order $order, string $sku ): int {
	$sku   = trim( $sku );
	$count = 0;

	if ( $sku === '' ) {
		return 0;
	}

	foreach ( $order->get_items() as $item ) {
		$pid = (int) ( $item->get_variation_id() ?: $item->get_product_id() );
		if ( ! ls_is_licensesender_enabled( $pid ) ) {
			continue;
		}
		if ( ls_get_mapped_sku( $pid ) !== $sku ) {
			continue;
		}
		$count += max( 1, (int) $item->get_quantity() );
	}

	return $count;
}

/**
 * Cached license rows for an order + mapped SKU (any WooCommerce product_id).
 */
function ls_count_cached_licenses_for_sku( int $order_id, string $sku ): int {
	global $wpdb;

	$sku = trim( $sku );
	if ( ! $order_id || $sku === '' ) {
		return 0;
	}

	$table = $wpdb->prefix . 'ls_cached_licenses';

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE order_id = %d AND sku = %s AND fetched = 1",
			$order_id,
			$sku
		)
	);
}

/**
 * How many distinct WooCommerce products on an order map to this SaaS SKU.
 */
function ls_count_wc_products_for_sku_in_order( WC_Order $order, string $sku ): int {
	$sku = trim( $sku );
	if ( $sku === '' ) {
		return 0;
	}

	$ids = array();
	foreach ( $order->get_items() as $item ) {
		$pid = (int) ( $item->get_variation_id() ?: $item->get_product_id() );
		if ( ! ls_is_licensesender_enabled( $pid ) ) {
			continue;
		}
		if ( ls_get_mapped_sku( $pid ) !== $sku ) {
			continue;
		}
		$ids[ $pid ] = true;
	}

	return count( $ids );
}

/**
 * SaaS fetch quantity for a WooCommerce line when multiple lines may share one SKU.
 *
 * SaaS treats `quantity` as the target total for order+sku. Request only enough to
 * cover this product's remaining need on top of keys already cached for the SKU,
 * capped at the order-wide expected total for that SKU.
 *
 * When multiple WC products share the SKU, always target the full order SKU total
 * so parallel/sequential fetches top up the shared pool correctly.
 */
function ls_api_quantity_for_product_fetch( WC_Order $order, int $product_id, string $sku ): int {
	$order_id         = (int) $order->get_id();
	$sku              = trim( $sku );
	$product_expected = ls_count_expected_keys_for_product_in_order( $order, $product_id );
	$product_cached   = count( ls_get_cached_licenses_for_product( $order_id, $product_id ) );
	$product_need     = max( 0, $product_expected - $product_cached );

	if ( $product_need < 1 ) {
		return max( 1, $product_expected );
	}

	$sku_expected = ls_count_expected_keys_for_sku_in_order( $order, $sku );
	if ( $sku_expected < 1 ) {
		return max( 1, $product_need );
	}

	if ( ls_count_wc_products_for_sku_in_order( $order, $sku ) > 1 ) {
		return max( 1, $sku_expected );
	}

	$sku_cached = ls_count_cached_licenses_for_sku( $order_id, $sku );
	$target     = min( $sku_expected, $sku_cached + $product_need );

	return max( 1, $target );
}

/**
 * WooCommerce product IDs that may own cache rows for a line item.
 * Includes the line id and the meta-resolved parent when variation support is off.
 *
 * @return int[]
 */
function ls_license_cache_product_ids( int $product_id ): array {
	$product_id = absint( $product_id );
	if ( ! $product_id ) {
		return array();
	}

	$ids      = array( $product_id );
	$resolved = (int) ls_resolve_license_product_id( $product_id );
	if ( $resolved > 0 ) {
		$ids[] = $resolved;
	}

	return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
}

/**
 * Cached license rows for a product on an order (capped to expected quantity, newest kept).
 *
 * @return object[]
 */
function ls_get_cached_licenses_for_product( int $order_id, int $product_id ): array {
	global $wpdb;
	$table = $wpdb->prefix . 'ls_cached_licenses';

	$product_ids = ls_license_cache_product_ids( $product_id );
	if ( $order_id < 1 || $product_ids === array() ) {
		return array();
	}

	$placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );
	$params       = array_merge( array( $order_id ), $product_ids );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	$sql = "SELECT * FROM {$table} WHERE order_id = %d AND product_id IN ({$placeholders}) AND fetched = 1 ORDER BY id DESC";
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

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
 * Lists with more than 5 keys collapse behind a "View all" control.
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

	$total      = count( $items );
	$preview_n  = 5;
	$collapsed  = $total > $preview_n;
	$list_class = 'ls-admin-key-list' . ( $collapsed ? ' is-collapsed' : '' );

	$html = '<div class="' . esc_attr( $list_class ) . '" data-preview-count="' . esc_attr( (string) $preview_n ) . '">';
	foreach ( $items as $index => $item ) {
		$hidden_class = ( $collapsed && $index >= $preview_n ) ? ' is-overflow' : '';
		$html        .= '<div class="ls-admin-key-row' . esc_attr( $hidden_class ) . '">';
		$html        .= '<code class="ls-admin-key-value">' . esc_html( $item['key'] ) . '</code>';
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

	if ( $collapsed ) {
		$html .= '<div class="ls-admin-key-toggle-wrap">';
		$html .= '<button type="button" class="button-link ls-admin-key-toggle" aria-expanded="false">';
		$html .= '<span class="ls-admin-key-toggle-more">' . esc_html(
			sprintf(
				/* translators: %d: total license keys */
				__( 'View all %d keys', 'licensesender' ),
				$total
			)
		) . '</span>';
		$html .= '<span class="ls-admin-key-toggle-less" hidden>' . esc_html__( 'Show less', 'licensesender' ) . '</span>';
		$html .= '</button>';
		$html .= '<span class="ls-admin-key-toggle-hint">' . esc_html(
			sprintf(
				/* translators: 1: visible count, 2: total count */
				__( 'Showing %1$d of %2$d', 'licensesender' ),
				$preview_n,
				$total
			)
		) . '</span>';
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
 * Whether LicenseSender stamped this order as completed under its delivery flow.
 */
function ls_order_has_ls_delivery_meta( $order ): bool {
	if ( ! $order instanceof WC_Order ) {
		$order = wc_get_order( $order );
	}

	return $order instanceof WC_Order
		&& $order->get_meta( '_ls_completed_licensesender', true ) === 'yes';
}

/**
 * Order meta key for cached license delivery progress (complete|partial|pending).
 */
function ls_order_license_delivery_meta_key(): string {
	return '_ls_license_keys_status';
}

/**
 * Compute and persist license delivery status meta for list/column UI.
 *
 * @return string complete|partial|pending|none
 */
function ls_refresh_order_license_delivery_meta( $order ): string {
	if ( ! $order instanceof WC_Order ) {
		$order = wc_get_order( $order );
	}
	if ( ! $order instanceof WC_Order ) {
		return 'none';
	}

	if ( ! ls_order_has_licensable_products( $order ) ) {
		$order->delete_meta_data( ls_order_license_delivery_meta_key() );
		$order->save();
		return 'none';
	}

	$expected = ls_count_expected_license_keys( $order );
	$fetched  = ls_count_fetched_license_keys( (int) $order->get_id() );

	if ( $expected <= 0 ) {
		$status = 'none';
	} elseif ( $fetched >= $expected ) {
		$status = 'complete';
	} elseif ( $fetched > 0 ) {
		$status = 'partial';
	} else {
		$status = 'pending';
	}

	$order->update_meta_data( ls_order_license_delivery_meta_key(), $status );
	$order->update_meta_data( '_ls_license_keys_fetched', $fetched );
	$order->update_meta_data( '_ls_license_keys_expected', $expected );
	$order->save();

	return $status;
}

/**
 * Delivery status for admin UI: complete|partial|pending|waiting|none
 * "waiting" = licensable but order not completed yet.
 */
function ls_get_order_license_delivery_status( WC_Order $order ): string {
	if ( ! ls_order_has_licensable_products( $order ) ) {
		return 'none';
	}

	$expected = ls_count_expected_license_keys( $order );
	if ( $expected <= 0 ) {
		return 'none';
	}

	$fetched = ls_count_fetched_license_keys( (int) $order->get_id() );
	if ( $fetched >= $expected ) {
		return 'complete';
	}
	if ( $fetched > 0 ) {
		return 'partial';
	}

	$meta = (string) $order->get_meta( ls_order_license_delivery_meta_key(), true );
	if ( $meta === 'complete' ) {
		return 'complete';
	}
	if ( $meta === 'partial' ) {
		return 'partial';
	}

	if ( $order->get_status() !== 'completed' ) {
		return 'waiting';
	}

	return 'pending';
}

/**
 * Ensure and return the plugin activation date (Y-m-d, site timezone).
 *
 * Existing installs without a stored date are seeded once on first read.
 */
function ls_ensure_plugin_activation_date(): string {
	$existing = trim( (string) get_option( 'lship_activated_at', '' ) );
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $existing ) ) {
		return $existing;
	}

	$date = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d' ) : gmdate( 'Y-m-d' );
	add_option( 'lship_activated_at', $date );

	return $date;
}

/**
 * Effective delivery start cutoff (Y-m-d).
 * Falls back to plugin activation date when the setting is empty/invalid.
 */
function ls_get_delivery_start_date(): string {
	$cutoff = trim( (string) get_option( 'lship_delivery_start_date', '' ) );
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $cutoff ) ) {
		return $cutoff;
	}

	$activated = ls_ensure_plugin_activation_date();
	add_option( 'lship_delivery_start_date', $activated );

	return $activated;
}

/**
 * Whether the order was paid (or, if unpaid, created) on/after the merchant delivery start date.
 */
function ls_order_is_after_delivery_start( $order ): bool {
	if ( ! $order instanceof WC_Order ) {
		$order = wc_get_order( $order );
	}

	if ( ! $order instanceof WC_Order ) {
		return false;
	}

	$cutoff = ls_get_delivery_start_date();
	if ( $cutoff === '' || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $cutoff ) ) {
		return false;
	}

	// Paid (else created) date: it never changes, unlike the completed date, which moves to
	// "now" whenever an old order is completed again.
	$order_dt = $order->get_date_paid() ?: $order->get_date_created();
	if ( ! $order_dt ) {
		return false;
	}

	try {
		$cutoff_dt = new DateTimeImmutable( $cutoff . ' 00:00:00', wp_timezone() );
	} catch ( Exception $e ) {
		return false;
	}

	$order_local = $order_dt->setTimezone( wp_timezone() );

	return $order_local->getTimestamp() >= $cutoff_dt->getTimestamp();
}

/**
 * Whether this order may call SaaS to assign new license keys.
 *
 * Allowed for completed orders paid on/after lship_delivery_start_date, which blocks
 * orders an earlier system delivered (even if one is completed again later).
 */
function ls_order_can_fetch_new_keys( $order ): bool {
	if ( ! $order instanceof WC_Order ) {
		$order = wc_get_order( $order );
	}

	if ( ! $order instanceof WC_Order ) {
		return false;
	}

	if ( ! $order->has_status( 'completed' ) ) {
		return false;
	}

	if ( ! ls_order_has_licensesender_product( $order ) ) {
		return false;
	}

	// Only orders paid on/after the delivery start date. The "completed by LicenseSender" flag
	// is not enough on its own: it is also set when an old order is completed again, which
	// would let it fetch new keys for an order another system already delivered.
	return ls_order_is_after_delivery_start( $order );
}

/**
 * Customer-facing reason when new key delivery is blocked.
 */
function ls_order_delivery_block_message( $order = null ): string {
	if ( $order && ! $order instanceof WC_Order ) {
		$order = wc_get_order( $order );
	}

	if ( $order instanceof WC_Order && ! $order->has_status( 'completed' ) ) {
		return __( 'Your license keys will be available here once your order is complete.', 'licensesender' );
	}

	return __(
		'Keys for this order were delivered before our current system. Please check your earlier order email, or contact us if you need help.',
		'licensesender'
	);
}

/**
 * Whether license fetch/display is allowed for this order (HPOS-safe).
 *
 * Fetch of new keys requires LS completion meta OR delivery-start cutoff.
 * Display of already-cached keys is handled separately by callers.
 */
function ls_is_order_license_ready( $order ) {
	return ls_order_can_fetch_new_keys( $order );
}

/**
 * Stable secret for activation-guide download tokens (does not rotate with AUTH salts).
 */
function ls_guide_download_secret(): string {
	$secret = (string) get_option( 'lship_guide_download_secret', '' );
	if ( strlen( $secret ) >= 32 ) {
		return $secret;
	}

	$secret = wp_generate_password( 64, true, true );
	update_option( 'lship_guide_download_secret', $secret, false );

	return $secret;
}

/**
 * URL-safe base64 (no +, /, or =) so email clients and query parsers do not corrupt tokens.
 */
function ls_base64url_encode( string $data ): string {
	return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
}

/**
 * Decode URL-safe or legacy standard base64 tokens.
 */
function ls_base64url_decode( string $data ) {
	$data = trim( $data );
	if ( $data === '' ) {
		return false;
	}

	// Repair common query-string corruption: + became space.
	$data = str_replace( ' ', '+', $data );

	if ( strpos( $data, '-' ) !== false || strpos( $data, '_' ) !== false ) {
		$padded = $data . str_repeat( '=', ( 4 - ( strlen( $data ) % 4 ) ) % 4 );
		$raw    = base64_decode( strtr( $padded, '-_', '+/' ), true );
		if ( is_string( $raw ) && $raw !== '' ) {
			return $raw;
		}
	}

	$raw = base64_decode( $data, true );
	return ( is_string( $raw ) && $raw !== '' ) ? $raw : false;
}

/**
 * Long-lived HMAC token for activation-guide downloads (email-safe).
 *
 * @param int      $key_id Cached license row ID.
 * @param WC_Order $order  Order.
 * @param int      $ttl    Seconds until expiry.
 * @return string
 */
function ls_create_guide_download_token( $key_id, WC_Order $order, $ttl = null ) {
	$key_id  = absint( $key_id );
	$ttl     = null === $ttl ? ( defined( 'YEAR_IN_SECONDS' ) ? 5 * YEAR_IN_SECONDS : 5 * 365 * DAY_IN_SECONDS ) : (int) $ttl;
	$expires = time() + max( DAY_IN_SECONDS, $ttl );
	$email   = strtolower( sanitize_email( (string) $order->get_billing_email() ) );
	$payload = $key_id . '|' . (int) $order->get_id() . '|' . $email . '|' . $expires;
	$sig     = hash_hmac( 'sha256', $payload, ls_guide_download_secret() );

	return ls_base64url_encode( $payload . '|' . $sig );
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
	$raw = ls_base64url_decode( (string) $token );
	if ( ! is_string( $raw ) || $raw === '' ) {
		return false;
	}

	$parts = explode( '|', $raw );
	if ( count( $parts ) !== 5 ) {
		return false;
	}

	list( $tok_key_id, $tok_order_id, $tok_email, $tok_expires, $tok_sig ) = $parts;
	$payload = $tok_key_id . '|' . $tok_order_id . '|' . $tok_email . '|' . $tok_expires;

	$secrets = array_unique(
		array_filter(
			array(
				ls_guide_download_secret(),
				(string) wp_salt( 'auth' ), // Legacy tokens signed with AUTH salt.
			)
		)
	);

	$sig_ok = false;
	foreach ( $secrets as $secret ) {
		$expect = hash_hmac( 'sha256', $payload, $secret );
		if ( hash_equals( $expect, $tok_sig ) ) {
			$sig_ok = true;
			break;
		}
	}

	if ( ! $sig_ok ) {
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
function ls_acquire_fetch_lock( int $order_id, int $product_id, string $sku = '' ) {
	$sku = trim( $sku );
	// Shared-SKU lines must serialize on the SKU, not the WC product id.
	$option = $sku !== ''
		? 'ls_fetch_lock_' . $order_id . '_sku_' . md5( strtolower( $sku ) )
		: 'ls_fetch_lock_' . $order_id . '_' . $product_id;
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

function ls_release_fetch_lock( int $order_id, int $product_id, string $sku = '' ): void {
	$sku = trim( $sku );
	$option = $sku !== ''
		? 'ls_fetch_lock_' . $order_id . '_sku_' . md5( strtolower( $sku ) )
		: 'ls_fetch_lock_' . $order_id . '_' . $product_id;
	delete_option( $option );
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

/**
 * License Keys section on the order details and thank-you pages (React, build/public/my-keys.js).
 *
 * Renders into a Shadow DOM so the theme can't restyle it. Guests (thank-you page) use the
 * same proof as before: the order key + billing email, checked on every request.
 *
 * @param int  $order_id Order ID.
 * @param bool $return   Return the markup instead of printing it.
 * @return string|void
 */
function ls_render_license_table( $order_id, $return = false ) {
	if ( empty( $order_id ) ) {
		return;
	}

	$order = wc_get_order( $order_id );

	if ( ! $order || $order->get_status() !== 'completed' ) {
		return;
	}

	if ( ! ls_order_has_licensesender_product( $order ) ) {
		return;
	}

	$items = \LicenseSender\Licenses\MyKeysEndpoint::items_from_orders( array( $order ) );
	if ( ! $items ) {
		return;
	}

	$tokens = class_exists( \LicenseSender\Core\DesignSystem::class ) ? \LicenseSender\Core\DesignSystem::resolve_tokens() : array();
	$config = array(
		'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
		'nonce'         => wp_create_nonce( 'ls_order_keys' ),
		'orderId'       => (int) $order->get_id(),
		'orderKey'      => (string) $order->get_order_key(),
		'email'         => (string) $order->get_billing_email(),
		'exportUrl'     => add_query_arg(
			array(
				'action'    => 'export_license_key',
				'order_id'  => $order->get_id(),
				'order_key' => $order->get_order_key(),
				'email'     => $order->get_billing_email(),
				'_wpnonce'  => wp_create_nonce( 'export_license_nonce' ),
			),
			admin_url( 'admin-ajax.php' )
		),
		'canFetch'      => ls_order_can_fetch_new_keys( $order ),
		'blockMessage'  => ls_order_delivery_block_message( $order ),
		'items'         => $items,
		'brand'         => (string) ( $tokens['brand'] ?? '#4f46e5' ),
		'radiusCard'    => (string) ( $tokens['radius_card'] ?? '14px' ),
		'radiusControl' => (string) ( $tokens['radius_ctl'] ?? '10px' ),
		'i18n'          => array_merge(
			\LicenseSender\Licenses\MyKeysEndpoint::app_strings(),
			array(
				'sectionTitle'    => __( 'License keys', 'licensesender' ),
				'downloadAll'     => __( 'Download all (CSV)', 'licensesender' ),
				'getAll'          => __( 'Get all keys', 'licensesender' ),
				/* translators: 1: current product, 2: products to fetch */
				'gettingAll'      => __( 'Getting keys %d of %d…', 'licensesender' ),
				'allDeliveredOne' => __( 'Your keys are ready.', 'licensesender' ),
				'readyTitle'      => __( 'Your license keys are ready', 'licensesender' ),
				/* translators: %s: billing email */
				'readyLead'       => __( 'Get them now — they appear right here, and we email a copy to %s.', 'licensesender' ),
				'readyLeadNoEmail' => __( 'Get them now — they appear right here.', 'licensesender' ),
				'oneLicense'      => __( '1 license key', 'licensesender' ),
				/* translators: %d: number of keys */
				'licensesCount'   => __( '%d license keys', 'licensesender' ),
				'delivering'      => __( 'Delivering your keys…', 'licensesender' ),
				/* translators: 1: current step, 2: total steps */
				'stepOf'          => __( '%d of %d', 'licensesender' ),
				'deliveryNote'    => __( 'Delivered instantly to your order.', 'licensesender' ),
				'getMyKey'        => __( 'Get my key', 'licensesender' ),
				/* translators: %d: number of keys */
				'getMyKeys'       => __( 'Get my %d keys', 'licensesender' ),
				'getAllCount'     => __( 'Get all %d keys', 'licensesender' ),
				/* translators: %d: number of products */
				'allDelivered'    => __( 'Keys delivered for all %d products.', 'licensesender' ),
			)
		),
	);

	wp_enqueue_script( 'ls-my-keys-app' );
	wp_add_inline_script( 'ls-my-keys-app', 'window.LSOrderKeysApp = ' . wp_json_encode( $config ) . ';', 'before' );

	ob_start();
	?>
	<div class="ls-order-keys-app" data-theme="<?php echo esc_attr( \LicenseSender\Core\DesignSystem::color_mode() ); ?>" style="margin:0 0 40px;">
		<p class="ls-order-keys-loading" style="padding:24px 0;color:#64748b;font:14px/1.5 system-ui,-apple-system,'Segoe UI',sans-serif;"><?php esc_html_e( 'Loading license keys…', 'licensesender' ); ?></p>
		<noscript><p><?php esc_html_e( 'Please enable JavaScript to view your license keys.', 'licensesender' ); ?></p></noscript>
	</div>
	<?php
	$html = ob_get_clean();

	if ( $return ) {
		return $html;
	}

	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above with escaping
}

function ls_customizeEmailTemplate( $template, $data = array() ) {
	return strtr( $template, $data );
}

/**
 * Neutralize spreadsheet formula injection in CSV cells.
 *
 * Cells starting with =, +, -, @ (or control chars) execute as formulas when
 * the export is opened in Excel/Sheets. Key values come from the SaaS and
 * product names from the catalog, so treat both as untrusted.
 */
function ls_csv_safe_field( $value ): string {
	$value = (string) $value;
	if ( $value === '' ) {
		return $value;
	}

	if ( preg_match( '/^[=+\-@\t\r]/', $value ) ) {
		return "'" . $value;
	}

	return $value;
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


	if ( class_exists( \LicenseSender\Core\Activator::class ) ) {
		\LicenseSender\Core\Activator::ls_create_license_cache_table();
		\LicenseSender\Core\Activator::ls_create_download_links_table();
		\LicenseSender\Core\Activator::ls_create_activation_guides_table();
	}

	// Seed delivery cutoff defaults for upgrades that predate these options.
	ls_get_delivery_start_date();

	update_option( 'licensesender_db_version', LICENSESENDER_VERSION );
}

add_action( 'plugins_loaded', 'ls_maybe_upgrade_database', 5 );
