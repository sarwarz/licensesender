<?php
/**
 * Customer support shortcodes.
 *
 * Shortcodes:
 * - [ls_support_open]    Open a new support ticket
 * - [ls_support_manage]  View and reply to tickets
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Support_Shortcodes {

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

		add_action( 'woocommerce_login_form_start', array( __CLASS__, 'output_auth_redirect_field' ) );
		add_action( 'woocommerce_register_form_start', array( __CLASS__, 'output_auth_redirect_field' ) );
		add_filter( 'woocommerce_login_redirect', array( __CLASS__, 'filter_auth_redirect' ), 10, 2 );
		add_filter( 'woocommerce_registration_redirect', array( __CLASS__, 'filter_auth_redirect' ) );
		add_filter( 'option_woocommerce_enable_myaccount_registration', array( __CLASS__, 'enable_support_registration' ) );
	}

	public static function render_open( $atts = array() ) {
		unset( $atts );
		self::enqueue_assets();
		ob_start();

		if ( ! LS_Support::is_enabled() ) {
			self::render_disabled_notice();
			return ob_get_clean();
		}

		if ( ! is_user_logged_in() ) {
			self::render_auth_gate(
				__( 'Open a support ticket', 'licensesender' ),
				__( 'Log in or create an account to contact support.', 'licensesender' )
			);
			return ob_get_clean();
		}

		$user   = wp_get_current_user();
		$orders = LS_Support::get_customer_orders( $user );

		?>
		<div class="ls-support-wrap alignwide ls-support-open-wrap" id="ls-support-open">
			<div class="ls-support-open-shell">
				<header class="ls-support-open-hero">
					<div class="ls-support-open-hero-copy">
						<p class="ls-support-lead"><?php esc_html_e( 'Tell us what went wrong and our team will help you as soon as possible.', 'licensesender' ); ?></p>
					</div>
					<?php
					$manage_url = LS_Support::get_manage_page_url();
					if ( $manage_url ) :
						?>
						<a class="button ls-support-btn-secondary ls-support-open-back" href="<?php echo esc_url( $manage_url ); ?>">
							<?php esc_html_e( 'My tickets', 'licensesender' ); ?>
						</a>
					<?php endif; ?>
				</header>

				<div class="ls-support-card ls-support-open-card">
					<form class="ls-support-form" id="ls-support-open-form" enctype="multipart/form-data">
						<section class="ls-support-form-section">
							<div class="ls-support-section-head">
								<h3><?php esc_html_e( 'Request details', 'licensesender' ); ?></h3>
								<p><?php esc_html_e( 'A clear subject and category help us route your ticket faster.', 'licensesender' ); ?></p>
							</div>

							<div class="ls-support-field">
								<label for="ls-support-subject"><?php esc_html_e( 'Subject', 'licensesender' ); ?> <span class="required">*</span></label>
								<input type="text" id="ls-support-subject" name="subject" required maxlength="200" placeholder="<?php esc_attr_e( 'Brief summary of the issue', 'licensesender' ); ?>" />
							</div>

							<div class="ls-support-grid">
								<div class="ls-support-field">
									<label for="ls-support-category"><?php esc_html_e( 'Category', 'licensesender' ); ?></label>
									<div class="ls-support-select-wrap">
										<select id="ls-support-category" name="category" class="ls-support-select">
											<?php foreach ( LS_Support::get_categories() as $value => $label ) : ?>
												<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
										<span class="ls-support-select-caret" aria-hidden="true"></span>
									</div>
								</div>
								<div class="ls-support-field">
									<label for="ls-support-priority"><?php esc_html_e( 'Priority', 'licensesender' ); ?></label>
									<div class="ls-support-select-wrap">
										<select id="ls-support-priority" name="priority" class="ls-support-select">
											<?php foreach ( LS_Support::get_priorities() as $value => $label ) : ?>
												<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, 'normal' ); ?>><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
										<span class="ls-support-select-caret" aria-hidden="true"></span>
									</div>
								</div>
							</div>
						</section>

						<section class="ls-support-form-section">
							<div class="ls-support-section-head">
								<h3><?php esc_html_e( 'Order context', 'licensesender' ); ?></h3>
								<p><?php esc_html_e( 'Optional, but linking an order or license key speeds up resolution.', 'licensesender' ); ?></p>
							</div>

							<div class="ls-support-grid">
								<div class="ls-support-field">
									<label for="ls-support-order"><?php esc_html_e( 'Related order', 'licensesender' ); ?> <span class="ls-support-optional"><?php esc_html_e( 'optional', 'licensesender' ); ?></span></label>
									<div class="ls-support-select-wrap">
										<select id="ls-support-order" name="order_id" class="ls-support-select">
											<option value=""><?php esc_html_e( 'Select an order', 'licensesender' ); ?></option>
											<?php foreach ( $orders as $order ) : ?>
												<option value="<?php echo esc_attr( (string) $order['id'] ); ?>"><?php echo esc_html( $order['label'] ); ?></option>
											<?php endforeach; ?>
										</select>
										<span class="ls-support-select-caret" aria-hidden="true"></span>
									</div>
								</div>
								<div class="ls-support-field">
									<label for="ls-support-license-key"><?php esc_html_e( 'License key', 'licensesender' ); ?> <span class="ls-support-optional"><?php esc_html_e( 'optional', 'licensesender' ); ?></span></label>
									<div class="ls-support-select-wrap">
										<select id="ls-support-license-key" name="license_key" class="ls-support-select" disabled>
											<option value=""><?php esc_html_e( 'Select order first', 'licensesender' ); ?></option>
										</select>
										<span class="ls-support-select-caret" aria-hidden="true"></span>
									</div>
								</div>
							</div>
						</section>

						<section class="ls-support-form-section">
							<div class="ls-support-section-head">
								<h3><?php esc_html_e( 'Message', 'licensesender' ); ?></h3>
								<p><?php esc_html_e( 'Include error messages, steps to reproduce, or anything else we should know.', 'licensesender' ); ?></p>
							</div>

							<div class="ls-support-field">
								<label for="ls-support-message"><?php esc_html_e( 'Description', 'licensesender' ); ?> <span class="required">*</span></label>
								<textarea id="ls-support-message" name="message" rows="7" required maxlength="10000" placeholder="<?php esc_attr_e( 'Describe the problem in detail…', 'licensesender' ); ?>"></textarea>
							</div>

							<div class="ls-support-field ls-support-attach-field">
								<span class="ls-support-field-label-text"><?php esc_html_e( 'Attachments', 'licensesender' ); ?> <span class="ls-support-optional"><?php esc_html_e( 'optional', 'licensesender' ); ?></span></span>
								<div class="ls-support-open-attach">
									<input type="file" class="ls-support-file-input" id="ls-support-attachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.zip" />
									<button type="button" class="button ls-support-btn-secondary ls-support-attach-link"><?php esc_html_e( 'Attach files', 'licensesender' ); ?></button>
									<span class="ls-support-attach-names" aria-live="polite"><?php esc_html_e( 'No files selected', 'licensesender' ); ?></span>
								</div>
								<p class="ls-support-help"><?php esc_html_e( 'Images, PDF, TXT, or ZIP up to 5 MB each.', 'licensesender' ); ?></p>
							</div>
						</section>

						<div class="ls-support-actions ls-support-open-actions">
							<button type="submit" class="button ls-support-btn-primary"><?php esc_html_e( 'Submit ticket', 'licensesender' ); ?></button>
							<div class="ls-support-form-message" aria-live="polite"></div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	public static function render_manage( $atts = array() ) {
		unset( $atts );
		ob_start();

		if ( ! LS_Support::is_enabled() ) {
			self::enqueue_assets();
			self::render_disabled_notice();
			return ob_get_clean();
		}

		if ( ! is_user_logged_in() ) {
			self::enqueue_assets();
			self::render_auth_gate(
				__( 'My support tickets', 'licensesender' ),
				__( 'Log in to view and reply to your support tickets.', 'licensesender' )
			);
			return ob_get_clean();
		}

		$ticket_number = isset( $_GET['ls_ticket'] ) ? sanitize_text_field( wp_unslash( $_GET['ls_ticket'] ) ) : '';
		self::enqueue_assets( $ticket_number !== '' );
		if ( $ticket_number !== '' ) {
			self::render_ticket_view( $ticket_number );
			return ob_get_clean();
		}

		?>
		<div class="ls-support-wrap alignwide ls-support-manage-wrap" id="ls-support-manage">
			<div class="ls-support-manage-shell">
				<?php if ( ! empty( $_GET['ls_support_created'] ) ) : ?>
					<?php
					self::render_notice(
						'success',
						__( 'Ticket submitted', 'licensesender' ),
						__( 'Your support ticket was created. You can track it below.', 'licensesender' )
					);
					?>
				<?php endif; ?>

				<header class="ls-support-manage-hero">
					<div class="ls-support-manage-hero-copy">
						<p class="ls-support-lead"><?php esc_html_e( 'Track requests, read replies, and continue conversations with our team.', 'licensesender' ); ?></p>
					</div>
					<?php
					$open_url = LS_Support::get_open_page_url();
					if ( $open_url ) :
						?>
						<a class="button ls-support-btn-primary ls-support-manage-cta" href="<?php echo esc_url( $open_url ); ?>">
							<?php esc_html_e( 'Open a ticket', 'licensesender' ); ?>
						</a>
					<?php endif; ?>
				</header>

				<div class="ls-support-card ls-support-manage-card">
					<div class="ls-support-manage-toolbar">
						<div class="ls-support-toolbar-main">
							<div class="ls-support-search-wrap">
								<label class="ls-support-field-label" for="ls-support-search"><?php esc_html_e( 'Search', 'licensesender' ); ?></label>
								<div class="ls-support-search-control">
									<span class="ls-support-search-icon" aria-hidden="true">
										<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
											<path d="M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke="currentColor" stroke-width="2"/>
											<path d="M16.5 16.5 21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
										</svg>
									</span>
									<input type="search" id="ls-support-search" class="ls-support-search-input" placeholder="<?php esc_attr_e( 'Search by ID, subject, or order…', 'licensesender' ); ?>" autocomplete="off" />
								</div>
							</div>

							<div class="ls-support-toolbar-controls">
								<div class="ls-support-filter-field">
									<label class="ls-support-field-label" for="ls-support-filter-status"><?php esc_html_e( 'Status', 'licensesender' ); ?></label>
									<select id="ls-support-filter-status" class="ls-support-filter-select" aria-label="<?php esc_attr_e( 'Status', 'licensesender' ); ?>">
										<?php foreach ( LS_Support::get_status_filter_options() as $value => $label ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="ls-support-filter-field">
									<label class="ls-support-field-label" for="ls-support-filter-sort"><?php esc_html_e( 'Sort', 'licensesender' ); ?></label>
									<select id="ls-support-filter-sort" class="ls-support-filter-select" aria-label="<?php esc_attr_e( 'Sort by', 'licensesender' ); ?>">
										<?php foreach ( LS_Support::get_sort_options() as $value => $label ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, 'updated_at' ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="ls-support-toolbar-actions">
									<span class="ls-support-field-label ls-support-field-label-spacer" aria-hidden="true">&nbsp;</span>
									<div class="ls-support-toolbar-action-row">
										<button type="button" class="button ls-support-btn-apply" id="ls-support-apply-filters"><?php esc_html_e( 'Apply', 'licensesender' ); ?></button>
										<button type="button" class="button ls-support-btn-secondary ls-support-btn-refresh" id="ls-support-refresh"><?php esc_html_e( 'Refresh', 'licensesender' ); ?></button>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="ls-support-table-scroll">
						<table class="ls-support-table" id="ls-support-tickets-table">
							<colgroup>
								<col class="ls-support-col-ticket" />
								<col class="ls-support-col-priority" />
								<col class="ls-support-col-category" />
								<col class="ls-support-col-status" />
								<col class="ls-support-col-order" />
								<col class="ls-support-col-date" />
							</colgroup>
							<thead>
								<tr>
									<th scope="col" class="ls-support-col-ticket"><?php esc_html_e( 'Ticket', 'licensesender' ); ?></th>
									<th scope="col" class="ls-support-col-priority"><?php esc_html_e( 'Priority', 'licensesender' ); ?></th>
									<th scope="col" class="ls-support-col-category"><?php esc_html_e( 'Category', 'licensesender' ); ?></th>
									<th scope="col" class="ls-support-col-status"><?php esc_html_e( 'Status', 'licensesender' ); ?></th>
									<th scope="col" class="ls-support-col-order"><?php esc_html_e( 'Order', 'licensesender' ); ?></th>
									<th scope="col" class="ls-support-col-date"><?php esc_html_e( 'Updated', 'licensesender' ); ?></th>
								</tr>
							</thead>
							<tbody id="ls-support-ticket-list">
								<tr>
									<td colspan="6" class="ls-support-table-empty">
										<div class="ls-support-empty-state is-loading">
											<span class="ls-support-loading-dots" aria-hidden="true"></span>
											<p><?php esc_html_e( 'Loading tickets…', 'licensesender' ); ?></p>
										</div>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
					<div class="ls-support-table-footer">
						<div class="ls-support-pagination" id="ls-support-pagination" aria-live="polite"></div>
					</div>
				</div>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	private static function render_ticket_view( $ticket_number ) {
		$user          = wp_get_current_user();
		$ticket_number = sanitize_text_field( (string) $ticket_number );
		$back_url      = LS_Support::get_manage_page_url();

		// Claim via access token before ownership check (email-magic-link deep links).
		LS_Support::capture_access_token_from_request( $user->ID, $ticket_number, $user->user_email );

		if ( ! LS_Support::customer_owns_ticket( $user->ID, $user->user_email, $ticket_number ) ) {
			echo '<div class="ls-support-wrap alignwide"><div class="ls-support-card ls-support-notice ls-support-notice-error">';
			echo '<strong>' . esc_html__( 'Ticket not found', 'licensesender' ) . '</strong>';
			echo '<p>' . esc_html__( 'This ticket does not exist or you do not have permission to view it.', 'licensesender' ) . '</p>';
			if ( $back_url ) {
				echo '<p><a class="ls-support-back-link" href="' . esc_url( $back_url ) . '">' . esc_html__( 'Back to ticket list', 'licensesender' ) . '</a></p>';
			}
			echo '</div></div>';
			return;
		}

		?>
		<div class="ls-support-wrap alignwide ls-support-ticket-wrap" id="ls-support-ticket-view" data-ticket="<?php echo esc_attr( $ticket_number ); ?>">
			<div class="ls-support-ticket-shell">
				<?php if ( ! empty( $_GET['ls_support_created'] ) ) : ?>
					<?php
					self::render_notice(
						'success',
						__( 'Ticket submitted', 'licensesender' ),
						__( 'Your support ticket was created. You can continue the conversation below.', 'licensesender' )
					);
					?>
				<?php endif; ?>

				<div class="ls-support-ticket-nav">
					<?php if ( $back_url ) : ?>
						<a class="button ls-support-btn-secondary ls-support-back-btn" href="<?php echo esc_url( $back_url ); ?>">
							<span aria-hidden="true">&larr;</span>
							<?php esc_html_e( 'Back to ticket list', 'licensesender' ); ?>
						</a>
					<?php else : ?>
						<span></span>
					<?php endif; ?>
					<button type="button" class="button ls-support-btn-secondary" id="ls-support-ticket-refresh"><?php esc_html_e( 'Refresh', 'licensesender' ); ?></button>
				</div>

				<div class="ls-support-card ls-support-ticket-card">
					<div class="ls-support-ticket-page is-loading">
						<div id="ls-support-ticket-header" class="ls-support-ticket-header" hidden></div>
						<div class="ls-support-ticket-main">
							<div id="ls-support-ticket-content">
								<div class="ls-support-loading-panel" role="status" aria-live="polite">
									<div class="ls-support-empty-state is-loading">
										<span class="ls-support-loading-dots" aria-hidden="true"></span>
										<p><?php esc_html_e( 'Loading ticket…', 'licensesender' ); ?></p>
									</div>
								</div>
							</div>
						</div>
						<aside class="ls-support-ticket-sidebar" id="ls-support-ticket-sidebar" aria-live="polite" hidden></aside>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public static function ajax_create_ticket() {
		self::verify_ajax();

		$user = wp_get_current_user();
		$files = LS_Support::normalize_uploaded_files();
		$validated = LS_Support::validate_uploads( $files );
		if ( is_wp_error( $validated ) ) {
			wp_send_json_error( array( 'message' => $validated->get_error_message() ), 400 );
		}

		$subject = LS_Support::validate_subject( wp_unslash( $_POST['subject'] ?? '' ) );
		if ( is_wp_error( $subject ) ) {
			wp_send_json_error( array( 'message' => $subject->get_error_message() ), 400 );
		}

		$message = LS_Support::validate_message_content( wp_unslash( $_POST['message'] ?? '' ) );
		if ( is_wp_error( $message ) ) {
			wp_send_json_error( array( 'message' => $message->get_error_message() ), 400 );
		}

		$category = LS_Support::sanitize_category( wp_unslash( $_POST['category'] ?? 'general' ) );
		$priority = LS_Support::sanitize_priority( wp_unslash( $_POST['priority'] ?? 'normal' ) );

		$order_id = absint( $_POST['order_id'] ?? 0 );
		if ( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order || (int) $order->get_user_id() !== (int) $user->ID ) {
				wp_send_json_error( array( 'message' => __( 'Invalid order selected.', 'licensesender' ) ), 400 );
			}
		}

		$license_key = LS_Support::validate_license_key_for_order( $user->ID, $order_id, wp_unslash( $_POST['license_key'] ?? '' ) );
		if ( is_wp_error( $license_key ) ) {
			wp_send_json_error( array( 'message' => $license_key->get_error_message() ), 400 );
		}

		$result = Licensesender_Api::create_support_ticket(
			array(
				'customer_name'  => $user->display_name ?: $user->user_login,
				'customer_email' => $user->user_email,
				'subject'        => $subject,
				'message'        => $message,
				'category'       => $category,
				'priority'       => $priority,
				'order_id'       => $order_id ? (string) $order_id : '',
				'license_key'    => $license_key,
			),
			is_array( $validated ) ? $validated : array()
		);

		if ( empty( $result['success'] ) ) {
			wp_send_json_error(
				array( 'message' => (string) ( $result['message'] ?? __( 'Could not create ticket.', 'licensesender' ) ) ),
				400
			);
		}

		$ticket_payload = is_array( $result['ticket'] ?? null ) ? $result['ticket'] : array();
		$result_data    = is_array( $result['data'] ?? null ) ? $result['data'] : array();
		$result_meta    = is_array( $result['meta'] ?? null ) ? $result['meta'] : array();
		$ticket         = array_merge( $result_meta, $result_data, $ticket_payload );
		$number         = LS_Support::extract_ticket_number( $ticket );
		$token          = LS_Support::extract_access_token( $ticket );

		if ( $token === '' ) {
			$token = LS_Support::extract_access_token( $result );
		}

		if ( $number !== '' ) {
			$save_data = array(
				'subject'        => $subject,
				'status'         => (string) ( $ticket['status'] ?? 'open' ),
				'category'       => $category,
				'priority'       => $priority,
				'order_id'       => $order_id ? (string) $order_id : LS_Support::extract_ticket_order_id( $ticket ),
				'customer_email' => $user->user_email,
				'created_at'     => LS_Support::extract_ticket_created_at( $ticket ) ?: current_time( 'mysql' ),
				'updated_at'     => LS_Support::extract_ticket_updated_at( $ticket ) ?: current_time( 'mysql' ),
			);

			if ( $token !== '' ) {
				$save_data['access_token'] = $token;
			}

			LS_Support::save_ticket_access( $user->ID, $number, $save_data );
		}

		$redirect_url = LS_Support::get_ticket_url( $number );
		if ( $redirect_url === '' ) {
			$redirect_url = LS_Support::get_manage_page_url();
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
		$result = LS_Support::list_customer_tickets(
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

		LS_Support::capture_access_token_from_post( $user->ID, $ticket_number, $user->user_email );

		if ( ! LS_Support::customer_owns_ticket( $user->ID, $user->user_email, $ticket_number ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have access to this ticket.', 'licensesender' ) ), 403 );
		}

		$result = LS_Support::get_customer_conversation( $user->ID, $user->user_email, $ticket_number );
		if ( empty( $result['success'] ) ) {
			wp_send_json_error(
				array( 'message' => (string) ( $result['message'] ?? __( 'Could not load conversation.', 'licensesender' ) ) ),
				(int) ( $result['http_code'] ?? 400 )
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

		LS_Support::capture_access_token_from_post( $user->ID, $ticket_number, $user->user_email );

		if ( ! LS_Support::customer_owns_ticket( $user->ID, $user->user_email, $ticket_number ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have access to this ticket.', 'licensesender' ) ), 403 );
		}

		$message = LS_Support::validate_message_content( wp_unslash( $_POST['message'] ?? '' ) );
		if ( is_wp_error( $message ) ) {
			wp_send_json_error( array( 'message' => $message->get_error_message() ), 400 );
		}

		$files     = LS_Support::normalize_uploaded_files();
		$validated = LS_Support::validate_uploads( $files );
		if ( is_wp_error( $validated ) ) {
			wp_send_json_error( array( 'message' => $validated->get_error_message() ), 400 );
		}

		$result = LS_Support::reply_customer_ticket(
			$user->ID,
			$user->user_email,
			$ticket_number,
			$message,
			is_array( $validated ) ? $validated : array()
		);

		if ( empty( $result['success'] ) ) {
			wp_send_json_error(
				array( 'message' => (string) ( $result['message'] ?? __( 'Could not send reply.', 'licensesender' ) ) ),
				(int) ( $result['http_code'] ?? 400 )
			);
		}

		$conversation = LS_Support::get_customer_conversation( $user->ID, $user->user_email, $ticket_number );
		if ( empty( $conversation['success'] ) ) {
			wp_send_json_success(
				array(
					'message'       => (string) ( $result['message'] ?? __( 'Reply sent.', 'licensesender' ) ),
					'reload_ticket' => $ticket_number,
				)
			);
		}

		LS_Support::sync_ticket_activity(
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
		$keys     = LS_Support::get_order_license_keys( $user->ID, $order_id );

		wp_send_json_success( array( 'keys' => $keys ) );
	}

	private static function verify_ajax() {
		check_ajax_referer( 'ls_support', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'licensesender' ) ), 401 );
		}
	}

	private static function enqueue_assets( $with_editor = false ) {
		wp_enqueue_style( 'ls-support' );

		if ( $with_editor ) {
			wp_enqueue_editor();
			wp_enqueue_style( 'editor-buttons' );

			global $wp_scripts;
			if ( isset( $wp_scripts->registered['ls-support'] ) ) {
				$wp_scripts->registered['ls-support']->deps[] = 'editor';
			}
		}

		wp_enqueue_script( 'ls-support' );
	}

	private static function render_auth_gate( $title, $lead ) {
		$redirect_url = get_permalink();
		if ( ! $redirect_url ) {
			$redirect_url = home_url( '/' );
		}

		echo '<div class="ls-support-wrap alignwide">';
		echo '<div class="ls-support-card ls-support-auth-card">';
		echo '<h2 class="ls-support-title">' . esc_html( $title ) . '</h2>';
		echo '<p class="ls-support-lead">' . esc_html( $lead ) . '</p>';
		if ( function_exists( 'woocommerce_output_all_notices' ) ) {
			woocommerce_output_all_notices();
		}
		self::render_wc_auth_section( $redirect_url );
		echo '</div>';
		echo '</div>';
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

	private static function load_support_template( $template ) {
		$path = dirname( dirname( __FILE__ ) ) . '/templates/wholesale/' . $template;
		if ( file_exists( $path ) ) {
			include $path;
		}
	}

	private static function is_register_auth_view() {
		return isset( $_GET['ls_auth'] ) && sanitize_key( wp_unslash( $_GET['ls_auth'] ) ) === 'register';
	}

	private static function is_support_registration_request() {
		if ( empty( $_POST['register'] ) || ! is_singular() ) {
			return false;
		}

		$post = get_post();
		if ( ! $post ) {
			return false;
		}

		return has_shortcode( $post->post_content, 'ls_support_open' )
			|| has_shortcode( $post->post_content, 'ls_support_manage' );
	}

	public static function enable_support_registration( $value ) {
		if ( self::$support_auth_active || self::is_register_auth_view() || self::is_support_registration_request() ) {
			return 'yes';
		}

		return $value;
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

LS_Support_Shortcodes::init();
