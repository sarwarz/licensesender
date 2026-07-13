<?php
/**
 * Incoming webhooks from LicenseSender app (license replacements, wholesale catalog).
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Webhook_Receiver {

	const OPTION_SECRET = 'lship_webhook_secret';
	const ROUTE         = '/webhooks/license-replaced';
	const NAMESPACE     = 'licensesender/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Ensure a webhook secret exists (activation / first use).
	 *
	 * @return string
	 */
	public static function ensure_secret() {
		$secret = (string) get_option( self::OPTION_SECRET, '' );
		if ( $secret === '' ) {
			$secret = self::generate_secret();
			update_option( self::OPTION_SECRET, $secret, false );
		}
		return $secret;
	}

	/**
	 * @return string
	 */
	public static function generate_secret() {
		if ( function_exists( 'wp_generate_password' ) ) {
			return wp_generate_password( 48, false, false );
		}
		return bin2hex( random_bytes( 24 ) );
	}

	/**
	 * Public webhook URL for this site.
	 *
	 * @return string
	 */
	public static function get_webhook_url() {
		return rest_url( self::NAMESPACE . self::ROUTE );
	}

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings/webhook-secret/regenerate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'regenerate_secret' ),
				'permission_callback' => array( __CLASS__, 'can_manage_options' ),
			)
		);
	}

	public static function can_manage_options() {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Regenerate webhook secret (admin only).
	 *
	 * @return WP_REST_Response
	 */
	public static function regenerate_secret() {
		$secret = self::generate_secret();
		update_option( self::OPTION_SECRET, $secret, false );

		return rest_ensure_response(
			array(
				'success'              => true,
				'lship_webhook_secret' => $secret,
				'lship_webhook_url'    => self::get_webhook_url(),
				'message'              => __( 'Webhook secret regenerated.', 'licensesender' ),
			)
		);
	}

	/**
	 * Dispatch signed webhooks from LicenseSender by event type.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_webhook( WP_REST_Request $request ) {
		$raw = $request->get_body();
		if ( $raw === '' ) {
			return new WP_Error( 'empty_body', __( 'Empty webhook body.', 'licensesender' ), array( 'status' => 400 ) );
		}

		$secret = self::ensure_secret();
		if ( $secret === '' ) {
			return new WP_Error( 'webhook_not_configured', __( 'Webhook secret is not configured.', 'licensesender' ), array( 'status' => 503 ) );
		}

		$signature = (string) $request->get_header( 'x-ls-signature' );
		if ( $signature === '' ) {
			$signature = (string) $request->get_header( 'X-LS-Signature' );
		}

		if ( ! self::verify_signature( $raw, $signature, $secret ) ) {
			return new WP_Error( 'invalid_signature', __( 'Invalid webhook signature.', 'licensesender' ), array( 'status' => 401 ) );
		}

		$timestamp = (string) $request->get_header( 'x-ls-timestamp' );
		if ( $timestamp === '' ) {
			$timestamp = (string) $request->get_header( 'X-LS-Timestamp' );
		}
		if ( $timestamp === '' ) {
			return new WP_Error( 'missing_timestamp', __( 'Webhook timestamp is required.', 'licensesender' ), array( 'status' => 401 ) );
		}
		$ts = is_numeric( $timestamp ) ? (int) $timestamp : strtotime( $timestamp );
		if ( ! $ts || abs( time() - $ts ) > 300 ) {
			return new WP_Error( 'stale_webhook', __( 'Webhook timestamp is outside the allowed window.', 'licensesender' ), array( 'status' => 401 ) );
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_json', __( 'Invalid JSON payload.', 'licensesender' ), array( 'status' => 400 ) );
		}

		$event = (string) ( $data['event'] ?? 'license.replaced' );

		if ( $event === 'license.replaced' || $event === '' ) {
			return self::handle_license_replaced_payload( $data );
		}

		if ( $event === 'wholesale.catalog.changed' ) {
			return self::handle_wholesale_catalog_changed( $data );
		}

		return new WP_Error(
			'unsupported_event',
			sprintf( __( 'Unsupported event: %s', 'licensesender' ), $event ),
			array( 'status' => 400 )
		);
	}

	/**
	 * @deprecated 1.0.0 Use handle_webhook().
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_license_replaced( WP_REST_Request $request ) {
		return self::handle_webhook( $request );
	}

	/**
	 * Clear wholesale catalog cache when SaaS product pricing changes.
	 *
	 * @param array $data Decoded payload.
	 * @return WP_REST_Response
	 */
	private static function handle_wholesale_catalog_changed( array $data ) {
		if ( class_exists( 'LS_Wholesale' ) ) {
			LS_Wholesale::clear_catalog_cache();
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'cleared' => true,
				'message' => __( 'Wholesale catalog cache cleared.', 'licensesender' ),
				'sku'     => isset( $data['sku'] ) ? (string) $data['sku'] : '',
			)
		);
	}

	/**
	 * Handle license.replaced webhook payload.
	 *
	 * @param array $data Decoded payload.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function handle_license_replaced_payload( array $data ) {
		$order_id        = absint( $data['order_id'] ?? 0 );
		$original_key    = trim( (string) ( $data['original_key'] ?? '' ) );
		$replacement_key = trim( (string) ( $data['replacement_key'] ?? '' ) );
		$new_remote_id   = (int) ( $data['replacement_license_id'] ?? $data['new_license_id'] ?? 0 );

		if ( ! $order_id || $original_key === '' || $replacement_key === '' ) {
			return new WP_Error(
				'missing_fields',
				__( 'order_id, original_key, and replacement_key are required.', 'licensesender' ),
				array( 'status' => 422 )
			);
		}

		if ( ! class_exists( 'LS_License_Cache' ) ) {
			return new WP_Error( 'unavailable', __( 'License cache is not available.', 'licensesender' ), array( 'status' => 500 ) );
		}

		$applied = LS_License_Cache::apply_replacement(
			$order_id,
			$original_key,
			$replacement_key,
			$new_remote_id
		);

		if ( ! $applied ) {
			// Original key missing locally — pull from API then retry once.
			LS_License_Cache::sync_order_licenses( $order_id, true );
			$applied = LS_License_Cache::apply_replacement(
				$order_id,
				$original_key,
				$replacement_key,
				$new_remote_id
			);
		}

		if ( ! $applied ) {
			// Still missing original; if we only have the new key from sync, treat as success after prune.
			LS_License_Cache::prune_excess_keys_for_order( $order_id );

			return rest_ensure_response(
				array(
					'success'  => true,
					'updated'  => false,
					'message'  => __( 'Webhook received; original key not found in local cache. Order synced.', 'licensesender' ),
					'order_id' => $order_id,
				)
			);
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'updated'  => true,
				'message'  => __( 'License key replaced in WordPress cache.', 'licensesender' ),
				'order_id' => $order_id,
			)
		);
	}

	/**
	 * @param string $raw_body  Raw request body.
	 * @param string $signature Header value (sha256=... or bare hex).
	 * @param string $secret    Shared secret.
	 */
	public static function verify_signature( $raw_body, $signature, $secret ) {
		$signature = trim( (string) $signature );
		$secret    = (string) $secret;

		if ( $signature === '' || $secret === '' ) {
			return false;
		}

		if ( stripos( $signature, 'sha256=' ) === 0 ) {
			$signature = substr( $signature, 7 );
		}

		$expected = hash_hmac( 'sha256', $raw_body, $secret );

		return hash_equals( $expected, $signature );
	}
}

LS_Webhook_Receiver::init();
