<?php
/**
 * React admin asset loader.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Admin_Assets {

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
		if ( ! LS_Admin_Service::is_react_admin_screen() ) {
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
		if ( LS_Admin_Service::is_react_admin_screen() ) {
			$classes .= ' ls-admin-screen';
		}

		return $classes;
	}

	public static function get_entry_for_page( $page ) {
		return self::$page_scripts[ $page ] ?? null;
	}

	public static function enqueue( $hook ) {
		if ( ! LS_Admin_Service::uses_react_admin() ) {
			return;
		}

		$page  = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$entry = self::get_entry_for_page( $page );

		if ( ! $entry ) {
			return;
		}

		$build_dir = plugin_dir_path( __FILE__ ) . 'build/';
		$build_url = plugin_dir_url( __FILE__ ) . 'build/';
		$js_file   = $build_dir . $entry . '.js';

		if ( ! file_exists( $js_file ) ) {
			if ( class_exists( 'LS_Admin_Notice' ) ) {
				LS_Admin_Notice::error(
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
		$files = glob( plugin_dir_path( __FILE__ ) . 'build/assets/style*.css' );
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

		return plugin_dir_url( __FILE__ ) . 'build/assets/' . basename( $path );
	}

	public static function get_bootstrap( $page, $entry ) {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';

		$notices = class_exists( 'LS_Admin_Notice' ) ? LS_Admin_Notice::consume_notices() : array();

		return array(
			'restUrl'         => rest_url( 'licensesender/v1/' ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'page'            => $entry,
			'tab'             => $tab,
			'brandColor'      => get_option( 'ls_brand', '#4f46e5' ),
			'pluginVersion'   => defined( 'LICENSESENDER_VERSION' ) ? (string) LICENSESENDER_VERSION : '',
			'logoUrl'         => plugin_dir_url( __FILE__ ) . 'img/logo.png',
			'adminUrl'        => admin_url(),
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'activationNonce' => wp_create_nonce( 'ls_activation_guide_nonce' ),
			'licenseId'       => isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0,
			'notices'         => $notices,
			'i18n'            => self::get_i18n_strings( $entry ),
			'setup'           => ( $entry === 'setup' && class_exists( 'LS_Admin_Setup' ) ) ? LS_Admin_Setup::get_status() : null,
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
					'subtitle'  => __( 'View and manage all delivered license keys.', 'licensesender' ),
					'totalKeys' => __( 'Total Keys', 'licensesender' ),
					'orders'    => __( 'Orders', 'licensesender' ),
					'products'  => __( 'LS Products', 'licensesender' ),
					'emails'    => __( 'Unique Emails', 'licensesender' ),
					'search'    => __( 'Search keys, orders, SKU, email…', 'licensesender' ),
					'empty'     => __( 'No license keys yet. Keys appear after customers redeem orders.', 'licensesender' ),
					'copy'      => __( 'Copy', 'licensesender' ),
					'copied'    => __( 'Copied!', 'licensesender' ),
					'report'    => __( 'Report Key', 'licensesender' ),
					'export'    => __( 'Export', 'licensesender' ),
					'refresh'   => __( 'Refresh', 'licensesender' ),
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

LS_Admin_Assets::init();
