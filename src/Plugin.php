<?php

namespace LicenseSender;

use LicenseSender\Admin\Admin;
use LicenseSender\Admin\ActivationGuideAjax;
use LicenseSender\Admin\AdminNotice;
use LicenseSender\Admin\Assets;
use LicenseSender\Admin\Orders\DeliveryStatus;
use LicenseSender\Admin\Orders\FetchLicense;
use LicenseSender\Admin\Orders\OrderActions;
use LicenseSender\Admin\Orders\OrderMetaBox;
use LicenseSender\Admin\Pages\SetupPage;
use LicenseSender\Admin\Pages\WholesaleOrdersPage;
use LicenseSender\Admin\Products\LicenseColumn;
use LicenseSender\Admin\Products\ProductTab;
use LicenseSender\Chat\Chat;
use LicenseSender\Core\I18n;
use LicenseSender\Core\Loader;
use LicenseSender\Frontend\Frontend;
use LicenseSender\Frontend\FrontendActions;
use LicenseSender\Licenses\LicenseEmailService;
use LicenseSender\Licenses\MyKeysEndpoint;
use LicenseSender\Orders\OrderHandler;
use LicenseSender\Orders\OrderPush;
use LicenseSender\Orders\WebhookReceiver;
use LicenseSender\Rest\AdminController;
use LicenseSender\Support\AccountEndpoint;
use LicenseSender\Support\Shortcodes as SupportShortcodes;
use LicenseSender\Wholesale\Cart;
use LicenseSender\Wholesale\Catalog;
use LicenseSender\Wholesale\Checkout as WholesaleCheckout;
use LicenseSender\Wholesale\Emails;
use LicenseSender\Wholesale\Orders as WholesaleOrders;
use LicenseSender\Wholesale\Payments;
use LicenseSender\Wholesale\Portal as WholesalePortal;
use LicenseSender\Wholesale\Shortcodes as WholesaleShortcodes;
use LicenseSender\Wholesale\Wholesale;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin entry point: registers every service's hooks once, in a fixed order.
 */
class Plugin {

	/** @var self|null */
	private static $instance = null;

	/** @var bool */
	private $booted = false;

	/** @var Loader */
	protected $loader;

	/** @var string */
	protected $plugin_name = 'licensesender';

	/** @var string */
	protected $version;

	/**
	 * Services with a static init() that adds their hooks. The order matches the old file load order.
	 *
	 * @var array<int, class-string>
	 */
	private const SERVICES = array(
		AdminNotice::class,
		OrderHandler::class,
		OrderPush::class,
		MyKeysEndpoint::class,
		LicenseEmailService::class,
		WebhookReceiver::class,
		Wholesale::class,
		Catalog::class,
		Cart::class,
		WholesaleOrders::class,
		Payments::class,
		WholesaleShortcodes::class,
		WholesalePortal::class,
		WholesaleCheckout::class,
		SupportShortcodes::class,
		AccountEndpoint::class,
		SetupPage::class,
		ActivationGuideAjax::class,
		OrderActions::class,
		DeliveryStatus::class,
		AdminController::class,
		Assets::class,
		WholesaleOrdersPage::class,
		FrontendActions::class,
	);

	/**
	 * Services that add their hooks in the constructor.
	 *
	 * @var array<int, class-string>
	 */
	private const INSTANCES = array(
		ProductTab::class,
		OrderMetaBox::class,
		LicenseColumn::class,
		FetchLicense::class,
	);

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		$this->version = defined( 'LICENSESENDER_VERSION' ) ? LICENSESENDER_VERSION : '1.0.0';
		$this->loader  = new Loader();
	}

	/**
	 * Registers all hooks. Called once from licensesender.php; later calls do nothing.
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		foreach ( self::SERVICES as $service ) {
			$service::init();
		}
		foreach ( self::INSTANCES as $service ) {
			new $service();
		}
		add_action( 'woocommerce_init', array( Emails::class, 'init' ) );

		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->loader->run();
	}

	private function set_locale() {
		$plugin_i18n = new I18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	private function define_admin_hooks() {
		$plugin_admin = new Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'ls_register_admin_menus' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'lship_do_activation_redirect' );
	}

	private function define_public_hooks() {
		$plugin_public = new Frontend( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'register_wholesale_assets' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'register_support_assets' );
		$this->loader->add_action( 'wp_head', $plugin_public, 'enqueue_custom_styles' );

		Chat::init();
	}

	/**
	 * Old entry point of the Licensesender class; same as boot().
	 */
	public function run() {
		$this->boot();
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * @return Loader
	 */
	public function get_loader() {
		return $this->loader;
	}

	public function get_version() {
		return $this->version;
	}
}
