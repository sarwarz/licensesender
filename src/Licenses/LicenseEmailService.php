<?php

namespace LicenseSender\Licenses;

use LicenseSender\Core\Api;
use LicenseSender\Licenses\Emails\LicenseKeysEmail;

defined( 'ABSPATH' ) || exit;

/**
 * The customer's license keys email: one per order, once every key is delivered.
 *
 * Who sends it (Settings → Email):
 * - WordPress (default): the "License keys" WooCommerce email, through the store's mailer.
 * - LicenseSender app: the app emails the customer from the shop's mail settings and its
 *   own template. If the app can't (shop mail not set up, API down), WordPress sends instead,
 *   so customers always get their keys exactly once.
 */
class LicenseEmailService {

	const ORDER_META_SENT = '_ls_license_email_sent';
	const ORDER_META_BY   = '_ls_license_email_sent_by';
	const CRON_HOOK       = 'ls_send_license_email_event';
	const DEBOUNCE_SEC    = 10;
	const OPTION_SENDER   = 'lship_email_sender';

	const SENDER_WORDPRESS = 'wordpress';
	const SENDER_APP       = 'app';

	/** @var string Outcome of the last send, for admin messages. */
	public static $last_message = '';

	public static function init() {
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register_email' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'send_order_email' ), 10, 2 );
	}

	/**
	 * @param array<string, \WC_Email> $emails Registered emails.
	 * @return array<string, \WC_Email>
	 */
	public static function register_email( $emails ) {
		$emails['LS_License_Keys_Email'] = new LicenseKeysEmail();

		return $emails;
	}

	public static function is_enabled(): bool {
		return get_option( 'lship_send_email_after_redeem' ) === 'yes';
	}

	public static function sender(): string {
		return get_option( self::OPTION_SENDER, self::SENDER_WORDPRESS ) === self::SENDER_APP ? self::SENDER_APP : self::SENDER_WORDPRESS;
	}

	/** Edit link for the WooCommerce email. */
	public static function wc_settings_url(): string {
		return admin_url( 'admin.php?page=wc-settings&tab=email&section=' . strtolower( 'LS_License_Keys_Email' ) );
	}

	public static function is_order_email_sent( int $order_id ): bool {
		$order = wc_get_order( $order_id );
		if ( $order && $order->get_meta( self::ORDER_META_SENT, true ) === 'yes' ) {
			return true;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ls_cached_licenses';

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE order_id = %d AND email_sent = 1", $order_id )
		) > 0;
	}

	public static function is_order_ready_for_email( int $order_id ): bool {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$expected = ls_count_expected_license_keys( $order );
		$fetched  = ls_count_fetched_license_keys( $order_id );

		return $expected > 0 && $fetched >= $expected;
	}

	public static function maybe_schedule_after_fetch( \WC_Order $order, string $email ): void {
		if ( ! self::is_enabled() || ! is_email( $email ) ) {
			return;
		}

		if ( self::is_order_email_sent( $order->get_id() ) || ! self::is_order_ready_for_email( $order->get_id() ) ) {
			return;
		}

		self::schedule_order_email( $order->get_id(), $email );
	}

	public static function schedule_order_email( int $order_id, string $email ): void {
		if ( self::is_order_email_sent( $order_id ) ) {
			return;
		}

		$args = array( $order_id, $email );
		if ( wp_next_scheduled( self::CRON_HOOK, $args ) ) {
			return;
		}

		wp_schedule_single_event( time() + self::DEBOUNCE_SEC, self::CRON_HOOK, $args );
	}

	/**
	 * Email the customer their keys.
	 *
	 * @param bool $force Resend: skip the "already sent" and "emails off" checks.
	 */
	public static function send_order_email( int $order_id, string $email, bool $force = false ): bool {
		self::$last_message = '';

		if ( ! is_email( $email ) ) {
			self::$last_message = __( 'The order has no valid email address.', 'licensesender' );
			return false;
		}
		if ( ! $force && ( ! self::is_enabled() || self::is_order_email_sent( $order_id ) ) ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$entries = self::entries_for_order( $order );
		if ( empty( $entries ) ) {
			self::$last_message = __( 'No license keys have been delivered for this order yet.', 'licensesender' );
			return false;
		}

		if ( self::sender() === self::SENDER_APP ) {
			$result = Api::send_order_email( $order_id, $email );
			if ( $result['success'] ) {
				$order->add_order_note(
					/* translators: %s: customer email */
					sprintf( __( 'License keys emailed to %s by LicenseSender.', 'licensesender' ), $email )
				);
				self::mark_order_email_sent( $order_id, self::SENDER_APP );
				self::$last_message = __( 'LicenseSender emailed the keys.', 'licensesender' );

				return true;
			}

			// Never leave the customer without keys: WordPress sends instead.
			$order->add_order_note(
				sprintf(
					/* translators: %s: reason from LicenseSender */
					__( 'LicenseSender could not email the license keys (%s). Sent from WordPress instead.', 'licensesender' ),
					$result['message'] !== '' ? rtrim( $result['message'], '.' ) : __( 'no response', 'licensesender' )
				)
			);
		}

		$sent = self::send_with_woocommerce( $order, $email, $entries, $force );
		if ( $sent ) {
			self::mark_order_email_sent( $order_id, self::SENDER_WORDPRESS );
			self::$last_message = __( 'License email sent.', 'licensesender' );
		} else {
			self::$last_message = __( 'WordPress could not send the email. Check your mail settings (an SMTP plugin is recommended).', 'licensesender' );
			self::log_failure( $order_id, $email );
		}

		return $sent;
	}

	/**
	 * A sample email through the current sender's WordPress email (the app's own template
	 * has its test button in the LicenseSender panel).
	 */
	public static function send_test_email( string $email ): bool {
		if ( ! is_email( $email ) ) {
			return false;
		}

		$entries = array(
			array(
				'name'     => 'Windows 11 Pro',
				'keys'     => array( 'VK7JG-NPHTM-C97JM-9MPGT-3V66T', 'W269N-WFGWX-YVC9B-4J6C9-T83GX' ),
				'download' => home_url( '/' ),
				'guide'    => home_url( '/' ),
			),
			array(
				'name'     => 'Office 2021 Professional Plus',
				'keys'     => array( 'FXYTK-NJJ8C-GB6DW-3DYQT-6F7TH' ),
				'download' => home_url( '/' ),
				'guide'    => '',
			),
		);

		return self::send_with_woocommerce( null, $email, $entries, true );
	}

	/**
	 * @param array<int, array<string, mixed>> $entries
	 */
	private static function send_with_woocommerce( ?\WC_Order $order, string $email, array $entries, bool $send_anyway ): bool {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}

		$emails = WC()->mailer()->get_emails();
		$mail   = $emails['LS_License_Keys_Email'] ?? null;
		if ( ! $mail instanceof LicenseKeysEmail ) {
			return false;
		}

		$mail->send_anyway = $send_anyway;
		$sent              = $mail->trigger( $order, $email, $entries );
		$mail->send_anyway = false;

		return $sent;
	}

	/**
	 * Delivered keys grouped by product, with download and activation guide links.
	 *
	 * @return array<int, array{name: string, keys: array<int, string>, download: string, guide: string}>
	 */
	public static function entries_for_order( \WC_Order $order ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_cached_licenses';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, product_id, key_value, download_link FROM {$table} WHERE order_id = %d AND fetched = 1 ORDER BY product_id ASC, id ASC",
				$order->get_id()
			)
		);

		$entries = array();
		foreach ( (array) $rows as $row ) {
			$pid = (int) $row->product_id;
			if ( ! isset( $entries[ $pid ] ) ) {
				$product         = wc_get_product( $pid );
				$entries[ $pid ] = array(
					'name'     => $product ? $product->get_name() : sprintf( 'Product #%d', $pid ),
					'keys'     => array(),
					'download' => filter_var( (string) $row->download_link, FILTER_VALIDATE_URL ) ? (string) $row->download_link : '',
					'guide'    => function_exists( 'ls_activation_guide_download_url' ) ? (string) ls_activation_guide_download_url( (int) $row->id, $order ) : '',
				);
			}
			if ( (string) $row->key_value !== '' ) {
				$entries[ $pid ]['keys'][] = (string) $row->key_value;
			}
		}

		return array_values(
			array_filter(
				$entries,
				static function ( $entry ) {
					return ! empty( $entry['keys'] );
				}
			)
		);
	}

	private static function mark_order_email_sent( int $order_id, string $by ): void {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$order->update_meta_data( self::ORDER_META_SENT, 'yes' );
			$order->update_meta_data( self::ORDER_META_BY, $by );
			$order->save();
		}

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'ls_cached_licenses',
			array( 'email_sent' => 1 ),
			array( 'order_id' => $order_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	private static function log_failure( int $order_id, string $email ): void {
		$message = sprintf( 'licensesender: failed to send license email for order %d to %s', $order_id, $email );
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $message, array( 'source' => 'licensesender' ) );
		} else {
			error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
