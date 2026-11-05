<?php

namespace LicenseSender\Chat;

use LicenseSender\Core\Api;
use LicenseSender\Core\DesignSystem;

defined( 'ABSPATH' ) || exit;

/**
 * Storefront live chat.
 *
 * The chat itself is hosted by LicenseSender (an iframe on the app's domain). This class
 * only prints the small loader script on store pages, with this store's widget key, the
 * look-and-feel settings, and a signed identity for logged-in customers.
 */
class Chat {

	/** Cached widget key, identity secret and loader URL from the app. */
	const CONFIG_OPTION = 'ls_chat_widget';

	/** Refresh the cached widget config this often. */
	const CONFIG_TTL = 12 * HOUR_IN_SECONDS;

	/** While chat is off in LicenseSender, check again this often (so turning it on shows up quickly). */
	const OFF_TTL = 5 * MINUTE_IN_SECONDS;

	/** After a failed fetch, wait this long before asking the app again. */
	const RETRY_AFTER = 10 * MINUTE_IN_SECONDS;

	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'render_loader' ), 100 );
		// A new API key may belong to another shop: fetch its widget key on next page view.
		add_action( 'update_option_lship_api_key', array( __CLASS__, 'forget_config' ) );
		add_action( 'update_option_lship_chat_enabled', array( __CLASS__, 'forget_config' ) );
	}

	/**
	 * @return bool
	 */
	public static function is_enabled() {
		return get_option( 'lship_chat_enabled', 'no' ) === 'yes'
			&& class_exists( Api::class )
			&& (string) get_option( 'lship_api_key', '' ) !== '';
	}

	/**
	 * Optional storefront brand-color override.
	 * Empty means the loader uses the SaaS widget appearance color.
	 *
	 * @return string Hex color or empty string.
	 */
	public static function brand_color() {
		$value = (string) get_option( 'lship_chat_color', '' );
		if ( 'brand' === $value ) {
			return class_exists( DesignSystem::class ) ? DesignSystem::get_brand() : '';
		}

		$color = sanitize_hex_color( $value );

		return $color ? $color : '';
	}

	/**
	 * Widget key, identity secret and loader URL for this store, cached locally.
	 *
	 * @return array{widget_key:string,identity_secret:string,loader_url:string,chat_enabled:bool}|null
	 */
	public static function widget_config() {
		// Keyed to the API key and server, so switching either fetches the right widget.
		$api_key_hash = md5( (string) get_option( 'lship_api_key', '' ) . '|' . Api::panel_url() );
		$cached       = get_option( self::CONFIG_OPTION );

		$fresh = is_array( $cached )
			&& ( $cached['api_key_hash'] ?? '' ) === $api_key_hash
			&& ( (int) ( $cached['fetched_at'] ?? 0 ) ) > time() - ( empty( $cached['chat_enabled'] ) ? self::OFF_TTL : self::CONFIG_TTL );
		if ( $fresh ) {
			return $cached;
		}

		if ( get_transient( 'ls_chat_widget_retry' ) ) {
			// The app was unreachable recently; keep using the last good config if it was for this key.
			return is_array( $cached ) && ( $cached['api_key_hash'] ?? '' ) === $api_key_hash ? $cached : null;
		}

		$response = Api::chat_widget_config();
		$data     = is_array( $response['data'] ?? null ) ? $response['data'] : array();

		if ( empty( $response['success'] ) || empty( $data['widget_key'] ) || empty( $data['loader_url'] ) ) {
			set_transient( 'ls_chat_widget_retry', 1, self::RETRY_AFTER );
			return is_array( $cached ) && ( $cached['api_key_hash'] ?? '' ) === $api_key_hash ? $cached : null;
		}

		$config = array(
			'widget_key'      => sanitize_text_field( (string) $data['widget_key'] ),
			'identity_secret' => (string) ( $data['identity_secret'] ?? '' ),
			'loader_url'      => esc_url_raw( (string) $data['loader_url'] ),
			'chat_enabled'    => ! empty( $data['chat_enabled'] ),
			'chat_status'     => sanitize_key( (string) ( $data['chat_status'] ?? ( empty( $data['chat_enabled'] ) ? 'turned_off' : 'ready' ) ) ),
			'api_key_hash'    => $api_key_hash,
			'fetched_at'      => time(),
		);
		// Not autoloaded: it holds the identity secret and is only needed on the storefront.
		update_option( self::CONFIG_OPTION, $config, false );

		return $config;
	}

	/**
	 * Fresh status for Settings → Live Chat.
	 *
	 * @return array{state: string, message: string, settings_url: string}
	 */
	public static function status() {
		$settings_url = Api::panel_url( 'app/chat/settings' );

		if ( get_option( 'lship_chat_enabled', 'no' ) !== 'yes' ) {
			return array( 'state' => 'off', 'message' => __( 'Live chat is off on this store.', 'licensesender' ), 'settings_url' => $settings_url );
		}
		if ( (string) get_option( 'lship_api_key', '' ) === '' ) {
			return array( 'state' => 'error', 'message' => __( 'Add your LicenseSender API key on the API tab first.', 'licensesender' ), 'settings_url' => $settings_url );
		}

		self::forget_config();
		$config = self::widget_config();

		if ( ! $config ) {
			return array( 'state' => 'error', 'message' => __( 'Could not load the chat from LicenseSender. Check that the API key is linked to this shop.', 'licensesender' ), 'settings_url' => $settings_url );
		}

		$messages = array(
			'ready'       => __( 'Live on your store.', 'licensesender' ),
			'turned_off'  => __( 'Live chat is turned off in LicenseSender. Turn it on under Live Chat → Settings; it appears here within a few minutes.', 'licensesender' ),
			'not_in_plan' => __( 'Your LicenseSender plan does not include live chat.', 'licensesender' ),
		);
		$state = ! empty( $config['chat_enabled'] ) ? 'ready' : (string) ( $config['chat_status'] ?? 'turned_off' );

		return array(
			'state'        => $state,
			'message'      => $messages[ $state ] ?? $messages['turned_off'],
			'settings_url' => $settings_url,
		);
	}

	public static function forget_config() {
		delete_option( self::CONFIG_OPTION );
		delete_transient( 'ls_chat_widget_retry' );
	}

	/**
	 * Signed identity for the logged-in customer, so the chat knows who they are without
	 * asking. The app recomputes the HMAC; a forged identity is ignored.
	 *
	 * @param array $config Widget config.
	 * @return array{id:string,name:string,email:string,hash:string}|null
	 */
	public static function identity( array $config ) {
		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() || ! is_email( $user->user_email ) || empty( $config['identity_secret'] ) ) {
			return null;
		}

		$id    = (string) $user->ID;
		$email = strtolower( trim( $user->user_email ) );

		return array(
			'id'    => $id,
			'name'  => (string) $user->display_name,
			'email' => $email,
			'hash'  => hash_hmac( 'sha256', $id . '|' . $email, (string) $config['identity_secret'] ),
		);
	}

	/**
	 * Prints the loader on storefront pages.
	 */
	public static function render_loader() {
		if ( is_admin() || wp_doing_ajax() || ! self::is_enabled() ) {
			return;
		}

		$config = self::widget_config();
		if ( ! $config || empty( $config['chat_enabled'] ) ) {
			return;
		}

		// Appearance (title, launcher, welcome, email gate) comes from SaaS
		// /api/widget/{key}/config. Only pass color when this shop overrides it.
		$settings = array(
			'key'    => $config['widget_key'],
			'locale' => str_replace( '_', '-', get_locale() ),
			'user'   => self::identity( $config ),
		);

		$color = self::brand_color();
		if ( $color !== '' ) {
			$settings['color'] = $color;
		}

		/**
		 * Filters the storefront chat settings before they are printed.
		 *
		 * @param array $settings Loader settings.
		 */
		$settings = apply_filters( 'licensesender_chat_settings', $settings );

		$loader = add_query_arg( 'v', LICENSESENDER_VERSION, $config['loader_url'] );

		printf(
			"<script id=\"lschat-settings\">window.LSChatSettings = %s;</script>\n<script id=\"lschat-loader\" src=\"%s\" async></script>\n",
			wp_json_encode( $settings, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ),
			esc_url( $loader )
		);
	}
}
