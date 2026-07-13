<?php
/**
 * Base WooCommerce email for wholesale notifications.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

abstract class LS_Wholesale_Email_Base extends WC_Email {

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
		return plugin_dir_path( dirname( dirname( dirname( __FILE__ ) ) ) ) . 'templates/';
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
		$page_id = LS_Wholesale::get_catalog_page_id();
		return $page_id ? (string) get_permalink( $page_id ) : home_url( '/' );
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
		$this->application = LS_Wholesale::get_application( (int) $application_id );
	}

	/**
	 * @return bool
	 */
	protected function has_valid_application() {
		return is_array( $this->application ) && ! empty( $this->application['id'] );
	}
}
