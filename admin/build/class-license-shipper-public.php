<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://licenseshipper.com
 * @since      1.0.0
 *
 * @package    License_Shipper
 * @subpackage License_Shipper/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    License_Shipper
 * @subpackage License_Shipper/public
 * @author     License Shipper <hello@licenseshipper.com>
 */
class License_Shipper_Public {

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
		 * defined in License_Shipper_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The License_Shipper_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/license-shipper-public.css', array(), $this->version, 'all' );

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
		 * defined in License_Shipper_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The License_Shipper_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script('ls_sweetalert2', plugin_dir_url(__FILE__) . 'js/sweetalert2.js', ['jquery'], $this->version, true);

		wp_enqueue_script( 'ls-my-keys', plugin_dir_url( __FILE__ ) . 'js/ls-my-keys.js', array( 'jquery','ls_sweetalert2' ), $this->version, false );

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/license-shipper-public.js', array( 'jquery','ls_sweetalert2' ), $this->version, false );

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
                'loading'   => __('Fetching key…', 'license-shipper'),
                'viewing'   => __('Loading key…', 'license-shipper'),
                'copied'    => __('License key copied to clipboard.', 'license-shipper'),
                'noSaved'   => __('No saved key found. Fetch a new key?', 'license-shipper'),
                'error'     => __('Unable to fetch key. Please try again.', 'license-shipper'),
            ], $popup_i18n),
        ]);

	}


  /** Echo CSS variables into <head> so front-end CSS can use them */
  public static function enqueue_custom_styles() {
    // Helper to fetch a hex color option with a default + sanitize
    $get = function($key, $default) {
      $val = get_option($key, '');
      $val = $val !== '' ? $val : $default;
      $val = sanitize_hex_color($val);
      return $val ?: $default;
    };

    // Read options (with sensible defaults)
    $brand        = $get('ls_brand',        '#4f46e5');
    $brand2       = $get('ls_brand_2',      '#6366f1');
    $success      = $get('ls_success',      '#059669');
    $success2     = $get('ls_success_2',    '#10b981');
    $blue600      = $get('ls_blue_600',     '#2563eb');
    $blue500      = $get('ls_blue_500',     '#3b82f6');
    $amber500     = $get('ls_amber_500',    '#f59e0b');
    $amber400     = $get('ls_amber_400',    '#fbbf24');
    $ring_base    = $get('ls_ring',         '#6366f1');
    $code_bg      = $get('ls_code_bg',      '#1e1e2e');
    $code_fg      = $get('ls_code_fg',      '#cdd6f4');
    $code_border  = $get('ls_code_border',  '#313244');
    $code_accent  = $get('ls_code_accent',  '#89b4fa');

    $ring_rgba = self::hex_to_rgba($ring_base, 0.55);

    // Output :root custom properties
    echo '<style id="ls-design-vars">:root{'
        .'--ls-brand:'        . $brand        . ';'
        .'--ls-brand-1:'      . $brand2       . ';'
        .'--ls-brand-2:'      . $brand2       . ';'
        .'--ls-emerald-600:'  . $success      . ';'
        .'--ls-emerald-500:'  . $success2     . ';'
        .'--ls-blue-600:'     . $blue600      . ';'
        .'--ls-blue-500:'     . $blue500      . ';'
        .'--ls-amber-500:'    . $amber500     . ';'
        .'--ls-amber-400:'    . $amber400     . ';'
        .'--ls-ring:'         . $ring_rgba    . ';'
        .'--ls-code-bg:'      . $code_bg      . ';'
        .'--ls-code-fg:'      . $code_fg      . ';'
        .'--ls-code-border:'  . $code_border  . ';'
        .'--ls-code-accent:'  . $code_accent  . ';'
        .'}</style>' . "\n";
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
