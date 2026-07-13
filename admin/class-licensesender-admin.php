<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://licensesender.com
 * @since      1.0.0
 *
 * @package    Licensesender
 * @subpackage Licensesender/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Licensesender
 * @subpackage Licensesender/admin
 * @author     licensesender <hello@licensesender.com>
 */
class Licensesender_Admin {

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
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			'ls-admin-menu',
			plugin_dir_url( __FILE__ ) . 'css/ls-admin-menu.css',
			array(),
			$this->version,
			'all'
		);

		if ( LS_Admin_Service::uses_react_admin() && $this->is_react_admin_page() ) {
			return;
		}

		wp_enqueue_style( 'ls_select2', plugin_dir_url( __FILE__ ) . 'css/select2.min.css', array(), $this->version, 'all' );
		wp_enqueue_style( 'ls-dataTables', plugin_dir_url( __FILE__ ) . 'css/datatables/dataTables.min.css', array(), $this->version, 'all' );
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/licensesender-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		if ( LS_Admin_Service::uses_react_admin() && $this->is_react_admin_page() ) {
			return;
		}

		wp_enqueue_script('ls_select2', plugin_dir_url(__FILE__) . 'js/select2.min.js', ['jquery'], $this->version, true);
		wp_enqueue_script('ls_sweetalert', plugin_dir_url(__FILE__) . 'js/sweetalert.js', ['jquery'], $this->version, true);
		wp_enqueue_script('ls_dataTables', plugin_dir_url(__FILE__) . 'js/datatables/dataTables.min.js', ['jquery'], $this->version, true);
		
		// Enqueue WordPress color picker
	    wp_enqueue_style('wp-color-picker');
		wp_enqueue_script('selectWoo');
	    wp_enqueue_script('ls-admin-color-picker', plugin_dir_url(__FILE__) . 'js/color-picker-init.js', ['wp-color-picker'], false, true);


	    if (isset($_GET['page']) && $_GET['page'] === 'ls-licensesender-download-links') {
		    add_thickbox();
		    wp_enqueue_script('ls-admin-download', plugin_dir_url(__FILE__) . 'js/licensesender-download.js', ['jquery'], false, true);
		    wp_localize_script('ls-admin-download', 'ls_ajax_object', [
		        'ajax_url' => admin_url('admin-ajax.php'),
		        'nonce'    => wp_create_nonce('ls_admin_manage_nonce'),
		    ]);
		}

		if (isset($_GET['page']) && $_GET['page'] === 'ls-activation-guides') {

		    wp_enqueue_script('ls-admin-activation', plugin_dir_url(__FILE__) . 'js/licensesender-activation.js', ['jquery'], false, true);
		    wp_localize_script('ls-admin-activation', 'lsActivation', [
		        'ajaxUrl' => admin_url('admin-ajax.php'),
		        'nonce'   => wp_create_nonce('ls_admin_manage_nonce'),
		        'confirmDelete' => __('Are you sure?', 'licensesender'),
		        'confirmText' => __('Yes, delete it!', 'licensesender'),
		        'cancelText' => __('Cancel', 'licensesender'),
		        'deleteMsg' => __('This will permanently delete the activation guide.', 'licensesender'),
		        'successMsg' => __('Deleted successfully.', 'licensesender'),
		        'errorMsg' => __('Failed to delete record.', 'licensesender'),
		        'serverError' => __('Could not connect to the server.', 'licensesender'),
		        'emptyTable' => __('No activation guides found.', 'licensesender')
		    ]);
		}

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/licensesender-admin.js', array( 'jquery','ls_select2'), $this->version, false );
		wp_localize_script($this->plugin_name, 'ls_ajax_object', [
	        'ajax_url' => admin_url('admin-ajax.php'),
	        'nonce'    => wp_create_nonce('ls_ping_api_nonce'),
	        'test_email_nonce' => wp_create_nonce('ls_test_email_nonce'),
	        'manage_nonce' => wp_create_nonce('ls_admin_manage_nonce'),
	    ]);

	}


	public function ls_register_admin_menus() {

		LS_Admin_Licenses_Datatables::init_for_menu('ls-licensesender');

		// Top-level menu opens the Dashboard.
		add_menu_page(
			__( 'LicenseSender', 'licensesender' ),
			'LicenseSender',
			'manage_options',
			'ls-dashboard',
			array( 'LS_Admin_Dashboard', 'render_page' ),
			plugin_dir_url( __FILE__ ) . 'img/licensesender-icon.png',
			56
		);

		add_submenu_page(
			'ls-dashboard',
			__( 'Dashboard', 'licensesender' ),
			__( 'Dashboard', 'licensesender' ),
			'manage_options',
			'ls-dashboard',
			array( 'LS_Admin_Dashboard', 'render_page' )
		);

		add_submenu_page(
			'ls-dashboard',
			__( 'License Keys', 'licensesender' ),
			__( 'License Keys', 'licensesender' ),
			'manage_options',
			'ls-licensesender',
			array( 'LS_Admin_Licenses_Datatables', 'render' )
		);

		add_submenu_page(
		    null,
		    __( 'Edit License', 'licensesender' ),
		    __( 'Edit License', 'licensesender' ),
		    'manage_options',
		    'ls-licensesender-edit',
		    [ 'LS_Admin_Licenses_Datatables', 'render_edit' ]
		);

		add_submenu_page(
		    null,
		    __( 'Report Key', 'licensesender' ),
		    __( 'Report Key', 'licensesender' ),
		    'manage_options',
		    'ls-licensesender-report',
		    [ 'LS_Admin_Licenses_Datatables', 'render_report' ]
		);

		// Submenu: Download Links
		if (get_option('lship_enable_manage_downloads') === 'yes') {
			
			add_submenu_page(
				'ls-dashboard',
				__( 'Download Links', 'licensesender' ),
				__( 'Download Links', 'licensesender' ),
				'manage_options',
				'ls-licensesender-download-links',
				array( 'Ls_Admin_Download_Display', 'output' )
			);
		}

		// Submenu: Activation guide
		if (get_option('lship_enable_manage_activation_guides') === 'yes') {
			
			add_submenu_page(
				'ls-dashboard',
				__( 'Activation Guides', 'licensesender' ),
				__( 'Activation Guides', 'licensesender' ),
				'manage_options',
				'ls-activation-guides',
				array( 'Ls_Admin_Activation_Display', 'output' )
			);
		}


		// Submenu: Wholesale (above Settings)
		add_submenu_page(
			'ls-dashboard',
			__( 'Wholesale Applications', 'licensesender' ),
			__( 'Wholesale', 'licensesender' ),
			'manage_options',
			'ls-wholesale-applications',
			array( 'LS_Admin_Wholesale', 'render_page' )
		);

		// Submenu: Settings
		add_submenu_page(
			'ls-dashboard',
			__( 'Settings', 'licensesender' ),
			__( 'Settings', 'licensesender' ),
			'manage_options',
			'ls-licensesender-settings',
			array( 'Ls_Admin_Settings_Display', 'output' )
		);
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

	public function ls_boot_datatables(){
		
		if (class_exists('LS_Admin_Licenses_Datatables')) {
	        LS_Admin_Licenses_Datatables::boot();
	    }
	}

	private function is_react_admin_page() {
		return LS_Admin_Service::is_plugin_admin_page();
	}

}
