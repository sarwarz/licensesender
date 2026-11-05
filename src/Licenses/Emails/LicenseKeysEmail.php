<?php

namespace LicenseSender\Licenses\Emails;

defined( 'ABSPATH' ) || exit;

// WooCommerce loads WC_Email only when its mailer starts; load it so this class can autoload any time.
if ( ! class_exists( 'WC_Email', false ) && defined( 'WC_ABSPATH' ) ) {
	include_once WC_ABSPATH . 'includes/emails/class-wc-email.php';
}

/**
 * "License keys" customer email (WooCommerce → Settings → Emails).
 *
 * One email per order once every key is delivered, in the store's WooCommerce email
 * style. Subject, heading, extra text and HTML/plain are edited on that screen; the
 * on/off switch is shared with Settings → Email in the plugin.
 */
class LicenseKeysEmail extends \WC_Email {

	const ID = 'ls_license_keys';

	/**
	 * Products with their keys: [ [ name, keys[], download, guide ] ].
	 *
	 * @var array<int, array{name: string, keys: array<int, string>, download: string, guide: string}>
	 */
	public $entries = array();

	/** @var bool Send even when automatic emails are off (tests and manual resends). */
	public $send_anyway = false;

	public function __construct() {
		$this->id             = self::ID;
		$this->customer_email = true;
		$this->title          = __( 'License keys', 'licensesender' );
		$this->description    = __( 'Sent to the customer once every license key in their order has been delivered. Turn it on or off and choose who sends it under LicenseSender → Settings → Email.', 'licensesender' );
		$this->template_html  = 'emails/ls-license-keys.php';
		$this->template_plain = 'emails/plain/ls-license-keys.php';
		$this->template_base  = LICENSESENDER_PATH . 'templates/';
		$this->placeholders   = array(
			'{order_date}'   => '',
			'{order_number}' => '',
			'{key_count}'    => '',
		);

		parent::__construct();

		$this->maybe_import_old_texts();
		// One switch for both screens: the plugin setting decides.
		$this->enabled = get_option( 'lship_send_email_after_redeem', 'no' ) === 'yes' ? 'yes' : 'no';
	}

	public function get_default_subject() {
		return __( 'Your license keys for order #{order_number}', 'licensesender' );
	}

	public function get_default_heading() {
		return __( 'Your license keys are ready', 'licensesender' );
	}

	public function get_default_additional_content() {
		return __( 'Keep this email for your records. Need help activating? Just reply to this email.', 'licensesender' );
	}

	/** One key: singular subject and heading (unless the store wrote its own). */
	public function get_subject() {
		if ( count( $this->all_keys() ) === 1 && $this->get_option( 'subject', '' ) === '' ) {
			return apply_filters( 'woocommerce_email_subject_' . $this->id, $this->format_string( __( 'Your license key for order #{order_number}', 'licensesender' ) ), $this->object, $this );
		}

		return parent::get_subject();
	}

	public function get_heading() {
		if ( count( $this->all_keys() ) === 1 && $this->get_option( 'heading', '' ) === '' ) {
			return apply_filters( 'woocommerce_email_heading_' . $this->id, $this->format_string( __( 'Your license key is ready', 'licensesender' ) ), $this->object, $this );
		}

		return parent::get_heading();
	}

	/**
	 * @param \WC_Order|null                    $order   Order (null for a test).
	 * @param string                            $to      Recipient.
	 * @param array<int, array<string, mixed>>  $entries Products with keys.
	 */
	public function trigger( $order, $to, array $entries ) {
		$this->setup_locale();

		$this->object  = $order instanceof \WC_Order ? $order : null;
		$this->entries = $entries;
		$this->recipient = $to;

		$date = $this->object && $this->object->get_date_created() ? $this->object->get_date_created() : null;
		$this->placeholders['{order_number}'] = $this->object ? $this->object->get_order_number() : '1042';
		$this->placeholders['{order_date}']   = wc_format_datetime( $date ?: new \WC_DateTime() );
		$this->placeholders['{key_count}']    = (string) count( $this->all_keys() );

		$sent = false;
		if ( $this->get_recipient() && ( $this->send_anyway || $this->is_enabled() ) ) {
			$sent = (bool) $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();

		return $sent;
	}

	/** @return array<int, string> */
	private function all_keys() {
		$keys = array();
		foreach ( $this->entries as $entry ) {
			$keys = array_merge( $keys, (array) ( $entry['keys'] ?? array() ) );
		}

		return $keys;
	}

	/** @return array<string, mixed> */
	private function template_args( $plain ) {
		return array(
			'order'              => $this->object,
			'entries'            => $this->entries,
			'email_heading'      => $this->get_heading(),
			'additional_content' => $this->get_additional_content(),
			'sent_to_admin'      => false,
			'plain_text'         => $plain,
			'email'              => $this,
		);
	}

	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->template_args( false ), '', $this->template_base );
	}

	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->template_args( true ), '', $this->template_base );
	}

	public function init_form_fields() {
		parent::init_form_fields();

		$placeholders = '<code>' . esc_html( implode( '</code>, <code>', array_keys( $this->placeholders + array( '{site_title}' => '' ) ) ) ) . '</code>';
		/* translators: %s: list of placeholders */
		$this->form_fields['subject']['description'] = sprintf( __( 'Available placeholders: %s', 'licensesender' ), $placeholders );
		$this->form_fields['heading']['description'] = $this->form_fields['subject']['description'];

		// The switch lives in LicenseSender → Settings → Email (it also picks who sends).
		unset( $this->form_fields['enabled'] );
	}

	/**
	 * Earlier versions kept the subject and intro in LicenseSender's own settings: carry
	 * them over once so a store's wording isn't lost.
	 */
	private function maybe_import_old_texts() {
		if ( get_option( 'ls_license_email_imported' ) ) {
			return;
		}
		update_option( 'ls_license_email_imported', '1', false );

		$subject = trim( (string) ( get_option( 'lship_email_subject_bulk', '' ) ?: get_option( 'lship_email_subject', '' ) ) );
		$intro   = trim( (string) get_option( 'lship_email_intro_bulk', '' ) );
		// Old activation defaults aren't the store's own wording.
		if ( in_array( strtolower( $subject ), array( 'your license key', 'your license keys' ), true ) ) {
			$subject = '';
		}
		if ( $subject === '' && $intro === '' ) {
			return;
		}

		if ( $subject !== '' && $this->get_option( 'subject', '' ) === '' ) {
			$this->settings['subject'] = sanitize_text_field( $subject );
		}
		if ( $intro !== '' && $this->get_option( 'additional_content', '' ) === '' ) {
			$this->settings['additional_content'] = sanitize_textarea_field( $intro );
		}
		update_option( $this->get_option_key(), $this->settings, 'yes' );
	}
}
