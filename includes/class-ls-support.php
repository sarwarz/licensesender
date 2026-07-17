<?php
/**
 * Customer support ticket helpers (API + local token storage).
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Support {

	const USER_META_KEY = 'ls_support_tickets';

	const MAX_SUBJECT_LENGTH = 200;

	const MAX_MESSAGE_LENGTH = 10000;

	/**
	 * Whether customer support shortcodes are enabled.
	 */
	public static function is_enabled() {
		return get_option( 'lship_support_enabled', 'yes' ) === 'yes';
	}

	/**
	 * @return int
	 */
	public static function get_open_page_id() {
		return absint( get_option( 'lship_support_open_page_id', 0 ) );
	}

	/**
	 * @return int
	 */
	public static function get_manage_page_id() {
		return absint( get_option( 'lship_support_manage_page_id', 0 ) );
	}

	/**
	 * @return string
	 */
	public static function get_manage_page_url() {
		$page_id = self::get_manage_page_id();
		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * @return string
	 */
	public static function get_open_page_url() {
		$page_id = self::get_open_page_id();
		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * @param string $ticket_number Ticket number.
	 * @return string
	 */
	public static function get_ticket_url( $ticket_number, $access_token = '' ) {
		unset( $access_token );

		$base = self::get_manage_page_url();
		if ( $base === '' ) {
			return '';
		}

		$ticket_number = sanitize_text_field( (string) $ticket_number );
		if ( $ticket_number === '' ) {
			return $base;
		}

		$url = add_query_arg( 'ls_ticket', rawurlencode( $ticket_number ), $base );

		return $url;
	}

	/**
	 * @param array<string, mixed> $ticket Ticket payload.
	 * @param string               $fallback_name Customer name fallback.
	 * @return array<string, mixed>
	 */
	public static function build_ticket_summary( array $ticket, $fallback_name = '' ) {
		$status   = (string) ( $ticket['status'] ?? 'open' );
		$category = (string) ( $ticket['category'] ?? '' );
		$priority = (string) ( $ticket['priority'] ?? '' );
		$order_id = self::extract_ticket_order_id( $ticket );

		return array(
			'ticket_number'  => self::extract_ticket_number( $ticket ),
			'subject'        => (string) ( $ticket['subject'] ?? '' ),
			'status'         => $status,
			'status_label'   => self::get_status_label( $status ),
			'status_class'   => self::get_status_badge_class( $status ),
			'category'       => $category,
			'category_label' => self::get_category_label( $category ),
			'priority'       => $priority,
			'priority_label' => self::get_priority_label( $priority ),
			'priority_class' => self::get_priority_badge_class( $priority ),
			'customer_name'  => (string) ( $ticket['customer_name'] ?? $fallback_name ),
			'customer_email' => (string) ( $ticket['customer_email'] ?? '' ),
			'order_id'       => $order_id,
			'order_label'    => self::format_ticket_order_label( $order_id ),
			'created_at'     => self::extract_ticket_created_at( $ticket ),
			'updated_at'     => self::extract_ticket_updated_at( $ticket ),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $messages Messages list.
	 * @return array<int, array<string, mixed>>
	 */
	public static function prepare_messages_for_display( array $messages ) {
		$prepared = array();

		foreach ( $messages as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}

			$author = strtolower( (string) ( $message['author_type'] ?? $message['sender_type'] ?? $message['role'] ?? '' ) );
			$body   = (string) ( $message['message'] ?? $message['body'] ?? $message['content'] ?? '' );

			$message['body_html']    = wp_kses_post( $body );
			$message['author_label'] = str_contains( $author, 'customer' ) || str_contains( $author, 'user' )
				? __( 'You', 'licensesender' )
				: ( (string) ( $message['author_name'] ?? __( 'Support', 'licensesender' ) ) );

			$attachments = $message['attachments'] ?? array();
			if ( ! is_array( $attachments ) ) {
				$attachments = array();
			}

			$normalized_attachments = array();
			foreach ( $attachments as $attachment ) {
				if ( ! is_array( $attachment ) ) {
					continue;
				}

				$mime = (string) ( $attachment['mime'] ?? '' );
				$normalized_attachments[] = array(
					'id'           => (int) ( $attachment['id'] ?? 0 ),
					'name'         => (string) ( $attachment['name'] ?? $attachment['original_name'] ?? __( 'Attachment', 'licensesender' ) ),
					'mime'         => $mime,
					'size'         => (int) ( $attachment['size'] ?? 0 ),
					'is_image'     => ! empty( $attachment['is_image'] ) || str_starts_with( $mime, 'image/' ),
					'download_url' => (string) ( $attachment['download_url'] ?? $attachment['url'] ?? '' ),
				);
			}
			$message['attachments'] = $normalized_attachments;

			$prepared[] = $message;
		}

		return $prepared;
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_categories() {
		return array(
			'license_issue' => __( 'License issue', 'licensesender' ),
			'activation'    => __( 'Activation problem', 'licensesender' ),
			'billing'       => __( 'Billing / order', 'licensesender' ),
			'download'      => __( 'Download link', 'licensesender' ),
			'general'       => __( 'General question', 'licensesender' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_priorities() {
		return array(
			'low'    => __( 'Low', 'licensesender' ),
			'normal' => __( 'Normal', 'licensesender' ),
			'high'   => __( 'High', 'licensesender' ),
			'urgent' => __( 'Urgent', 'licensesender' ),
		);
	}

	/**
	 * @param string $value Raw category slug.
	 * @return string
	 */
	public static function sanitize_category( $value ) {
		$slug       = sanitize_key( (string) $value );
		$categories = self::get_categories();

		return isset( $categories[ $slug ] ) ? $slug : 'general';
	}

	/**
	 * @param string $value Raw priority slug.
	 * @return string
	 */
	public static function sanitize_priority( $value ) {
		$slug       = sanitize_key( (string) $value );
		$priorities = self::get_priorities();

		return isset( $priorities[ $slug ] ) ? $slug : 'normal';
	}

	/**
	 * @param string $subject Raw subject.
	 * @return string|WP_Error
	 */
	public static function validate_subject( $subject ) {
		$subject = sanitize_text_field( (string) $subject );

		if ( $subject === '' ) {
			return new WP_Error( 'invalid_subject', __( 'Subject is required.', 'licensesender' ) );
		}

		if ( mb_strlen( $subject ) > self::MAX_SUBJECT_LENGTH ) {
			return new WP_Error(
				'subject_too_long',
				sprintf(
					/* translators: %d: max subject length */
					__( 'Subject must be %d characters or fewer.', 'licensesender' ),
					self::MAX_SUBJECT_LENGTH
				)
			);
		}

		return $subject;
	}

	/**
	 * @param string $message Raw message HTML.
	 * @return string|WP_Error
	 */
	public static function validate_message_content( $message ) {
		$message = wp_kses_post( (string) $message );

		if ( trim( wp_strip_all_tags( $message ) ) === '' ) {
			return new WP_Error( 'invalid_message', __( 'Message is required.', 'licensesender' ) );
		}

		if ( mb_strlen( wp_strip_all_tags( $message ) ) > self::MAX_MESSAGE_LENGTH ) {
			return new WP_Error(
				'message_too_long',
				sprintf(
					/* translators: %d: max message length */
					__( 'Message must be %d characters or fewer.', 'licensesender' ),
					self::MAX_MESSAGE_LENGTH
				)
			);
		}

		return $message;
	}

	/**
	 * @param int    $user_id User ID.
	 * @param int    $order_id Order ID.
	 * @param string $license_key License key value.
	 * @return string|WP_Error
	 */
	public static function validate_license_key_for_order( $user_id, $order_id, $license_key ) {
		$user_id     = (int) $user_id;
		$order_id    = (int) $order_id;
		$license_key = sanitize_text_field( (string) $license_key );

		if ( $license_key === '' ) {
			return '';
		}

		if ( ! $order_id ) {
			return new WP_Error( 'invalid_license_key', __( 'Select an order before choosing a license key.', 'licensesender' ) );
		}

		foreach ( self::get_order_license_keys( $user_id, $order_id ) as $item ) {
			if ( hash_equals( (string) ( $item['key'] ?? '' ), $license_key ) ) {
				return $license_key;
			}
		}

		return new WP_Error( 'invalid_license_key', __( 'Invalid license key for the selected order.', 'licensesender' ) );
	}

	/**
	 * @param string $ticket_status Raw ticket status.
	 * @param string $filter_status Selected filter slug.
	 * @return bool
	 */
	public static function ticket_matches_status_filter( $ticket_status, $filter_status ) {
		$filter_status = sanitize_key( (string) $filter_status );
		if ( $filter_status === '' ) {
			return true;
		}

		$normalized = strtolower( sanitize_key( (string) $ticket_status ) );
		if ( $normalized === $filter_status ) {
			return true;
		}

		$groups = array(
			'closed'                  => array( 'closed', 'resolved' ),
			'resolved'                => array( 'resolved', 'closed' ),
			'awaiting_customer'       => array( 'awaiting_customer', 'awaiting_customer_reply', 'waiting_customer', 'pending_customer' ),
			'awaiting_staff'          => array( 'awaiting_staff', 'awaiting_agent_reply', 'waiting_agent', 'pending_agent' ),
			'awaiting_customer_reply' => array( 'awaiting_customer', 'awaiting_customer_reply', 'waiting_customer', 'pending_customer' ),
			'awaiting_agent_reply'    => array( 'awaiting_staff', 'awaiting_agent_reply', 'waiting_agent', 'pending_agent' ),
			'open'                    => array( 'open', 'awaiting_staff', 'awaiting_customer' ),
		);

		if ( ! isset( $groups[ $filter_status ] ) ) {
			return false;
		}

		return in_array( $normalized, $groups[ $filter_status ], true );
	}

	/**
	 * @param string $filename Raw upload filename.
	 * @return string
	 */
	public static function sanitize_upload_filename( $filename ) {
		$filename = sanitize_file_name( wp_basename( (string) $filename ) );

		if ( $filename === '' ) {
			return 'attachment';
		}

		return str_replace( array( '"', "\r", "\n" ), '', $filename );
	}

	/**
	 * @param int $user_id User ID.
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_saved_tickets( $user_id ) {
		$user_id = (int) $user_id;
		if ( ! $user_id ) {
			return array();
		}

		$tickets = get_user_meta( $user_id, self::USER_META_KEY, true );
		return is_array( $tickets ) ? $tickets : array();
	}

	/**
	 * @param int                  $user_id User ID.
	 * @param string               $ticket_number Ticket number.
	 * @param array<string, mixed> $data Ticket data.
	 */
	public static function save_ticket_access( $user_id, $ticket_number, array $data ) {
		$user_id       = (int) $user_id;
		$ticket_number = sanitize_text_field( (string) $ticket_number );

		if ( ! $user_id || $ticket_number === '' ) {
			return;
		}

		$tickets   = self::get_saved_tickets( $user_id );
		$existing  = $tickets[ $ticket_number ] ?? array();
		$new_token = trim( (string) ( $data['access_token'] ?? '' ) );

		if ( $new_token === '' && ! empty( $existing['access_token'] ) ) {
			unset( $data['access_token'] );
		}

		$merged_ticket = array_merge( $existing, $data );
		$updated_at    = self::extract_ticket_updated_at( $merged_ticket );
		if ( $updated_at === '' ) {
			$updated_at = current_time( 'mysql' );
		}

		$tickets[ $ticket_number ] = array_merge(
			$existing,
			$data,
			array(
				'ticket_number' => $ticket_number,
				'updated_at'    => $updated_at,
			)
		);

		update_user_meta( $user_id, self::USER_META_KEY, $tickets );
	}

	/**
	 * @param int    $user_id User ID.
	 * @param string $ticket_number Ticket number.
	 * @return string
	 */
	public static function get_ticket_access_token( $user_id, $ticket_number ) {
		$tickets = self::get_saved_tickets( $user_id );
		$ticket  = $tickets[ sanitize_text_field( (string) $ticket_number ) ] ?? array();

		return trim( (string) ( $ticket['access_token'] ?? '' ) );
	}

	/**
	 * @param array<string, mixed> $ticket API ticket payload.
	 * @return string
	 */
	public static function extract_ticket_number( array $ticket ) {
		foreach ( array( 'ticket_number', 'number', 'ticket_no' ) as $key ) {
			if ( ! empty( $ticket[ $key ] ) ) {
				return sanitize_text_field( (string) $ticket[ $key ] );
			}
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $ticket Ticket payload.
	 * @param array<int, string>   $keys Field names to try in order.
	 * @return string
	 */
	public static function ticket_scalar_field( array $ticket, array $keys ) {
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $ticket ) || ! is_scalar( $ticket[ $key ] ) ) {
				continue;
			}

			$value = trim( (string) $ticket[ $key ] );
			if ( $value !== '' ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $ticket Ticket payload.
	 * @return string
	 */
	public static function extract_ticket_created_at( array $ticket ) {
		return self::ticket_scalar_field(
			$ticket,
			array(
				'created_at',
				'createdAt',
				'date_created',
				'opened_at',
				'created',
			)
		);
	}

	/**
	 * @param array<string, mixed> $ticket Ticket payload.
	 * @return string
	 */
	public static function extract_ticket_updated_at( array $ticket ) {
		$candidates = array(
			self::infer_latest_message_at( $ticket ),
			self::ticket_scalar_field(
				$ticket,
				array(
					'last_reply_at',
					'last_replied_at',
					'last_message_at',
					'latest_reply_at',
					'recent_reply_at',
				)
			),
			self::ticket_scalar_field(
				$ticket,
				array(
					'updated_at',
					'updatedAt',
					'last_updated_at',
					'last_updated',
					'date_updated',
					'modified_at',
					'modifiedAt',
				)
			),
		);

		$latest = self::latest_timestamp( ...$candidates );
		if ( $latest !== '' ) {
			return $latest;
		}

		return self::extract_ticket_created_at( $ticket );
	}

	/**
	 * @param string ...$values Datetime strings.
	 * @return string
	 */
	public static function latest_timestamp( ...$values ) {
		$best    = '';
		$best_ts = 0;

		foreach ( $values as $value ) {
			$value = trim( (string) $value );
			if ( $value === '' ) {
				continue;
			}

			$timestamp = strtotime( $value );
			if ( false === $timestamp ) {
				continue;
			}

			if ( $timestamp >= $best_ts ) {
				$best_ts = $timestamp;
				$best    = $value;
			}
		}

		return $best;
	}

	/**
	 * @param array<string, mixed> $ticket Ticket payload.
	 * @return bool
	 */
	public static function ticket_has_messages( array $ticket ) {
		foreach ( array( 'messages', 'conversation', 'replies' ) as $key ) {
			if ( ! empty( $ticket[ $key ] ) && is_array( $ticket[ $key ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Persist the latest reply time for ticket list sorting/display.
	 *
	 * @param int                         $user_id User ID.
	 * @param string                      $ticket_number Ticket number.
	 * @param array<int, array<string,mixed>> $messages Conversation messages.
	 * @param array<string, mixed>        $summary Optional ticket summary to update.
	 * @return array<string, mixed>
	 */
	public static function sync_ticket_activity( $user_id, $ticket_number, array $messages, array $summary = array() ) {
		$user_id       = (int) $user_id;
		$ticket_number = sanitize_text_field( (string) $ticket_number );

		if ( ! $user_id || $ticket_number === '' ) {
			return $summary;
		}

		$last_activity = self::infer_latest_message_at( array( 'messages' => $messages ) );
		if ( $last_activity === '' ) {
			return $summary;
		}

		$updated_at = self::latest_timestamp( $summary['updated_at'] ?? '', $last_activity );
		self::save_ticket_access(
			$user_id,
			$ticket_number,
			array(
				'updated_at' => $updated_at,
			)
		);

		if ( ! empty( $summary ) ) {
			$summary['updated_at'] = $updated_at;
		}

		return $summary;
	}

	/**
	 * @param array<string, mixed> $ticket Ticket payload.
	 * @return string
	 */
	public static function extract_ticket_order_id( array $ticket, $depth = 0 ) {
		if ( $depth > 3 ) {
			return '';
		}

		$direct = self::ticket_scalar_field(
			$ticket,
			array(
				'order_id',
				'order_number',
				'orderNumber',
				'woocommerce_order_id',
				'wc_order_id',
				'external_order_id',
				'woo_order_id',
				'shop_order_id',
				'related_order_id',
			)
		);

		if ( $direct !== '' ) {
			return self::normalize_order_id_value( $direct );
		}

		if ( ! empty( $ticket['order'] ) && is_array( $ticket['order'] ) ) {
			$nested = self::extract_ticket_order_id( $ticket['order'], $depth + 1 );
			if ( $nested !== '' ) {
				return $nested;
			}
		}

		foreach ( array( 'meta', 'data', 'ticket', 'relationships' ) as $key ) {
			if ( empty( $ticket[ $key ] ) || ! is_array( $ticket[ $key ] ) ) {
				continue;
			}

			$nested = self::extract_ticket_order_id( $ticket[ $key ], $depth + 1 );
			if ( $nested !== '' ) {
				return $nested;
			}
		}

		return '';
	}

	/**
	 * @param string $value Raw order id/number from API.
	 * @return string
	 */
	public static function normalize_order_id_value( $value ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return '';
		}

		$value = ltrim( $value, '#' );

		return $value;
	}

	/**
	 * @param string $order_id Order ID or order number.
	 * @return string
	 */
	public static function format_ticket_order_label( $order_id ) {
		$order_id = self::normalize_order_id_value( $order_id );
		if ( $order_id === '' ) {
			return '';
		}

		$numeric_id = absint( preg_replace( '/\D/', '', $order_id ) );
		if ( $numeric_id && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $numeric_id );
			if ( $order ) {
				return '#' . $order->get_order_number();
			}
		}

		return '#' . $order_id;
	}

	/**
	 * @param string ...$values Order id candidates.
	 * @return string
	 */
	public static function latest_order_id( ...$values ) {
		foreach ( $values as $value ) {
			$value = self::normalize_order_id_value( $value );
			if ( $value !== '' ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $ticket Ticket payload.
	 * @return string
	 */
	public static function infer_latest_message_at( array $ticket ) {
		$message_lists = array();

		foreach ( array( 'messages', 'conversation', 'replies' ) as $key ) {
			if ( ! empty( $ticket[ $key ] ) && is_array( $ticket[ $key ] ) ) {
				$message_lists[] = $ticket[ $key ];
			}
		}

		if ( empty( $message_lists ) ) {
			return '';
		}

		$latest = '';
		foreach ( $message_lists as $messages ) {
			foreach ( $messages as $message ) {
				if ( ! is_array( $message ) ) {
					continue;
				}

				$when = self::ticket_scalar_field(
					$message,
					array(
						'created_at',
						'sent_at',
						'updated_at',
						'posted_at',
						'timestamp',
						'date',
					)
				);

				if ( $when === '' ) {
					continue;
				}

				$timestamp = strtotime( $when );
				if ( false === $timestamp ) {
					continue;
				}

				if ( $latest === '' || $timestamp > ( strtotime( $latest ) ?: 0 ) ) {
					$latest = $when;
				}
			}
		}

		return $latest;
	}

	/**
	 * @param array<string, mixed> $ticket API ticket payload.
	 * @return string
	 */
	public static function extract_access_token( array $payload, $depth = 0 ) {
		if ( $depth > 4 ) {
			return '';
		}

		foreach ( array(
			'access_token',
			'customer_access_token',
			'ticket_access_token',
			'portal_access_token',
			'customer_portal_token',
			'customer_portal_access_token',
			'one_time_access_token',
			'portal_token',
			'accessToken',
			'ticketAccessToken',
			'token',
		) as $key ) {
			if ( ! empty( $payload[ $key ] ) && is_scalar( $payload[ $key ] ) ) {
				return trim( (string) $payload[ $key ] );
			}
		}

		foreach ( array(
			'portal_url',
			'customer_portal_url',
			'access_url',
			'customer_portal_link',
			'portal_link',
			'url',
			'link',
		) as $url_key ) {
			if ( empty( $payload[ $url_key ] ) || ! is_scalar( $payload[ $url_key ] ) ) {
				continue;
			}

			$token = self::extract_access_token_from_url( (string) $payload[ $url_key ] );
			if ( $token !== '' ) {
				return $token;
			}
		}

		foreach ( array( 'ticket', 'meta', 'data', 'portal', 'customer_portal', 'links' ) as $nested_key ) {
			if ( empty( $payload[ $nested_key ] ) || ! is_array( $payload[ $nested_key ] ) ) {
				continue;
			}

			$found = self::extract_access_token( $payload[ $nested_key ], $depth + 1 );
			if ( $found !== '' ) {
				return $found;
			}
		}

		return '';
	}

	/**
	 * @param string $url Portal or ticket URL that may contain an access token query param.
	 * @return string
	 */
	public static function extract_access_token_from_url( $url ) {
		$url = trim( (string) $url );
		if ( $url === '' ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['query'] ) ) {
			return '';
		}

		parse_str( (string) $parts['query'], $query );
		if ( ! is_array( $query ) ) {
			return '';
		}

		foreach ( array( 'access_token', 'ticket_access_token', 'token', 'portal_token' ) as $key ) {
			if ( ! empty( $query[ $key ] ) && is_scalar( $query[ $key ] ) ) {
				return trim( (string) $query[ $key ] );
			}
		}

		return '';
	}

	/**
	 * Persist a ticket access token from the current request query string.
	 *
	 * @param int    $user_id User ID.
	 * @param string $ticket_number Ticket number.
	 */
	public static function capture_access_token_from_request( $user_id, $ticket_number, $email = '' ) {
		$user_id       = (int) $user_id;
		$ticket_number = sanitize_text_field( (string) $ticket_number );
		$email         = sanitize_email( (string) $email );

		if ( ! $user_id || $ticket_number === '' || $email === '' ) {
			return;
		}

		$token = '';
		foreach ( array( 'access_token', 'ticket_access_token', 'token', 'portal_token' ) as $key ) {
			if ( empty( $_GET[ $key ] ) ) {
				continue;
			}

			$token = sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) );
			if ( $token !== '' ) {
				break;
			}
		}

		if ( $token === '' ) {
			return;
		}

		if ( self::customer_owns_ticket( $user_id, $email, $ticket_number ) ) {
			self::save_ticket_access(
				$user_id,
				$ticket_number,
				array(
					'access_token' => $token,
				)
			);
			return;
		}

		self::claim_ticket_with_access_token( $user_id, $email, $ticket_number, $token );
	}

	/**
	 * Persist a ticket access token from the current POST payload.
	 *
	 * @param int    $user_id User ID.
	 * @param string $ticket_number Ticket number.
	 */
	public static function capture_access_token_from_post( $user_id, $ticket_number, $email = '' ) {
		$user_id       = (int) $user_id;
		$ticket_number = sanitize_text_field( (string) $ticket_number );
		$email         = sanitize_email( (string) $email );

		if ( ! $user_id || $ticket_number === '' || $email === '' ) {
			return;
		}

		$token = '';
		foreach ( array( 'access_token', 'ticket_access_token', 'token', 'portal_token' ) as $key ) {
			if ( empty( $_POST[ $key ] ) ) {
				continue;
			}

			$token = sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) );
			if ( $token !== '' ) {
				break;
			}
		}

		if ( $token === '' ) {
			return;
		}

		if ( self::customer_owns_ticket( $user_id, $email, $ticket_number ) ) {
			self::save_ticket_access(
				$user_id,
				$ticket_number,
				array(
					'access_token' => $token,
				)
			);
			return;
		}

		self::claim_ticket_with_access_token( $user_id, $email, $ticket_number, $token );
	}

	/**
	 * @param int                  $user_id User ID.
	 * @param string               $email Customer email.
	 * @param string               $ticket_number Ticket number.
	 * @param array<string, mixed> $ticket Ticket payload.
	 */
	public static function persist_ticket_access( $user_id, $ticket_number, $email, array $ticket ) {
		self::persist_ticket_snapshot( $user_id, $ticket_number, $email, $ticket, true );
	}

	/**
	 * Store ticket metadata locally so list/detail views can show dates and order info.
	 *
	 * @param int                  $user_id User ID.
	 * @param string               $ticket_number Ticket number.
	 * @param string               $email Customer email.
	 * @param array<string, mixed> $ticket Ticket payload.
	 * @param bool                 $require_token Only persist when an access token is present.
	 */
	public static function persist_ticket_snapshot( $user_id, $ticket_number, $email, array $ticket, $require_token = false ) {
		$user_id       = (int) $user_id;
		$ticket_number = sanitize_text_field( (string) $ticket_number );
		$email         = sanitize_email( (string) $email );

		if ( ! $user_id || $ticket_number === '' || empty( $ticket ) ) {
			return;
		}

		$ticket_email = sanitize_email( (string) ( $ticket['customer_email'] ?? '' ) );
		if ( $email !== '' && $ticket_email !== '' && strcasecmp( $ticket_email, $email ) !== 0 ) {
			return;
		}

		$token = self::extract_access_token( $ticket );
		if ( $require_token && $token === '' ) {
			return;
		}

		$data = array(
			'subject'         => (string) ( $ticket['subject'] ?? '' ),
			'status'          => (string) ( $ticket['status'] ?? 'open' ),
			'category'        => (string) ( $ticket['category'] ?? '' ),
			'priority'        => (string) ( $ticket['priority'] ?? '' ),
			'order_id'        => self::extract_ticket_order_id( $ticket ),
			'customer_email'  => $ticket_email !== '' ? $ticket_email : $email,
			'created_at'      => self::extract_ticket_created_at( $ticket ),
			'updated_at'      => self::extract_ticket_updated_at( $ticket ),
		);

		if ( $token !== '' ) {
			$data['access_token'] = $token;
		}

		self::save_ticket_access( $user_id, $ticket_number, $data );
	}

	/**
	 * @param int    $user_id User ID.
	 * @param string $email Customer email.
	 * @param string $ticket_number Ticket number.
	 * @return bool
	 */
	public static function customer_owns_ticket( $user_id, $email, $ticket_number ) {
		$user_id       = (int) $user_id;
		$email         = sanitize_email( (string) $email );
		$ticket_number = sanitize_text_field( (string) $ticket_number );

		if ( ! $user_id || $ticket_number === '' || $email === '' ) {
			return false;
		}

		// Ownership is local: ticket must be saved under this WP user (create / list / token claim).
		// Do not grant access from API email match alone (IDOR).
		$saved       = self::get_saved_tickets( $user_id );
		$saved_entry = $saved[ $ticket_number ] ?? array();
		if ( empty( $saved_entry ) ) {
			return false;
		}

		$saved_email = sanitize_email( (string) ( $saved_entry['customer_email'] ?? '' ) );
		if ( $saved_email !== '' && strcasecmp( $saved_email, $email ) !== 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * Claim a ticket for the logged-in user using a customer access token.
	 *
	 * @param int    $user_id User ID.
	 * @param string $email Customer email.
	 * @param string $ticket_number Ticket number.
	 * @param string $access_token Portal / email access token.
	 * @return bool
	 */
	public static function claim_ticket_with_access_token( $user_id, $email, $ticket_number, $access_token ) {
		$user_id       = (int) $user_id;
		$email         = sanitize_email( (string) $email );
		$ticket_number = sanitize_text_field( (string) $ticket_number );
		$access_token  = trim( (string) $access_token );

		if ( ! $user_id || $email === '' || $ticket_number === '' || $access_token === '' ) {
			return false;
		}

		$result = Licensesender_Api::get_support_conversation( $ticket_number, $access_token );
		if ( empty( $result['success'] ) ) {
			return false;
		}

		$ticket_res = Licensesender_Api::get_support_ticket( $ticket_number );
		$ticket     = ( ! empty( $ticket_res['success'] ) && is_array( $ticket_res['ticket'] ?? null ) )
			? $ticket_res['ticket']
			: array();

		$ticket_email = sanitize_email( (string) ( $ticket['customer_email'] ?? '' ) );
		if ( $ticket_email !== '' && strcasecmp( $ticket_email, $email ) !== 0 ) {
			return false;
		}

		self::save_ticket_access(
			$user_id,
			$ticket_number,
			array_filter(
				array(
					'customer_email' => $email,
					'access_token'   => $access_token,
					'subject'        => (string) ( $ticket['subject'] ?? '' ),
					'status'         => (string) ( $ticket['status'] ?? '' ),
					'order_id'       => self::extract_ticket_order_id( $ticket ),
				)
			)
		);

		return true;
	}

	/**
	 * @param array<int, array<string, mixed>> $messages Messages list.
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_customer_messages( array $messages ) {
		$filtered = array();

		foreach ( $messages as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}

			if ( ! empty( $message['is_internal'] ) ) {
				continue;
			}

			$author = strtolower( (string) ( $message['author_type'] ?? $message['sender_type'] ?? $message['role'] ?? '' ) );
			if ( str_contains( $author, 'internal' ) ) {
				continue;
			}

			$filtered[] = $message;
		}

		return $filtered;
	}

	/**
	 * @param int    $user_id User ID.
	 * @param string $email Customer email.
	 * @param string $ticket_number Ticket number.
	 * @return array<string, mixed>
	 */
	public static function get_customer_conversation( $user_id, $email, $ticket_number ) {
		$user_id       = (int) $user_id;
		$email         = sanitize_email( (string) $email );
		$ticket_number = sanitize_text_field( (string) $ticket_number );

		if ( ! $user_id || $ticket_number === '' ) {
			return array(
				'success'   => false,
				'message'   => __( 'Invalid ticket.', 'licensesender' ),
				'http_code' => 400,
			);
		}

		if ( ! self::customer_owns_ticket( $user_id, $email, $ticket_number ) ) {
			return array(
				'success'   => false,
				'message'   => __( 'You do not have access to this ticket.', 'licensesender' ),
				'http_code' => 403,
			);
		}

		$ticket_res = Licensesender_Api::get_support_ticket( $ticket_number );
		if ( ! empty( $ticket_res['success'] ) && is_array( $ticket_res['ticket'] ?? null ) ) {
			self::persist_ticket_access( $user_id, $ticket_number, $email, $ticket_res['ticket'] );
		}

		$ticket    = is_array( $ticket_res['ticket'] ?? null ) ? $ticket_res['ticket'] : array();
		$saved     = self::get_saved_tickets( $user_id )[ $ticket_number ] ?? array();

		if ( empty( $ticket_res['success'] ) && empty( $ticket ) && empty( $saved ) ) {
			return array(
				'success'   => false,
				'message'   => (string) ( $ticket_res['message'] ?? __( 'Could not load ticket.', 'licensesender' ) ),
				'http_code' => (int) ( $ticket_res['http_code'] ?? 404 ),
			);
		}

		$user      = get_userdata( $user_id );
		$user_name = $user instanceof WP_User ? ( $user->display_name ?: $user->user_login ) : '';
		$summary   = self::build_ticket_summary( $ticket, $user_name );

		if ( $summary['order_id'] === '' && ! empty( $saved['order_id'] ) ) {
			$summary['order_id'] = (string) $saved['order_id'];
		}
		$summary['order_label'] = self::format_ticket_order_label( (string) ( $summary['order_id'] ?? '' ) );
		if ( $summary['subject'] === '' && ! empty( $saved['subject'] ) ) {
			$summary['subject'] = (string) $saved['subject'];
		}
		if ( $summary['status'] === '' && ! empty( $saved['status'] ) ) {
			$summary['status']       = (string) $saved['status'];
			$summary['status_label'] = self::get_status_label( $summary['status'] );
			$summary['status_class'] = self::get_status_badge_class( $summary['status'] );
		}
		if ( $summary['category'] === '' && ! empty( $saved['category'] ) ) {
			$summary['category']       = (string) $saved['category'];
			$summary['category_label'] = self::get_category_label( $summary['category'] );
		}
		if ( $summary['priority'] === '' && ! empty( $saved['priority'] ) ) {
			$summary['priority']       = (string) $saved['priority'];
			$summary['priority_label'] = self::get_priority_label( $summary['priority'] );
			$summary['priority_class'] = self::get_priority_badge_class( $summary['priority'] );
		}
		if ( $summary['updated_at'] === '' && ! empty( $saved['updated_at'] ) ) {
			$summary['updated_at'] = (string) $saved['updated_at'];
		}
		if ( $summary['created_at'] === '' && ! empty( $saved['created_at'] ) ) {
			$summary['created_at'] = (string) $saved['created_at'];
		}

		$token = self::get_ticket_access_token( $user_id, $ticket_number );

		if ( $token !== '' ) {
			$result = Licensesender_Api::get_support_conversation( $ticket_number, $token );
			if ( ! empty( $result['success'] ) ) {
				$messages = self::filter_customer_messages( $result['messages'] ?? array() );
				$messages = self::prepare_messages_for_display( $messages );
				$summary  = self::sync_ticket_activity( $user_id, $ticket_number, $messages, $summary );
				self::persist_ticket_snapshot(
					$user_id,
					$ticket_number,
					$email,
					array_merge( $ticket, $summary, array( 'messages' => $messages ) )
				);

				$result['can_reply'] = self::ticket_allows_customer_reply( (string) ( $summary['status'] ?? '' ) );
				$result['subject']   = (string) ( $summary['subject'] ?? '' );
				$result['ticket']    = $summary;
				$result['messages']  = $messages;
				return $result;
			}
		}

		$messages = $ticket['messages'] ?? $ticket['conversation'] ?? array();
		if ( ! is_array( $messages ) ) {
			$messages = array();
		}

		$messages = self::filter_customer_messages( $messages );
		if ( empty( $messages ) ) {
			$initial = trim( (string) ( $ticket['message'] ?? $ticket['initial_message'] ?? $ticket['body'] ?? '' ) );
			if ( $initial !== '' ) {
				$messages[] = array(
					'message'     => $initial,
					'author_type' => 'customer',
					'created_at'  => (string) ( $ticket['created_at'] ?? '' ),
				);
			}
		}

		$messages = self::prepare_messages_for_display( $messages );
		$summary  = self::sync_ticket_activity( $user_id, $ticket_number, $messages, $summary );
		self::persist_ticket_snapshot(
			$user_id,
			$ticket_number,
			$email,
			array_merge( $ticket, $summary, array( 'messages' => $messages ) )
		);

		return array(
			'success'   => true,
			'messages'  => $messages,
			'can_reply' => self::ticket_allows_customer_reply( (string) ( $summary['status'] ?? '' ) ),
			'subject'   => (string) ( $summary['subject'] ?? '' ),
			'ticket'    => $summary,
		);
	}

	/**
	 * @param string $status Ticket status slug.
	 * @return bool
	 */
	public static function ticket_allows_customer_reply( $status ) {
		$value = strtolower( sanitize_key( (string) $status ) );

		return ! ( str_contains( $value, 'close' ) || str_contains( $value, 'resolve' ) );
	}

	/**
	 * @param int                  $user_id User ID.
	 * @param string               $email Customer email.
	 * @param string               $ticket_number Ticket number.
	 * @param string               $message Reply message.
	 * @param array<int, array>    $files Uploaded files.
	 * @return array<string, mixed>
	 */
	public static function reply_customer_ticket( $user_id, $email, $ticket_number, $message, array $files = array() ) {
		$user_id       = (int) $user_id;
		$email         = sanitize_email( (string) $email );
		$ticket_number = sanitize_text_field( (string) $ticket_number );
		$message       = trim( (string) $message );

		if ( ! self::customer_owns_ticket( $user_id, $email, $ticket_number ) ) {
			return array(
				'success'   => false,
				'message'   => __( 'You do not have access to this ticket.', 'licensesender' ),
				'http_code' => 403,
			);
		}

		$ticket_res = Licensesender_Api::get_support_ticket( $ticket_number );
		if ( ! empty( $ticket_res['success'] ) && is_array( $ticket_res['ticket'] ?? null ) ) {
			self::persist_ticket_access( $user_id, $ticket_number, $email, $ticket_res['ticket'] );
		}

		self::capture_access_token_from_request( $user_id, $ticket_number, $email );
		self::capture_access_token_from_post( $user_id, $ticket_number, $email );

		$ticket  = is_array( $ticket_res['ticket'] ?? null ) ? $ticket_res['ticket'] : array();
		$saved   = self::get_saved_tickets( $user_id )[ $ticket_number ] ?? array();
		$user    = get_userdata( $user_id );
		$user_name = $user instanceof WP_User ? ( $user->display_name ?: $user->user_login ) : '';
		$summary = self::build_ticket_summary( $ticket, $user_name );
		if ( $summary['status'] === '' && ! empty( $saved['status'] ) ) {
			$summary['status'] = (string) $saved['status'];
		}
		$token = self::get_ticket_access_token( $user_id, $ticket_number );
		if ( ! self::ticket_allows_customer_reply( (string) ( $summary['status'] ?? '' ) ) ) {
			return array(
				'success'   => false,
				'message'   => __( 'This ticket is closed and cannot receive new replies.', 'licensesender' ),
				'http_code' => 403,
			);
		}

		if ( $token !== '' ) {
			$result = Licensesender_Api::reply_support_ticket( $ticket_number, $token, $message, $files );
			if ( ! empty( $result['success'] ) || ! self::is_missing_ticket_access_token_error( $result ) ) {
				return $result;
			}
		}

		$user      = get_userdata( $user_id );
		$user_name = $user instanceof WP_User ? ( $user->display_name ?: $user->user_login ) : '';

		$result = Licensesender_Api::reply_support_ticket_as_customer( $ticket_number, $email, $message, $files );
		if ( ! empty( $result['success'] ) ) {
			return $result;
		}

		if ( self::is_missing_ticket_access_token_error( $result ) ) {
			$result = Licensesender_Api::reply_support_ticket_for_shop_customer(
				$ticket_number,
				$email,
				$user_name,
				$message,
				$files
			);
			if ( ! empty( $result['success'] ) ) {
				return $result;
			}
		}

		if ( self::is_missing_ticket_access_token_error( $result ) ) {
			$result['message'] = __( 'This ticket cannot be replied to yet. Open it from your ticket confirmation email once, or create a new ticket from this site.', 'licensesender' );
		}

		return $result;
	}

	/**
	 * @param array<string, mixed> $result API response.
	 * @return bool
	 */
	public static function is_missing_ticket_access_token_error( array $result ) {
		$message = strtolower( (string) ( $result['message'] ?? '' ) );

		return str_contains( $message, 'missing ticket access token' );
	}

	/**
	 * @param WP_User $user Current user.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_customer_orders( WP_User $user ) {
		$orders = wc_get_orders(
			array(
				'customer_id' => $user->ID,
				'status'      => array( 'wc-completed', 'wc-processing', 'wc-on-hold' ),
				'limit'       => 50,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'return'      => 'objects',
			)
		);

		$items = array();
		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$items[] = array(
				'id'    => $order->get_id(),
				'label' => sprintf(
					/* translators: 1: order number, 2: date, 3: total */
					__( 'Order #%1$s — %2$s (%3$s)', 'licensesender' ),
					$order->get_order_number(),
					wc_format_datetime( $order->get_date_created() ),
					wp_strip_all_tags( $order->get_formatted_order_total() )
				),
			);
		}

		return $items;
	}

	/**
	 * @param int $user_id User ID.
	 * @param int $order_id Order ID.
	 * @return array<int, array<string, string>>
	 */
	public static function get_order_license_keys( $user_id, $order_id ) {
		global $wpdb;

		$user_id  = (int) $user_id;
		$order_id = (int) $order_id;
		if ( ! $user_id || ! $order_id ) {
			return array();
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || (int) $order->get_user_id() !== $user_id ) {
			return array();
		}

		$table = $wpdb->prefix . 'ls_cached_licenses';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT key_value, sku FROM {$table} WHERE order_id = %d AND fetched = 1 ORDER BY id ASC",
				$order_id
			)
		);

		$keys = array();
		foreach ( $rows as $row ) {
			$key = trim( (string) ( $row->key_value ?? '' ) );
			if ( $key === '' ) {
				continue;
			}
			$keys[] = array(
				'key' => $key,
				'sku' => (string) ( $row->sku ?? '' ),
			);
		}

		return $keys;
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_status_filter_options() {
		return array(
			''                  => __( 'All', 'licensesender' ),
			'awaiting_staff'    => __( 'Awaiting staff', 'licensesender' ),
			'awaiting_customer' => __( 'Awaiting customer', 'licensesender' ),
			'open'              => __( 'Open', 'licensesender' ),
			'resolved'          => __( 'Resolved', 'licensesender' ),
			'closed'            => __( 'Closed', 'licensesender' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_sort_options() {
		return array(
			'updated_at' => __( 'Date Updated', 'licensesender' ),
			'created_at' => __( 'Date Created', 'licensesender' ),
			'subject'    => __( 'Subject', 'licensesender' ),
			'status'     => __( 'Status', 'licensesender' ),
			'priority'   => __( 'Priority', 'licensesender' ),
		);
	}

	/**
	 * @param string $slug Category slug.
	 * @return string
	 */
	public static function get_category_label( $slug ) {
		$slug   = sanitize_key( (string) $slug );
		$labels = self::get_categories();

		return $labels[ $slug ] ?? ( $slug !== '' ? ucwords( str_replace( '_', ' ', $slug ) ) : '—' );
	}

	/**
	 * @param string $slug Priority slug.
	 * @return string
	 */
	public static function get_priority_label( $slug ) {
		$slug   = sanitize_key( (string) $slug );
		$labels = self::get_priorities();

		return $labels[ $slug ] ?? ( $slug !== '' ? ucfirst( $slug ) : '—' );
	}

	/**
	 * @param string $status Raw status slug.
	 * @return string
	 */
	public static function get_status_label( $status ) {
		$value = strtolower( sanitize_key( (string) $status ) );
		$map   = array(
			'awaiting_staff'          => __( 'Awaiting staff', 'licensesender' ),
			'awaiting_customer'       => __( 'Awaiting customer', 'licensesender' ),
			'awaiting_agent_reply'    => __( 'Awaiting staff', 'licensesender' ),
			'awaiting_customer_reply' => __( 'Awaiting customer', 'licensesender' ),
			'waiting_customer'        => __( 'Awaiting customer', 'licensesender' ),
			'waiting_agent'           => __( 'Awaiting staff', 'licensesender' ),
			'pending_customer'        => __( 'Awaiting customer', 'licensesender' ),
			'pending_agent'           => __( 'Awaiting staff', 'licensesender' ),
			'closed'                  => __( 'Closed', 'licensesender' ),
			'resolved'                => __( 'Resolved', 'licensesender' ),
			'open'                    => __( 'Open', 'licensesender' ),
		);

		if ( isset( $map[ $value ] ) ) {
			return $map[ $value ];
		}

		if ( $value === '' ) {
			return __( 'Open', 'licensesender' );
		}

		return ucwords( str_replace( '_', ' ', $value ) );
	}

	/**
	 * @param string $status Raw status slug.
	 * @return string CSS class suffix.
	 */
	public static function get_status_badge_class( $status ) {
		$value = strtolower( sanitize_key( (string) $status ) );

		if ( str_contains( $value, 'close' ) ) {
			return 'closed';
		}
		if ( str_contains( $value, 'resolve' ) ) {
			return 'resolved';
		}
		if ( str_contains( $value, 'customer' ) || str_contains( $value, 'waiting_customer' ) || str_contains( $value, 'pending_customer' ) ) {
			return 'awaiting-customer';
		}
		if ( str_contains( $value, 'staff' ) || str_contains( $value, 'agent' ) || str_contains( $value, 'waiting_agent' ) || str_contains( $value, 'pending_agent' ) ) {
			return 'awaiting-staff';
		}
		if ( str_contains( $value, 'open' ) ) {
			return 'open';
		}

		return 'default';
	}

	/**
	 * @param string $priority Raw priority slug.
	 * @return string CSS class suffix.
	 */
	public static function get_priority_badge_class( $priority ) {
		$value = strtolower( sanitize_key( (string) $priority ) );

		if ( $value === 'urgent' || $value === 'high' ) {
			return 'high';
		}
		if ( $value === 'normal' || $value === 'medium' ) {
			return 'normal';
		}

		return 'low';
	}

	/**
	 * @param int                  $user_id User ID.
	 * @param string               $email Customer email.
	 * @param array<string, mixed> $args Query args.
	 * @return array<string, mixed>
	 */
	public static function list_customer_tickets( $user_id, $email, array $args = array() ) {
		$user_id = (int) $user_id;
		$email   = sanitize_email( (string) $email );
		$saved   = self::get_saved_tickets( $user_id );
		$user    = get_userdata( $user_id );
		$name    = $user instanceof WP_User ? ( $user->display_name ?: $user->user_login ) : '';

		$search     = sanitize_text_field( (string) ( $args['search'] ?? '' ) );
		$status     = sanitize_key( (string) ( $args['status'] ?? '' ) );
		$sort_by    = sanitize_key( (string) ( $args['sort_by'] ?? 'updated_at' ) );
		$sort_order = strtolower( sanitize_key( (string) ( $args['sort_order'] ?? 'desc' ) ) ) === 'asc' ? 'asc' : 'desc';
		$page       = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page   = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );

		$api_query = array( 'per_page' => 100 );
		if ( $status !== '' ) {
			$api_query['status'] = $status;
		}

		$api_res = Licensesender_Api::list_support_tickets( $api_query );
		$remote  = array();

		if ( ! empty( $api_res['success'] ) && is_array( $api_res['tickets'] ?? null ) ) {
			foreach ( $api_res['tickets'] as $ticket ) {
				if ( ! is_array( $ticket ) ) {
					continue;
				}

				$ticket_email = sanitize_email( (string) ( $ticket['customer_email'] ?? '' ) );
				if ( $email !== '' && $ticket_email !== '' && strcasecmp( $ticket_email, $email ) !== 0 ) {
					continue;
				}

				$number = self::extract_ticket_number( $ticket );
				if ( $number === '' ) {
					continue;
				}

				$remote[ $number ] = $ticket;
				self::persist_ticket_snapshot( $user_id, $number, $email, $ticket );
			}
		}

		$merged  = array();
		$numbers = array_unique( array_merge( array_keys( $saved ), array_keys( $remote ) ) );

		foreach ( $numbers as $number ) {
			$remote_ticket = $remote[ $number ] ?? array();
			$local_ticket  = $saved[ $number ] ?? array();

			if ( empty( $remote_ticket ) ) {
				$local_email = sanitize_email( (string) ( $local_ticket['customer_email'] ?? '' ) );
				if ( $email !== '' && ( $local_email === '' || strcasecmp( $local_email, $email ) !== 0 ) ) {
					continue;
				}
			}

			$ticket_status = (string) ( $remote_ticket['status'] ?? $local_ticket['status'] ?? 'open' );

			if ( ! self::ticket_matches_status_filter( $ticket_status, $status ) ) {
				continue;
			}

			$order_id = self::latest_order_id(
				self::extract_ticket_order_id( $remote_ticket ),
				self::extract_ticket_order_id( $local_ticket )
			);

			$created_at = self::extract_ticket_created_at( $remote_ticket );
			if ( $created_at === '' ) {
				$created_at = self::extract_ticket_created_at( $local_ticket );
			}

			$updated_at = self::latest_timestamp(
				self::extract_ticket_updated_at( $remote_ticket ),
				self::extract_ticket_updated_at( $local_ticket )
			);

			$row      = array(
				'ticket_number'  => $number,
				'subject'        => (string) ( $remote_ticket['subject'] ?? $local_ticket['subject'] ?? '' ),
				'status'         => $ticket_status,
				'status_label'   => self::get_status_label( $ticket_status ),
				'status_class'   => self::get_status_badge_class( $ticket_status ),
				'category'       => (string) ( $remote_ticket['category'] ?? $local_ticket['category'] ?? '' ),
				'category_label' => self::get_category_label( (string) ( $remote_ticket['category'] ?? $local_ticket['category'] ?? '' ) ),
				'priority'       => (string) ( $remote_ticket['priority'] ?? $local_ticket['priority'] ?? '' ),
				'priority_label' => self::get_priority_label( (string) ( $remote_ticket['priority'] ?? $local_ticket['priority'] ?? '' ) ),
				'priority_class' => self::get_priority_badge_class( (string) ( $remote_ticket['priority'] ?? $local_ticket['priority'] ?? '' ) ),
				'customer_name'  => (string) ( $remote_ticket['customer_name'] ?? $name ),
				'order_id'       => $order_id,
				'order_label'    => self::format_ticket_order_label( $order_id ),
				'created_at'     => $created_at,
				'updated_at'     => $updated_at,
				'has_token'      => self::get_ticket_access_token( $user_id, $number ) !== '',
				'ticket_url'     => self::get_ticket_url( $number ),
			);

			if ( $search !== '' ) {
				$haystack = strtolower(
					implode(
						' ',
						array(
							$row['ticket_number'],
							$row['subject'],
							$row['category_label'],
							$row['status_label'],
							$row['customer_name'],
							$row['order_id'],
						)
					)
				);
				if ( ! str_contains( $haystack, strtolower( $search ) ) ) {
					continue;
				}
			}

			$merged[] = $row;
		}

		$enriched = 0;
		foreach ( $merged as $index => $row ) {
			if ( $enriched >= 20 ) {
				break;
			}

			$remote_ticket = $remote[ $row['ticket_number'] ] ?? array();
			$needs_dates   = ! ( self::ticket_has_messages( $remote_ticket ) && self::infer_latest_message_at( $remote_ticket ) !== '' );
			$needs_order   = (string) ( $row['order_id'] ?? '' ) === '';

			if ( ! $needs_dates && ! $needs_order ) {
				continue;
			}

			$detail_res = Licensesender_Api::get_support_ticket( (string) $row['ticket_number'] );
			if ( empty( $detail_res['success'] ) || ! is_array( $detail_res['ticket'] ?? null ) ) {
				continue;
			}

			$detail = $detail_res['ticket'];
			self::persist_ticket_snapshot( $user_id, (string) $row['ticket_number'], $email, $detail );

			$order_id = self::latest_order_id(
				$row['order_id'],
				self::extract_ticket_order_id( $detail )
			);
			$merged[ $index ]['order_id']    = $order_id;
			$merged[ $index ]['order_label'] = self::format_ticket_order_label( $order_id );
			$merged[ $index ]['created_at'] = self::latest_timestamp(
				$row['created_at'],
				self::extract_ticket_created_at( $detail )
			);
			$merged[ $index ]['updated_at'] = self::latest_timestamp(
				$row['updated_at'],
				self::extract_ticket_updated_at( $detail )
			);
			$enriched++;
		}

		$allowed_sort = array( 'updated_at', 'created_at', 'subject', 'status', 'priority' );
		if ( ! in_array( $sort_by, $allowed_sort, true ) ) {
			$sort_by = 'updated_at';
		}

		usort(
			$merged,
			static function ( $a, $b ) use ( $sort_by, $sort_order ) {
				$left  = strtolower( (string) ( $a[ $sort_by ] ?? '' ) );
				$right = strtolower( (string) ( $b[ $sort_by ] ?? '' ) );

				if ( in_array( $sort_by, array( 'updated_at', 'created_at' ), true ) ) {
					$left  = strtotime( (string) ( $a[ $sort_by ] ?? '' ) ) ?: 0;
					$right = strtotime( (string) ( $b[ $sort_by ] ?? '' ) ) ?: 0;
					$cmp   = $left <=> $right;
				} else {
					$cmp = strcmp( $left, $right );
				}

				return $sort_order === 'asc' ? $cmp : -$cmp;
			}
		);

		$total       = count( $merged );
		$total_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 1;
		$page        = min( $page, max( 1, $total_pages ) );
		$offset      = ( $page - 1 ) * $per_page;
		$tickets     = array_slice( $merged, $offset, $per_page );

		return array(
			'tickets'     => $tickets,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $total_pages,
		);
	}

	/**
	 * @param array<int, array> $files $_FILES style list.
	 * @return array<int, array>|WP_Error
	 */
	public static function validate_uploads( array $files ) {
		$allowed      = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'mp4', 'webm', 'mov', 'm4v' );
		$max_mb       = (int) apply_filters( 'ls_support_max_upload_mb', 5 );
		$max_b        = max( 1, $max_mb ) * 1024 * 1024;
		$max_files    = max( 1, (int) apply_filters( 'ls_support_max_upload_count', 5 ) );
		$max_total_mb = (int) apply_filters( 'ls_support_max_total_upload_mb', 20 );
		$max_total_b  = max( 1, $max_total_mb ) * 1024 * 1024;
		$clean        = array();
		$total_size   = 0;

		foreach ( $files as $file ) {
			if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
				continue;
			}

			if ( count( $clean ) >= $max_files ) {
				return new WP_Error(
					'upload_count',
					sprintf(
						/* translators: %d: max number of attachments */
						__( 'You can attach up to %d files.', 'licensesender' ),
						$max_files
					)
				);
			}

			if ( ! empty( $file['error'] ) && (int) $file['error'] !== UPLOAD_ERR_OK ) {
				return new WP_Error( 'upload_error', __( 'One of the attachments failed to upload.', 'licensesender' ) );
			}

			$file_size = (int) ( $file['size'] ?? 0 );
			if ( $file_size > $max_b ) {
				return new WP_Error(
					'upload_too_large',
					sprintf(
						/* translators: %d: max file size in MB */
						__( 'Attachments must be %d MB or smaller.', 'licensesender' ),
						$max_mb
					)
				);
			}

			$total_size += $file_size;
			if ( $total_size > $max_total_b ) {
				return new WP_Error(
					'upload_total_too_large',
					sprintf(
						/* translators: %d: max total upload size in MB */
						__( 'Total attachment size must be %d MB or smaller.', 'licensesender' ),
						$max_total_mb
					)
				);
			}

			$file['name'] = self::sanitize_upload_filename( $file['name'] ?? '' );
			$check        = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
			$ext          = strtolower( (string) ( $check['ext'] ?? '' ) );
			if ( $ext === '' || ! in_array( $ext, $allowed, true ) ) {
				return new WP_Error( 'upload_type', __( 'Unsupported attachment type.', 'licensesender' ) );
			}

			$clean[] = $file;
		}

		return $clean;
	}

	/**
	 * Normalize $_FILES for multiple attachments.
	 *
	 * @return array<int, array>
	 */
	public static function normalize_uploaded_files() {
		if ( empty( $_FILES['attachments'] ) || ! is_array( $_FILES['attachments'] ) ) {
			return array();
		}

		$input = $_FILES['attachments'];
		if ( empty( $input['name'] ) || ! is_array( $input['name'] ) ) {
			return isset( $input['tmp_name'] ) ? array( $input ) : array();
		}

		$files = array();
		foreach ( $input['name'] as $index => $name ) {
			if ( empty( $input['tmp_name'][ $index ] ) ) {
				continue;
			}
			$files[] = array(
				'name'     => $name,
				'type'     => $input['type'][ $index ] ?? '',
				'tmp_name' => $input['tmp_name'][ $index ],
				'error'    => $input['error'][ $index ] ?? 0,
				'size'     => $input['size'][ $index ] ?? 0,
			);
		}

		return $files;
	}
}
