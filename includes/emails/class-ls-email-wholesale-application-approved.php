<?php
/**
 * Customer email when a wholesale application is approved.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Email_Wholesale_Application_Approved extends LS_Wholesale_Email_Base {

	public function __construct() {
		$this->id             = 'ls_wholesale_application_approved';
		$this->title          = __( 'Wholesale application approved', 'licensesender' );
		$this->description    = __( 'Sent to customers when their wholesale application is approved.', 'licensesender' );
		$this->template_html  = 'emails/ls-wholesale-application-approved.php';
		$this->template_plain = 'emails/plain/ls-wholesale-application-approved.php';
		$this->placeholders   = array(
			'{site_title}'   => '',
			'{company_name}' => '',
		);

		$this->customer_email = true;

		parent::__construct();
	}

	public function get_default_subject() {
		return __( 'Your wholesale application has been approved', 'licensesender' );
	}

	public function get_default_heading() {
		return __( 'Wholesale access approved', 'licensesender' );
	}

	public function get_default_additional_content() {
		return __( 'You can now sign in and browse the wholesale catalog to place B2B orders.', 'licensesender' );
	}

	/**
	 * @param int $application_id Application ID.
	 */
	public function trigger( $application_id ) {
		$this->setup_locale();

		$this->load_application( $application_id );
		if ( ! $this->has_valid_application() ) {
			$this->restore_locale();
			return;
		}

		$recipient = sanitize_email( (string) ( $this->application['business_email'] ?? '' ) );
		if ( ! is_email( $recipient ) ) {
			$user = get_userdata( (int) ( $this->application['user_id'] ?? 0 ) );
			$recipient = $user ? sanitize_email( (string) $user->user_email ) : '';
		}

		if ( ! is_email( $recipient ) ) {
			$this->restore_locale();
			return;
		}

		$this->recipient = $recipient;
		$this->placeholders['{company_name}'] = (string) ( $this->application['company_name'] ?? '' );

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}
}
