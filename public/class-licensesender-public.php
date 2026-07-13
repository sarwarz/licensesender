<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://licensesender.com
 * @since      1.0.0
 *
 * @package    Licensesender
 * @subpackage Licensesender/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Licensesender
 * @subpackage Licensesender/public
 * @author     licensesender <hello@licensesender.com>
 */
class Licensesender_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Licensesender_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Licensesender_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/licensesender-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Licensesender_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Licensesender_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script('ls_sweetalert2', plugin_dir_url(__FILE__) . 'js/sweetalert2.js', ['jquery'], $this->version, true);

		wp_enqueue_script( 'ls-my-keys', plugin_dir_url( __FILE__ ) . 'js/ls-my-keys.js', array( 'jquery','ls_sweetalert2' ), $this->version, false );

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/licensesender-public.js', array( 'jquery','ls_sweetalert2' ), $this->version, false );

		$popup_i18n = class_exists( 'LS_Admin_Service' ) ? LS_Admin_Service::get_popup_i18n() : array();

		wp_localize_script($this->plugin_name, 'ls_ajax_object', array_merge([
	        'ajax_url'         => admin_url('admin-ajax.php'),
	        'nonce'            => wp_create_nonce('ls_fetch_license_api'),
	    ], $popup_i18n));

	    wp_localize_script('ls-my-keys', 'LSMyKeys', [
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('ls_get_key'),
            'viewNonce' => wp_create_nonce('ls_view_key'),
            'i18n'      => array_merge([
                'loading'   => __('Fetching key…', 'licensesender'),
                'viewing'   => __('Loading key…', 'licensesender'),
                'copied'    => __('License key copied to clipboard.', 'licensesender'),
                'noSaved'   => __('No saved key found. Fetch a new key?', 'licensesender'),
                'error'     => __('Unable to fetch key. Please try again.', 'licensesender'),
            ], $popup_i18n),
        ]);

	}

	public function register_wholesale_assets() {
		wp_register_style(
			'ls-wholesale',
			plugin_dir_url( __FILE__ ) . 'css/ls-wholesale.css',
			array(),
			$this->version
		);

		wp_register_script(
			'ls-wholesale',
			plugin_dir_url( __FILE__ ) . 'js/ls-wholesale.js',
			array( 'jquery', 'wc-cart-fragments' ),
			$this->version,
			true
		);

		wp_localize_script(
			'ls-wholesale',
			'lsWholesale',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'ls_wholesale_cart' ),
				'catalogPerPage'   => (int) apply_filters( 'ls_wholesale_catalog_per_page', LS_Wholesale::get_catalog_per_page() ),
				'minOrderQuantity' => LS_Wholesale::get_min_order_quantity(),
				'cartWholesaleQty' => LS_Wholesale_Catalog::get_cart_wholesale_quantity(),
				'i18n'             => array(
					'addToCart'   => __( 'Add', 'licensesender' ),
					'adding'      => __( 'Adding…', 'licensesender' ),
					'showing'     => __( 'Showing %1$d–%2$d of %3$d', 'licensesender' ),
					'pageOf'      => __( 'Page %1$d of %2$d', 'licensesender' ),
					'minOrderAdd' => __( 'Wholesale orders require a minimum of %1$d units. Add at least %2$d more units to continue.', 'licensesender' ),
				),
			)
		);
	}


	public function register_support_assets() {
		wp_register_style(
			'ls-support',
			plugin_dir_url( __FILE__ ) . 'css/ls-support.css',
			array(),
			$this->version
		);

		wp_register_script(
			'ls-support',
			plugin_dir_url( __FILE__ ) . 'js/ls-support.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			'ls-support',
			'LSSupport',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'ls_support' ),
				'perPage'        => 20,
				'managePageUrl'  => LS_Support::get_manage_page_url(),
				'openPageUrl'    => LS_Support::get_open_page_url(),
				'ticketUrlTemplate' => add_query_arg( 'ls_ticket', '__TICKET__', LS_Support::get_manage_page_url() ?: home_url( '/' ) ),
				'i18n'     => array(
					'loadingKeys'         => __( 'Loading license keys…', 'licensesender' ),
					'selectOrderFirst'    => __( 'Select order first', 'licensesender' ),
					'selectKey'           => __( 'Select a license key', 'licensesender' ),
					'noKeys'              => __( 'No license keys for this order', 'licensesender' ),
					'submitting'          => __( 'Submitting ticket…', 'licensesender' ),
					'created'             => __( 'Ticket created successfully.', 'licensesender' ),
					'error'               => __( 'Something went wrong. Please try again.', 'licensesender' ),
					'loadingTickets'      => __( 'Loading tickets…', 'licensesender' ),
					'loadingConversation' => __( 'Loading conversation…', 'licensesender' ),
					'noTickets'           => __( 'You have no support tickets yet.', 'licensesender' ),
					'noTicketsTitle'      => __( 'No tickets yet', 'licensesender' ),
					'noTicketsHint'       => __( 'When you need help with an order or license, open a ticket and we will follow up here.', 'licensesender' ),
					'openTicketCta'       => __( 'Open a ticket', 'licensesender' ),
					'attachmentFallback'  => __( 'Attachment', 'licensesender' ),
					'noMessages'          => __( 'No messages yet.', 'licensesender' ),
					'noToken'             => __( 'This ticket cannot be opened here. Use the link from your ticket confirmation email.', 'licensesender' ),
					'viewOnly'            => __( 'You can view this ticket here. Replying requires the access link from your ticket confirmation email.', 'licensesender' ),
					'ticketClosed'        => __( 'This ticket is closed and cannot receive new replies.', 'licensesender' ),
					'ticketTitle'         => __( '[Ticket %1] %2', 'licensesender' ),
					'ticketStatus'        => __( 'Ticket Status', 'licensesender' ),
					'ticketInfo'          => __( 'Ticket Info', 'licensesender' ),
					'customerLabel'       => __( 'Customer', 'licensesender' ),
					'statusLabel'         => __( 'Status', 'licensesender' ),
					'categoryLabel'       => __( 'Category', 'licensesender' ),
					'priorityLabel'       => __( 'Priority', 'licensesender' ),
					'nameLabel'           => __( 'Name', 'licensesender' ),
					'orderLabel'          => __( 'Order Number', 'licensesender' ),
					'updatedLabel'        => __( 'Date Updated', 'licensesender' ),
					'activityLabel'       => __( 'Activity', 'licensesender' ),
					'replyPlaceholder'    => __( 'Write your reply…', 'licensesender' ),
					'you'                 => __( 'You', 'licensesender' ),
					'supportName'         => __( 'Support', 'licensesender' ),
					'reported'            => __( 'reported', 'licensesender' ),
					'replied'             => __( 'replied', 'licensesender' ),
					'viewMore'            => __( 'View more!', 'licensesender' ),
					'supportTeam'         => __( 'Support replied', 'licensesender' ),
					'backToTickets'       => __( 'Back to ticket list', 'licensesender' ),
					'loadingTicket'       => __( 'Loading ticket…', 'licensesender' ),
					'untitled'            => __( 'Untitled ticket', 'licensesender' ),
					'replyLabel'          => __( 'Your reply', 'licensesender' ),
					'replyRequired'       => __( 'Reply message is required.', 'licensesender' ),
					'attachFile'          => __( 'Attach file', 'licensesender' ),
					'noFilesSelected'     => __( 'No files selected', 'licensesender' ),
					'attachments'         => __( 'Attachments (optional)', 'licensesender' ),
					'sendReply'           => __( 'Send reply', 'licensesender' ),
					'sendingReply'        => __( 'Sending reply…', 'licensesender' ),
					'replySent'           => __( 'Reply sent.', 'licensesender' ),
					'selectTicket'        => __( 'Select a ticket to view the conversation.', 'licensesender' ),
					'closeDetail'         => __( 'Close conversation', 'licensesender' ),
					'firstPage'           => __( 'First Page', 'licensesender' ),
					'lastPage'            => __( 'Last Page', 'licensesender' ),
					'prevPage'            => __( 'Previous page', 'licensesender' ),
					'nextPage'            => __( 'Next page', 'licensesender' ),
					'ticketRange'         => __( '%1–%2 of %3 Tickets', 'licensesender' ),
					'fromNowPast'         => __( '%d %s ago', 'licensesender' ),
					'fromNowFuture'       => __( 'in %d %s', 'licensesender' ),
					'seconds'             => __( 'seconds', 'licensesender' ),
					'minute'              => __( 'minute', 'licensesender' ),
					'minutes'             => __( 'minutes', 'licensesender' ),
					'hour'                => __( 'hour', 'licensesender' ),
					'hours'               => __( 'hours', 'licensesender' ),
					'day'                 => __( 'day', 'licensesender' ),
					'days'                => __( 'days', 'licensesender' ),
				),
			)
		);
	}


  /** Echo CSS variables into <head> so front-end CSS can use them */
  public static function enqueue_custom_styles() {
    if ( class_exists( 'LS_Design_System' ) ) {
      echo '<style id="ls-design-vars">' . LS_Design_System::css_variables_block() . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      return;
    }

    // Fallback if design system is unavailable.
    $get = function( $key, $default ) {
      $val = get_option( $key, '' );
      $val = $val !== '' ? $val : $default;
      $val = sanitize_hex_color( $val );
      return $val ?: $default;
    };

    $brand    = $get( 'ls_brand', '#4f46e5' );
    $brand2   = $get( 'ls_brand_2', '#6366f1' );
    $success  = $get( 'ls_success', '#059669' );
    $success2 = $get( 'ls_success_2', '#10b981' );
    $blue600  = $get( 'ls_blue_600', '#2563eb' );
    $blue500  = $get( 'ls_blue_500', '#3b82f6' );
    $amber500 = $get( 'ls_amber_500', '#f59e0b' );
    $amber400 = $get( 'ls_amber_400', '#fbbf24' );
    $ring_base = $get( 'ls_ring', '#6366f1' );
    $code_bg  = $get( 'ls_code_bg', '#1e1e2e' );
    $code_fg  = $get( 'ls_code_fg', '#cdd6f4' );
    $code_border = $get( 'ls_code_border', '#313244' );
    $code_accent = $get( 'ls_code_accent', '#89b4fa' );
    $ring_rgba = self::hex_to_rgba( $ring_base, 0.55 );

    echo '<style id="ls-design-vars">:root{'
        . '--ls-brand:' . $brand . ';'
        . '--ls-brand-1:' . $brand2 . ';'
        . '--ls-brand-2:' . $brand2 . ';'
        . '--ls-emerald-600:' . $success . ';'
        . '--ls-emerald-500:' . $success2 . ';'
        . '--ls-blue-600:' . $blue600 . ';'
        . '--ls-blue-500:' . $blue500 . ';'
        . '--ls-amber-500:' . $amber500 . ';'
        . '--ls-amber-400:' . $amber400 . ';'
        . '--ls-ring:' . $ring_rgba . ';'
        . '--ls-code-bg:' . $code_bg . ';'
        . '--ls-code-fg:' . $code_fg . ';'
        . '--ls-code-border:' . $code_border . ';'
        . '--ls-code-accent:' . $code_accent . ';'
        . '}</style>' . "\n";
  }

  /** Convert #hex to rgba() with alpha (for focus ring) */
  private static function hex_to_rgba($hex, $alpha = 1.0) {
    $hex = ltrim((string)$hex, '#');
    if (strlen($hex) === 3) {
      $r = hexdec(str_repeat($hex[0],2));
      $g = hexdec(str_repeat($hex[1],2));
      $b = hexdec(str_repeat($hex[2],2));
    } else {
      $r = hexdec(substr($hex,0,2));
      $g = hexdec(substr($hex,2,2));
      $b = hexdec(substr($hex,4,2));
    }
    $alpha = is_numeric($alpha) ? max(0, min(1, (float)$alpha)) : 1;
    return "rgba({$r},{$g},{$b},{$alpha})";
  }


}
