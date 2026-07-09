<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://licenseshipper.com
 * @since      1.0.0
 *
 * @package    License_Shipper
 * @subpackage License_Shipper/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    License_Shipper
 * @subpackage License_Shipper/admin
 * @author     License Shipper <hello@licenseshipper.com>
 */
class License_Shipper_Admin {

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

		if ( LS_Admin_Service::uses_react_admin() && $this->is_react_admin_page() ) {
			return;
		}

		wp_enqueue_style( 'ls_select2', plugin_dir_url( __FILE__ ) . 'css/select2.min.css', array(), $this->version, 'all' );
		wp_enqueue_style( 'ls-dataTables', plugin_dir_url( __FILE__ ) . 'css/datatables/dataTables.min.css', array(), $this->version, 'all' );
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/license-shipper-admin.css', array(), $this->version, 'all' );

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


	    if (isset($_GET['page']) && $_GET['page'] === 'ls-license-shipper-download-links') {
		    add_thickbox();
		    wp_enqueue_script('ls-admin-download', plugin_dir_url(__FILE__) . 'js/license-shipper-download.js', ['jquery'], false, true);
		}

		if (isset($_GET['page']) && $_GET['page'] === 'ls-activation-guides') {

		    wp_enqueue_script('ls-admin-activation', plugin_dir_url(__FILE__) . 'js/license-shipper-activation.js', ['jquery'], false, true);
		    wp_localize_script('ls-admin-activation', 'lsActivation', [
		        'ajaxUrl' => admin_url('admin-ajax.php'),
		        'confirmDelete' => __('Are you sure?', 'license-shipper'),
		        'confirmText' => __('Yes, delete it!', 'license-shipper'),
		        'cancelText' => __('Cancel', 'license-shipper'),
		        'deleteMsg' => __('This will permanently delete the activation guide.', 'license-shipper'),
		        'successMsg' => __('Deleted successfully.', 'license-shipper'),
		        'errorMsg' => __('Failed to delete record.', 'license-shipper'),
		        'serverError' => __('Could not connect to the server.', 'license-shipper'),
		        'emptyTable' => __('No activation guides found.', 'license-shipper')
		    ]);
		}

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/license-shipper-admin.js', array( 'jquery','ls_select2'), $this->version, false );
		wp_localize_script($this->plugin_name, 'ls_ajax_object', [
	        'ajax_url' => admin_url('admin-ajax.php'),
	        'nonce'    => wp_create_nonce('ls_ping_api_nonce'),
	        'test_email_nonce' => wp_create_nonce('ls_test_email_nonce'),
	    ]);

	}


	public function ls_register_admin_menus() {

		LS_Admin_Licenses_Datatables::init_for_menu('ls-license-shipper');

		// Main Menu: License Sender (linked to Voucher Codes)
		add_menu_page(
			__( 'License Sender', 'license-shipper' ),
			'License Shipper',
			'manage_options',
			'ls-license-shipper',
			array( 'LS_Admin_Licenses_Datatables', 'render' ),
			plugin_dir_url( __FILE__ ) . 'img/licenseshipper-icon.png',
			56
		);



		// Submenu: Voucher Codes (duplicate of main menu for consistency)
		add_submenu_page(
			'ls-license-shipper',
			__( 'License Keys', 'license-shipper' ),
			__( 'License Keys', 'license-shipper' ),
			'manage_options',
			'ls-license-shipper',
			array( 'LS_Admin_Licenses_Datatables', 'render' )
		);

		add_submenu_page(
		    null,
		    __( 'Edit License', 'license-shipper' ),
		    __( 'Edit License', 'license-shipper' ),
		    'manage_options',
		    'ls-license-shipper-edit',
		    [ 'LS_Admin_Licenses_Datatables', 'render_edit' ]
		);

		add_submenu_page(
		    null,
		    __( 'Change License', 'license-shipper' ),
		    __( 'Change License', 'license-shipper' ),
		    'manage_options',
		    'ls-license-shipper-change',
		    [ 'LS_Admin_Licenses_Datatables', 'render_change' ]
		);

		// Submenu: Download Links
		if (get_option('lship_enable_manage_downloads') === 'yes') {
			
			add_submenu_page(
				'ls-license-shipper',
				__( 'Download Links', 'license-shipper' ),
				__( 'Download Links', 'license-shipper' ),
				'manage_options',
				'ls-license-shipper-download-links',
				array( 'Ls_Admin_Download_Display', 'output' )
			);
		}

		// Submenu: Activation guide
		if (get_option('lship_enable_manage_activation_guides') === 'yes') {
			
			add_submenu_page(
				'ls-license-shipper',
				__( 'Activation Guides', 'license-shipper' ),
				__( 'Activation Guides', 'license-shipper' ),
				'manage_options',
				'ls-activation-guides',
				array( 'Ls_Admin_Activation_Display', 'output' )
			);
		}


		// Submenu: Settings
		add_submenu_page(
			'ls-license-shipper',
			__( 'Settings', 'license-shipper' ),
			__( 'Settings', 'license-shipper' ),
			'manage_options',
			'ls-license-shipper-settings',
			array( 'Ls_Admin_Settings_Display', 'output' )
		);
	}

	public function lship_do_activation_redirect() {
	    // Only redirect for admins and not during AJAX calls
	    if (!get_option('lship_do_activation_redirect') || !current_user_can('activate_plugins')) {
	        return;
	    }

	    // Remove the flag so it doesn't redirect again
	    delete_option('lship_do_activation_redirect');

	    // Prevent redirect on network activation
	    if (is_network_admin() || isset($_GET['activate-multi'])) {
	        return;
	    }

	    // Redirect to your plugin settings page and tab
	    wp_safe_redirect(admin_url('admin.php?page=ls-license-shipper-settings&tab=api'));
	    exit;
	}

	public function ls_boot_datatables(){
		
		if (class_exists('LS_Admin_Licenses_Datatables')) {
	        LS_Admin_Licenses_Datatables::boot();
	    }
	}

	private function is_react_admin_page() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$react_pages = array(
			'ls-license-shipper',
			'ls-license-shipper-edit',
			'ls-license-shipper-change',
			'ls-license-shipper-settings',
			'ls-license-shipper-download-links',
			'ls-activation-guides',
		);
		return in_array( $page, $react_pages, true );
	}

}
