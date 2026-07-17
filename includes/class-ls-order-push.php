<?php
/**
 * Push completed WooCommerce orders to the LicenseSender SaaS app.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Order_Push {

	const CRON_HOOK         = 'ls_push_order_to_saas';
	const CRON_BACKFILL     = 'ls_backfill_orders_to_saas';
	const META_PUSHED       = '_ls_saas_order_pushed';
	const META_INGEST_ONLY  = '_ls_saas_ingest_only';
	const META_ATTEMPTS     = '_ls_saas_order_push_attempts';
	const OPTION_BACKFILL   = 'ls_order_backfill_status';
	const MAX_ATTEMPTS      = 5;
	const BACKFILL_BATCH    = 15;

	public static function init() {
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'schedule_push' ), 20, 1 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'handle_push' ), 10, 1 );
		add_action( self::CRON_BACKFILL, array( __CLASS__, 'process_backfill_batch' ), 10, 0 );
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
	 * Cron/AS callback: ingest order on SaaS only.
	 * Keys are assigned when the customer clicks Get Key (or admin fetches) — not on complete.
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
			return;
		}

		$result = self::ingest_order_record( $order );

		if ( empty( $result['success'] ) ) {
			$attempts = (int) $order->get_meta( self::META_ATTEMPTS ) + 1;
			$order->update_meta_data( self::META_ATTEMPTS, $attempts );
			$order->save();

			if ( $attempts < self::MAX_ATTEMPTS ) {
				$delay = min( 900, 30 * (int) pow( 2, max( 0, $attempts - 1 ) ) );
				wp_schedule_single_event( time() + $delay, self::CRON_HOOK, array( $order_id ) );
			}

			return;
		}

		$order->update_meta_data( self::META_PUSHED, 'yes' );
		$order->update_meta_data( self::META_ATTEMPTS, (int) $order->get_meta( self::META_ATTEMPTS ) + 1 );
		$order->save();
	}

	/**
	 * Start a background backfill of past completed orders (ingest only — no key delivery).
	 *
	 * @return array<string, mixed>
	 */
	public static function start_backfill() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'success' => false,
				'message' => __( 'WooCommerce is required.', 'licensesender' ),
			);
		}

		$api_key = (string) get_option( 'lship_api_key', '' );
		if ( $api_key === '' ) {
			return array(
				'success' => false,
				'message' => __( 'Set your LicenseSender API key before backfilling orders.', 'licensesender' ),
			);
		}

		$status = self::get_backfill_status();
		if ( ! empty( $status['running'] ) ) {
			return array(
				'success' => true,
				'message' => __( 'Backfill is already running.', 'licensesender' ),
				'status'  => $status,
			);
		}

		$pending = self::count_pending_backfill_orders();

		update_option(
			self::OPTION_BACKFILL,
			array(
				'running'    => $pending > 0,
				'started_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
				'total'      => $pending,
				'processed'  => 0,
				'succeeded'  => 0,
				'failed'     => 0,
				'skipped'    => 0,
				'last_error' => '',
				'finished'   => $pending === 0,
			),
			false
		);

		if ( $pending === 0 ) {
			return array(
				'success' => true,
				'message' => __( 'No completed LicenseSender orders need backfilling.', 'licensesender' ),
				'status'  => self::get_backfill_status(),
			);
		}

		if ( ! wp_next_scheduled( self::CRON_BACKFILL ) ) {
			wp_schedule_single_event( time() + 2, self::CRON_BACKFILL );
		}

		// Run first batch immediately so the admin sees progress without waiting for WP-Cron.
		self::process_backfill_batch();

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %d: order count */
				__( 'Backfill started for %d completed order(s). Keys will not be re-delivered.', 'licensesender' ),
				$pending
			),
			'status'  => self::get_backfill_status(),
		);
	}

	/**
	 * Process one batch of historical completed orders (ingest-only).
	 */
	public static function process_backfill_batch() {
		$status = self::get_backfill_status( true );

		if ( empty( $status['running'] ) ) {
			return;
		}

		$orders = self::query_pending_backfill_orders( self::BACKFILL_BATCH );
		if ( $orders === array() ) {
			$status['running']    = false;
			$status['finished']   = true;
			$status['updated_at'] = current_time( 'mysql' );
			update_option( self::OPTION_BACKFILL, $status, false );
			return;
		}

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			if ( ! ls_order_has_licensesender_product( $order ) ) {
				$status['skipped']++;
				$status['processed']++;
				continue;
			}

			// Mark ingest-only before push so retries never deliver keys.
			$order->update_meta_data( self::META_INGEST_ONLY, 'yes' );
			$order->save();

			$result = self::ingest_order_record( $order );

			if ( empty( $result['success'] ) ) {
				$status['failed']++;
				$status['processed']++;
				$status['last_error'] = (string) ( $result['message'] ?? __( 'Ingest failed.', 'licensesender' ) );
				continue;
			}

			$order->update_meta_data( self::META_PUSHED, 'yes' );
			$order->update_meta_data( self::META_INGEST_ONLY, 'yes' );
			$order->save();

			$status['succeeded']++;
			$status['processed']++;
		}

		$status['updated_at'] = current_time( 'mysql' );

		$still_pending = self::count_pending_backfill_orders();
		if ( $still_pending < 1 ) {
			$status['running']  = false;
			$status['finished'] = true;
		} else {
			$status['running'] = true;
			if ( ! wp_next_scheduled( self::CRON_BACKFILL ) ) {
				wp_schedule_single_event( time() + 5, self::CRON_BACKFILL );
			}
		}

		update_option( self::OPTION_BACKFILL, $status, false );
	}

	/**
	 * @param bool $raw Return stored array without defaults when missing.
	 * @return array<string, mixed>
	 */
	public static function get_backfill_status( $raw = false ) {
		$stored = get_option( self::OPTION_BACKFILL, null );
		if ( ! is_array( $stored ) ) {
			if ( $raw ) {
				return array();
			}

			$pending = self::count_pending_backfill_orders();

			return array(
				'running'           => false,
				'finished'          => false,
				'total'             => $pending,
				'processed'         => 0,
				'succeeded'         => 0,
				'failed'            => 0,
				'skipped'           => 0,
				'pending'           => $pending,
				'last_error'        => '',
				'started_at'        => '',
				'updated_at'        => '',
			);
		}

		$stored['pending'] = self::count_pending_backfill_orders();

		return $stored;
	}

	/**
	 * @return int
	 */
	public static function count_pending_backfill_orders() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}

		$ids = self::query_pending_backfill_order_ids( 200 );
		$count = count( $ids );

		// Coarse scan for UI when many exist; exact total is refined during processing.
		if ( $count >= 200 ) {
			return $count; // "at least"
		}

		$eligible = 0;
		foreach ( $ids as $id ) {
			$order = wc_get_order( $id );
			if ( $order && ls_order_has_licensesender_product( $order ) ) {
				$eligible++;
			}
		}

		return $eligible;
	}

	/**
	 * @param int $limit Batch size.
	 * @return WC_Order[]
	 */
	protected static function query_pending_backfill_orders( $limit = 15 ) {
		$ids = self::query_pending_backfill_order_ids( max( 50, $limit * 4 ) );
		$out = array();

		foreach ( $ids as $id ) {
			$order = wc_get_order( $id );
			if ( ! $order || ! ls_order_has_licensesender_product( $order ) ) {
				continue;
			}
			$out[] = $order;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * @param int $limit Max IDs to return.
	 * @return int[]
	 */
	protected static function query_pending_backfill_order_ids( $limit = 50 ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$ids = wc_get_orders(
			array(
				'type'       => 'shop_order',
				'status'     => array( 'wc-completed', 'completed' ),
				'limit'      => (int) $limit,
				'return'     => 'ids',
				'orderby'    => 'ID',
				'order'      => 'ASC',
				'meta_query' => array(
					array(
						'key'     => self::META_PUSHED,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		return array_map( 'absint', is_array( $ids ) ? $ids : array() );
	}

	/**
	 * @param WC_Order $order Order.
	 * @return array<string, mixed>
	 */
	protected static function ingest_order_record( WC_Order $order ) {
		return Licensesender_Api::ingest_order( self::build_payload( $order ) );
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
	 * Kept for admin/manual use. Order-complete push no longer calls this —
	 * customers assign keys via Get Key on My Keys / thank-you flows.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function auto_deliver_licenses( WC_Order $order ) {
		$order_id = $order->get_id();
		$email    = $order->get_billing_email();

		if ( ! $email ) {
			return;
		}

		if ( $order->get_meta( self::META_INGEST_ONLY ) === 'yes' ) {
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
					// Keep Source consistent with My Keys / other fetch paths (shop name, not platform).
					'source'   => sanitize_title( get_bloginfo( 'name' ) ) ?: 'woocommerce',
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
