<?php
/**
 * Full-screen setup wizard after plugin activation.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Admin_Setup {

	const PAGE_SLUG       = 'ls-setup';
	const OPTION_COMPLETE = 'ls_setup_complete';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ), 99 );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'admin_head', array( __CLASS__, 'print_fullscreen_css' ) );
	}

	public static function is_complete() {
		return get_option( self::OPTION_COMPLETE, 'no' ) === 'yes';
	}

	public static function mark_complete() {
		update_option( self::OPTION_COMPLETE, 'yes', false );
		delete_option( 'lship_do_activation_redirect' );
		delete_transient( 'ls_activation_redirect' );
	}

	public static function is_setup_screen() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return $page === self::PAGE_SLUG;
	}

	public static function register_page() {
		// Hidden admin page (not in sidebar) — accessible via admin.php?page=ls-setup.
		add_submenu_page(
			'options.php',
			__( 'LicenseSender Setup', 'licensesender' ),
			__( 'Setup', 'licensesender' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function body_class( $classes ) {
		if ( self::is_setup_screen() ) {
			$classes .= ' ls-setup-wizard ls-admin-screen';
		}
		return $classes;
	}

	public static function print_fullscreen_css() {
		if ( ! self::is_setup_screen() ) {
			return;
		}
		?>
		<style id="ls-setup-wizard-css">
			html.wp-toolbar { padding-top: 0 !important; }
			body.ls-setup-wizard #wpadminbar,
			body.ls-setup-wizard #adminmenumain,
			body.ls-setup-wizard #adminmenuback,
			body.ls-setup-wizard #adminmenuwrap,
			body.ls-setup-wizard #wpfooter {
				display: none !important;
			}
			/* Keep other-plugin notices only inside our notice tray. */
			body.ls-setup-wizard .notice,
			body.ls-setup-wizard .update-nag {
				display: none !important;
			}
			body.ls-setup-wizard #ls-admin-foreign-notices > .notice,
			body.ls-setup-wizard #ls-admin-foreign-notices > .updated,
			body.ls-setup-wizard #ls-admin-foreign-notices > .error,
			body.ls-setup-wizard #ls-admin-foreign-notices > .update-nag {
				display: block !important;
			}
			body.ls-setup-wizard #ls-admin-foreign-notices {
				max-width: 42rem;
				margin: 0 auto 12px;
				padding: 12px 16px 0;
			}
			body.ls-setup-wizard #wpcontent,
			body.ls-setup-wizard #wpbody,
			body.ls-setup-wizard #wpbody-content {
				margin: 0 !important;
				padding: 0 !important;
			}
			body.ls-setup-wizard .ls-admin-wrap {
				margin: 0 !important;
				max-width: none !important;
			}
			body.ls-setup-wizard #ls-app-root {
				min-height: 100vh;
			}
		</style>
		<?php
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run setup.', 'licensesender' ) );
		}

		if ( LS_Admin_Service::uses_react_admin() ) {
			LS_Admin_Assets::render_shell( 'setup' );
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'LicenseSender Setup', 'licensesender' ) . '</h1>';
		echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=ls-licensesender-settings&tab=api' ) ) . '">' . esc_html__( 'Open API settings', 'licensesender' ) . '</a></p></div>';
	}

	/**
	 * Wizard status payload for React.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_status() {
		$user = wp_get_current_user();

		return array(
			'complete'           => self::is_complete(),
			'api_key'            => (string) get_option( 'lship_api_key', '' ),
			'autocomplete'       => get_option( 'lship_autocomplete_order', 'yes' ) === 'yes',
			'send_email_redeem'  => get_option( 'lship_send_email_after_redeem', 'no' ) === 'yes',
			'manage_downloads'   => get_option( 'lship_enable_manage_downloads', 'no' ) === 'yes',
			'manage_guides'      => get_option( 'lship_enable_manage_activation_guides', 'no' ) === 'yes',
			'site_name'          => get_bloginfo( 'name' ),
			'admin_email'        => (string) $user->user_email,
			'dashboard_url'      => admin_url( 'admin.php?page=ls-dashboard' ),
			'settings_url'       => admin_url( 'admin.php?page=ls-licensesender-settings&tab=api' ),
			'products_url'       => admin_url( 'edit.php?post_type=product' ),
			'account_url'        => 'https://licensesender.com/client',
			'docs_url'           => 'https://licensesender.com/docs',
			'plugin_version'     => defined( 'LICENSESENDER_VERSION' ) ? (string) LICENSESENDER_VERSION : '',
		);
	}

	/**
	 * Persist wizard step data.
	 *
	 * @param array<string, mixed> $data Incoming fields.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function save_step( array $data ) {
		if ( isset( $data['api_key'] ) ) {
			update_option( 'lship_api_key', sanitize_text_field( (string) $data['api_key'] ) );
		}

		$bool_map = array(
			'autocomplete'      => 'lship_autocomplete_order',
			'send_email_redeem' => 'lship_send_email_after_redeem',
			'manage_downloads'  => 'lship_enable_manage_downloads',
			'manage_guides'     => 'lship_enable_manage_activation_guides',
		);

		foreach ( $bool_map as $field => $option ) {
			if ( array_key_exists( $field, $data ) ) {
				$enabled = filter_var( $data[ $field ], FILTER_VALIDATE_BOOLEAN );
				update_option( $option, $enabled ? 'yes' : 'no' );
			}
		}

		return self::get_status();
	}
}

LS_Admin_Setup::init();
