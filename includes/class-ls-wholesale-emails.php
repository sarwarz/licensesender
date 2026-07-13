<?php
/**
 * Register and trigger wholesale WooCommerce emails.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Wholesale_Emails {

	public static function init() {
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register_emails' ) );
	}

	/**
	 * @param array<string, WC_Email> $emails Registered emails.
	 * @return array<string, WC_Email>
	 */
	public static function register_emails( $emails ) {
		self::includes();

		$emails['LS_Email_Wholesale_New_Application']       = new LS_Email_Wholesale_New_Application();
		$emails['LS_Email_Wholesale_Application_Approved']  = new LS_Email_Wholesale_Application_Approved();
		$emails['LS_Email_Wholesale_Application_Rejected']   = new LS_Email_Wholesale_Application_Rejected();

		return $emails;
	}

	private static function includes() {
		require_once plugin_dir_path( __FILE__ ) . 'emails/abstract-class-ls-wholesale-email.php';
		require_once plugin_dir_path( __FILE__ ) . 'emails/class-ls-email-wholesale-new-application.php';
		require_once plugin_dir_path( __FILE__ ) . 'emails/class-ls-email-wholesale-application-approved.php';
		require_once plugin_dir_path( __FILE__ ) . 'emails/class-ls-email-wholesale-application-rejected.php';
	}

	/**
	 * @param int $application_id Application ID.
	 */
	public static function send_new_application( $application_id ) {
		self::trigger_email( 'LS_Email_Wholesale_New_Application', (int) $application_id );
	}

	/**
	 * @param int $application_id Application ID.
	 */
	public static function send_application_approved( $application_id ) {
		self::trigger_email( 'LS_Email_Wholesale_Application_Approved', (int) $application_id );
	}

	/**
	 * @param int $application_id Application ID.
	 */
	public static function send_application_rejected( $application_id ) {
		self::trigger_email( 'LS_Email_Wholesale_Application_Rejected', (int) $application_id );
	}

	/**
	 * @param string $class_name Email class name.
	 * @param int    $application_id Application ID.
	 */
	private static function trigger_email( $class_name, $application_id ) {
		if ( ! function_exists( 'WC' ) || ! $application_id ) {
			return;
		}

		$mailer = WC()->mailer();
		$emails = $mailer ? $mailer->get_emails() : array();
		if ( empty( $emails[ $class_name ] ) ) {
			return;
		}

		$email = $emails[ $class_name ];
		if ( $email instanceof WC_Email && method_exists( $email, 'trigger' ) ) {
			$email->trigger( $application_id );
		}
	}
}

add_action( 'woocommerce_init', array( 'LS_Wholesale_Emails', 'init' ) );
