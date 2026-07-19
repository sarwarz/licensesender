<?php
/**
 * Local license delivery cache and API sync.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_License_Cache {

	const SYNC_TRANSIENT_PREFIX = 'ls_license_sync_order_';
	const SYNC_DEBOUNCE_SECONDS = 60;

	/**
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'ls_cached_licenses';
	}

	/**
	 * @param array<string, mixed> $license License payload from fetch API.
	 */
	public static function extract_remote_license_id( array $license ) {
		if ( ! empty( $license['license_id'] ) ) {
			return (int) $license['license_id'];
		}
		if ( ! empty( $license['id'] ) ) {
			return (int) $license['id'];
		}
		return 0;
	}

	/**
	 * @param array<string, mixed> $license License payload from fetch API.
	 */
	public static function extract_license_key( array $license ) {
		if ( ! empty( $license['key'] ) ) {
			return trim( (string) $license['key'] );
		}
		if ( ! empty( $license['key_value'] ) ) {
			return trim( (string) $license['key_value'] );
		}
		return '';
	}

	/**
	 * Persist licenses returned from license/fetch.
	 *
	 * @param int                  $order_id         WooCommerce order ID.
	 * @param int                  $product_id       WooCommerce product ID.
	 * @param string               $sku              Mapped SKU.
	 * @param string               $email            Customer email.
	 * @param array<int, array>    $licenses         API license rows.
	 * @param string               $download_link    Download URL.
	 * @param string               $activation_guide Activation guide URL/content.
	 * @param string               $source           Source label.
	 * @return array<int, object> Inserted/updated row objects for rendering.
	 */
	public static function save_fetched_licenses( $order_id, $product_id, $sku, $email, array $licenses, $download_link, $activation_guide, $source = 'api' ) {
		global $wpdb;

		$order_id   = (int) $order_id;
		$product_id = (int) $product_id;
		$saved      = array();

		foreach ( $licenses as $license ) {
			if ( ! is_array( $license ) ) {
				continue;
			}

			$key_val = self::extract_license_key( $license );
			if ( $key_val === '' ) {
				continue;
			}

			$remote_id = self::extract_remote_license_id( $license );
			$row_id    = self::upsert_license_row(
				array(
					'order_id'          => $order_id,
					'product_id'        => $product_id,
					'sku'               => $sku,
					'email'             => $email,
					'key_value'         => $key_val,
					'download_link'     => $download_link,
					'activation_guide'  => $activation_guide,
					'source'            => $source,
					'remote_license_id' => $remote_id,
					'fetched'           => 1,
				)
			);

			if ( $row_id ) {
				$row = $wpdb->get_row(
					$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', $row_id )
				);
				if ( $row ) {
					$saved[] = $row;
				}
			}
		}

		delete_transient( self::SYNC_TRANSIENT_PREFIX . $order_id );

		return $saved;
	}

	/**
	 * Insert or update a cache row.
	 *
	 * Identity is the SaaS remote_license_id so quantity N keeps N rows even when
	 * multiple inventory units share the same key string. Key-value matching is only
	 * used as a legacy fallback for rows that still have no remote id.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return int Local row ID or 0.
	 */
	public static function upsert_license_row( array $data ) {
		global $wpdb;

		$table           = self::table_name();
		$order_id        = (int) ( $data['order_id'] ?? 0 );
		$product_id      = (int) ( $data['product_id'] ?? 0 );
		$key_value       = trim( (string) ( $data['key_value'] ?? '' ) );
		$remote_id       = (int) ( $data['remote_license_id'] ?? 0 );
		$now             = current_time( 'mysql' );

		if ( ! $order_id || $key_value === '' ) {
			return 0;
		}

		$existing_id = 0;

		if ( $remote_id > 0 ) {
			// Prefer remote ID — never collapse different remote licenses by key text.
			$existing_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE remote_license_id = %d LIMIT 1",
					$remote_id
				)
			);
		} elseif ( $product_id > 0 ) {
			// Legacy rows without a remote ID: only rematch unlinked rows.
			$existing_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table}
					WHERE order_id = %d AND product_id = %d AND key_value = %s
					AND (remote_license_id IS NULL OR remote_license_id = 0)
					LIMIT 1",
					$order_id,
					$product_id,
					$key_value
				)
			);
		}

		$row = array(
			'order_id'         => $order_id,
			'product_id'       => $product_id,
			'sku'              => (string) ( $data['sku'] ?? '' ),
			'email'            => (string) ( $data['email'] ?? '' ),
			'key_value'        => $key_value,
			'download_link'    => (string) ( $data['download_link'] ?? '' ),
			'activation_guide' => (string) ( $data['activation_guide'] ?? '' ),
			'source'           => (string) ( $data['source'] ?? 'api' ),
			'fetched'          => (int) ( $data['fetched'] ?? 1 ),
			'last_synced_at'   => $now,
		);

		if ( $remote_id > 0 ) {
			$row['remote_license_id'] = $remote_id;
		}

		$formats = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' );
		if ( isset( $row['remote_license_id'] ) ) {
			$formats = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' );
		}

		if ( $existing_id ) {
			$wpdb->update(
				$table,
				$row,
				array( 'id' => $existing_id ),
				$formats,
				array( '%d' )
			);
			return $existing_id;
		}

		$inserted = $wpdb->insert( $table, $row, $formats );

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Apply a key replacement to the local cache (updates the same row; no duplicate).
	 *
	 * @param int    $order_id          WooCommerce order ID.
	 * @param string $original_key      Previous key value.
	 * @param string $replacement_key   New key value.
	 * @param int    $remote_license_id Optional NEW API license ID (not report ticket ID).
	 * @return bool
	 */
	public static function apply_replacement( $order_id, $original_key, $replacement_key, $remote_license_id = 0 ) {
		global $wpdb;

		$order_id          = (int) $order_id;
		$original_key      = trim( (string) $original_key );
		$replacement_key   = trim( (string) $replacement_key );
		$remote_license_id = (int) $remote_license_id;

		if ( ! $order_id || $replacement_key === '' || $original_key === '' ) {
			return false;
		}

		$table = self::table_name();

		// Always match the dead key by value — never by report/ticket id.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d AND key_value = %s LIMIT 1",
				$order_id,
				$original_key
			)
		);

		if ( ! $row ) {
			return false;
		}

		$update = array(
			'key_value'        => $replacement_key,
			'source'           => 'replaced',
			'last_synced_at'   => current_time( 'mysql' ),
			// Clear stale remote id so a later sync can rematch the new license.
			'remote_license_id' => $remote_license_id > 0 ? $remote_license_id : 0,
		);

		$updated = $wpdb->update(
			$table,
			$update,
			array( 'id' => (int) $row->id ),
			array( '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return false;
		}

		// Keep other quantity slots even when they share the same key string.
		delete_transient( self::SYNC_TRANSIENT_PREFIX . $order_id );
		self::prune_excess_keys_for_order( $order_id );

		return true;
	}

	/**
	 * Delete local cache rows that exceed the ordered quantity (keeps newest per product).
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return int Number of rows deleted.
	 */
	public static function prune_excess_keys_for_order( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			return 0;
		}

		$table   = self::table_name();
		$deleted = 0;
		$seen    = array();

		foreach ( $order->get_items() as $item ) {
			$product_id = (int) ( $item->get_variation_id() ?: $item->get_product_id() );
			if ( ! $product_id || isset( $seen[ $product_id ] ) ) {
				continue;
			}
			$seen[ $product_id ] = true;

			if ( ! ls_is_licensesender_enabled( $product_id ) || ! ls_get_mapped_sku( $product_id ) ) {
				continue;
			}

			$expected = ls_count_expected_keys_for_product_in_order( $order, $product_id );
			if ( $expected < 1 ) {
				continue;
			}

			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE order_id = %d AND product_id = %d AND fetched = 1 ORDER BY id DESC",
					$order_id,
					$product_id
				)
			);

			if ( ! is_array( $ids ) || count( $ids ) <= $expected ) {
				continue;
			}

			$keep    = array_slice( array_map( 'intval', $ids ), 0, $expected );
			$remove  = array_diff( array_map( 'intval', $ids ), $keep );
			if ( empty( $remove ) ) {
				continue;
			}

			$placeholders = implode( ',', array_fill( 0, count( $remove ), '%d' ) );
			$sql          = "DELETE FROM {$table} WHERE id IN ({$placeholders})";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $wpdb->prepare( $sql, $remove ) );
			$deleted += count( $remove );
		}

		return $deleted;
	}

	/**
	 * Delete local rows for an order that were not matched to the latest API sync.
	 *
	 * @param int        $order_id    WooCommerce order ID.
	 * @param array<int> $matched_ids Local row IDs that remain valid.
	 * @return int Number of rows deleted.
	 */
	public static function prune_unmatched_keys_for_order( $order_id, array $matched_ids ) {
		global $wpdb;

		$order_id = (int) $order_id;
		$table    = self::table_name();
		$matched_ids = array_values( array_unique( array_filter( array_map( 'intval', $matched_ids ) ) ) );

		if ( ! $order_id ) {
			return 0;
		}

		// Never wipe an order's cache when the matched set is empty (empty API / caller bug).
		if ( empty( $matched_ids ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $matched_ids ), '%d' ) );
		$params       = array_merge( array( $order_id ), $matched_ids );
		$sql          = "DELETE FROM {$table} WHERE order_id = %d AND id NOT IN ({$placeholders})";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->query( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Pull assigned/redeemed licenses from the API and update local cache.
	 *
	 * @param int  $order_id WooCommerce order ID.
	 * @param bool $force    Skip debounce transient.
	 * @return array{success:bool,message:string,updated:int,inserted:int}
	 */
	public static function sync_order_licenses( $order_id, $force = false ) {
		$order_id = (int) $order_id;
		if ( ! $order_id ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid order ID.', 'licensesender' ),
				'updated' => 0,
				'inserted' => 0,
			);
		}

		if ( ! $force && get_transient( self::SYNC_TRANSIENT_PREFIX . $order_id ) ) {
			return array(
				'success'  => true,
				'message'  => __( 'Licenses were synced recently.', 'licensesender' ),
				'updated'  => 0,
				'inserted' => 0,
				'skipped'  => true,
			);
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array(
				'success' => false,
				'message' => __( 'Order not found.', 'licensesender' ),
				'updated' => 0,
				'inserted' => 0,
			);
		}

		if ( ! class_exists( 'Licensesender_Api' ) ) {
			return array(
				'success' => false,
				'message' => __( 'License API is not available.', 'licensesender' ),
				'updated' => 0,
				'inserted' => 0,
			);
		}

		$email   = (string) $order->get_billing_email();
		$api_res = Licensesender_Api::list_licenses_for_order( $order_id, $email, array( 'per_page' => 100 ) );

		if ( empty( $api_res['success'] ) ) {
			return array(
				'success'  => false,
				'message'  => (string) ( $api_res['message'] ?? __( 'Could not sync licenses from API.', 'licensesender' ) ),
				'updated'  => 0,
				'inserted' => 0,
			);
		}

		$api_licenses = is_array( $api_res['licenses'] ?? null ) ? $api_res['licenses'] : array();
		$updated      = 0;
		$inserted     = 0;
		$removed      = 0;
		$local_rows   = self::get_rows_for_order( $order_id );
		$matched_ids  = array();
		$api_remote_ids = array();
		$processed_remote_ids = array();

		foreach ( $api_licenses as $api_license ) {
			if ( ! is_array( $api_license ) ) {
				continue;
			}

			$remote_id = self::extract_remote_license_id( $api_license );
			if ( ! $remote_id ) {
				continue;
			}
			$api_remote_ids[] = $remote_id;

			$full = Licensesender_Api::get_license( $remote_id );
			if ( empty( $full['success'] ) || empty( $full['license'] ) || ! is_array( $full['license'] ) ) {
				continue;
			}

			$license   = $full['license'];
			$key_value = self::extract_license_key( $license );
			if ( $key_value === '' ) {
				continue;
			}

			$sku        = self::resolve_api_license_sku( $license, $order );
			$product_id = self::resolve_wc_product_id_for_order( $order, $sku );
			$local_row  = self::match_local_row( $local_rows, $remote_id, $key_value, $order_id, $sku, $matched_ids );

			$links = $product_id ? ls_get_license_product_links( $product_id ) : array(
				'download_link'    => '',
				'activation_guide' => '',
			);

			if ( $local_row ) {
				$matched_ids[] = (int) $local_row->id;
				$processed_remote_ids[] = $remote_id;
				$changed       = ( (string) $local_row->key_value !== $key_value )
					|| (int) ( $local_row->remote_license_id ?? 0 ) !== $remote_id;

				if ( $changed ) {
					self::upsert_license_row(
						array(
							'order_id'          => $order_id,
							'product_id'        => $product_id ?: (int) $local_row->product_id,
							'sku'               => $sku ?: (string) $local_row->sku,
							'email'             => $email,
							'key_value'         => $key_value,
							'download_link'     => $links['download_link'] ?: (string) $local_row->download_link,
							'activation_guide'  => $links['activation_guide'] ?: (string) $local_row->activation_guide,
							'source'            => 'synced',
							'remote_license_id' => $remote_id,
							'fetched'           => 1,
						)
					);
					++$updated;
				} else {
					global $wpdb;
					$wpdb->update(
						self::table_name(),
						array( 'last_synced_at' => current_time( 'mysql' ) ),
						array( 'id' => (int) $local_row->id ),
						array( '%s' ),
						array( '%d' )
					);
				}
				continue;
			}

			if ( ! $product_id && $sku ) {
				$product_id = self::resolve_wc_product_id_for_order( $order, $sku );
			}

			if ( ! $product_id ) {
				continue;
			}

			$new_id = self::upsert_license_row(
				array(
					'order_id'          => $order_id,
					'product_id'        => $product_id,
					'sku'               => $sku,
					'email'             => $email,
					'key_value'         => $key_value,
					'download_link'     => $links['download_link'],
					'activation_guide'  => $links['activation_guide'],
					'source'            => 'synced',
					'remote_license_id' => $remote_id,
					'fetched'           => 1,
				)
			);

			if ( $new_id ) {
				$matched_ids[] = $new_id;
				$processed_remote_ids[] = $remote_id;
				++$inserted;
			}
		}

		$api_remote_ids       = array_values( array_unique( $api_remote_ids ) );
		$processed_remote_ids = array_values( array_unique( $processed_remote_ids ) );

		// Only prune locals that no longer appear in a non-empty, fully-processed API set.
		// Never wipe cache when the API returns an empty list (outage / filter miss).
		if ( ! empty( $api_remote_ids ) && count( $processed_remote_ids ) === count( $api_remote_ids ) ) {
			$removed += self::prune_unmatched_keys_for_order( $order_id, $matched_ids );
		}

		// Hard cap: never keep more cached keys than the order quantity.
		$removed += self::prune_excess_keys_for_order( $order_id );

		set_transient( self::SYNC_TRANSIENT_PREFIX . $order_id, 1, self::SYNC_DEBOUNCE_SECONDS );

		return array(
			'success'  => true,
			'message'  => __( 'Licenses synced from API.', 'licensesender' ),
			'updated'  => $updated,
			'inserted' => $inserted,
			'removed'  => $removed,
		);
	}

	/**
	 * @return array<int, object>
	 */
	public static function get_rows_for_order( $order_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table_name() . ' WHERE order_id = %d AND fetched = 1 ORDER BY id ASC',
				(int) $order_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array<int, object> $local_rows Local cache rows.
	 * @param int                $remote_id  API license ID.
	 * @param string             $key_value  Full license key.
	 * @param int                $order_id   Order ID.
	 * @param string             $sku        Product SKU.
	 * @param array<int, int>    $matched_ids Already matched local IDs.
	 * @return object|null
	 */
	private static function match_local_row( array $local_rows, $remote_id, $key_value, $order_id, $sku, array $matched_ids ) {
		foreach ( $local_rows as $row ) {
			if ( in_array( (int) $row->id, $matched_ids, true ) ) {
				continue;
			}
			if ( (int) ( $row->remote_license_id ?? 0 ) === $remote_id ) {
				return $row;
			}
		}

		// Legacy fallback: only bind unlinked rows so identical key strings
		// belonging to different remote licenses stay as separate quantity slots.
		foreach ( $local_rows as $row ) {
			if ( in_array( (int) $row->id, $matched_ids, true ) ) {
				continue;
			}
			if ( (int) ( $row->remote_license_id ?? 0 ) !== 0 ) {
				continue;
			}
			if ( (string) $row->key_value === $key_value ) {
				return $row;
			}
		}

		if ( $sku !== '' ) {
			foreach ( $local_rows as $row ) {
				if ( in_array( (int) $row->id, $matched_ids, true ) ) {
					continue;
				}
				if ( (string) $row->sku === $sku && (int) ( $row->remote_license_id ?? 0 ) === 0 ) {
					return $row;
				}
			}
		}

		unset( $order_id );
		return null;
	}

	/**
	 * @param array<string, mixed> $license API license row.
	 * @param WC_Order             $order   WooCommerce order.
	 */
	private static function resolve_api_license_sku( array $license, WC_Order $order ) {
		if ( ! empty( $license['sku'] ) ) {
			return (string) $license['sku'];
		}

		$product_id = (int) ( $license['product_id'] ?? 0 );
		if ( $product_id && class_exists( 'Licensesender_Api' ) ) {
			static $sku_cache = array();
			if ( ! isset( $sku_cache[ $product_id ] ) ) {
				$res = Licensesender_Api::get_product( $product_id );
				$sku_cache[ $product_id ] = ( ! empty( $res['success'] ) && ! empty( $res['product']['sku'] ) )
					? (string) $res['product']['sku']
					: '';
			}
			if ( $sku_cache[ $product_id ] !== '' ) {
				return $sku_cache[ $product_id ];
			}
		}

		// Do not invent SKU from another product on the order — leave unmatched.
		return '';
	}

	/**
	 * @param WC_Order $order WooCommerce order.
	 * @param string   $sku   Licensesender SKU.
	 */
	private static function resolve_wc_product_id_for_order( WC_Order $order, $sku ) {
		$sku = trim( (string) $sku );
		if ( $sku === '' ) {
			return 0;
		}

		foreach ( $order->get_items() as $item ) {
			$pid = (int) ( $item->get_variation_id() ?: $item->get_product_id() );
			if ( ls_get_mapped_sku( $pid ) === $sku ) {
				return $pid;
			}
		}

		return 0;
	}
}
