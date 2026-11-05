<?php

namespace LicenseSender\Admin;

use LicenseSender\Admin\Pages\SetupPage;

defined( 'ABSPATH' ) || exit;

class Assets {

	private static $page_scripts = array(
		'ls-dashboard'                    => 'dashboard',
		'ls-licensesender'                => 'licenses',
		'ls-licensesender-edit'           => 'licenses',
		'ls-licensesender-report'         => 'licenses',
		'ls-licensesender-settings'       => 'settings',
		'ls-licensesender-download-links' => 'download-links',
		'ls-activation-guides'              => 'activation-guides',
		'ls-wholesale-applications'         => 'wholesale',
		'ls-setup'                          => 'setup',
	);

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 100 );
		add_action( 'in_admin_header', array( __CLASS__, 'suppress_foreign_notices' ), 1000 );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
	}

	/**
	 * Keep other plugins' admin_notices hooks, but prevent default WP layout breakage.
	 * Notices are relocated into #ls-admin-foreign-notices by JS.
	 */
	public static function suppress_foreign_notices() {
		if ( ! AdminService::is_plugin_admin_page() ) {
			return;
		}

		// Intentionally do not remove_all_actions( 'admin_notices' ).
		// Foreign notices are moved into #ls-admin-foreign-notices.
	}

	/**
	 * Add a body class for scoped admin notice CSS.
	 *
	 * @param string $classes Existing classes.
	 */
	public static function admin_body_class( $classes ) {
		if ( AdminService::is_plugin_admin_page() ) {
			$classes .= ' ls-admin-screen';
		}

		return $classes;
	}

	public static function get_entry_for_page( $page ) {
		return self::$page_scripts[ $page ] ?? null;
	}

	public static function enqueue( $hook ) {
		$page  = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$entry = self::get_entry_for_page( $page );

		if ( ! $entry ) {
			return;
		}

		$build_dir = LICENSESENDER_PATH . 'build/admin/';
		$build_url = LICENSESENDER_URL . 'build/admin/';
		$js_file   = $build_dir . $entry . '.js';

		if ( ! file_exists( $js_file ) ) {
			if ( class_exists( AdminNotice::class ) ) {
				AdminNotice::error(
					__( 'licensesender React assets could not be loaded. Run npm run build in the plugin directory.', 'licensesender' )
				);
			}
			return;
		}

		$handle = 'ls-admin-' . $entry;
		$css    = self::get_build_css_url();

		if ( $css ) {
			wp_enqueue_style(
				$handle . '-css',
				$css,
				array(),
				(string) filemtime( self::get_build_css_path() )
			);
		}

		if ( $entry === 'activation-guides' ) {
			wp_enqueue_editor();
		}

		wp_enqueue_script(
			$handle,
			$build_url . $entry . '.js',
			$entry === 'activation-guides' ? array( 'wp-editor' ) : array(),
			(string) filemtime( $js_file ),
			true
		);
	}

	private static function get_build_css_path() {
		$files = glob( LICENSESENDER_PATH . 'build/admin/assets/style*.css' );
		if ( empty( $files ) ) {
			return '';
		}

		usort(
			$files,
			static function ( $a, $b ) {
				return filemtime( $b ) - filemtime( $a );
			}
		);

		return $files[0];
	}

	private static function get_build_css_url() {
		$path = self::get_build_css_path();
		if ( ! $path ) {
			return '';
		}

		return LICENSESENDER_URL . 'build/admin/assets/' . basename( $path );
	}

	public static function get_bootstrap( $page, $entry ) {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';

		$notices = class_exists( AdminNotice::class ) ? AdminNotice::consume_notices() : array();

		return array(
			'restUrl'         => rest_url( 'licensesender/v1/' ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'page'            => $entry,
			'tab'             => $tab,
			'brandColor'      => get_option( 'ls_brand', '#4f46e5' ),
			'pluginVersion'   => defined( 'LICENSESENDER_VERSION' ) ? (string) LICENSESENDER_VERSION : '',
			'logoUrl'         => LICENSESENDER_URL . 'assets/admin/img/logo.svg',
			'adminUrl'        => admin_url(),
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'activationNonce' => wp_create_nonce( 'ls_activation_guide_nonce' ),
			'licenseId'       => isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0,
			'notices'         => $notices,
			'i18n'            => self::get_i18n_strings( $entry ),
			'setup'           => ( $entry === 'setup' && class_exists( SetupPage::class ) ) ? SetupPage::get_status() : null,
		);
	}

	private static function get_i18n_strings( $entry ) {
		$common = array(
			'save'    => __( 'Save Changes', 'licensesender' ),
			'cancel'  => __( 'Cancel', 'licensesender' ),
			'delete'  => __( 'Delete', 'licensesender' ),
			'edit'    => __( 'Edit', 'licensesender' ),
			'loading' => __( 'Loading…', 'licensesender' ),
			'error'   => __( 'Something went wrong.', 'licensesender' ),
			'success' => __( 'Saved successfully.', 'licensesender' ),
			'confirm' => __( 'Are you sure?', 'licensesender' ),
		);

		if ( $entry === 'licenses' ) {
			return array_merge(
				$common,
				array(
					'title'     => __( 'License Keys', 'licensesender' ),
					'subtitle'  => __( 'Every key delivered to your customers.', 'licensesender' ),
					'totalKeys' => __( 'Total Keys', 'licensesender' ),
					'orders'    => __( 'Orders', 'licensesender' ),
					'products'  => __( 'LS Products', 'licensesender' ),
					'emails'    => __( 'Unique Emails', 'licensesender' ),
					'search'    => __( 'Search keys, orders, SKU, email…', 'licensesender' ),
					'empty'     => __( 'No license keys yet. Keys appear after customers redeem orders.', 'licensesender' ),
					'copy'      => __( 'Copy', 'licensesender' ),
					'copied'    => __( 'Copied!', 'licensesender' ),
					'report'    => __( 'Report / replace', 'licensesender' ),
					'export'    => __( 'Export', 'licensesender' ),
					'refresh'   => __( 'Refresh', 'licensesender' ),
					'customers' => __( 'Customers', 'licensesender' ),
					'notEmailedKeys' => __( 'Not emailed', 'licensesender' ),
					'filtered' => __( 'in current filter', 'licensesender' ),
					'all' => __( 'All', 'licensesender' ),
					'emailed' => __( 'Emailed', 'licensesender' ),
					'notEmailed' => __( 'Not emailed', 'licensesender' ),
					'searchPlaceholder' => __( 'Search key, order, email, SKU…', 'licensesender' ),
					'allProducts' => __( 'All products', 'licensesender' ),
					'allTime' => __( 'All time', 'licensesender' ),
					'today' => __( 'Today', 'licensesender' ),
					'last7' => __( 'Last 7 days', 'licensesender' ),
					'last30' => __( 'Last 30 days', 'licensesender' ),
					'last90' => __( 'Last 90 days', 'licensesender' ),
					'customRange' => __( 'Custom range…', 'licensesender' ),
					'from' => __( 'From', 'licensesender' ),
					'to' => __( 'To', 'licensesender' ),
					'clearFilters' => __( 'Clear filters', 'licensesender' ),
					'showKeys' => __( 'Show keys', 'licensesender' ),
					'hideKeys' => __( 'Hide keys', 'licensesender' ),
					/* translators: placeholders are numbers */
					'selectedCount' => __( '%d selected', 'licensesender' ),
					'copyKeys' => __( 'Copy keys', 'licensesender' ),
					/* translators: placeholders are numbers */
					'keysCopied' => __( '%d keys copied.', 'licensesender' ),
					'exportSelected' => __( 'Export', 'licensesender' ),
					'exportCsv' => __( 'Export CSV', 'licensesender' ),
					'resendEmails' => __( 'Resend emails', 'licensesender' ),
					'resendEmail' => __( 'Resend email', 'licensesender' ),
					'syncFromApi' => __( 'Sync from API', 'licensesender' ),
					'clearSelection' => __( 'Clear selection', 'licensesender' ),
					'licenseKey' => __( 'License key', 'licensesender' ),
					'product' => __( 'Product', 'licensesender' ),
					'order' => __( 'Order', 'licensesender' ),
					'customer' => __( 'Customer', 'licensesender' ),
					'emailStatus' => __( 'Email', 'licensesender' ),
					'soldAt' => __( 'Sold', 'licensesender' ),
					'actions' => __( 'Actions', 'licensesender' ),
					'noMatches' => __( 'No keys match these filters', 'licensesender' ),
					'noKeys' => __( 'No license keys yet', 'licensesender' ),
					'viewDetails' => __( 'View details', 'licensesender' ),
					'copyKey' => __( 'Copy key', 'licensesender' ),
					'edit' => __( 'Edit', 'licensesender' ),
					'openOrder' => __( 'Open order', 'licensesender' ),
					/* translators: placeholders are numbers */
					'showing' => __( 'Showing %1$s–%2$s of %3$s', 'licensesender' ),
					'page' => __( 'page', 'licensesender' ),
					'nothingToExport' => __( 'Nothing to export.', 'licensesender' ),
					/* translators: placeholders are numbers */
					'exported' => __( '%d keys exported.', 'licensesender' ),
					'resendConfirmTitle' => __( 'Resend license emails?', 'licensesender' ),
					/* translators: placeholders are numbers */
					'resendConfirmDesc' => __( 'Customers of the %d selected order(s) will get their license email again, with all keys from the order.', 'licensesender' ),
					'cancel' => __( 'Cancel', 'licensesender' ),
					'licenseDetails' => __( 'License details', 'licensesender' ),
					'lastSynced' => __( 'Last synced', 'licensesender' ),
					'source' => __( 'Source', 'licensesender' ),
					'appLicenseId' => __( 'App license ID', 'licensesender' ),
					'openActivationGuide' => __( 'Open activation guide', 'licensesender' ),
					'downloadLink' => __( 'Download link', 'licensesender' ),
					'activationGuide' => __( 'Activation guide', 'licensesender' ),
					'checkStock' => __( 'Check stock', 'licensesender' ),
					'checkStockTitle' => __( 'Check stock by SKU', 'licensesender' ),
					'checkStockDesc' => __( 'See whether your LicenseSender inventory has an unsold key for a SKU. Nothing is reserved or sold.', 'licensesender' ),
					'checking' => __( 'Checking…', 'licensesender' ),
					'check' => __( 'Check', 'licensesender' ),
					'inStock' => __( 'Keys are available for this SKU.', 'licensesender' ),
					/* translators: placeholders are numbers */
					'inStockCount' => __( '%d keys available for this SKU.', 'licensesender' ),
					'stockAvailable' => __( 'Available', 'licensesender' ),
					'stockAssigned' => __( 'Assigned', 'licensesender' ),
					'stockRedeemed' => __( 'Redeemed', 'licensesender' ),
					'stockTotal' => __( 'Total', 'licensesender' ),
				)
			);
		}

		if ( $entry === 'dashboard' ) {
			return array_merge(
				$common,
				array(
					'title'              => __( 'Dashboard', 'licensesender' ),
					'subtitle'           => __( 'Overview of license delivery and store activity.', 'licensesender' ),
					'totalKeys'          => __( 'Total Keys', 'licensesender' ),
					'orders'             => __( 'Orders', 'licensesender' ),
					'products'           => __( 'LS Products', 'licensesender' ),
					'emails'             => __( 'Unique Emails', 'licensesender' ),
					'today'              => __( 'Delivered Today', 'licensesender' ),
					'week'               => __( 'Last 7 Days', 'licensesender' ),
					'emailsSent'         => __( 'Emails Sent', 'licensesender' ),
					'emailsPending'      => __( 'Emails Pending', 'licensesender' ),
					'wholesalePending'   => __( 'Pending Wholesale', 'licensesender' ),
					'apiStatus'          => __( 'API Connection', 'licensesender' ),
					'connected'          => __( 'Connected', 'licensesender' ),
					'notConnected'       => __( 'Not connected', 'licensesender' ),
					'subscriptionOverview' => __( 'Subscription Overview', 'licensesender' ),
					'subscriptionOverviewDesc' => __( 'Plan, quota, and account details from your LicenseSender API key.', 'licensesender' ),
					'refresh'            => __( 'Refresh', 'licensesender' ),
					'featureRequest'     => __( 'Feature Request', 'licensesender' ),
					'featureRequestDesc' => __( 'Suggest a plugin improvement. Requests appear in the LicenseSender admin panel.', 'licensesender' ),
					'featureRequestTitle' => __( 'Title', 'licensesender' ),
					'featureRequestTitlePlaceholder' => __( 'Short summary of your idea', 'licensesender' ),
					'featureRequestMessage' => __( 'Details', 'licensesender' ),
					'featureRequestMessagePlaceholder' => __( 'Describe the problem or what you would like added…', 'licensesender' ),
					'featureRequestSubmit' => __( 'Send request', 'licensesender' ),
					'featureRequestSubmitting' => __( 'Sending…', 'licensesender' ),
					'featureRequestRequired' => __( 'Please enter a title and description.', 'licensesender' ),
					'featureRequestSuccess' => __( 'Feature request submitted. Thank you!', 'licensesender' ),
					'keysDelivered' => __( 'Keys delivered · 30 days', 'licensesender' ),
					'ordersFulfilled' => __( 'Orders fulfilled · 30 days', 'licensesender' ),
					'todayShort' => __( 'today', 'licensesender' ),
					'weekShort' => __( 'this week', 'licensesender' ),
					'allTime' => __( 'all time', 'licensesender' ),
					'productsShort' => __( 'products', 'licensesender' ),
					'customersShort' => __( 'customers', 'licensesender' ),
					'sentShort' => __( 'sent', 'licensesender' ),
					'emailedShort' => __( 'emailed', 'licensesender' ),
					'deliveries' => __( 'Key deliveries', 'licensesender' ),
					'deliveriesDesc' => __( 'Keys delivered to customers per day.', 'licensesender' ),
					'total' => __( 'Total', 'licensesender' ),
					'dailyAverage' => __( 'Daily average', 'licensesender' ),
					'bestDay' => __( 'Best day', 'licensesender' ),
					'noDeliveries' => __( 'No keys delivered in this period.', 'licensesender' ),
					'needsAttention' => __( 'Needs attention', 'licensesender' ),
					'needsAttentionDesc' => __( 'Things to check in your store.', 'licensesender' ),
					'allGood' => __( 'All good', 'licensesender' ),
					'allGoodDesc' => __( 'Delivery is running and nothing needs your attention.', 'licensesender' ),
					'attnApiTitle' => __( 'Connect your API key', 'licensesender' ),
					'attnApiDesc' => __( 'Keys cannot be delivered until the store is connected to LicenseSender.', 'licensesender' ),
					'attnPlanTitle' => __( 'Your plan is not active', 'licensesender' ),
					/* translators: %d: number, %s: status */
					'attnPlanDesc' => __( 'Status: %s. Renew to keep delivering keys.', 'licensesender' ),
					/* translators: %d: number, %s: status */
					'attnRenewTitle' => __( 'Plan renews in %d days', 'licensesender' ),
					'attnRenewDesc' => __( 'Check your payment method so delivery is not interrupted.', 'licensesender' ),
					/* translators: %d: number, %s: status */
					'attnQuotaTitle' => __( '%d% of monthly API requests used', 'licensesender' ),
					'attnSetupTitle' => __( 'Finish the setup wizard', 'licensesender' ),
					'attnSetupDesc' => __( 'A few steps are left before delivery is fully configured.', 'licensesender' ),
					/* translators: %d: number, %s: status */
					'attnWholesaleTitle' => __( '%d wholesale application(s) waiting', 'licensesender' ),
					'attnWholesaleDesc' => __( 'Approve or reject new reseller requests.', 'licensesender' ),
					/* translators: %d: number, %s: status */
					'attnEmailsTitle' => __( '%d key(s) not emailed yet', 'licensesender' ),
					'attnEmailsDesc' => __( 'Customers can still see them in My Account. Resend from the License Keys page.', 'licensesender' ),
					'connect' => __( 'Connect', 'licensesender' ),
					'review' => __( 'Review', 'licensesender' ),
					'continue' => __( 'Continue', 'licensesender' ),
					'view' => __( 'View', 'licensesender' ),
					'recentDeliveries' => __( 'Recent deliveries', 'licensesender' ),
					'recentDeliveriesDesc' => __( 'The latest keys sent to customers.', 'licensesender' ),
					'viewAll' => __( 'View all', 'licensesender' ),
					'noRecent' => __( 'No keys delivered yet. They appear here after the first order.', 'licensesender' ),
					'product' => __( 'Product', 'licensesender' ),
					'key' => __( 'Key', 'licensesender' ),
					'customer' => __( 'Customer', 'licensesender' ),
					'order' => __( 'Order', 'licensesender' ),
					'when' => __( 'When', 'licensesender' ),
					'emailed' => __( 'Emailed', 'licensesender' ),
					'notEmailed' => __( 'Not emailed', 'licensesender' ),
					'topProducts' => __( 'Top products', 'licensesender' ),
					'topProductsDesc' => __( 'Most keys delivered, last 30 days.', 'licensesender' ),
					'noTopProducts' => __( 'No deliveries in the last 30 days.', 'licensesender' ),
					'yourPlan' => __( 'Your plan', 'licensesender' ),
					'renews' => __( 'Renews', 'licensesender' ),
					'apiRequests' => __( 'API requests this month', 'licensesender' ),
					'apiProducts' => __( 'Products', 'licensesender' ),
					'planUnavailable' => __( 'Connect your API key to see plan details.', 'licensesender' ),
					'manageSubscription' => __( 'Subscription details', 'licensesender' ),
					'quickActions' => __( 'Quick links', 'licensesender' ),
					'licenseKeys' => __( 'License keys', 'licensesender' ),
					'ordersLink' => __( 'Orders', 'licensesender' ),
					'productsLink' => __( 'Products', 'licensesender' ),
					'wholesaleLink' => __( 'Wholesale', 'licensesender' ),
					'downloadLinks' => __( 'Download links', 'licensesender' ),
					'activationGuides' => __( 'Activation guides', 'licensesender' ),
					'settingsLink' => __( 'Settings', 'licensesender' ),
					'suggestFeature' => __( 'Suggest a feature', 'licensesender' ),
					'cancel' => __( 'Cancel', 'licensesender' ),
					'loadFailed' => __( 'The dashboard could not be loaded.', 'licensesender' ),
					'tryAgain' => __( 'Try again', 'licensesender' ),
				)
			);
		}

		if ( $entry === 'setup' ) {
			return array_merge(
				$common,
				array(
					'welcomeTitle'       => __( 'Welcome to LicenseSender', 'licensesender' ),
					'welcomeSubtitle'    => __( 'A short setup gets license delivery working on your WooCommerce store.', 'licensesender' ),
					'getStarted'         => __( 'Get started', 'licensesender' ),
					'skipSetup'          => __( 'Skip for now', 'licensesender' ),
					'back'               => __( 'Back', 'licensesender' ),
					'continue'           => __( 'Continue', 'licensesender' ),
					'connectTitle'       => __( 'Connect your API key', 'licensesender' ),
					'connectSubtitle'    => __( 'Paste the API key from your LicenseSender account. You can find it under API Keys.', 'licensesender' ),
					'apiKey'             => __( 'API Key', 'licensesender' ),
					'apiKeyPlaceholder'  => __( 'Paste your API key here', 'licensesender' ),
					'testConnection'     => __( 'Test connection', 'licensesender' ),
					'testing'            => __( 'Testing…', 'licensesender' ),
					'connectionOk'       => __( 'Connected successfully', 'licensesender' ),
					'openAccount'        => __( 'Open LicenseSender account', 'licensesender' ),
					'essentialsTitle'    => __( 'Choose essentials', 'licensesender' ),
					'essentialsSubtitle' => __( 'You can change these anytime in Settings.', 'licensesender' ),
					'autocomplete'       => __( 'Auto-complete orders', 'licensesender' ),
					'autocompleteHint'   => __( 'Mark orders completed after keys are delivered.', 'licensesender' ),
					'emailRedeem'        => __( 'Email after redemption', 'licensesender' ),
					'emailRedeemHint'    => __( 'Send license emails when customers redeem keys.', 'licensesender' ),
					'manageDownloads'    => __( 'Manage download links', 'licensesender' ),
					'manageDownloadsHint'=> __( 'Enable the Download Links admin menu.', 'licensesender' ),
					'manageGuides'       => __( 'Manage activation guides', 'licensesender' ),
					'manageGuidesHint'   => __( 'Enable the Activation Guides admin menu.', 'licensesender' ),
					'finishTitle'        => __( 'You are ready', 'licensesender' ),
					'finishSubtitle'     => __( 'Map LicenseSender products in WooCommerce, then start delivering keys.', 'licensesender' ),
					'goDashboard'        => __( 'Go to Dashboard', 'licensesender' ),
					'mapProducts'        => __( 'Open products', 'licensesender' ),
					'finishSetup'        => __( 'Finish setup', 'licensesender' ),
					'stepWelcome'        => __( 'Welcome', 'licensesender' ),
					'stepConnect'        => __( 'Connect', 'licensesender' ),
					'stepEssentials'     => __( 'Essentials', 'licensesender' ),
					'stepDone'           => __( 'Done', 'licensesender' ),
					'needApiKey'         => __( 'Enter your API key to continue.', 'licensesender' ),
				)
			);
		}

		if ( $entry === 'wholesale' ) {
			return array_merge(
				$common,
				array(
					'title'                 => __( 'Wholesale Applications', 'licensesender' ),
					'subtitle'              => __( 'Review B2B wholesale access requests and manage catalog access.', 'licensesender' ),
					'catalogTier'           => __( 'Catalog tier', 'licensesender' ),
					'wholesaleProducts'     => __( 'Wholesale products', 'licensesender' ),
					'pending'               => __( 'Pending', 'licensesender' ),
					'shortcodes'            => __( 'Shortcodes', 'licensesender' ),
					'applicant'             => __( 'Applicant', 'licensesender' ),
					'company'               => __( 'Company', 'licensesender' ),
					'email'                 => __( 'Email', 'licensesender' ),
					'phone'                 => __( 'Phone', 'licensesender' ),
					'status'                => __( 'Status', 'licensesender' ),
					'submitted'             => __( 'Submitted', 'licensesender' ),
					'actions'               => __( 'Actions', 'licensesender' ),
					'approve'               => __( 'Approve', 'licensesender' ),
					'reject'                => __( 'Reject', 'licensesender' ),
					'view'                  => __( 'View', 'licensesender' ),
					'viewApplication'       => __( 'Application details', 'licensesender' ),
					'website'               => __( 'Website', 'licensesender' ),
					'reviewed'              => __( 'Reviewed', 'licensesender' ),
					'adminNote'             => __( 'Admin note', 'licensesender' ),
					'delete'                => __( 'Delete', 'licensesender' ),
					'deleted'               => __( 'Application deleted.', 'licensesender' ),
					'deleteApplication'     => __( 'Delete application?', 'licensesender' ),
					'deleteApplicationConfirm' => __( 'This will permanently delete this wholesale application. This action cannot be undone.', 'licensesender' ),
					'bulkDelete'            => __( 'Delete selected', 'licensesender' ),
					'bulkDeleteTitle'       => __( 'Delete selected applications?', 'licensesender' ),
					'bulkDeleteConfirm'     => __( 'This will permanently delete %d wholesale applications. This action cannot be undone.', 'licensesender' ),
					'selected'              => __( 'selected', 'licensesender' ),
					'selectAll'             => __( 'Select all', 'licensesender' ),
					'select'                => __( 'Select', 'licensesender' ),
					'message'               => __( 'Message', 'licensesender' ),
					'messenger'             => __( 'Telegram / WhatsApp', 'licensesender' ),
					'empty'                 => __( 'No applications found.', 'licensesender' ),
					'deletedUser'           => __( '(Deleted user)', 'licensesender' ),
					'rejectApplication'     => __( 'Reject application', 'licensesender' ),
					'rejectNote'            => __( 'Optional note', 'licensesender' ),
					'rejectNotePlaceholder' => __( 'Reason for rejection (optional)', 'licensesender' ),
					'success'               => __( 'Application updated.', 'licensesender' ),
					'refreshCatalog'        => __( 'Refresh catalog', 'licensesender' ),
					'catalogRefreshed'      => __( 'Catalog refreshed.', 'licensesender' ),
					'catalogEmpty'          => __( 'Catalog refreshed, but no wholesale products were returned by the API.', 'licensesender' ),
					'catalogEmptyTitle'     => __( 'No wholesale products from API', 'licensesender' ),
					'catalogEmptyHint'      => __( 'Enable wholesale on products in the licensesender app, set wholesale prices for your tier, then click Refresh catalog.', 'licensesender' ),
					'all'                   => __( 'All', 'licensesender' ),
					'approved'              => __( 'Approved', 'licensesender' ),
					'rejected'              => __( 'Rejected', 'licensesender' ),
					'refresh'               => __( 'Refresh', 'licensesender' ),
					'copy'                  => __( 'Copy', 'licensesender' ),
					'copied'                => __( 'Copied!', 'licensesender' ),
					'contact'               => __( 'Contact', 'licensesender' ),
					'taxId'                 => __( 'Tax / VAT ID', 'licensesender' ),
					'awaitingReview'        => __( 'Awaiting review', 'licensesender' ),
					'activeResellers'       => __( 'Approved resellers', 'licensesender' ),
					/* translators: %d: number of pending applications */
					'pendingBadge'          => __( '%d pending', 'licensesender' ),
					/* translators: %s: catalog tier name */
					'tierLabel'             => __( 'tier: %s', 'licensesender' ),
					'searchPlaceholder'     => __( 'Search name, company, email…', 'licensesender' ),
					'clearSearch'           => __( 'Clear search', 'licensesender' ),
					/* translators: %d: number of selected rows */
					'selectedCount'         => __( '%d selected', 'licensesender' ),
					/* translators: %d: number of pending applications */
					'approveSelected'       => __( 'Approve %d pending', 'licensesender' ),
					'clearSelection'        => __( 'Clear selection', 'licensesender' ),
					'noMatches'             => __( 'No applications match your search', 'licensesender' ),
					'inboxZero'             => __( "You're all caught up", 'licensesender' ),
					'noApplications'        => __( 'No wholesale applications yet', 'licensesender' ),
					'noApplicationsHint'    => __( 'Requests submitted through the wholesale application form will appear here.', 'licensesender' ),
					'showAll'               => __( 'Show all applications', 'licensesender' ),
					'emailApplicant'        => __( 'Email applicant', 'licensesender' ),
					'openUser'              => __( 'Open WordPress user', 'licensesender' ),
					/* translators: 1: visible rows, 2: total applications */
					'showingCount'          => __( 'Showing %1$d of %2$d applications', 'licensesender' ),
					/* translators: %s: relative time, e.g. "2 days ago" */
					'submittedAgo'          => __( 'Submitted %s', 'licensesender' ),
					'approvedOn'            => __( 'Approved', 'licensesender' ),
					'rejectedOn'            => __( 'Rejected', 'licensesender' ),
					'noteToApplicant'       => __( 'Note to applicant (optional)', 'licensesender' ),
					'noteHint'              => __( 'Saved with the decision and shown in the email.', 'licensesender' ),
					'revoke'                => __( 'Revoke access', 'licensesender' ),
					'revokeTitle'           => __( 'Revoke wholesale access?', 'licensesender' ),
					/* translators: %s: company or applicant name */
					'revokeDesc'            => __( '%s will lose wholesale pricing and receive the rejection email.', 'licensesender' ),
					/* translators: %s: company or applicant name */
					'rejectDesc'            => __( '%s will be notified by email that the application was declined.', 'licensesender' ),
					'approveInstead'        => __( 'Approve instead', 'licensesender' ),
					'buyerGroup'            => __( 'Buyer group', 'licensesender' ),
					'defaultGroup'          => __( 'Default group', 'licensesender' ),
					'groupHintApproved'     => __( "Changes apply to this buyer's prices right away.", 'licensesender' ),
					'groupHintPending'      => __( 'The buyer joins this group when you approve.', 'licensesender' ),
					'groupSaved'            => __( 'Buyer group updated.', 'licensesender' ),
					/* translators: %d: number of keys */
					'keysAvailable'         => __( '%d keys available', 'licensesender' ),
					'sellAs'                => __( 'Sell as WooCommerce product', 'licensesender' ),
					'searchProducts'        => __( 'Search products…', 'licensesender' ),
					'mapSku'                => __( 'Map', 'licensesender' ),
					'unmappedOne'           => __( "1 catalog product can't be ordered yet", 'licensesender' ),
					/* translators: %d: number of products */
					'unmappedMany'          => __( "%d catalog products can't be ordered yet", 'licensesender' ),
					'unmappedHint'          => __( 'Resellers see these in the catalog, but no WooCommerce product sells their SKU. Pick the product (or variation) to sell each one as.', 'licensesender' ),
					'approvedToast'         => __( 'Application approved. The customer now has wholesale access.', 'licensesender' ),
					'rejectedToast'         => __( 'Application rejected. The customer has been notified.', 'licensesender' ),
					/* translators: %d: number of applications */
					'approvedMany'          => __( '%d applications approved.', 'licensesender' ),
					/* translators: %d: number of applications */
					'deletedMany'           => __( '%d applications deleted.', 'licensesender' ),
				)
			);
		}

		return $common;
	}

	public static function render_shell( $page_slug = '' ) {
		$wp_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$entry   = self::get_entry_for_page( $wp_page ) ?: $page_slug;
		$boot    = self::get_bootstrap( $wp_page, $entry );
		?>
		<div class="wrap ls-admin-wrap">
			<script type="text/javascript">window.lsAdmin = <?php echo wp_json_encode( $boot ); ?>;</script>
			<div
				id="ls-admin-foreign-notices"
				class="ls-admin-foreign-notices"
				role="region"
				aria-label="<?php esc_attr_e( 'WordPress notices', 'licensesender' ); ?>"
			></div>
			<div id="ls-app-root" data-page="<?php echo esc_attr( $page_slug ); ?>">
				<div class="ls-admin-loading">
					<div class="ls-admin-loading__spinner" aria-hidden="true"></div>
					<p><?php esc_html_e( 'Loading licensesender…', 'licensesender' ); ?></p>
				</div>
			</div>
		</div>
		<style>
			/* Contain other plugins' notices so they cannot break the LicenseSender UI. */
			.ls-admin-foreign-notices {
				max-width: 1400px;
				margin: 0 auto 16px;
				display: flex;
				flex-direction: column;
				gap: 8px;
			}
			.ls-admin-foreign-notices:empty {
				display: none;
			}
			.ls-admin-foreign-notices > .notice,
			.ls-admin-foreign-notices > .updated,
			.ls-admin-foreign-notices > .error,
			.ls-admin-foreign-notices > .update-nag {
				display: block !important;
				margin: 0 !important;
				box-sizing: border-box;
				max-width: 100%;
			}

			body.ls-admin-screen #wpbody-content > .notice,
			body.ls-admin-screen #wpbody-content > .updated,
			body.ls-admin-screen #wpbody-content > .error,
			body.ls-admin-screen #wpbody-content > .update-nag,
			body.ls-admin-screen .ls-admin-wrap > .notice,
			body.ls-admin-screen .ls-admin-wrap > .updated,
			body.ls-admin-screen .ls-admin-wrap > .error,
			body.ls-admin-screen .ls-admin-wrap > .update-nag,
			body.ls-admin-screen .ls-admin-wrap > div:not(#ls-app-root):not(#ls-admin-foreign-notices):not(script),
			body.ls-admin-screen .ls-admin-app .notice:not(.ls-plugin-notice),
			body.ls-admin-screen .ls-admin-app .updated:not(.ls-plugin-notice),
			body.ls-admin-screen .ls-admin-app .error:not(.ls-plugin-notice) {
				display: none !important;
			}
			.ls-admin-wrap { margin-top: 12px; }
			.ls-admin-loading {
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				gap: 14px;
				min-height: calc(100vh - 120px);
				width: 100%;
				padding: 32px 16px;
				color: #64748b;
				font-size: 14px;
				text-align: center;
			}
			.ls-admin-loading__spinner {
				width: 28px;
				height: 28px;
				border: 3px solid #e2e8f0;
				border-top-color: #6366f1;
				border-radius: 50%;
				animation: ls-spin 0.8s linear infinite;
			}
			.ls-admin-loading p {
				margin: 0;
			}
			@keyframes ls-spin { to { transform: rotate(360deg); } }
		</style>
		<?php
	}
}
