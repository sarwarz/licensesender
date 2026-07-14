<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://licensesender.com
 * @since      1.0.0
 *
 * @package    Licensesender
 * @subpackage Licensesender/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Licensesender
 * @subpackage Licensesender/includes
 * @author     licensesender <hello@licensesender.com>
 */
class Licensesender {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Licensesender_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'LICENSESENDER_VERSION' ) ) {
			$this->version = LICENSESENDER_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'licensesender';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Licensesender_Loader. Orchestrates the hooks of the plugin.
	 * - Licensesender_i18n. Defines internationalization functionality.
	 * - Licensesender_Admin. Defines all hooks for the admin area.
	 * - Licensesender_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-licensesender-loader.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/ls-licensesender-functions.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ls-admin-service.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-licensesender-i18n.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-licensesender-api.php';
		
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ls-admin-notice.php';
		
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-licensesender-order-handler.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ls-order-push.php';
		
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ls-my-keys-endpoint.php';
		
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ls-license-email-service.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ls-license-cache.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ls-design-system.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ls-webhook-receiver.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-licensesender-email-handler.php';
		
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-licensesender-sso.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ls-wholesale.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ls-wholesale-catalog.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ls-wholesale-cart.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ls-wholesale-orders.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ls-wholesale-payments.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ls-wholesale-currency.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ls-wholesale-shortcodes.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ls-wholesale-emails.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ls-support.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ls-support-shortcodes.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ls-support-endpoint.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ls-chat.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-ls-admin-dashboard.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-ls-admin-setup.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-licensesender-admin.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-licensesender-admin-action.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-licensesender-product-tab.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-licensesender-admin-order-metabox.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-ls-admin-order-actions.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-ls-admin-licenses-datatable.php';
		
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-order-delivery-status.php';
		
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-ls-product-list-column.php';
		
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-ls-admin-fetch-license.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-ls-admin-rest.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-ls-admin-assets.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-ls-admin-wholesale.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-ls-admin-wholesale-orders.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/views/ls-admin-download-display.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/views/ls-admin-activation-display.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/views/ls-admin-settings-display.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-licensesender-public-action.php';
		
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-licensesender-public.php';

		$this->loader = new Licensesender_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Licensesender_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Licensesender_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Licensesender_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'ls_register_admin_menus' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'lship_do_activation_redirect' );
		$this->loader->add_action( 'plugins_loaded', $plugin_admin, 'ls_boot_datatables' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Licensesender_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'register_wholesale_assets' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'register_support_assets' );
		$this->loader->add_action( 'wp_head', $plugin_public, 'enqueue_custom_styles' );

		if ( class_exists( 'LS_Chat' ) ) {
			LS_Chat::init();
		}
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Licensesender_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
