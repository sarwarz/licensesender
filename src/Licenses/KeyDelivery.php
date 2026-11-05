<?php

namespace LicenseSender\Licenses;

use LicenseSender\Core\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Viewing and fetching an order line's license keys for customers.
 *
 * Shared by My Account → My Keys (logged-in owner) and the order details / thank-you
 * License Keys section (owner, or guest with order key + billing email). Callers check
 * access first; everything here assumes the caller may see this order.
 */
class KeyDelivery {

	/**
	 * The order line's product id (variation first), or 0 when it isn't in the order.
	 */
	public static function product_in_order( \WC_Order $order, int $product_id ): int {
		foreach ( $order->get_items() as $item ) {
			$pid = (int) $item->get_product_id();
			$vid = (int) $item->get_variation_id();
			if ( $vid && $vid === $product_id ) {
				return $vid;
			}
			if ( ! $vid && $pid === $product_id ) {
				return $pid;
			}
		}
		return 0;
	}

	/**
	 * Saved keys for a line, refreshed from LicenseSender first (cheap: see LicenseCache).
	 *
	 * @return array<string, mixed>|\WP_Error Payload, or not_found (404) when nothing is saved yet.
	 */
	public static function view( \WC_Order $order, int $product_id ) {
		$product_id = self::product_in_order( $order, $product_id );
		if ( ! $product_id ) {
			return new \WP_Error( 'bad_req', __( 'Product not found in this order.', 'licensesender' ), array( 'status' => 404 ) );
		}

		$order_id = (int) $order->get_id();
		if ( ! self::cached_rows( $order_id, $product_id ) ) {
			return new \WP_Error( 'not_found', __( 'No saved keys found.', 'licensesender' ), array( 'status' => 404 ) );
		}

		if ( class_exists( LicenseCache::class ) ) {
			LicenseCache::sync_order_licenses( $order_id );
		}

		return self::payload( $order, self::cached_rows( $order_id, $product_id ), ls_count_expected_keys_for_product_in_order( $order, $product_id ) );
	}

	/**
	 * Return a line's keys, fetching missing ones from LicenseSender.
	 *
	 * @return array<string, mixed>|\WP_Error Payload (with `expected`), or an error whose
	 *                                       message is safe to show the customer.
	 */
	public static function fetch( \WC_Order $order, int $product_id, string $email = '' ) {
		$product_id = self::product_in_order( $order, $product_id );
		if ( ! $product_id ) {
			return new \WP_Error( 'bad_req', __( 'Product not found in this order.', 'licensesender' ), array( 'status' => 404 ) );
		}

		// Completed orders only — checked before any API call (the sync below talks to the API).
		if ( ! $order->has_status( 'completed' ) ) {
			return new \WP_Error( 'not_completed', ls_order_delivery_block_message( $order ), array( 'status' => 400 ) );
		}

		$order_id = (int) $order->get_id();
		$email    = $email !== '' ? $email : (string) $order->get_billing_email();
		$expected = ls_count_expected_keys_for_product_in_order( $order, $product_id );

		if ( class_exists( LicenseCache::class ) ) {
			LicenseCache::sync_order_licenses( $order_id );
		}

		$rows = self::cached_rows( $order_id, $product_id );
		if ( $expected > 0 && count( $rows ) >= $expected ) {
			return self::payload( $order, $rows, $expected );
		}

		if ( ! ls_order_can_fetch_new_keys( $order ) ) {
			return new \WP_Error( 'not_eligible', ls_order_delivery_block_message( $order ), array( 'status' => 403 ) );
		}
		if ( ! ls_is_licensesender_enabled( $product_id ) ) {
			return new \WP_Error( 'disabled', __( 'licensesender is not enabled for this product.', 'licensesender' ), array( 'status' => 400 ) );
		}
		$mapped_sku = ls_get_mapped_sku( $product_id );
		if ( empty( $mapped_sku ) ) {
			return new \WP_Error( 'no_sku', __( 'This product does not have a mapped SKU.', 'licensesender' ), array( 'status' => 400 ) );
		}
		if ( ! class_exists( Api::class ) || ! method_exists( Api::class, 'fetch_license' ) ) {
			return new \WP_Error( 'no_api', __( 'License API is not available.', 'licensesender' ), array( 'status' => 500 ) );
		}

		$lock = ls_acquire_fetch_lock( $order_id, $product_id, $mapped_sku );
		if ( is_wp_error( $lock ) ) {
			$lock->add_data( array( 'status' => 409 ) );
			return $lock;
		}

		// Release the lock on every path, including unexpected exceptions.
		try {
			$result = Api::fetch_license(
				array(
					'sku'      => $mapped_sku,
					'quantity' => ls_api_quantity_for_product_fetch( $order, $product_id, $mapped_sku ),
					'order_id' => $order_id,
					'email'    => $email,
					'source'   => sanitize_title( get_bloginfo( 'name' ) ),
				)
			);

			if ( empty( $result['success'] ) ) {
				self::note_fetch_failure( $order, $result );
				return new \WP_Error( 'fetch_failed', self::customer_fetch_error( $result ), array( 'status' => 422 ) );
			}

			$licenses     = is_array( $result['licenses'] ?? null ) ? $result['licenses'] : array();
			$product_info = is_array( $result['product'] ?? null ) ? $result['product'] : array();
			$links        = ls_get_license_product_links( $product_id );

			LicenseCache::save_fetched_licenses(
				$order_id,
				$product_id,
				$mapped_sku,
				$email,
				$licenses,
				$links['download_link'] ?: ( $product_info['download_link'] ?? '' ),
				$links['activation_guide'] ?: ( $product_info['activation_guide'] ?? '' ),
				'api'
			);

			if ( class_exists( LicenseEmailService::class ) ) {
				LicenseEmailService::maybe_schedule_after_fetch( $order, $email );
			}

			$rows = self::cached_rows( $order_id, $product_id );
		} catch ( \Throwable $e ) {
			self::note_fetch_failure( $order, array( 'message' => $e->getMessage() ) );
			return new \WP_Error( 'fetch_crashed', __( 'We could not deliver your keys right now. Please try again in a few minutes.', 'licensesender' ), array( 'status' => 500 ) );
		} finally {
			ls_release_fetch_lock( $order_id, $product_id, $mapped_sku );
		}

		if ( empty( $rows ) ) {
			return new \WP_Error( 'empty', __( 'Your keys are not ready yet. Please try again in a few minutes.', 'licensesender' ), array( 'status' => 409 ) );
		}

		return self::payload( $order, $rows, $expected );
	}

	/**
	 * Saved keys for an order line (latest first).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function cached_rows( int $order_id, int $product_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_cached_licenses';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, key_value, download_link, activation_guide, sku, email, source FROM {$table} WHERE order_id = %d AND product_id = %d AND fetched = 1 ORDER BY id DESC",
				$order_id,
				$product_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Keys plus shared links/meta and a signed activation-guide URL per key.
	 *
	 * @param array<int, array<string, mixed>> $rows Cached rows.
	 * @return array<string, mixed>
	 */
	public static function payload( \WC_Order $order, array $rows, int $expected ): array {
		$meta = array(
			'download_link'    => '',
			'activation_guide' => '',
			'sku'              => '',
			'email'            => '',
			'source'           => '',
			'guide_url'        => '',
		);
		$keys = array();
		foreach ( $rows as $i => $r ) {
			foreach ( array( 'download_link', 'activation_guide', 'sku', 'email', 'source' ) as $k ) {
				if ( $meta[ $k ] === '' && ! empty( $r[ $k ] ) ) {
					$meta[ $k ] = (string) $r[ $k ];
				}
			}
			$key_id = isset( $r['id'] ) ? (int) $r['id'] : 0;
			$gurl   = $key_id ? ls_activation_guide_download_url( $key_id, $order ) : '';
			if ( 0 === $i && $gurl ) {
				$meta['guide_url'] = $gurl;
			}
			$keys[] = array(
				'id'               => $key_id,
				'key_value'        => (string) ( $r['key_value'] ?? '' ),
				'download_link'    => (string) ( $r['download_link'] ?? '' ),
				'activation_guide' => (string) ( $r['activation_guide'] ?? '' ),
				'sku'              => (string) ( $r['sku'] ?? '' ),
				'email'            => (string) ( $r['email'] ?? '' ),
				'source'           => (string) ( $r['source'] ?? '' ),
				'guide_url'        => $gurl,
			);
		}

		return array(
			'count'    => count( $rows ),
			'expected' => $expected,
			'keys'     => $keys,
			'meta'     => $meta,
		);
	}

	/**
	 * Send a view()/fetch() result as the AJAX response.
	 *
	 * @param array<string, mixed>|\WP_Error $result Result.
	 */
	public static function send( $result ): void {
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				(int) ( is_array( $data ) ? ( $data['status'] ?? 400 ) : 400 )
			);
		}
		wp_send_json_success( $result );
	}

	/**
	 * What the customer sees when LicenseSender refuses a fetch. The API's own messages are
	 * written for the store owner (quota, subscription, blacklist reasons) and must not
	 * reach customers; only stock and "busy" states are worth telling them as-is.
	 *
	 * @param array<string, mixed> $result Api::fetch_license() result.
	 */
	public static function customer_fetch_error( array $result ): string {
		$message = strtolower( (string) ( $result['message'] ?? '' ) );

		if ( str_contains( $message, 'not currently available' ) || str_contains( $message, 'restock' ) ) {
			return __( 'These keys are being restocked. Please check back in 5–20 minutes.', 'licensesender' );
		}
		if ( str_contains( $message, 'already in progress' ) ) {
			return __( 'Your keys are already being prepared. Please try again in a moment.', 'licensesender' );
		}

		return __( 'We could not deliver your keys automatically. Please contact support and we will sort it out.', 'licensesender' );
	}

	/**
	 * Private order note with the real reason, so the store owner can act on it.
	 * The same failure is noted at most once every 10 minutes per order.
	 *
	 * @param array<string, mixed> $result Api::fetch_license() result.
	 */
	public static function note_fetch_failure( \WC_Order $order, array $result ): void {
		$detail = trim( (string) ( $result['message'] ?? '' ) );
		$reason = trim( (string) ( $result['meta']['reason'] ?? '' ) );
		if ( $reason !== '' ) {
			$detail .= ' — ' . $reason;
		}
		if ( $detail === '' ) {
			$detail = __( 'Unknown error', 'licensesender' );
		}

		$throttle = 'ls_fetch_fail_' . $order->get_id() . '_' . md5( $detail );
		if ( get_transient( $throttle ) ) {
			return;
		}
		set_transient( $throttle, 1, 10 * MINUTE_IN_SECONDS );

		/* translators: %s: reason returned by LicenseSender */
		$order->add_order_note( sprintf( __( 'Customer tried to get license keys, but LicenseSender refused: %s', 'licensesender' ), $detail ) );
	}
}
