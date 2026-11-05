<?php

namespace LicenseSender\Admin;


defined( 'ABSPATH' ) || exit;

/**
 * The admin-specific functionality of the plugin.
 *
 * Admin menu, the menu icon styles, the product mapping script and the post-activation redirect.
 *
 * @package    Licensesender
 * @subpackage Licensesender/admin
 * @author     licensesender <hello@licensesender.com>
 */
class Admin {

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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Menu icon styles on every admin screen.
	 */
	public function enqueue_styles() {
		wp_enqueue_style( 'ls-admin-menu', LICENSESENDER_URL . 'assets/admin/css/ls-admin-menu.css', array(), $this->version );
	}

	/**
	 * Product mapping fields (product tab and variations). Plugin pages load their React bundle in Assets.
	 */
	public function enqueue_scripts() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}

		wp_enqueue_script( 'selectWoo' );
		wp_enqueue_script( $this->plugin_name, LICENSESENDER_URL . 'assets/admin/js/licensesender-admin.js', array( 'jquery', 'selectWoo' ), $this->version, true );
	}

	public function ls_register_admin_menus() {
		// Top-level menu opens the Dashboard.
		add_menu_page(
			__( 'LicenseSender', 'licensesender' ),
			'LicenseSender',
			'manage_options',
			'ls-dashboard',
			self::shell( 'dashboard' ),
			self::menu_icon(),
			56
		);

		$pages = array(
			// slug => [ menu title, page title, React entry, parent (null = hidden) ]
			'ls-dashboard'              => array( __( 'Dashboard', 'licensesender' ), __( 'Dashboard', 'licensesender' ), 'dashboard', 'ls-dashboard' ),
			'ls-licensesender'          => array( __( 'License Keys', 'licensesender' ), __( 'License Keys', 'licensesender' ), 'licenses', 'ls-dashboard' ),
			'ls-licensesender-edit'     => array( __( 'Edit License', 'licensesender' ), __( 'Edit License', 'licensesender' ), 'licenses', null ),
			'ls-licensesender-report'   => array( __( 'Report Key', 'licensesender' ), __( 'Report Key', 'licensesender' ), 'licenses', null ),
		);
		if ( get_option( 'lship_enable_manage_downloads' ) === 'yes' ) {
			$pages['ls-licensesender-download-links'] = array( __( 'Download Links', 'licensesender' ), __( 'Download Links', 'licensesender' ), 'download-links', 'ls-dashboard' );
		}
		if ( get_option( 'lship_enable_manage_activation_guides' ) === 'yes' ) {
			$pages['ls-activation-guides'] = array( __( 'Activation Guides', 'licensesender' ), __( 'Activation Guides', 'licensesender' ), 'activation-guides', 'ls-dashboard' );
		}
		$pages['ls-wholesale-applications']  = array( __( 'Wholesale', 'licensesender' ), __( 'Wholesale Applications', 'licensesender' ), 'wholesale', 'ls-dashboard' );
		$pages['ls-licensesender-settings']  = array( __( 'Settings', 'licensesender' ), __( 'Settings', 'licensesender' ), 'settings', 'ls-dashboard' );

		foreach ( $pages as $slug => list( $menu_title, $page_title, $entry, $parent ) ) {
			// A null parent registers a page without a menu item.
			add_submenu_page( $parent, $page_title, $menu_title, 'manage_options', $slug, self::shell( $entry ) );
		}
	}

	/**
	 * One-colour SVG as a base64 data URI: WordPress repaints it to match the admin
	 * colour scheme (grey, white when hovered or active), like the built-in menu icons.
	 */
	private static function menu_icon() {
		$svg = file_get_contents( LICENSESENDER_PATH . 'assets/admin/img/menu-icon.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return $svg ? 'data:image/svg+xml;base64,' . base64_encode( $svg ) : 'dashicons-admin-network'; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Page callback that prints the React app container for $entry.
	 */
	private static function shell( $entry ) {
		// One callback per entry: the top-level page and its first submenu share a hook, and WordPress
		// only skips a duplicate when it is the same callable. A new closure each time printed the app twice.
		static $callbacks = array();
		if ( ! isset( $callbacks[ $entry ] ) ) {
			$callbacks[ $entry ] = static function () use ( $entry ) {
				Assets::render_shell( $entry );
			};
		}
		return $callbacks[ $entry ];
	}

	public function lship_do_activation_redirect() {
		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$should_redirect = (string) get_option( 'lship_do_activation_redirect', '' ) === '1'
			|| (string) get_transient( 'ls_activation_redirect' ) === '1';

		if ( ! $should_redirect ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Prevent redirect on bulk / network activation.
		if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			delete_option( 'lship_do_activation_redirect' );
			delete_transient( 'ls_activation_redirect' );
			return;
		}

		delete_option( 'lship_do_activation_redirect' );
		delete_transient( 'ls_activation_redirect' );

		wp_safe_redirect( admin_url( 'admin.php?page=ls-setup' ) );
		exit;
	}



}
