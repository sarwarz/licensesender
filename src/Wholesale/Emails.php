<?php

namespace LicenseSender\Wholesale;

use LicenseSender\Wholesale\Emails\ApplicationApprovedEmail;
use LicenseSender\Wholesale\Emails\ApplicationRejectedEmail;
use LicenseSender\Wholesale\Emails\NewApplicationEmail;

defined( 'ABSPATH' ) || exit;

class Emails {

	public static function init() {
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register_emails' ) );
	}

	/**
	 * @param array<string, WC_Email> $emails Registered emails.
	 * @return array<string, WC_Email>
	 */
	public static function register_emails( $emails ) {
		$emails['LS_Email_Wholesale_New_Application']      = new NewApplicationEmail();
		$emails['LS_Email_Wholesale_Application_Approved'] = new ApplicationApprovedEmail();
		$emails['LS_Email_Wholesale_Application_Rejected'] = new ApplicationRejectedEmail();

		return $emails;
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
		if ( $email instanceof \WC_Email && method_exists( $email, 'trigger' ) ) {
			$email->trigger( $application_id );
		}
	}
}
