<?php
/**
 * LicenseSender – Support Tickets WooCommerce My Account endpoint
 *
 * Text domain: licensesender
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LS_Support_Endpoint {

	const ENDPOINT   = 'support-tickets';
	const MENU_TITLE = 'Support Tickets';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_endpoint' ), 0 );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_var' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'inject_menu_item' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render_endpoint' ) );
	}

	/** Call on plugin activation */
	public static function activate() {
		self::add_endpoint();
		flush_rewrite_rules();
	}

	/** Optional: call on deactivation */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	public static function is_enabled() {
		return 'yes' === get_option( 'lship_support_enabled', 'yes' )
			&& 'yes' === get_option( 'lship_support_my_account', 'no' );
	}

	public static function add_endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	public static function add_query_var( $vars ) {
		$vars[] = self::ENDPOINT;
		return $vars;
	}

	public static function inject_menu_item( $items ) {
		if ( ! self::is_enabled() ) {
			return $items;
		}

		$new      = array();
		$inserted = false;
		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'orders' === $key || ( ! $inserted && 'my-keys' === $key ) ) {
				$new[ self::ENDPOINT ] = __( self::MENU_TITLE, 'licensesender' );
				$inserted              = true;
			}
		}
		if ( ! $inserted ) {
			$new[ self::ENDPOINT ] = __( self::MENU_TITLE, 'licensesender' );
		}
		return $new;
	}

	public static function render_endpoint() {
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'You must be logged in to view this page.', 'licensesender' ) . '</p>';
			return;
		}

		if ( ! self::is_enabled() ) {
			echo '<p>' . esc_html__( 'Support tickets are currently unavailable.', 'licensesender' ) . '</p>';
			return;
		}

		echo '<div class="ls-support-my-account-wrap">';
		echo '<h3 class="ls-title">' . esc_html__( self::MENU_TITLE, 'licensesender' ) . '</h3>';
		echo '<p class="ls-subtitle">' . esc_html__( 'Open and track your support requests.', 'licensesender' ) . '</p>';

		$open_id    = absint( get_option( 'lship_support_open_page_id', 0 ) );
		$manage_id  = absint( get_option( 'lship_support_manage_page_id', 0 ) );
		$open_url   = $open_id ? get_permalink( $open_id ) : '';
		$manage_url = $manage_id ? get_permalink( $manage_id ) : '';

		if ( $open_url || $manage_url ) {
			echo '<p class="ls-support-my-account-actions">';
			if ( $open_url ) {
				printf(
					'<a class="button" href="%s">%s</a> ',
					esc_url( $open_url ),
					esc_html__( 'Open a ticket', 'licensesender' )
				);
			}
			if ( $manage_url ) {
				printf(
					'<a class="button" href="%s">%s</a>',
					esc_url( $manage_url ),
					esc_html__( 'View all tickets', 'licensesender' )
				);
			}
			echo '</p>';
		}

		if ( shortcode_exists( 'ls_support_manage' ) ) {
			echo do_shortcode( '[ls_support_manage]' );
		} else {
			echo '<p>' . esc_html__( 'Support portal is not available on this site.', 'licensesender' ) . '</p>';
		}

		echo '</div>';
	}
}

LS_Support_Endpoint::init();
