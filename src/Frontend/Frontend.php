<?php

namespace LicenseSender\Frontend;

use LicenseSender\Wholesale\Catalog;
use LicenseSender\Core\DesignSystem;
use LicenseSender\Wholesale\Wholesale;

defined( 'ABSPATH' ) || exit;

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
class Frontend {

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
		 * defined in Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, LICENSESENDER_URL . 'assets/public/css/licensesender-public.css', array(), $this->version, 'all' );

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
		 * defined in Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		// The old jQuery + SweetAlert pop-up script is gone: keys are fetched and shown by the
		// React keys section below (My Keys, order and thank-you pages).

		// My Account → My Keys (React, build/public/my-keys.js). Enqueued by the endpoint
		// itself with its settings, so it only loads on that page.
		$keys_app = LICENSESENDER_PATH . 'build/public/my-keys.js';
		wp_register_script(
			'ls-my-keys-app',
			LICENSESENDER_URL . 'build/public/my-keys.js',
			array(),
			file_exists( $keys_app ) ? $this->version . '.' . filemtime( $keys_app ) : $this->version,
			true
		);

		$wholesale_app = LICENSESENDER_PATH . 'build/public/wholesale.js';
		wp_register_script(
			'ls-wholesale-app',
			LICENSESENDER_URL . 'build/public/wholesale.js',
			array(),
			file_exists( $wholesale_app ) ? $this->version . '.' . filemtime( $wholesale_app ) : $this->version,
			true
		);
	}

	public function register_wholesale_assets() {
		wp_register_style(
			'ls-wholesale',
			LICENSESENDER_URL . 'assets/public/css/ls-wholesale.css',
			array(),
			$this->version
		);

		wp_register_script(
			'ls-wholesale',
			LICENSESENDER_URL . 'assets/public/js/ls-wholesale.js',
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
				'catalogPerPage'   => (int) apply_filters( 'ls_wholesale_catalog_per_page', Wholesale::get_catalog_per_page() ),
				'minOrderQuantity' => Wholesale::get_min_order_quantity(),
				'cartWholesaleQty' => Catalog::get_cart_wholesale_quantity(),
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
		// Styles for the PHP-rendered support screens (login gate, disabled notice).
		wp_register_style(
			'ls-support',
			LICENSESENDER_URL . 'assets/public/css/ls-support.css',
			array(),
			$this->version
		);

		// React support app (built by `npm run build` into build/public/). Settings are added
		// inline by the shortcode, only on pages that render it.
		$app_file = LICENSESENDER_PATH . 'build/public/support.js';
		wp_register_script(
			'ls-support-app',
			LICENSESENDER_URL . 'build/public/support.js',
			array(),
			file_exists( $app_file ) ? $this->version . '.' . filemtime( $app_file ) : $this->version,
			true
		);
	}


  /** Echo CSS variables into <head> so front-end CSS can use them */
  public static function enqueue_custom_styles() {
    if ( class_exists( DesignSystem::class ) ) {
      echo '<style id="ls-design-vars">' . DesignSystem::css_variables_block() . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
