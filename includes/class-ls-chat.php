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
			'ls_chat_typing',
			'ls_chat_escalate',
			'ls_chat_handoff',
			'ls_chat_broadcast_bootstrap',
			'ls_chat_broadcast_auth',
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

	/**
	 * Floating launcher style: icon | label.
	 *
	 * @return string
	 */
	public static function launcher_style() {
		$style = sanitize_key( (string) get_option( 'lship_chat_launcher_style', 'icon' ) );
		return in_array( $style, array( 'icon', 'label' ), true ) ? $style : 'icon';
	}

	/**
	 * Widget header title shown before a chat starts.
	 *
	 * @return string
	 */
	public static function widget_title() {
		$custom = trim( (string) get_option( 'lship_chat_title', '' ) );
		if ( $custom !== '' ) {
			return $custom;
		}

		return __( 'Chat with us', 'licensesender' );
	}

	/**
	 * Header subtitle — personalized when the visitor is logged in.
	 *
	 * @param \WP_User|null $user Current user.
	 * @return string
	 */
	public static function widget_subtitle( $user = null ) {
		if ( ! $user instanceof WP_User ) {
			$user = wp_get_current_user();
		}

		if ( $user && $user->exists() && $user->display_name ) {
			/* translators: %s: visitor display name */
			return sprintf( __( 'Hi %s — we typically reply in a few minutes', 'licensesender' ), $user->display_name );
		}

		return __( 'Online · typically replies in a few minutes', 'licensesender' );
	}

	public static function enqueue_assets() {
		if ( is_admin() || ! self::is_enabled() ) {
			return;
		}

		$base    = plugin_dir_url( dirname( __FILE__ ) ) . 'public/';
		$base_ver = defined( 'LICENSESENDER_VERSION' ) ? LICENSESENDER_VERSION : '1.0.0';
		$css     = plugin_dir_path( dirname( __FILE__ ) ) . 'public/css/ls-chat.css';
		$js      = plugin_dir_path( dirname( __FILE__ ) ) . 'public/js/ls-chat.js';
		$css_ver = $base_ver . ( file_exists( $css ) ? '.' . (string) filemtime( $css ) : '' );
		$js_ver  = $base_ver . ( file_exists( $js ) ? '.' . (string) filemtime( $js ) : '' );

		wp_enqueue_style(
			'ls-chat-font',
			'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700&display=swap',
			array(),
			null
		);
		wp_enqueue_style( 'ls-chat', $base . 'css/ls-chat.css', array( 'ls-chat-font' ), $css_ver );
		wp_enqueue_script(
			'pusher-js',
			'https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js',
			array(),
			'8.4.0',
			true
		);
		wp_enqueue_script(
			'laravel-echo',
			'https://cdn.jsdelivr.net/npm/laravel-echo@1.19.0/dist/echo.iife.js',
			array( 'pusher-js' ),
			'1.19.0',
			true
		);
		wp_enqueue_script( 'ls-chat', $base . 'js/ls-chat.js', array( 'laravel-echo' ), $js_ver, true );

		$user  = wp_get_current_user();
		$title = self::widget_title();

		wp_localize_script(
			'ls-chat',
			'LSChat',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'ls_chat' ),
				'welcome'       => self::welcome_message(),
				'requireEmail'  => self::requires_email(),
				'brandColor'    => self::brand_color(),
				'launcherStyle' => self::launcher_style(),
				'pollInterval'  => 4000,
				'visitorName'   => $user && $user->exists() ? (string) $user->display_name : '',
				'visitorEmail'  => $user && $user->exists() ? (string) $user->user_email : '',
				'i18n'          => array(
					'title'           => $title,
					'agentTitle'      => __( 'AI Assistant', 'licensesender' ),
					'agentRole'       => self::widget_subtitle( $user ),
					'aiRole'          => __( 'Automated support · Online', 'licensesender' ),
					'humanRole'       => __( 'Human support agent · Online', 'licensesender' ),
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
					'escalated'       => __( 'Connecting you with a support agent. Please wait…', 'licensesender' ),
					'waitingAgent'    => __( 'Waiting for an agent…', 'licensesender' ),
					'agentJoined'     => __( 'An agent has joined the chat.', 'licensesender' ),
					'ticketFallback'  => __( 'No agent was available. A support ticket was created — we will email you.', 'licensesender' ),
					'closed'          => __( 'Chat ended. Thanks for reaching out!', 'licensesender' ),
					'offline'         => __( 'Reconnecting… messages will sync when you are back online.', 'licensesender' ),
					'emailRequired'   => __( 'Please enter your email to continue.', 'licensesender' ),
					'online'          => self::widget_subtitle( $user ),
					'support'         => __( 'Customer support', 'licensesender' ),
					'gateHelp'        => __( 'Tell us who you are, then start chatting with our assistant.', 'licensesender' ),
					'today'           => __( 'Today', 'licensesender' ),
					'you'             => __( 'You', 'licensesender' ),
					'assistant'       => __( 'AI Assistant', 'licensesender' ),
					'agent'           => __( 'Support', 'licensesender' ),
					'system'          => __( 'System', 'licensesender' ),
					'powered'         => __( 'Powered by LicenseSender', 'licensesender' ),
					'defaultWelcome'  => __( 'Hi! How can we help you today?', 'licensesender' ),
					'launcherLabel'   => $title,
					'attach'          => __( 'Attach images', 'licensesender' ),
					'onlyImages'      => __( 'Only image attachments are allowed (JPG, PNG, GIF, WebP).', 'licensesender' ),
					/* translators: %s: file name */
					'imageTooLarge'   => __( '%s is larger than 5 MB.', 'licensesender' ),
					'maxImages'       => __( 'You can attach up to 5 images.', 'licensesender' ),
					'remove'          => __( 'Remove', 'licensesender' ),
					'isTyping'        => __( 'is typing', 'licensesender' ),
					'aiTyping'        => __( 'AI Assistant is typing', 'licensesender' ),
					'aiResumed'       => __( 'The AI assistant is back to help you.', 'licensesender' ),
					'restoring'       => __( 'Opening your chat…', 'licensesender' ),
					'openOriginal'    => __( 'Open', 'licensesender' ),
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
			case 'ls_chat_typing':
				self::ajax_typing();
				break;
			case 'ls_chat_escalate':
			case 'ls_chat_handoff':
				self::ajax_escalate();
				break;
			case 'ls_chat_broadcast_bootstrap':
				self::ajax_broadcast_bootstrap();
				break;
			case 'ls_chat_broadcast_auth':
				self::ajax_broadcast_auth();
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
		$resume_only = ! empty( $_POST['resume_only'] );
		$existing    = self::cookie_session();

		if ( $existing['id'] && $existing['token'] ) {
			$response = Licensesender_Api::chat_poll( $existing['id'], $existing['token'], 0 );
			$resumed_session = is_array( $response['data']['session'] ?? null ) ? $response['data']['session'] : array();

			// Only resume sessions that are still open; otherwise start fresh.
			if ( ! empty( $response['success'] ) && ( $resumed_session['status'] ?? '' ) !== 'closed' ) {
				wp_send_json_success(
					array(
						'session_id'    => (int) $existing['id'],
						'resumed'       => true,
						'session'       => $resumed_session,
						'messages'      => $response['data']['messages'] ?? array(),
						'welcome'       => self::welcome_message(),
					)
				);
			}
			self::clear_cookies();
		}

		// Soft resume check (on open/refresh) — do not create a new session.
		if ( $resume_only ) {
			wp_send_json_error( array( 'message' => __( 'No active chat session.', 'licensesender' ) ), 404 );
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
		$files   = LS_Support::normalize_uploaded_files();

		if ( $message === '' && $files === array() ) {
			wp_send_json_error( array( 'message' => __( 'Enter a message or attach a file.', 'licensesender' ) ), 422 );
		}

		if ( count( $files ) > 5 ) {
			wp_send_json_error( array( 'message' => __( 'You can attach up to 5 files.', 'licensesender' ) ), 422 );
		}

		$allowed_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' );
		foreach ( $files as $file ) {
			if ( ! empty( $file['error'] ) ) {
				wp_send_json_error( array( 'message' => __( 'The image could not be uploaded. Please try again.', 'licensesender' ) ), 422 );
			}

			if ( (int) ( $file['size'] ?? 0 ) > 5 * MB_IN_BYTES ) {
				wp_send_json_error( array( 'message' => __( 'Each image must be 5 MB or smaller.', 'licensesender' ) ), 422 );
			}

			$extension = strtolower( pathinfo( (string) ( $file['name'] ?? '' ), PATHINFO_EXTENSION ) );
			if ( ! in_array( $extension, $allowed_extensions, true ) ) {
				wp_send_json_error( array( 'message' => __( 'Only image attachments are allowed (JPG, PNG, GIF, WebP).', 'licensesender' ) ), 422 );
			}

			// Verify the real content type, not just the extension.
			$check = wp_check_filetype_and_ext(
				(string) ( $file['tmp_name'] ?? '' ),
				(string) ( $file['name'] ?? '' ),
				array(
					'jpg|jpeg' => 'image/jpeg',
					'png'      => 'image/png',
					'gif'      => 'image/gif',
					'webp'     => 'image/webp',
				)
			);
			if ( empty( $check['type'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Only image attachments are allowed (JPG, PNG, GIF, WebP).', 'licensesender' ) ), 422 );
			}
		}

		$client_message_id = isset( $_POST['client_message_id'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['client_message_id'] ) )
			: '';

		$payload = array(
			'message'       => $message,
			'visitor_name'  => isset( $_POST['visitor_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['visitor_name'] ) ) : '',
			'visitor_email' => isset( $_POST['visitor_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['visitor_email'] ) ) : '',
		);

		if ( $client_message_id !== '' ) {
			$payload['client_message_id'] = substr( $client_message_id, 0, 64 );
		}

		$response = Licensesender_Api::chat_send( $session['id'], $session['token'], $payload, $files );

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

	protected static function ajax_typing() {
		$session   = self::require_session_cookies();
		$is_typing = ! empty( $_POST['is_typing'] ) && (string) $_POST['is_typing'] !== '0';

		$response = Licensesender_Api::chat_typing(
			$session['id'],
			$session['token'],
			array(
				'is_typing'    => $is_typing ? 1 : 0,
				'visitor_name' => isset( $_POST['visitor_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['visitor_name'] ) ) : '',
			)
		);

		if ( empty( $response['success'] ) ) {
			wp_send_json_error(
				array( 'message' => $response['message'] ?? __( 'Unable to update typing state.', 'licensesender' ) ),
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

		$response = Licensesender_Api::chat_handoff(
			$session['id'],
			$session['token'],
			array(
				'visitor_email' => $email,
				'visitor_name'  => $name,
			)
		);

		if ( empty( $response['success'] ) ) {
			wp_send_json_error(
				array( 'message' => $response['message'] ?? __( 'Unable to request a human agent.', 'licensesender' ) ),
				(int) ( $response['http_code'] ?? 400 )
			);
		}

		wp_send_json_success( $response['data'] ?? array() );
	}

	/**
	 * Issue visitor broadcast bootstrap (Reverb config + short-lived credential).
	 * API key never leaves WordPress.
	 */
	protected static function ajax_broadcast_bootstrap() {
		$session = self::require_session_cookies();

		$response = Licensesender_Api::chat_broadcast_credential( $session['id'], $session['token'] );

		if ( empty( $response['success'] ) ) {
			wp_send_json_error(
				array( 'message' => $response['message'] ?? __( 'Realtime is unavailable.', 'licensesender' ) ),
				(int) ( $response['http_code'] ?? 400 )
			);
		}

		$data = is_array( $response['data'] ?? null ) ? $response['data'] : array();
		$data['auth_endpoint'] = admin_url( 'admin-ajax.php' );
		$data['auth_action']   = 'ls_chat_broadcast_auth';
		$data['nonce']         = wp_create_nonce( 'ls_chat' );

		wp_send_json_success( $data );
	}

	/**
	 * Proxy Pusher/Reverb private channel auth for the visitor credential.
	 */
	protected static function ajax_broadcast_auth() {
		$session = self::require_session_cookies();

		$channel    = isset( $_POST['channel_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['channel_name'] ) ) : '';
		$socket_id  = isset( $_POST['socket_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['socket_id'] ) ) : '';
		$credential = isset( $_POST['credential'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['credential'] ) ) : '';

		if ( $channel === '' || $socket_id === '' || $credential === '' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid broadcast auth request.', 'licensesender' ) ), 422 );
		}

		$response = Licensesender_Api::chat_broadcast_auth(
			$session['token'],
			array(
				'channel_name' => $channel,
				'socket_id'    => $socket_id,
				'credential'   => $credential,
				'session_id'   => $session['id'],
			)
		);

		if ( empty( $response['success'] ) ) {
			wp_send_json_error(
				array( 'message' => $response['message'] ?? __( 'Broadcast authorization failed.', 'licensesender' ) ),
				(int) ( $response['http_code'] ?? 403 )
			);
		}

		$auth = $response['data']['auth'] ?? ( $response['auth'] ?? '' );
		if ( $auth === '' ) {
			wp_send_json_error( array( 'message' => __( 'Broadcast authorization failed.', 'licensesender' ) ), 500 );
		}

		// Echo / Pusher client expects a raw { auth: "..." } payload.
		wp_send_json( array( 'auth' => $auth ) );
	}

	protected static function ajax_close() {
		$session = self::require_session_cookies();

		$response  = Licensesender_Api::chat_close( $session['id'], $session['token'] );
		$http_code = (int) ( $response['http_code'] ?? 400 );

		// Keep cookies on transient failures so the visitor can retry;
		// clear them when the close succeeded or the session is already gone/invalid.
		if ( ! empty( $response['success'] ) || in_array( $http_code, array( 401, 403, 404, 410, 422 ), true ) ) {
			self::clear_cookies();
		}

		if ( empty( $response['success'] ) ) {
			wp_send_json_error(
				array( 'message' => $response['message'] ?? __( 'Unable to close chat.', 'licensesender' ) ),
				$http_code
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
			// Session id is readable by JS so the widget can skip a resume round-trip
			// when there is no active session. The secret token stays HttpOnly.
			setcookie(
				self::COOKIE_SESSION_ID,
				(string) $session_id,
				array(
					'expires'  => $expire,
					'path'     => $path,
					'domain'   => $domain,
					'secure'   => $secure,
					'httponly' => false,
					'samesite' => 'Lax',
				)
			);
			setcookie(
				self::COOKIE_TOKEN,
				$token,
				array(
					'expires'  => $expire,
					'path'     => $path,
					'domain'   => $domain,
					'secure'   => $secure,
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		} else {
			setcookie( self::COOKIE_SESSION_ID, (string) $session_id, $expire, $path, $domain, $secure, false );
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
