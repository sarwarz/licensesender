<?php

namespace LicenseSender\Support;

use LicenseSender\Core\Api;
use LicenseSender\Core\DesignSystem;

defined( 'ABSPATH' ) || exit;

class Shortcodes {

	private static $auth_redirect_url = '';
	private static $support_auth_active = false;

	public static function init() {
		add_shortcode( 'ls_support_open', array( __CLASS__, 'render_open' ) );
		add_shortcode( 'ls_support_manage', array( __CLASS__, 'render_manage' ) );

		add_action( 'wp_ajax_ls_support_create_ticket', array( __CLASS__, 'ajax_create_ticket' ) );
		add_action( 'wp_ajax_ls_support_list_tickets', array( __CLASS__, 'ajax_list_tickets' ) );
		add_action( 'wp_ajax_ls_support_get_conversation', array( __CLASS__, 'ajax_get_conversation' ) );
		add_action( 'wp_ajax_ls_support_reply_ticket', array( __CLASS__, 'ajax_reply_ticket' ) );
		add_action( 'wp_ajax_ls_support_order_keys', array( __CLASS__, 'ajax_order_keys' ) );
		add_action( 'wp_ajax_ls_support_attachment', array( Support::class, 'ajax_proxy_attachment' ) );

		add_action( 'woocommerce_login_form_start', array( __CLASS__, 'output_auth_redirect_field' ) );
		add_action( 'woocommerce_register_form_start', array( __CLASS__, 'output_auth_redirect_field' ) );
		add_filter( 'woocommerce_login_redirect', array( __CLASS__, 'filter_auth_redirect' ), 10, 2 );
		add_filter( 'woocommerce_registration_redirect', array( __CLASS__, 'filter_auth_redirect' ) );
		add_filter( 'option_woocommerce_enable_myaccount_registration', array( __CLASS__, 'enable_support_registration' ) );
	}

	public static function render_open( $atts = array() ) {
		$atts = shortcode_atts( array( 'theme' => '' ), $atts, 'ls_support_open' );
		self::enqueue_assets();
		ob_start();

		if ( ! Support::is_enabled() ) {
			self::render_disabled_notice();
			return ob_get_clean();
		}

		if ( ! is_user_logged_in() ) {
			self::render_auth_gate(
				__( 'Open a support ticket', 'licensesender' ),
				// Only mention creating an account when the store allows it.
				'yes' === get_option( 'woocommerce_enable_myaccount_registration' )
					? __( 'Log in or create an account to contact support.', 'licensesender' )
					: __( 'Log in to contact support.', 'licensesender' )
			);
			return ob_get_clean();
		}

		self::render_app(
			array(
				'view'  => 'open',
				'theme' => $atts['theme'],
			)
		);

		return ob_get_clean();
	}

	public static function render_manage( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'context' => '',
				'theme'   => '',
			),
			$atts,
			'ls_support_manage'
		);
		self::enqueue_assets();
		ob_start();

		if ( ! Support::is_enabled() ) {
			self::render_disabled_notice();
			return ob_get_clean();
		}

		if ( ! is_user_logged_in() ) {
			self::render_auth_gate(
				__( 'My support tickets', 'licensesender' ),
				__( 'Log in to view and reply to your support tickets.', 'licensesender' )
			);
			return ob_get_clean();
		}

		$ticket_number = isset( $_GET['ls_ticket'] ) ? sanitize_text_field( wp_unslash( $_GET['ls_ticket'] ) ) : '';
		if ( $ticket_number !== '' ) {
			// Email magic links carry an access token: claim the ticket before the app asks for it.
			$user = wp_get_current_user();
			Support::capture_access_token_from_request( $user->ID, $ticket_number, $user->user_email );
		}

		self::render_app(
			array(
				'view'    => 'manage',
				'ticket'  => $ticket_number,
				'context' => $atts['context'] === 'my_account' ? 'my_account' : '',
				'theme'   => $atts['theme'],
				'created' => ! empty( $_GET['ls_support_created'] ) ? '1' : '',
			)
		);

		return ob_get_clean();
	}

	/**
	 * Mount point for the React support app. The app renders into a Shadow DOM, so the
	 * placeholder below is only visible until it boots (or if it never does).
	 *
	 * @param array<string, string> $data data-* attributes for the instance.
	 */
	private static function render_app( array $data ) {
		self::enqueue_app();

		// theme="light|dark|auto" on the shortcode wins; otherwise Settings → Design → Colour mode.
		$data['theme'] = in_array( $data['theme'] ?? '', array( 'light', 'dark', 'auto' ), true ) ? $data['theme'] : DesignSystem::color_mode();

		$attrs = '';
		foreach ( $data as $key => $value ) {
			if ( (string) $value !== '' ) {
				$attrs .= ' data-' . esc_attr( $key ) . '="' . esc_attr( (string) $value ) . '"';
			}
		}

		?>
		<div class="ls-support-app alignwide"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>>
			<div class="ls-support-app-loading" role="status" style="padding:56px 16px;text-align:center;color:#64748b;font:14px/1.5 system-ui,-apple-system,'Segoe UI',sans-serif;">
				<?php esc_html_e( 'Loading support…', 'licensesender' ); ?>
			</div>
			<noscript>
				<p style="padding:16px;text-align:center;"><?php esc_html_e( 'Please enable JavaScript to use customer support.', 'licensesender' ); ?></p>
			</noscript>
		</div>
		<?php
		self::render_boot_watchdog();
	}

	/**
	 * Dependency-free failsafe: if the app script never boots (blocked by an optimizer,
	 * a JS error from another plugin, etc.), replace the loading text with a readable
	 * message so the customer is never stuck on "Loading…" forever.
	 */
	private static function render_boot_watchdog() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;

		$message = esc_js( __( 'The support app could not load on this page. Please reload the page or try again later.', 'licensesender' ) );
		$reload  = esc_js( __( 'Reload page', 'licensesender' ) );
		?>
		<script>
		(function () {
			setTimeout(function () {
				if (window.LSSupportBooted) {
					return;
				}
				var nodes = document.querySelectorAll('.ls-support-app .ls-support-app-loading');
				for (var i = 0; i < nodes.length; i++) {
					nodes[i].innerHTML = '<?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput ?>' +
						'<br><a href="javascript:window.location.reload()"><?php echo $reload; // phpcs:ignore WordPress.Security.EscapeOutput ?></a>';
				}
			}, 10000);
		})();
		</script>
		<?php
	}

	/**
	 * Enqueue the app bundle and its settings once per page.
	 */
	private static function enqueue_app() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		wp_enqueue_script( 'ls-support-app' );
		wp_add_inline_script(
			'ls-support-app',
			'window.LSSupportApp = ' . wp_json_encode( self::app_config() ) . ';',
			'before'
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function app_config() {
		$user   = wp_get_current_user();
		$tokens = class_exists( DesignSystem::class ) ? DesignSystem::resolve_tokens() : array();
		$limits = Support::get_upload_limits();

		$to_options = static function ( array $map ) {
			$options = array();
			foreach ( $map as $value => $label ) {
				$options[] = array(
					'value' => (string) $value,
					'label' => (string) $label,
				);
			}
			return $options;
		};

		// Customer-facing wording for the two "awaiting" states.
		$status_filters = Support::get_status_filter_options();
		$status_filters['awaiting_customer'] = __( 'Awaiting your reply', 'licensesender' );
		$status_filters['awaiting_staff']    = __( 'Awaiting support', 'licensesender' );

		return array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'ls_support' ),
			'manageUrl'     => Support::get_manage_page_url(),
			'openUrl'       => Support::get_open_page_url(),
			'perPage'       => 15,
			'brand'         => (string) ( $tokens['brand'] ?? '#4f46e5' ),
			'radiusCard'    => (string) ( $tokens['radius_card'] ?? '14px' ),
			'radiusControl' => (string) ( $tokens['radius_ctl'] ?? '10px' ),
			'user'          => array(
				'name'  => (string) ( $user->display_name ?: $user->user_login ),
				'email' => (string) $user->user_email,
			),
			'categories'    => $to_options( Support::get_categories() ),
			'priorities'    => $to_options( Support::get_priorities() ),
			'statusFilters' => $to_options( $status_filters ),
			'sortOptions'   => $to_options( Support::get_sort_options() ),
			// Logged-out visitors (sign-in screen) get none: customer_id 0 would match guest orders.
			'orders'        => ( $user->ID && function_exists( 'wc_get_orders' ) ) ? Support::get_customer_orders( $user ) : array(),
			'limits'        => array(
				'maxFileMb'  => $limits['max_mb'],
				'maxFiles'   => $limits['max_files'],
				'maxTotalMb' => $limits['max_total_mb'],
				'maxSubject' => Support::MAX_SUBJECT_LENGTH,
				'maxMessage' => Support::MAX_MESSAGE_LENGTH,
				'extensions' => $limits['extensions'],
			),
			'i18n'          => self::app_strings(),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function app_strings() {
		return array(
			'attach'                => __( 'Attach', 'licensesender' ),
			'benefitReplies'        => __( 'Get replies here and by email', 'licensesender' ),
			'benefitScreens'        => __( 'Attach screenshots of the problem', 'licensesender' ),
			'benefitTrack'          => __( 'Track all your tickets in one place', 'licensesender' ),
			'attachFiles'           => __( 'Attach files', 'licensesender' ),
			'attachments'           => __( 'Attachments', 'licensesender' ),
			'awaitingYouHint'       => __( 'Our team replied and is waiting for your answer.', 'licensesender' ),
			'backToTickets'         => __( 'All tickets', 'licensesender' ),
			'category'              => __( 'Category', 'licensesender' ),
			'clearFilters'          => __( 'Clear filters', 'licensesender' ),
			'clearSearch'           => __( 'Clear search', 'licensesender' ),
			'clickToUpload'         => __( 'Upload images', 'licensesender' ),
			'close'                 => __( 'Close', 'licensesender' ),
			'closedTitle'           => __( 'This ticket is closed', 'licensesender' ),
			'created'               => __( 'Created', 'licensesender' ),
			'createdNotice'         => __( 'Your ticket was created. We will reply here and by email.', 'licensesender' ),
			'createdTitle'          => __( 'Ticket submitted', 'licensesender' ),
			'createAccount'         => __( 'Create an account', 'licensesender' ),
			'description'           => __( 'Description', 'licensesender' ),
			'descriptionPlaceholder' => __( 'What happened? Include any error messages and the steps to reproduce it.', 'licensesender' ),
			'descriptionRequired'   => __( 'Describe the problem so we can help.', 'licensesender' ),
			'dismiss'               => __( 'Dismiss', 'licensesender' ),
			'download'              => __( 'Download', 'licensesender' ),
			'dropFiles'             => __( 'Drop files to attach', 'licensesender' ),
			'error'                 => __( 'Something went wrong. Please try again.', 'licensesender' ),
			/* translators: %d: max file size in MB */
			'fileLimits'            => __( 'PNG, JPG, GIF or WEBP · up to %d MB each', 'licensesender' ),
			/* translators: 1: file name, 2: max size in MB */
			'fileTooLarge'          => __( '"%s" is larger than %d MB.', 'licensesender' ),
			/* translators: %s: file name */
			'fileType'              => __( '"%s" is not an image. You can attach PNG, JPG, GIF or WEBP files.', 'licensesender' ),
			'lastUpdate'            => __( 'Last update', 'licensesender' ),
			'licenseKey'            => __( 'License key', 'licensesender' ),
			'loadFailed'            => __( 'Could not load your tickets', 'licensesender' ),
			'loadingTicket'         => __( 'Loading ticket…', 'licensesender' ),
			'logIn'                 => __( 'Log in', 'licensesender' ),
			'loadingKeys'           => __( 'Loading license keys…', 'licensesender' ),
			'myTickets'             => __( 'Support tickets', 'licensesender' ),
			'myTicketsLead'         => __( 'Track your requests and continue conversations with our team.', 'licensesender' ),
			'myTicketsLink'         => __( 'My tickets', 'licensesender' ),
			'networkError'          => __( 'Could not reach the server. Check your connection and try again.', 'licensesender' ),
			'newTicket'             => __( 'New ticket', 'licensesender' ),
			'newTicketLead'         => __( 'Tell us what went wrong and our team will get back to you as soon as possible.', 'licensesender' ),
			'newTicketTitle'        => __( 'Open a support ticket', 'licensesender' ),
			'nextPage'              => __( 'Next page', 'licensesender' ),
			'noKeys'                => __( 'No license keys on this order', 'licensesender' ),
			'noKey'                 => __( 'No specific key', 'licensesender' ),
			'noMatches'             => __( 'No tickets match your filters', 'licensesender' ),
			'noMatchesHint'         => __( 'Try a different search term or status.', 'licensesender' ),
			'noMessages'            => __( 'No messages yet.', 'licensesender' ),
			'noOrder'               => __( 'Not about a specific order', 'licensesender' ),
			'noTicketsHint'         => __( 'When you need help with an order or license, open a ticket and we will follow up here.', 'licensesender' ),
			'noTicketsTitle'        => __( 'No tickets yet', 'licensesender' ),
			'openNewTicket'         => __( 'Open a new ticket', 'licensesender' ),
			'optional'              => __( 'Optional', 'licensesender' ),
			'orDragDrop'            => __( 'or drag and drop', 'licensesender' ),
			'order'                 => __( 'Order', 'licensesender' ),
			/* translators: %s: order number */
			'orderShort'            => __( 'Order %s', 'licensesender' ),
			'prevPage'              => __( 'Previous page', 'licensesender' ),
			'priority'              => __( 'Priority', 'licensesender' ),
			'refresh'               => __( 'Refresh', 'licensesender' ),
			/* translators: %s: file name */
			'removeFile'            => __( 'Remove %s', 'licensesender' ),
			'replyLabel'            => __( 'Your reply', 'licensesender' ),
			'replyPlaceholder'      => __( 'Write your reply…', 'licensesender' ),
			'replyRequired'         => __( 'Write a message before sending.', 'licensesender' ),
			'search'                => __( 'Search', 'licensesender' ),
			'searchTickets'         => __( 'Search by subject, ticket or order number…', 'licensesender' ),
			'selectKey'             => __( 'Choose a license key', 'licensesender' ),
			'selectOrderFirst'      => __( 'Choose an order first', 'licensesender' ),
			'sendReply'             => __( 'Send reply', 'licensesender' ),
			'sendShortcut'          => __( 'Ctrl + Enter to send', 'licensesender' ),
			'sessionExpired'        => __( 'Your session has expired. Please reload the page.', 'licensesender' ),
			'showLess'              => __( 'Show less', 'licensesender' ),
			'showMore'              => __( 'Show full message', 'licensesender' ),
			'signInLead'            => __( 'Log in to open a ticket and follow the conversation with our team.', 'licensesender' ),
			'signInNote'            => __( "You'll sign in securely on our account page, then come straight back here.", 'licensesender' ),
			'signInTitle'           => __( 'Customer support', 'licensesender' ),
			'sortBy'                => __( 'Sort by', 'licensesender' ),
			'staff'                 => __( 'Staff', 'licensesender' ),
			'status'                => __( 'Status', 'licensesender' ),
			'statusAwaitingSupport' => __( 'Awaiting support', 'licensesender' ),
			'statusAwaitingYou'     => __( 'Awaiting your reply', 'licensesender' ),
			'statusOpen'            => __( 'Open', 'licensesender' ),
			'subject'               => __( 'Subject', 'licensesender' ),
			'subjectPlaceholder'    => __( 'e.g. My license key says it is already in use', 'licensesender' ),
			'subjectRequired'       => __( 'Add a short subject.', 'licensesender' ),
			'submitFailed'          => __( 'Your ticket was not sent', 'licensesender' ),
			'submitTicket'          => __( 'Submit ticket', 'licensesender' ),
			'submitted'             => __( 'Submitted', 'licensesender' ),
			/* translators: %s: customer name */
			'submittingAs'          => __( 'Submitting as %s', 'licensesender' ),
			'supportName'           => __( 'Support team', 'licensesender' ),
			'ticketClosed'          => __( 'This conversation can no longer receive replies. Open a new ticket if you still need help.', 'licensesender' ),
			'ticketLoadFailed'      => __( 'This ticket could not be opened', 'licensesender' ),
			/* translators: 1: first row, 2: last row, 3: total tickets */
			'ticketRange'           => __( '%d–%d of %d tickets', 'licensesender' ),
			/* translators: %d: max number of files */
			'tooManyFiles'          => __( 'You can attach up to %d files.', 'licensesender' ),
			/* translators: %d: max total size in MB */
			'totalTooLarge'         => __( 'Attachments can be %d MB in total.', 'licensesender' ),
			'tryAgain'              => __( 'Try again', 'licensesender' ),
			'untitled'              => __( 'Untitled ticket', 'licensesender' ),
			/* translators: %s: relative time, e.g. "2 hours ago" */
			'updatedAgo'            => __( 'Updated %s', 'licensesender' ),
			'viewOnly'              => __( 'You can read this ticket here. To reply, use the link in your ticket confirmation email.', 'licensesender' ),
			'viewOnlyTitle'         => __( 'Replies are not available', 'licensesender' ),
			'copyNumber'            => __( 'Copy ticket number', 'licensesender' ),
			/* translators: %s: date the ticket was opened */
			'openedOn'              => __( 'Opened %s', 'licensesender' ),
			'ticketDetails'         => __( 'Ticket details', 'licensesender' ),
			'conversation'          => __( 'Conversation', 'licensesender' ),
			'oneMessage'            => __( '1 message', 'licensesender' ),
			/* translators: %d: number of messages */
			'messagesCount'         => __( '%d messages', 'licensesender' ),
			'messageTooLong'        => __( 'Your message is too long. Please shorten it or split it into two replies.', 'licensesender' ),
			'newestFirst'           => __( 'Newest first', 'licensesender' ),
			'oldestFirst'           => __( 'Oldest first', 'licensesender' ),
			'you'                   => __( 'You', 'licensesender' ),
		);
	}

	public static function ajax_create_ticket() {
		self::verify_ajax();

		$user = wp_get_current_user();
		$files = Support::normalize_uploaded_files();
		$validated = Support::validate_uploads( $files );
		if ( is_wp_error( $validated ) ) {
			wp_send_json_error( array( 'message' => $validated->get_error_message() ), 400 );
		}

		$subject = Support::validate_subject( wp_unslash( $_POST['subject'] ?? '' ) );
		if ( is_wp_error( $subject ) ) {
			wp_send_json_error( array( 'message' => $subject->get_error_message() ), 400 );
		}

		$message = Support::validate_message_content( wp_unslash( $_POST['message'] ?? '' ) );
		if ( is_wp_error( $message ) ) {
			wp_send_json_error( array( 'message' => $message->get_error_message() ), 400 );
		}

		$category = Support::sanitize_category( wp_unslash( $_POST['category'] ?? 'general' ) );
		$priority = Support::sanitize_priority( wp_unslash( $_POST['priority'] ?? 'normal' ) );

		// Order and key must belong to this customer, so staff are never asked to act on someone else's purchase.
		$order = Support::resolve_customer_order( $user->ID, wp_unslash( $_POST['order_id'] ?? '' ) );
		if ( is_wp_error( $order ) ) {
			wp_send_json_error( array( 'message' => $order->get_error_message() ), 400 );
		}
		$order_id = $order ? (string) $order->get_order_number() : '';

		$license_key = Support::validate_license_key_for_order(
			$user->ID,
			$order ? $order->get_id() : 0,
			wp_unslash( $_POST['license_key'] ?? '' )
		);
		if ( is_wp_error( $license_key ) ) {
			wp_send_json_error( array( 'message' => $license_key->get_error_message() ), 400 );
		}

		$allowed = Support::rate_limit( $user->ID, 'create' );
		if ( is_wp_error( $allowed ) ) {
			wp_send_json_error( array( 'message' => $allowed->get_error_message() ), 429 );
		}

		$result = Api::create_support_ticket(
			array(
				'customer_name'  => $user->display_name ?: $user->user_login,
				'customer_email' => $user->user_email,
				'subject'        => $subject,
				'message'        => $message,
				'category'       => $category,
				'priority'       => $priority,
				'order_id'       => $order_id,
				'license_key'    => $license_key,
			),
			is_array( $validated ) ? $validated : array()
		);

		if ( empty( $result['success'] ) ) {
			wp_send_json_error(
				array( 'message' => self::customer_error_message( $result, __( 'Could not create ticket.', 'licensesender' ) ) ),
				400
			);
		}

		$ticket_payload = is_array( $result['ticket'] ?? null ) ? $result['ticket'] : array();
		$result_data    = is_array( $result['data'] ?? null ) ? $result['data'] : array();
		$result_meta    = is_array( $result['meta'] ?? null ) ? $result['meta'] : array();
		$ticket         = array_merge( $result_meta, $result_data, $ticket_payload );
		$number         = Support::extract_ticket_number( $ticket );
		$token          = Support::extract_access_token( $ticket );

		if ( $token === '' ) {
			$token = Support::extract_access_token( $result );
		}

		if ( $number !== '' ) {
			$save_data = array(
				'subject'        => $subject,
				'status'         => (string) ( $ticket['status'] ?? 'open' ),
				'category'       => $category,
				'priority'       => $priority,
				'order_id'       => $order_id !== '' ? $order_id : Support::extract_ticket_order_id( $ticket ),
				'customer_email' => $user->user_email,
				'created_at'     => Support::extract_ticket_created_at( $ticket ) ?: current_time( 'mysql' ),
				'updated_at'     => Support::extract_ticket_updated_at( $ticket ) ?: current_time( 'mysql' ),
			);

			if ( $token !== '' ) {
				$save_data['access_token'] = $token;
			}

			Support::save_ticket_access( $user->ID, $number, $save_data );
		}

		$redirect_url = Support::get_ticket_url( $number );
		if ( $redirect_url === '' ) {
			$redirect_url = Support::get_manage_page_url();
		}
		if ( $redirect_url === '' ) {
			$redirect_url = wp_get_referer() ?: get_permalink();
		}
		if ( ! $redirect_url ) {
			$redirect_url = home_url( '/' );
		}

		wp_send_json_success(
			array(
				'message'       => (string) ( $result['message'] ?? __( 'Ticket created successfully.', 'licensesender' ) ),
				'ticket_number' => $number,
				'redirect_url'  => add_query_arg( 'ls_support_created', '1', $redirect_url ),
			)
		);
	}

	public static function ajax_list_tickets() {
		self::verify_ajax();

		$user   = wp_get_current_user();
		$result = Support::list_customer_tickets(
			$user->ID,
			$user->user_email,
			array(
				'search'     => wp_unslash( $_POST['search'] ?? '' ),
				'status'     => wp_unslash( $_POST['status'] ?? '' ),
				'sort_by'    => wp_unslash( $_POST['sort_by'] ?? 'updated_at' ),
				'sort_order' => wp_unslash( $_POST['sort_order'] ?? 'desc' ),
				'page'       => absint( $_POST['page'] ?? 1 ),
				'per_page'   => absint( $_POST['per_page'] ?? 20 ),
			)
		);

		wp_send_json_success( $result );
	}

	public static function ajax_get_conversation() {
		self::verify_ajax();

		$user          = wp_get_current_user();
		$ticket_number = sanitize_text_field( wp_unslash( $_POST['ticket_number'] ?? '' ) );

		if ( $ticket_number === '' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid ticket.', 'licensesender' ) ), 400 );
		}

		Support::capture_access_token_from_post( $user->ID, $ticket_number, $user->user_email );

		if ( ! Support::customer_owns_ticket( $user->ID, $user->user_email, $ticket_number ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have access to this ticket.', 'licensesender' ) ), 403 );
		}

		$result = Support::get_customer_conversation( $user->ID, $user->user_email, $ticket_number );
		if ( empty( $result['success'] ) ) {
			wp_send_json_error(
				array( 'message' => self::customer_error_message( $result, __( 'Could not load conversation.', 'licensesender' ) ) ),
				self::error_status( $result )
			);
		}

		wp_send_json_success(
			array(
				'ticket_number' => $ticket_number,
				'subject'       => (string) ( $result['subject'] ?? '' ),
				'ticket'        => $result['ticket'] ?? array(),
				'messages'      => $result['messages'] ?? array(),
				'can_reply'     => ! empty( $result['can_reply'] ),
			)
		);
	}

	public static function ajax_reply_ticket() {
		self::verify_ajax();

		$user          = wp_get_current_user();
		$ticket_number = sanitize_text_field( wp_unslash( $_POST['ticket_number'] ?? '' ) );

		if ( $ticket_number === '' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid ticket.', 'licensesender' ) ), 400 );
		}

		Support::capture_access_token_from_post( $user->ID, $ticket_number, $user->user_email );

		if ( ! Support::customer_owns_ticket( $user->ID, $user->user_email, $ticket_number ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have access to this ticket.', 'licensesender' ) ), 403 );
		}

		$message = Support::validate_message_content( wp_unslash( $_POST['message'] ?? '' ) );
		if ( is_wp_error( $message ) ) {
			wp_send_json_error( array( 'message' => $message->get_error_message() ), 400 );
		}

		$files     = Support::normalize_uploaded_files();
		$validated = Support::validate_uploads( $files );
		if ( is_wp_error( $validated ) ) {
			wp_send_json_error( array( 'message' => $validated->get_error_message() ), 400 );
		}

		$allowed = Support::rate_limit( $user->ID, 'reply' );
		if ( is_wp_error( $allowed ) ) {
			wp_send_json_error( array( 'message' => $allowed->get_error_message() ), 429 );
		}

		$result = Support::reply_customer_ticket(
			$user->ID,
			$user->user_email,
			$ticket_number,
			$message,
			is_array( $validated ) ? $validated : array()
		);

		if ( empty( $result['success'] ) ) {
			wp_send_json_error(
				array( 'message' => self::customer_error_message( $result, __( 'Could not send reply.', 'licensesender' ) ) ),
				self::error_status( $result )
			);
		}

		$conversation = Support::get_customer_conversation( $user->ID, $user->user_email, $ticket_number );
		if ( empty( $conversation['success'] ) ) {
			wp_send_json_success(
				array(
					'message'       => (string) ( $result['message'] ?? __( 'Reply sent.', 'licensesender' ) ),
					'reload_ticket' => $ticket_number,
				)
			);
		}

		Support::sync_ticket_activity(
			$user->ID,
			$ticket_number,
			$conversation['messages'] ?? array(),
			is_array( $conversation['ticket'] ?? null ) ? $conversation['ticket'] : array()
		);

		wp_send_json_success(
			array(
				'message'   => (string) ( $result['message'] ?? __( 'Reply sent.', 'licensesender' ) ),
				'ticket'    => $conversation['ticket'] ?? array(),
				'messages'  => $conversation['messages'] ?? array(),
				'can_reply' => ! empty( $conversation['can_reply'] ),
			)
		);
	}

	public static function ajax_order_keys() {
		self::verify_ajax();

		$user     = wp_get_current_user();
		$order_id = absint( $_POST['order_id'] ?? 0 );
		$keys     = Support::get_order_license_keys( $user->ID, $order_id );

		wp_send_json_success( array( 'keys' => $keys ) );
	}

	private static function verify_ajax() {
		check_ajax_referer( 'ls_support', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'licensesender' ) ), 401 );
		}

		if ( ! Support::is_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Support tickets are currently disabled.', 'licensesender' ) ), 403 );
		}
	}

	/**
	 * HTTP status for a failed help desk call. Transport failures report 0 and the API can
	 * answer 401/422 about the store's own key, which the customer's browser should not see
	 * as "you are logged out" — map those to a plain server error.
	 *
	 * @param array<string, mixed> $result API/plugin result.
	 * @return int
	 */
	private static function error_status( array $result ) {
		$status = (int) ( $result['http_code'] ?? 0 );
		if ( in_array( $status, array( 400, 403, 404, 409, 413, 422, 429 ), true ) ) {
			return $status;
		}
		return $status >= 500 && $status <= 599 ? $status : 502;
	}

	/**
	 * Customer-safe error text (never leak raw SaaS/transport details).
	 *
	 * @param array<string, mixed> $result API/plugin result.
	 * @param string               $fallback Fallback message.
	 * @return string
	 */
	private static function customer_error_message( array $result, $fallback = '' ) {
		$fallback = $fallback !== '' ? (string) $fallback : __( 'Something went wrong. Please try again.', 'licensesender' );
		$message  = trim( (string) ( $result['message'] ?? '' ) );

		if ( $message === '' ) {
			return $fallback;
		}

		$allowed_fragments = array(
			'you do not have access',
			'invalid ticket',
			'closed',
			'cannot receive',
			'attach',
			'upload',
			'unsupported',
			'too large',
			'required',
			'missing',
			'disabled',
			'logged in',
			'reply',
			'ticket cannot',
			'unable to load',
			'no longer exists',
			'refresh',
		);

		$lower = strtolower( $message );
		foreach ( $allowed_fragments as $fragment ) {
			if ( str_contains( $lower, $fragment ) ) {
				return wp_strip_all_tags( $message );
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[licensesender support] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return $fallback;
	}

	/**
	 * Styles for the PHP-rendered screens (login gate, disabled notice). The app itself
	 * carries its own CSS inside its Shadow DOM.
	 */
	private static function enqueue_assets() {
		wp_enqueue_style( 'ls-support' );
	}

	/**
	 * Whether support auth should use WooCommerce My Account (captcha-safe).
	 * Default: yes. Set option to "no" to embed login/register forms on the support page.
	 */
	private static function use_my_account_auth() {
		return 'yes' === get_option( 'lship_support_auth_my_account', 'yes' );
	}

	private static function render_auth_gate( $title, $lead ) {
		$redirect_url = get_permalink();
		if ( ! $redirect_url ) {
			$redirect_url = home_url( '/' );
		}

		// Default mode: the app shows a sign-in screen that links to My Account. The embedded
		// WooCommerce forms (setting off) stay server-rendered so captcha plugins keep working.
		$urls = self::use_my_account_auth() ? self::get_auth_urls( $redirect_url ) : null;
		if ( $urls ) {
			self::render_app(
				array(
					'view'     => 'auth',
					'title'    => $title,
					'lead'     => $lead,
					'login'    => $urls['login'],
					'register' => $urls['register'],
				)
			);
			return;
		}

		echo '<div class="ls-support-wrap alignwide">';
		echo '<div class="ls-support-card ls-support-auth-card">';
		echo '<h2 class="ls-support-title">' . esc_html( $title ) . '</h2>';
		echo '<p class="ls-support-lead">' . esc_html( $lead ) . '</p>';
		if ( function_exists( 'woocommerce_output_all_notices' ) ) {
			woocommerce_output_all_notices();
		}

		if ( self::use_my_account_auth() ) {
			self::render_my_account_auth_cta( $redirect_url );
		} else {
			self::render_wc_auth_section( $redirect_url );
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * My Account login/register links that bring the customer back to this page.
	 *
	 * @param string $redirect_url Page to return to.
	 * @return array{login: string, register: string}|null Null when WooCommerce or its account page is missing.
	 */
	private static function get_auth_urls( $redirect_url ) {
		if ( ! function_exists( 'wc_get_page_permalink' ) ) {
			return null;
		}

		$account_url = wc_get_page_permalink( 'myaccount' );
		if ( ! $account_url ) {
			return null;
		}

		// Use a same-site relative path only. Full https:// URLs in query strings
		// are often blocked by ModSecurity / Cloudflare / security plugins (HTTP 403).
		$login_url = add_query_arg(
			array(
				'ls_support_redirect' => self::to_relative_path( $redirect_url ),
			),
			$account_url
		);

		return array(
			'login'    => $login_url,
			// Many themes/plugins open the register panel with ?action=register.
			'register' => 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) ? add_query_arg( 'action', 'register', $login_url ) : '',
		);
	}

	/**
	 * Send visitors to WooCommerce My Account so site captcha plugins work.
	 * Only used when the app can't render the sign-in screen (WooCommerce/account page missing).
	 */
	private static function render_my_account_auth_cta( $redirect_url ) {
		if ( ! function_exists( 'wc_get_page_permalink' ) ) {
			echo '<p>' . esc_html__( 'WooCommerce is required for customer login.', 'licensesender' ) . '</p>';
			return;
		}

		$urls = self::get_auth_urls( $redirect_url );
		if ( ! $urls ) {
			echo '<p>' . esc_html__( 'Please set a WooCommerce My Account page, then try again.', 'licensesender' ) . '</p>';
			return;
		}

		echo '<div class="ls-support-auth-cta">';
		echo '<p class="ls-support-auth-cta-help">' . esc_html__( 'You will sign in on the store account page (where captcha and store security apply), then return here.', 'licensesender' ) . '</p>';
		echo '<div class="ls-support-auth-cta-actions">';
		echo '<a class="button ls-support-btn-primary" href="' . esc_url( $urls['login'] ) . '">' . esc_html__( 'Log in', 'licensesender' ) . '</a>';
		if ( $urls['register'] !== '' ) {
			echo '<a class="button ls-support-btn-secondary" href="' . esc_url( $urls['register'] ) . '">' . esc_html__( 'Create account', 'licensesender' ) . '</a>';
		}
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Convert an absolute same-site URL to a relative path (path + query + fragment).
	 */
	private static function to_relative_path( $url ) {
		$url = (string) $url;
		if ( $url === '' ) {
			return '/';
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return '/';
		}

		// Already relative.
		if ( empty( $parts['host'] ) && ! empty( $parts['path'] ) && str_starts_with( $parts['path'], '/' ) ) {
			$path = $parts['path'];
			if ( ! empty( $parts['query'] ) ) {
				$path .= '?' . $parts['query'];
			}
			if ( ! empty( $parts['fragment'] ) ) {
				$path .= '#' . $parts['fragment'];
			}
			return $path;
		}

		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! empty( $parts['host'] ) && $home_host && strcasecmp( (string) $parts['host'], (string) $home_host ) !== 0 ) {
			// Foreign host — refuse and fall back to home.
			return '/';
		}

		$path = isset( $parts['path'] ) && $parts['path'] !== '' ? $parts['path'] : '/';
		if ( ! str_starts_with( $path, '/' ) ) {
			$path = '/' . $path;
		}
		if ( ! empty( $parts['query'] ) ) {
			$path .= '?' . $parts['query'];
		}
		if ( ! empty( $parts['fragment'] ) ) {
			$path .= '#' . $parts['fragment'];
		}

		return $path;
	}

	private static function render_wc_auth_section( $redirect_url ) {
		if ( ! function_exists( 'wc_get_template' ) ) {
			echo '<p>' . esc_html__( 'WooCommerce is required for customer login.', 'licensesender' ) . '</p>';
			return;
		}

		$is_register               = self::is_register_auth_view();
		self::$auth_redirect_url   = $redirect_url;
		self::$support_auth_active = true;

		echo '<div class="ls-support-wc-auth">';
		if ( $is_register ) {
			self::load_support_template( 'form-register.php' );
			echo '<p class="ls-support-auth-switch">';
			esc_html_e( 'Already have an account?', 'licensesender' );
			echo ' <a href="' . esc_url( $redirect_url ) . '">' . esc_html__( 'Log in', 'licensesender' ) . '</a>';
			echo '</p>';
		} else {
			self::load_support_template( 'form-login.php' );
			echo '<p class="ls-support-auth-switch">';
			esc_html_e( "Don't have an account?", 'licensesender' );
			echo ' <a href="' . esc_url( add_query_arg( 'ls_auth', 'register', $redirect_url ) ) . '">' . esc_html__( 'Register', 'licensesender' ) . '</a>';
			echo '</p>';
		}
		echo '</div>';

		self::$auth_redirect_url   = '';
		self::$support_auth_active = false;
	}

	/**
	 * Resolve a safe support-page return URL from the My Account query string.
	 */
	private static function get_support_redirect_from_request() {
		if ( empty( $_REQUEST['ls_support_redirect'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '';
		}

		$raw = wp_unslash( $_REQUEST['ls_support_redirect'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_string( $raw ) || $raw === '' ) {
			return '';
		}

		// Tolerate a single extra encode from older links.
		if ( str_contains( $raw, '%' ) && ! str_contains( $raw, '://' ) && ! str_starts_with( $raw, '/' ) ) {
			$raw = rawurldecode( $raw );
		}

		// Prefer relative paths (WAF-safe). Absolute same-site URLs still accepted.
		if ( str_starts_with( $raw, '/' ) ) {
			$absolute = home_url( $raw );
			$validated = wp_validate_redirect( $absolute, false );
			return $validated ? $validated : '';
		}

		$raw = esc_url_raw( $raw );
		if ( $raw === '' ) {
			return '';
		}

		$validated = wp_validate_redirect( $raw, false );
		return $validated ? $validated : '';
	}

	private static function load_support_template( $template ) {
		$path = LICENSESENDER_PATH . 'templates/wholesale/' . $template;
		if ( file_exists( $path ) ) {
			include $path;
		}
	}

	/**
	 * ?ls_auth=register only counts on a support page, so it cannot be used to
	 * switch on registration elsewhere (e.g. /my-account/) when the store disabled it.
	 */
	private static function is_register_auth_view() {
		return isset( $_GET['ls_auth'] )
			&& sanitize_key( wp_unslash( $_GET['ls_auth'] ) ) === 'register'
			&& ls_request_page_has_shortcode( array( 'ls_support_open', 'ls_support_manage' ) );
	}

	private static function is_support_registration_request() {
		if ( empty( $_POST['register'] ) ) {
			return false;
		}

		return ls_request_page_has_shortcode( array( 'ls_support_open', 'ls_support_manage' ) );
	}

	public static function enable_support_registration( $value ) {
		if ( self::$support_auth_active || self::is_register_auth_view() || self::is_support_registration_request() ) {
			return 'yes';
		}

		return $value;
	}

	public static function output_auth_redirect_field() {
		$url = self::$auth_redirect_url;
		if ( $url === '' ) {
			$url = self::get_support_redirect_from_request();
		}
		if ( $url === '' ) {
			return;
		}
		echo '<input type="hidden" name="redirect" value="' . esc_url( $url ) . '" />';
		echo '<input type="hidden" name="ls_support_redirect" value="' . esc_attr( $url ) . '" />';
	}

	public static function filter_auth_redirect( $redirect, $user = null ) {
		unset( $user );

		if ( ! empty( $_POST['redirect'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$target = wp_validate_redirect( wp_unslash( $_POST['redirect'] ), false ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
			if ( $target ) {
				return $target;
			}
		}

		$from_query = self::get_support_redirect_from_request();
		if ( $from_query ) {
			return $from_query;
		}

		return $redirect;
	}

	private static function render_disabled_notice() {
		echo '<div class="ls-support-wrap alignwide"><div class="ls-support-card ls-support-notice ls-support-notice-error">';
		echo '<strong>' . esc_html__( 'Support unavailable', 'licensesender' ) . '</strong>';
		echo '<p>' . esc_html__( 'Customer support is currently disabled. Please contact the store administrator.', 'licensesender' ) . '</p>';
		echo '</div></div>';
	}

	private static function render_notice( $type, $title, $message ) {
		$type = in_array( $type, array( 'success', 'error' ), true ) ? $type : 'success';
		$icon = 'success' === $type ? '✓' : '!';
		?>
		<div class="ls-support-notice ls-support-notice-<?php echo esc_attr( $type ); ?>" role="status">
			<span class="ls-support-notice-icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></span>
			<div class="ls-support-notice-body">
				<strong class="ls-support-notice-title"><?php echo esc_html( $title ); ?></strong>
				<p class="ls-support-notice-text"><?php echo esc_html( $message ); ?></p>
			</div>
		</div>
		<?php
	}
}
