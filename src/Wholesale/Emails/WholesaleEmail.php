<?php

namespace LicenseSender\Wholesale\Emails;

use LicenseSender\Wholesale\Wholesale;

defined( 'ABSPATH' ) || exit;

// WooCommerce loads WC_Email only when its mailer starts; load it here so these classes can autoload any time.
if ( ! class_exists( 'WC_Email', false ) && defined( 'WC_ABSPATH' ) ) {
	include_once WC_ABSPATH . 'includes/emails/class-wc-email.php';
}

abstract class WholesaleEmail extends \WC_Email {

	/**
	 * @var array<string, mixed>|null
	 */
	public $application;

	public function __construct() {
		$this->template_base = $this->get_template_directory();
		parent::__construct();
	}

	/**
	 * @return string
	 */
	protected function get_template_directory() {
		return LICENSESENDER_PATH . 'templates/';
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function get_template_args() {
		return array(
			'application'        => $this->application,
			'email_heading'      => $this->get_heading(),
			'additional_content' => $this->get_additional_content(),
			'sent_to_admin'      => ! $this->customer_email,
			'plain_text'         => false,
			'email'              => $this,
			'admin_review_url'   => admin_url( 'admin.php?page=ls-wholesale-applications' ),
			'catalog_url'        => self::get_catalog_url(),
		);
	}

	/**
	 * @return string
	 */
	protected static function get_catalog_url() {
		return Wholesale::get_portal_url();
	}

	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			$this->get_template_args(),
			'',
			$this->get_template_directory()
		);
	}

	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array_merge(
				$this->get_template_args(),
				array( 'plain_text' => true )
			),
			'',
			$this->get_template_directory()
		);
	}

	/**
	 * @param int $application_id Application ID.
	 */
	protected function load_application( $application_id ) {
		$this->application = Wholesale::get_application( (int) $application_id );
	}

	/**
	 * @return bool
	 */
	protected function has_valid_application() {
		return is_array( $this->application ) && ! empty( $this->application['id'] );
	}
}
