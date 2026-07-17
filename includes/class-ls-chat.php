<?php
/**
 * Sitewide AI chat widget (AJAX bridge to SaaS).
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Chat {

	const COOKIE_SESSION_ID = 'ls_chat_session_id';

	const COOKIE_TOKEN = 'ls_chat_session_token';

	const COOKIE_TTL = 86400; // 24 hours

	/**
	 * Boot hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_widget_root' ) );

		$actions = array(
			'ls_chat_start',
			'ls_chat_send',
			'ls_chat_poll',
			'ls_chat_escalate',
			'ls_chat_close',
			'ls_chat_feedback',
		);

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, 'ajax_dispatch' ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, 'ajax_dispatch' ) );
		}
	}

	/**
	 * @return bool
	 */
	public static function is_enabled() {
		return get_option( 'lship_chat_enabled', 'no' ) === 'yes'
			&& class_exists( 'Licensesender_Api' )
			&& (string) get_option( 'lship_api_key', '' ) !== '';
	}

	/**
	 * @return bool
	 */
	public static function requires_email() {
		return get_option( 'lship_chat_require_email', 'no' ) === 'yes';
	}

	/**
	 * @return string
	 */
	public static function welcome_message() {
		$stored = trim( (string) get_option( 'lship_chat_welcome', '' ) );
		if ( $stored !== '' ) {
			return $stored;
		}

		return __( 'Hi! How can we help you today?', 'licensesender' );
	}

	/**
	 * Widget brand color (chat override → design brand → default teal).
	 *
	 * @return string
	 */
	public static function brand_color() {
		$chat = sanitize_hex_color( (string) get_option( 'lship_chat_color', '' ) );
		if ( $chat ) {
			return $chat;
		}

		$brand = sanitize_hex_color( (string) get_option( 'ls_brand', '' ) );
		if ( $brand ) {
			return $brand;
		}

		$legacy = sanitize_hex_color( (string) get_option( 'lship_brand_color', '' ) );
		if ( $legacy ) {
			return $legacy;
		}

		return '#0f766e';
	}

	public static function enqueue_assets() {
		if ( is_admin() || ! self::is_enabled() ) {
			return;
		}

		$base = plugin_dir_url( dirname( __FILE__ ) ) . 'public/';
		$ver  = defined( 'LICENSESENDER_VERSION' ) ? LICENSESENDER_VERSION : '1.0.0';
		$css  = plugin_dir_path( dirname( __FILE__ ) ) . 'public/css/ls-chat.css';
		$js   = plugin_dir_path( dirname( __FILE__ ) ) . 'public/js/ls-chat.js';
		if ( file_exists( $css ) ) {
			$ver .= '.' . (string) filemtime( $css );
		} elseif ( file_exists( $js ) ) {
			$ver .= '.' . (string) filemtime( $js );
		}

		wp_enqueue_style(
			'ls-chat-font',
			'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700&display=swap',
			array(),
			null
		);
		wp_enqueue_style( 'ls-chat', $base . 'css/ls-chat.css', array( 'ls-chat-font' ), $ver );
		wp_enqueue_script( 'ls-chat', $base . 'js/ls-chat.js', array(), $ver, true );

		$user = wp_get_current_user();

		wp_localize_script(
			'ls-chat',
			'LSChat',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'ls_chat' ),
				'welcome'       => self::welcome_message(),
				'requireEmail'  => self::requires_email(),
				'brandColor'    => self::brand_color(),
				'pollInterval'  => 4000,
				'visitorName'   => $user && $user->exists() ? (string) $user->display_name : '',
				'visitorEmail'  => $user && $user->exists() ? (string) $user->user_email : '',
				'i18n'          => array(
					'title'           => __( 'Chat with us', 'licensesender' ),
					'agentTitle'      => __( 'Chat with us', 'licensesender' ),
					'agentRole'       => __( 'Online · usually replies in minutes', 'licensesender' ),
					'placeholder'     => __( 'Type here and press enter…', 'licensesender' ),
					'send'            => __( 'Send', 'licensesender' ),
					'escalate'        => __( 'Talk to a human', 'licensesender' ),
					'close'           => __( 'End this chat session', 'licensesender' ),
					'minimize'        => __( 'Close', 'licensesender' ),
					'menu'            => __( 'Menu', 'licensesender' ),
					'emailLabel'      => __( 'Email', 'licensesender' ),
					'nameLabel'       => __( 'Name', 'licensesender' ),
					'namePlaceholder' => __( 'Your name', 'licensesender' ),
					'emailPlaceholder'=> __( 'you@example.com', 'licensesender' ),
					'start'           => __( 'Start chat', 'licensesender' ),
					'error'           => __( 'Something went wrong. Please try again.', 'licensesender' ),
					'escalated'       => __( 'A support ticket was created. Our team will follow up by email.', 'licensesender' ),
					'closed'          => __( 'Chat ended. Thanks for reaching out!', 'licensesender' ),
					'emailRequired'   => __( 'Please enter your email to continue.', 'licensesender' ),
					'online'          => __( 'Online · usually replies in minutes', 'licensesender' ),
					'support'         => __( 'Customer support', 'licensesender' ),
					'gateHelp'        => __( 'Tell us who you are, then start chatting with our assistant.', 'licensesender' ),
					'today'           => __( 'Today', 'licensesender' ),
					'you'             => __( 'You', 'licensesender' ),
					'assistant'       => __( 'Assistant', 'licensesender' ),
					'agent'           => __( 'Support', 'licensesender' ),
					'powered'         => __( 'Powered by LicenseSender', 'licensesender' ),
					'defaultWelcome'  => __( 'Hi! How can we help you today?', 'licensesender' ),
				),
			)
		);
	}

	public static function render_widget_root() {
		if ( is_admin() || ! self::is_enabled() ) {
			return;
		}

		echo '<div id="ls-chat-root" class="ls-chat-root" aria-live="polite"></div>';
	}

	public static function ajax_dispatch() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['action'] ) ) : '';

		check_ajax_referer( 'ls_chat', 'nonce' );

		if ( ! self::is_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Chat is not enabled.', 'licensesender' ) ), 403 );
		}

		switch ( $action ) {
			case 'ls_chat_start':
				self::ajax_start();
				break;
			case 'ls_chat_send':
				self::ajax_send();
				break;
			case 'ls_chat_poll':
				self::ajax_poll();
				break;
			case 'ls_chat_escalate':
				self::ajax_escalate();
				break;
			case 'ls_chat_close':
				self::ajax_close();
				break;
			case 'ls_chat_feedback':
				self::ajax_feedback();
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Unknown action.', 'licensesender' ) ), 400 );
		}
	}

	protected static function ajax_start() {
		$existing = self::cookie_session();
		if ( $existing['id'] && $existing['token'] ) {
			$response = Licensesender_Api::chat_poll( $existing['id'], $existing['token'], 0 );
			if ( ! empty( $response['success'] ) ) {
				wp_send_json_success(
					array(
						'session_id'    => (int) $existing['id'],
						'resumed'       => true,
						'session'       => $response['data']['session'] ?? array(),
						'messages'      => $response['data']['messages'] ?? array(),
						'welcome'       => self::welcome_message(),
					)
				);
			}
			self::clear_cookies();
		}

		$name  = isset( $_POST['visitor_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['visitor_name'] ) ) : '';
		$email = isset( $_POST['visitor_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['visitor_email'] ) ) : '';

		if ( self::requires_email() && $email === '' ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your email to continue.', 'licensesender' ) ), 422 );
		}

		$user = wp_get_current_user();
		$payload = array(
			'visitor_name'  => $name !== '' ? $name : ( $user && $user->exists() ? $user->display_name : '' ),
			'visitor_email' => $email !== '' ? $email : ( $user && $user->exists() ? $user->user_email : '' ),
			'wp_user_id'    => $user && $user->exists() ? (string) $user->ID : '',
			'origin_url'    => isset( $_POST['origin_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['origin_url'] ) ) : home_url( '/' ),
			'locale'        => get_locale(),
			'website'       => home_url( '/' ),
		);

		$response = Licensesender_Api::chat_start( $payload );

		if ( empty( $response['success'] ) ) {
			wp_send_json_error(
				array(
					'message' => $response['message'] ?? __( 'Unable to start chat.', 'licensesender' ),
				),
				(int) ( $response['http_code'] ?? 400 )
			);
		}

		$data       = is_array( $response['data'] ?? null ) ? $response['data'] : array();
		$session_id = (int) ( $data['session_id'] ?? ( $data['session']['id'] ?? 0 ) );
		$token      = (string) ( $data['public_token'] ?? '' );

		if ( $session_id <= 0 || $token === '' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid chat session response.', 'licensesender' ) ), 500 );
		}

		self::set_cookies( $session_id, $token );

		wp_send_json_success(
			array(
				'session_id'      => $session_id,
				'resumed'         => false,
				'session'         => $data['session'] ?? array(),
				'welcome_message' => $data['welcome_message'] ?? null,
				'messages'        => ! empty( $data['welcome_message'] ) ? array( $data['welcome_message'] ) : array(),
			)
		);
	}

	protected static function ajax_send() {
		$session = self::require_session_cookies();
		$message = isset( $_POST['message'] ) ? trim( wp_unslash( (string) $_POST['message'] ) ) : '';

		if ( $message === '' ) {
			wp_send_json_error( array( 'message' => __( 'Message cannot be empty.', 'licensesender' ) ), 422 );
		}

		$payload = array(
			'message'       => $message,
			'visitor_name'  => isset( $_POST['visitor_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['visitor_name'] ) ) : '',
			'visitor_email' => isset( $_POST['visitor_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['visitor_email'] ) ) : '',
		);

		$response = Licensesender_Api::chat_send( $session['id'], $session['token'], $payload );

		if ( empty( $response['success'] ) ) {
			wp_send_json_error(
				array( 'message' => $response['message'] ?? __( 'Unable to send message.', 'licensesender' ) ),
				(int) ( $response['http_code'] ?? 400 )
			);
		}

		wp_send_json_success( $response['data'] ?? array() );
	}

	protected static function ajax_poll() {
		$session  = self::require_session_cookies();
		$since_id = isset( $_REQUEST['since_id'] ) ? absint( $_REQUEST['since_id'] ) : 0;

		$response = Licensesender_Api::chat_poll( $session['id'], $session['token'], $since_id );

		if ( empty( $response['success'] ) ) {
			wp_send_json_error(
				array( 'message' => $response['message'] ?? __( 'Unable to poll messages.', 'licensesender' ) ),
				(int) ( $response['http_code'] ?? 400 )
			);
		}

		wp_send_json_success( $response['data'] ?? array() );
	}

	protected static function ajax_escalate() {
		$session = self::require_session_cookies();

		$email = isset( $_POST['visitor_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['visitor_email'] ) ) : '';
		$name  = isset( $_POST['visitor_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['visitor_name'] ) ) : '';

		if ( $email === '' ) {
			$user = wp_get_current_user();
			$email = $user && $user->exists() ? (string) $user->user_email : '';
		}

		if ( $email === '' ) {
			wp_send_json_error( array( 'message' => __( 'An email address is required to reach a human.', 'licensesender' ) ), 422 );
		}

		$response = Licensesender_Api::chat_escalate(
			$session['id'],
			$session['token'],
			array(
				'visitor_email' => $email,
				'visitor_name'  => $name,
				'subject'       => isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['subject'] ) ) : '',
				'message'       => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['message'] ) ) : '',
			)
		);

		if ( empty( $response['success'] ) ) {
			wp_send_json_error(
				array( 'message' => $response['message'] ?? __( 'Unable to escalate chat.', 'licensesender' ) ),
				(int) ( $response['http_code'] ?? 400 )
			);
		}

		wp_send_json_success( $response['data'] ?? array() );
	}

	protected static function ajax_close() {
		$session = self::require_session_cookies();

		$response = Licensesender_Api::chat_close( $session['id'], $session['token'] );
		self::clear_cookies();

		if ( empty( $response['success'] ) ) {
			wp_send_json_error(
				array( 'message' => $response['message'] ?? __( 'Unable to close chat.', 'licensesender' ) ),
				(int) ( $response['http_code'] ?? 400 )
			);
		}

		wp_send_json_success( $response['data'] ?? array() );
	}

	protected static function ajax_feedback() {
		$session = self::require_session_cookies();
		$rating  = isset( $_POST['rating'] ) ? sanitize_key( wp_unslash( (string) $_POST['rating'] ) ) : '';

		$response = Licensesender_Api::chat_feedback(
			$session['id'],
			$session['token'],
			array(
				'rating'     => $rating,
				'message_id' => isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0,
			)
		);

		if ( empty( $response['success'] ) ) {
			wp_send_json_error(
				array( 'message' => $response['message'] ?? __( 'Unable to record feedback.', 'licensesender' ) ),
				(int) ( $response['http_code'] ?? 400 )
			);
		}

		wp_send_json_success( $response['data'] ?? array() );
	}

	/**
	 * @return array{id:int,token:string}
	 */
	protected static function cookie_session() {
		$id    = isset( $_COOKIE[ self::COOKIE_SESSION_ID ] ) ? absint( $_COOKIE[ self::COOKIE_SESSION_ID ] ) : 0;
		$token = isset( $_COOKIE[ self::COOKIE_TOKEN ] ) ? sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::COOKIE_TOKEN ] ) ) : '';

		return array(
			'id'    => $id,
			'token' => $token,
		);
	}

	/**
	 * @return array{id:int,token:string}
	 */
	protected static function require_session_cookies() {
		$session = self::cookie_session();

		if ( $session['id'] <= 0 || $session['token'] === '' ) {
			wp_send_json_error( array( 'message' => __( 'No active chat session.', 'licensesender' ) ), 400 );
		}

		return $session;
	}

	protected static function set_cookies( $session_id, $token ) {
		$expire   = time() + self::COOKIE_TTL;
		$secure   = is_ssl();
		$path     = COOKIEPATH ? COOKIEPATH : '/';
		$domain   = COOKIE_DOMAIN;
		$session_id = (int) $session_id;
		$token      = (string) $token;

		if ( PHP_VERSION_ID >= 70300 ) {
			$opts = array(
				'expires'  => $expire,
				'path'     => $path,
				'domain'   => $domain,
				'secure'   => $secure,
				'httponly' => true,
				'samesite' => 'Lax',
			);
			setcookie( self::COOKIE_SESSION_ID, (string) $session_id, $opts );
			setcookie( self::COOKIE_TOKEN, $token, $opts );
		} else {
			setcookie( self::COOKIE_SESSION_ID, (string) $session_id, $expire, $path, $domain, $secure, true );
			setcookie( self::COOKIE_TOKEN, $token, $expire, $path, $domain, $secure, true );
		}

		$_COOKIE[ self::COOKIE_SESSION_ID ] = (string) $session_id;
		$_COOKIE[ self::COOKIE_TOKEN ]      = $token;
	}

	protected static function clear_cookies() {
		$expire = time() - YEAR_IN_SECONDS;
		$path   = COOKIEPATH ? COOKIEPATH : '/';
		$domain = COOKIE_DOMAIN;

		setcookie( self::COOKIE_SESSION_ID, '', $expire, $path, $domain );
		setcookie( self::COOKIE_TOKEN, '', $expire, $path, $domain );
		unset( $_COOKIE[ self::COOKIE_SESSION_ID ], $_COOKIE[ self::COOKIE_TOKEN ] );
	}
}
