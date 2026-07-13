<?php
/**
 * Admin email for new wholesale applications.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Email_Wholesale_New_Application extends LS_Wholesale_Email_Base {

	public function __construct() {
		$this->id             = 'ls_wholesale_new_application';
		$this->title          = __( 'New wholesale application (admin)', 'licensesender' );
		$this->description    = __( 'Sent to the store admin when a customer submits a wholesale application.', 'licensesender' );
		$this->template_html  = 'emails/ls-wholesale-new-application.php';
		$this->template_plain = 'emails/plain/ls-wholesale-new-application.php';
		$this->placeholders   = array(
			'{site_title}'    => '',
			'{company_name}'  => '',
			'{business_email}' => '',
		);

		$this->customer_email = false;

		parent::__construct();
	}

	public function get_default_subject() {
		return __( '[{site_title}] New wholesale application', 'licensesender' );
	}

	public function get_default_heading() {
		return __( 'New wholesale application', 'licensesender' );
	}

	public function get_default_additional_content() {
		return __( 'Review the application in your wholesale dashboard and approve or reject the request.', 'licensesender' );
	}

	public function get_recipient() {
		return LS_Wholesale::get_notify_email();
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

		$this->placeholders['{company_name}']   = (string) ( $this->application['company_name'] ?? '' );
		$this->placeholders['{business_email}'] = (string) ( $this->application['business_email'] ?? '' );

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}
}
