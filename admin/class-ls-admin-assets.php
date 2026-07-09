<?php
/**
 * React admin asset loader.
 *
 * @package License_Shipper
 */

defined( 'ABSPATH' ) || exit;

class LS_Admin_Assets {

	private static $page_scripts = array(
		'ls-license-shipper'                => 'licenses',
		'ls-license-shipper-edit'           => 'licenses',
		'ls-license-shipper-change'         => 'licenses',
		'ls-license-shipper-settings'       => 'settings',
		'ls-license-shipper-download-links' => 'download-links',
		'ls-activation-guides'              => 'activation-guides',
	);

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 100 );
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
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'License Shipper React assets could not be loaded. Run npm run build in the plugin directory.', 'license-shipper' ) . '</p></div>';
				}
			);
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

		return array(
			'restUrl'         => rest_url( 'license-shipper/v1/' ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'page'            => $entry,
			'tab'             => $tab,
			'brandColor'      => get_option( 'ls_brand', '#4f46e5' ),
			'adminUrl'        => admin_url(),
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'activationNonce' => wp_create_nonce( 'ls_activation_guide_nonce' ),
			'licenseId'       => isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0,
			'i18n'            => self::get_i18n_strings( $entry ),
		);
	}

	private static function get_i18n_strings( $entry ) {
		$common = array(
			'save'    => __( 'Save Changes', 'license-shipper' ),
			'cancel'  => __( 'Cancel', 'license-shipper' ),
			'delete'  => __( 'Delete', 'license-shipper' ),
			'edit'    => __( 'Edit', 'license-shipper' ),
			'loading' => __( 'Loading…', 'license-shipper' ),
			'error'   => __( 'Something went wrong.', 'license-shipper' ),
			'success' => __( 'Saved successfully.', 'license-shipper' ),
			'confirm' => __( 'Are you sure?', 'license-shipper' ),
		);

		if ( $entry === 'licenses' ) {
			return array_merge(
				$common,
				array(
					'title'     => __( 'License Keys', 'license-shipper' ),
					'subtitle'  => __( 'View and manage all delivered license keys.', 'license-shipper' ),
					'totalKeys' => __( 'Total Keys', 'license-shipper' ),
					'orders'    => __( 'Orders', 'license-shipper' ),
					'products'  => __( 'Products', 'license-shipper' ),
					'emails'    => __( 'Unique Emails', 'license-shipper' ),
					'search'    => __( 'Search keys, orders, SKU, email…', 'license-shipper' ),
					'empty'     => __( 'No license keys yet. Keys appear after customers redeem orders.', 'license-shipper' ),
					'copy'      => __( 'Copy', 'license-shipper' ),
					'copied'    => __( 'Copied!', 'license-shipper' ),
					'change'    => __( 'Change', 'license-shipper' ),
					'export'    => __( 'Export', 'license-shipper' ),
					'refresh'   => __( 'Refresh', 'license-shipper' ),
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
			<div id="ls-app-root" data-page="<?php echo esc_attr( $page_slug ); ?>">
				<div class="ls-admin-loading">
					<div class="ls-admin-loading__spinner" aria-hidden="true"></div>
					<p><?php esc_html_e( 'Loading License Shipper…', 'license-shipper' ); ?></p>
				</div>
			</div>
		</div>
		<style>
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
