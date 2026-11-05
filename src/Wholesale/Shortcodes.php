<?php

namespace LicenseSender\Wholesale;

defined( 'ABSPATH' ) || exit;

class Shortcodes {

	/**
	 * Redirect URL injected into WooCommerce auth forms on wholesale pages.
	 *
	 * @var string
	 */
	private static $auth_redirect_url = '';

	/**
	 * Whether wholesale auth templates are being rendered.
	 *
	 * @var bool
	 */
	private static $wholesale_auth_active = false;

	public static function init() {
		add_shortcode( 'ls_wholesale_apply', array( __CLASS__, 'render_apply' ) );
		add_shortcode( 'ls_wholesale_catalog', array( __CLASS__, 'render_catalog' ) );
		// One shortcode for the whole portal: ordering for approved buyers, the application for everyone else.
		add_shortcode( 'ls_wholesale', array( __CLASS__, 'render_catalog' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_apply_submit' ) );
		add_action( 'wp_ajax_ls_wholesale_submit_application', array( __CLASS__, 'ajax_submit_application' ) );
		add_action( 'woocommerce_login_form_start', array( __CLASS__, 'output_auth_redirect_field' ) );
		add_action( 'woocommerce_register_form_start', array( __CLASS__, 'output_auth_redirect_field' ) );
		add_filter( 'woocommerce_login_redirect', array( __CLASS__, 'filter_auth_redirect' ), 10, 2 );
		add_filter( 'woocommerce_registration_redirect', array( __CLASS__, 'filter_auth_redirect' ) );
		add_filter( 'option_woocommerce_enable_myaccount_registration', array( __CLASS__, 'enable_wholesale_registration' ) );
	}

	public static function handle_apply_submit() {
		if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || empty( $_POST['ls_wholesale_apply'] ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ls_wholesale_apply' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'licensesender' ) );
		}

		$result       = self::process_application_submission( $_POST );
		$redirect     = self::get_apply_success_url();
		if ( is_wp_error( $result ) ) {
			$redirect = add_query_arg( 'ls_wholesale_error', rawurlencode( $result->get_error_message() ), $redirect );
		} else {
			$redirect = add_query_arg( 'ls_wholesale_applied', '1', $redirect );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	public static function ajax_submit_application() {
		check_ajax_referer( 'ls_wholesale_apply', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array( 'message' => __( 'You must be logged in to apply.', 'licensesender' ) ),
				401
			);
		}

		$result = self::process_application_submission( $_POST );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'title'        => __( 'Application submitted', 'licensesender' ),
				'message'      => __( 'Thank you. We will review your wholesale application and email you once approved.', 'licensesender' ),
				'redirect_url' => add_query_arg( 'ls_wholesale_applied', '1', self::get_apply_success_url() ),
			)
		);
	}

	private static function process_application_submission( $data ) {
		return Wholesale::submit_application(
			get_current_user_id(),
			array(
				'company_name'   => wp_unslash( $data['company_name'] ?? '' ),
				'business_email' => wp_unslash( $data['business_email'] ?? '' ),
				'phone'          => wp_unslash( $data['phone'] ?? '' ),
				'messenger_link' => wp_unslash( $data['messenger_link'] ?? '' ),
				'website'        => wp_unslash( $data['website'] ?? '' ),
				'tax_id'         => wp_unslash( $data['tax_id'] ?? '' ),
				'message'        => wp_unslash( $data['message'] ?? '' ),
			)
		);
	}

	private static function get_apply_success_url() {
		$redirect = '';
		if ( ! empty( $_POST['apply_page_url'] ) ) {
			$redirect = wp_validate_redirect( wp_unslash( $_POST['apply_page_url'] ), '' );
		}

		if ( ! $redirect ) {
			$redirect = wp_get_referer();
		}

		if ( ! $redirect ) {
			$redirect = get_permalink();
		}

		return remove_query_arg( array( 'ls_wholesale_applied', 'ls_wholesale_error' ), $redirect );
	}

	public static function render_apply() {
		if ( ! is_user_logged_in() ) {
			return self::render_logged_out(
				__( 'Wholesale application', 'licensesender' ),
				__( 'Log in or create a WooCommerce account to apply for wholesale access.', 'licensesender' )
			);
		}

		return Portal::render( 'apply' );
	}

	public static function render_catalog( $atts ) {
		unset( $atts );

		if ( ! is_user_logged_in() ) {
			return self::render_logged_out(
				__( 'Wholesale catalog', 'licensesender' ),
				__( 'Log in or register with your WooCommerce account to view wholesale products and pricing.', 'licensesender' )
			);
		}

		// Approved buyers get the ordering portal; everyone else the application or its status.
		return Portal::render( 'portal' );
	}

	/**
	 * Visitors who aren't logged in: WooCommerce's own login / register forms.
	 */
	private static function render_logged_out( $title, $message ) {
		// React sign-in step (sends visitors to the store's login, then back here), unless
		// WooCommerce has no account page or the visitor chose this page's own sign-up form.
		if ( Wholesale::is_enabled() && ! self::is_register_auth_view() && Portal::auth_links() ) {
			return Portal::render( 'guest' );
		}

		self::enqueue_wholesale_assets();

		ob_start();
		if ( ! Wholesale::is_enabled() ) {
			self::render_notice(
				'warning',
				__( 'Wholesale unavailable', 'licensesender' ),
				__( 'Wholesale ordering is currently disabled. Please contact the store administrator.', 'licensesender' )
			);
		} else {
			self::render_wc_auth_section( $title, $message );
		}

		return ob_get_clean();
	}

	private static function enqueue_wholesale_assets( $include_cart_fragments = false ) {
		wp_enqueue_style( 'ls-wholesale' );
		wp_enqueue_script( 'ls-wholesale' );

		if ( $include_cart_fragments ) {
			wp_enqueue_script( 'wc-cart-fragments' );
		}

		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		wp_enqueue_style( 'woocommerce-general' );
		wp_enqueue_style( 'woocommerce-layout' );
		wp_enqueue_style( 'woocommerce-smallscreen' );

		if ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) || self::is_register_auth_view() ) {
			if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) {
				wp_enqueue_script( 'wc-password-strength-meter' );
			}
		}
	}

	/**
	 * ?ls_auth=register only counts on a wholesale page, so it cannot be used to
	 * switch on registration elsewhere (e.g. /my-account/) when the store disabled it.
	 */
	private static function is_register_auth_view() {
		return isset( $_GET['ls_auth'] )
			&& sanitize_key( wp_unslash( $_GET['ls_auth'] ) ) === 'register'
			&& ls_request_page_has_shortcode( array( 'ls_wholesale', 'ls_wholesale_apply', 'ls_wholesale_catalog' ) );
	}

	private static function render_wc_auth_section( $title, $message ) {
		?>
		<div class="ls-wholesale-wrap">
			<div class="ls-wholesale-card ls-wholesale-auth-card">
				<h2><?php echo esc_html( $title ); ?></h2>
				<p class="ls-wholesale-lead"><?php echo esc_html( $message ); ?></p>
				<?php
				if ( function_exists( 'woocommerce_output_all_notices' ) ) {
					woocommerce_output_all_notices();
				}
				self::render_wc_login_register_forms();
				?>
			</div>
		</div>
		<?php
	}

	private static function render_wc_login_register_forms() {
		if ( ! function_exists( 'wc_get_template' ) ) {
			self::render_notice(
				'warning',
				__( 'Login unavailable', 'licensesender' ),
				__( 'WooCommerce is required for account login and registration.', 'licensesender' ),
				sprintf(
					'<a class="ls-wholesale-btn" href="%s">%s</a>',
					esc_url( wp_login_url( get_permalink() ) ),
					esc_html__( 'Log in', 'licensesender' )
				)
			);
			return;
		}

		$base_url              = get_permalink() ?: home_url( '/' );
		$redirect_url          = remove_query_arg( array( 'ls_auth', 'ls_wholesale_error', 'ls_wholesale_applied' ), $base_url );
		$is_register           = self::is_register_auth_view();
		self::$auth_redirect_url = $redirect_url;
		self::$wholesale_auth_active = true;

		echo '<div class="ls-wholesale-wc-auth">';

		if ( $is_register ) {
			self::load_wholesale_template( 'form-register.php' );
			echo '<p class="ls-wholesale-auth-switch">';
			esc_html_e( 'Already have an account?', 'licensesender' );
			echo ' <a href="' . esc_url( $redirect_url ) . '">' . esc_html__( 'Log in', 'licensesender' ) . '</a>';
			echo '</p>';
		} else {
			self::load_wholesale_template( 'form-login.php' );
			echo '<p class="ls-wholesale-auth-switch">';
			esc_html_e( "Don't have an account?", 'licensesender' );
			echo ' <a href="' . esc_url( add_query_arg( 'ls_auth', 'register', $redirect_url ) ) . '">' . esc_html__( 'Register', 'licensesender' ) . '</a>';
			echo '</p>';
		}

		echo '</div>';

		self::$auth_redirect_url     = '';
		self::$wholesale_auth_active = false;
	}

	private static function load_wholesale_template( $template ) {
		$path = LICENSESENDER_PATH . 'templates/wholesale/' . $template;
		if ( file_exists( $path ) ) {
			include $path;
		}
	}

	public static function enable_wholesale_registration( $value ) {
		if ( self::$wholesale_auth_active || self::is_register_auth_view() || self::is_wholesale_registration_request() ) {
			return 'yes';
		}

		return $value;
	}

	private static function is_wholesale_registration_request() {
		if ( empty( $_POST['register'] ) ) {
			return false;
		}

		return ls_request_page_has_shortcode( array( 'ls_wholesale', 'ls_wholesale_apply', 'ls_wholesale_catalog' ) );
	}

	public static function output_auth_redirect_field() {
		if ( self::$auth_redirect_url === '' ) {
			return;
		}

		echo '<input type="hidden" name="redirect" value="' . esc_url( self::$auth_redirect_url ) . '" />';
	}

	public static function filter_auth_redirect( $redirect, $user = null ) {
		unset( $user );

		if ( ! empty( $_POST['redirect'] ) ) {
			$target = wp_validate_redirect( wp_unslash( $_POST['redirect'] ), $redirect );
			if ( $target ) {
				return $target;
			}
		}

		return $redirect;
	}

	private static function render_notice( $type, $title, $message, $actions = '' ) {
		?>
		<div class="ls-wholesale-wrap">
			<div class="ls-wholesale-card ls-wholesale-notice ls-wholesale-notice-<?php echo esc_attr( $type ); ?>">
				<h2><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html( $message ); ?></p>
				<?php if ( $actions ) : ?>
					<div class="ls-wholesale-actions"><?php echo wp_kses_post( $actions ); ?></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
