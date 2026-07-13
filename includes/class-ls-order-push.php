<?php
/**
 * Push completed WooCommerce orders to the LicenseSender SaaS app.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Order_Push {

	const CRON_HOOK     = 'ls_push_order_to_saas';
	const META_PUSHED   = '_ls_saas_order_pushed';
	const META_ATTEMPTS = '_ls_saas_order_push_attempts';
	const MAX_ATTEMPTS  = 5;

	public static function init() {
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'schedule_push' ), 20, 1 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'handle_push' ), 10, 1 );
	}

	/**
	 * Queue a background push when an order with LS products is completed.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function schedule_push( $order_id ) {
		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! ls_order_has_licensesender_product( $order ) ) {
			return;
		}

		if ( $order->get_meta( self::META_PUSHED ) === 'yes' ) {
			return;
		}

		$hook = self::CRON_HOOK;
		$args = array( $order_id );

		if ( ! wp_next_scheduled( $hook, $args ) ) {
			wp_schedule_single_event( time() + 5, $hook, $args );
		}
	}

	/**
	 * Cron/AS callback: ingest order on SaaS, then fetch/cache licenses.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function handle_push( $order_id ) {
		$order_id = absint( $order_id );
		$order    = wc_get_order( $order_id );

		if ( ! $order || ! ls_order_has_licensesender_product( $order ) ) {
			return;
		}

		if ( $order->get_meta( self::META_PUSHED ) === 'yes' ) {
			self::auto_deliver_licenses( $order );
			return;
		}

		$attempts = (int) $order->get_meta( self::META_ATTEMPTS );
		$result   = Licensesender_Api::ingest_order( self::build_payload( $order ) );

		if ( empty( $result['success'] ) ) {
			$attempts++;
			$order->update_meta_data( self::META_ATTEMPTS, $attempts );
			$order->save();

			if ( $attempts < self::MAX_ATTEMPTS ) {
				$delay = min( 900, 30 * (int) pow( 2, max( 0, $attempts - 1 ) ) );
				wp_schedule_single_event( time() + $delay, self::CRON_HOOK, array( $order_id ) );
			}

			return;
		}

		$order->update_meta_data( self::META_PUSHED, 'yes' );
		$order->update_meta_data( self::META_ATTEMPTS, $attempts + 1 );
		$order->save();

		self::auto_deliver_licenses( $order );
	}

	/**
	 * Build ingest payload from a WC order (mapped LS SKUs only).
	 *
	 * @param WC_Order $order Order.
	 * @return array<string, mixed>
	 */
	public static function build_payload( WC_Order $order ) {
		$line_items = array();

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			$product_id = (int) $product->get_id();
			if ( ! ls_is_licensesender_enabled( $product_id ) ) {
				continue;
			}

			$sku = ls_get_mapped_sku( $product_id );
			if ( $sku === '' ) {
				continue;
			}

			$resolved = ls_resolve_license_product_id( $product_id );

			$line_items[] = array(
				'id'           => (string) $item->get_id(),
				'product_id'   => (string) ( $resolved ?: $product_id ),
				'variation_id' => (string) $item->get_variation_id(),
				'name'         => $item->get_name(),
				'sku'          => $sku,
				'quantity'     => max( 1, (int) $item->get_quantity() ),
				'total'        => (string) $item->get_total(),
			);
		}

		$billing_first = (string) $order->get_billing_first_name();
		$billing_last  = (string) $order->get_billing_last_name();
		$customer_name = trim( $billing_first . ' ' . $billing_last );

		$created = $order->get_date_created();

		return array(
			'external_id'    => (string) $order->get_id(),
			'order_number'   => (string) $order->get_order_number(),
			'status'         => $order->get_status(),
			'customer_name'  => $customer_name !== '' ? $customer_name : null,
			'customer_email' => $order->get_billing_email(),
			'total'          => $order->get_total(),
			'currency'       => $order->get_currency(),
			'ordered_at'     => $created ? $created->date( 'c' ) : gmdate( 'c' ),
			'website'        => wp_parse_url( home_url(), PHP_URL_HOST ),
			'line_items'     => $line_items,
		);
	}

	/**
	 * Fetch licenses for each mapped line item and cache locally.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function auto_deliver_licenses( WC_Order $order ) {
		$order_id = $order->get_id();
		$email    = $order->get_billing_email();

		if ( ! $email ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			$product_id = (int) $product->get_id();
			if ( ! ls_is_licensesender_enabled( $product_id ) ) {
				continue;
			}

			$sku = ls_get_mapped_sku( $product_id );
			if ( $sku === '' ) {
				continue;
			}

			$resolved = (int) ( ls_resolve_license_product_id( $product_id ) ?: $product_id );
			$expected = max( 1, (int) $item->get_quantity() );
			$cached   = count( ls_get_cached_licenses_for_product( $order_id, $resolved ) );
			$need     = max( 0, $expected - $cached );

			if ( $need < 1 ) {
				continue;
			}

			$lock = ls_acquire_fetch_lock( $order_id, $resolved );
			if ( is_wp_error( $lock ) ) {
				continue;
			}

			$result = Licensesender_Api::fetch_license(
				array(
					'sku'      => $sku,
					'quantity' => $need,
					'order_id' => $order_id,
					'email'    => $email,
					'source'   => 'woocommerce',
				)
			);

			if ( ! empty( $result['success'] ) && ! empty( $result['licenses'] ) && is_array( $result['licenses'] ) ) {
				$product_info = is_array( $result['product'] ?? null ) ? $result['product'] : array();
				$links        = ls_get_license_product_links( $resolved );

				LS_License_Cache::save_fetched_licenses(
					$order_id,
					$resolved,
					$sku,
					$email,
					$result['licenses'],
					$links['download_link'] ?: ( $product_info['download_link'] ?? '' ),
					$links['activation_guide'] ?: ( $product_info['activation_guide'] ?? '' ),
					'woocommerce'
				);

				LS_License_Email_Service::maybe_schedule_after_fetch( $order, $email );
			}

			ls_release_fetch_lock( $order_id, $resolved );
		}
	}
}

LS_Order_Push::init();
