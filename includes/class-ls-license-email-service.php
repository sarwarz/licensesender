<?php
defined( 'ABSPATH' ) || exit;

class LS_License_Email_Service {

	const ORDER_META_SENT = '_ls_license_email_sent';
	const CRON_HOOK       = 'ls_send_license_email_event';
	const DEBOUNCE_SEC    = 10;

	public static function is_enabled(): bool {
		return get_option( 'lship_send_email_after_redeem' ) === 'yes';
	}

	public static function is_order_email_sent( int $order_id ): bool {
		$order = wc_get_order( $order_id );
		if ( $order && $order->get_meta( self::ORDER_META_SENT, true ) === 'yes' ) {
			return true;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ls_cached_licenses';

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE order_id = %d AND email_sent = 1", $order_id )
		) > 0;
	}

	public static function is_order_ready_for_email( int $order_id ): bool {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$expected = ls_count_expected_license_keys( $order );
		$fetched  = ls_count_fetched_license_keys( $order_id );

		if ( $expected <= 0 ) {
			return false;
		}

		return $fetched >= $expected;
	}

	public static function maybe_schedule_after_fetch( WC_Order $order, string $email ): void {
		if ( ! self::is_enabled() || ! is_email( $email ) ) {
			return;
		}

		if ( self::is_order_email_sent( $order->get_id() ) ) {
			return;
		}

		if ( ! self::is_order_ready_for_email( $order->get_id() ) ) {
			return;
		}

		self::schedule_order_email( $order->get_id(), $email );
	}

	public static function schedule_order_email( int $order_id, string $email ): void {
		if ( self::is_order_email_sent( $order_id ) ) {
			return;
		}

		$args = array( $order_id, $email );
		if ( wp_next_scheduled( self::CRON_HOOK, $args ) ) {
			return;
		}

		wp_schedule_single_event( time() + self::DEBOUNCE_SEC, self::CRON_HOOK, $args );
	}

	/**
	 * @param bool $force Skip duplicate guard (resend).
	 */
	public static function send_order_email( int $order_id, string $email, bool $force = false ): bool {
		if ( ! is_email( $email ) ) {
			return false;
		}

		if ( ! $force && self::is_order_email_sent( $order_id ) ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$licenses = self::get_order_licenses( $order_id );
		if ( empty( $licenses ) ) {
			return false;
		}

		$context = self::build_email_context( $order, $licenses );
		$html    = self::render_html( $context );
		$text    = self::render_plain_text( $context );
		$headers = self::build_headers( $context['site_name'] );

		$sent = self::dispatch_mail( $email, $context['subject'], $html, $text, $headers );

		if ( $sent ) {
			self::mark_order_email_sent( $order_id );
		} else {
			self::log_failure( $order_id, $email );
		}

		return (bool) $sent;
	}

	public static function send_test_email( string $email, string $mode = 'bulk' ): bool {
		if ( ! is_email( $email ) ) {
			return false;
		}

		$licenses = self::get_mock_licenses( $mode );
		$context  = self::build_mock_context( $licenses, $mode );
		$html     = self::render_html( $context );
		$text     = self::render_plain_text( $context );
		$headers  = self::build_headers( $context['site_name'] );
		$subject  = sprintf(
			/* translators: %s: site name */
			__( 'Test: %s', 'licensesender' ),
			$context['subject']
		);

		return (bool) self::dispatch_mail( $email, $subject, $html, $text, $headers );
	}

	private static function get_order_licenses( int $order_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_cached_licenses';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, product_id, key_value, download_link, activation_guide FROM {$table} WHERE order_id = %d AND fetched = 1 ORDER BY id ASC",
				$order_id
			)
		);
	}

	private static function build_email_context( WC_Order $order, array $licenses ): array {
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$count     = count( $licenses );
		$is_single = $count === 1;

		$default_subject = $is_single
			? get_option( 'lship_email_subject', __( 'Your License Key', 'licensesender' ) )
			: get_option( 'lship_email_subject', sprintf( __( 'Your License Keys from %s', 'licensesender' ), $site_name ) );

		$subject_single = get_option( 'lship_email_subject_single', '' );
		$subject_bulk   = get_option( 'lship_email_subject_bulk', '' );
		$subject        = $is_single
			? ( $subject_single ?: $default_subject )
			: ( $subject_bulk ?: $default_subject );

		$intro_default_single = sprintf(
			/* translators: %s: order date */
			__( 'Thanks for your purchase on %s. Below is your license key with download and activation links.', 'licensesender' ),
			date_i18n( get_option( 'date_format', 'M j, Y' ), $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time() )
		);
		$intro_default_bulk = sprintf(
			/* translators: %s: order date */
			__( 'Thanks for your purchase on %s. Below you will find your keys, downloads, and activation guides.', 'licensesender' ),
			date_i18n( get_option( 'date_format', 'M j, Y' ), $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time() )
		);

		$code_bg     = get_option( 'ls_code_bg', '#1e1e2e' );
		$code_fg     = get_option( 'ls_code_fg', '#cdd6f4' );
		$code_border = get_option( 'ls_code_border', '#313244' );

		return array(
			'site_name'      => $site_name,
			'subject'        => $subject,
			'headline'       => $is_single
				? __( 'Your license key is ready', 'licensesender' )
				: __( 'Your license keys are ready', 'licensesender' ),
			'intro'          => $is_single
				? ( get_option( 'lship_email_intro_single', '' ) ?: $intro_default_single )
				: ( get_option( 'lship_email_intro_bulk', '' ) ?: $intro_default_bulk ),
			'preheader'      => get_option( 'lship_email_preheader', '' ) ?: __( 'Your license keys and download links are inside.', 'licensesender' ),
			'brand_color'    => get_option( 'lship_brand_color', '#4F46E5' ),
			'accent_color'   => get_option( 'lship_accent_color', '#0EA5E9' ),
			'text_color'     => '#111827',
			'muted_color'    => '#6B7280',
			'bg_color'       => '#F3F4F6',
			'card_bg'        => '#FFFFFF',
			'border_color'   => '#E5E7EB',
			'code_bg'        => $code_bg,
			'code_fg'        => $code_fg,
			'code_border'    => $code_border,
			'logo_url'       => esc_url( get_option( 'lship_email_logo', get_site_icon_url() ) ),
			'support_email'  => is_email( get_option( 'lship_support_email' ) ) ? get_option( 'lship_support_email' ) : get_option( 'admin_email' ),
			'customer_name'  => esc_html( $order->get_formatted_billing_full_name() ?: 'there' ),
			'order_number'   => esc_html( $order->get_order_number() ),
			'order_date'     => esc_html( date_i18n( get_option( 'date_format', 'M j, Y' ), $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time() ) ),
			'rows_html'      => self::build_rows_html( $licenses, $order ),
			'license_count'  => $count,
		);
	}

	private static function build_mock_context( array $licenses, string $mode ): array {
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$is_single = $mode === 'single';
		$count     = count( $licenses );

		$default_subject = $is_single
			? get_option( 'lship_email_subject', __( 'Your License Key', 'licensesender' ) )
			: get_option( 'lship_email_subject', sprintf( __( 'Your License Keys from %s', 'licensesender' ), $site_name ) );

		$subject_single = get_option( 'lship_email_subject_single', '' );
		$subject_bulk   = get_option( 'lship_email_subject_bulk', '' );
		$subject        = $is_single
			? ( $subject_single ?: $default_subject )
			: ( $subject_bulk ?: $default_subject );

		$intro_default_single = __( 'Thanks for your purchase. Below is your license key with download and activation links.', 'licensesender' );
		$intro_default_bulk   = __( 'Thanks for your purchase. Below you will find your keys, downloads, and activation guides.', 'licensesender' );

		$context = array(
			'site_name'      => $site_name,
			'subject'        => $subject,
			'headline'       => $is_single
				? __( 'Your license key is ready', 'licensesender' )
				: __( 'Your license keys are ready', 'licensesender' ),
			'intro'          => $is_single
				? ( get_option( 'lship_email_intro_single', '' ) ?: $intro_default_single )
				: ( get_option( 'lship_email_intro_bulk', '' ) ?: $intro_default_bulk ),
			'preheader'      => get_option( 'lship_email_preheader', '' ) ?: __( 'Your license keys and download links are inside.', 'licensesender' ),
			'brand_color'    => get_option( 'lship_brand_color', '#4F46E5' ),
			'accent_color'   => get_option( 'lship_accent_color', '#0EA5E9' ),
			'text_color'     => '#111827',
			'muted_color'    => '#6B7280',
			'bg_color'       => '#F3F4F6',
			'card_bg'        => '#FFFFFF',
			'border_color'   => '#E5E7EB',
			'code_bg'        => get_option( 'ls_code_bg', '#1e1e2e' ),
			'code_fg'        => get_option( 'ls_code_fg', '#cdd6f4' ),
			'code_border'    => get_option( 'ls_code_border', '#313244' ),
			'logo_url'       => esc_url( get_option( 'lship_email_logo', get_site_icon_url() ) ),
			'support_email'  => is_email( get_option( 'lship_support_email' ) ) ? get_option( 'lship_support_email' ) : get_option( 'admin_email' ),
			'customer_name'  => esc_html__( 'there', 'licensesender' ),
			'order_number'   => '12345',
			'order_date'     => esc_html( date_i18n( get_option( 'date_format', 'M j, Y' ) ) ),
			'license_count'  => $count,
		);

		$rows = '';
		foreach ( $licenses as $lic ) {
			$rows .= self::build_license_row_html(
				$lic['product_name'],
				$lic['key_value'],
				$lic['download_link'],
				$lic['guide_url'],
				$context
			);
		}
		$context['rows_html'] = $rows;

		return $context;
	}

	private static function get_mock_licenses( string $mode ): array {
		if ( $mode === 'single' ) {
			return array(
				array(
					'product_name'  => 'Microsoft Windows 11 Pro',
					'key_value'     => 'XXXXX-XXXXX-XXXXX-XXXXX-XXXXX',
					'download_link' => home_url( '/' ),
					'guide_url'     => home_url( '/' ),
				),
			);
		}

		return array(
			array(
				'product_name'  => 'Microsoft Windows 11 Pro',
				'key_value'     => 'AAAAA-BBBBB-CCCCC-DDDDD-EEEEE',
				'download_link' => home_url( '/' ),
				'guide_url'     => home_url( '/' ),
			),
			array(
				'product_name'  => 'Microsoft Office 2021',
				'key_value'     => 'FFFFF-GGGGG-HHHHH-IIIII-JJJJJ',
				'download_link' => home_url( '/' ),
				'guide_url'     => home_url( '/' ),
			),
		);
	}

	private static function build_rows_html( array $licenses, WC_Order $order ): string {
		$grouped = array();
		foreach ( $licenses as $lic ) {
			$pid = (int) $lic->product_id;
			if ( ! isset( $grouped[ $pid ] ) ) {
				$product = wc_get_product( $pid );
				$grouped[ $pid ] = array(
					'name' => $product ? $product->get_name() : ( 'Product #' . $pid ),
					'keys' => array(),
				);
			}
			$grouped[ $pid ]['keys'][] = $lic;
		}

		$code_bg     = get_option( 'ls_code_bg', '#1e1e2e' );
		$code_fg     = get_option( 'ls_code_fg', '#cdd6f4' );
		$code_border = get_option( 'ls_code_border', '#313244' );
		$brand       = get_option( 'lship_brand_color', '#4F46E5' );
		$accent      = get_option( 'lship_accent_color', '#0EA5E9' );
		$ctx         = compact( 'code_bg', 'code_fg', 'code_border', 'brand', 'accent' );
		$ctx['brand_color']  = $brand;
		$ctx['accent_color'] = $accent;
		$ctx['text_color']   = '#111827';
		$ctx['border_color'] = '#E5E7EB';

		$html = '';
		foreach ( $grouped as $pid => $group ) {
			foreach ( $group['keys'] as $lic ) {
				$download = filter_var( $lic->download_link, FILTER_VALIDATE_URL ) ? esc_url( $lic->download_link ) : esc_url( home_url( '/' ) );
				$guide    = esc_url( ls_activation_guide_download_url( (int) $lic->id, $order ) );
				$html    .= self::build_license_row_html( $group['name'], $lic->key_value, $download, $guide, $ctx );
			}
		}

		return $html;
	}

	private static function build_license_row_html( string $product_name, string $key, string $download, string $guide, array $ctx ): string {
		$pname = esc_html( $product_name );
		$key_e = esc_html( $key );
		$text  = esc_attr( $ctx['text_color'] ?? '#111827' );
		$border = esc_attr( $ctx['border_color'] ?? '#E5E7EB' );
		$brand  = esc_attr( $ctx['brand_color'] ?? '#4F46E5' );
		$accent = esc_attr( $ctx['accent_color'] ?? '#0EA5E9' );
		$code_bg = esc_attr( $ctx['code_bg'] ?? '#1e1e2e' );
		$code_fg = esc_attr( $ctx['code_fg'] ?? '#cdd6f4' );
		$code_border = esc_attr( $ctx['code_border'] ?? '#313244' );

		return '
		<tr class="ls-email-license-row">
			<td class="ls-email-col-product" style="padding:12px 14px; font:14px Arial,sans-serif; color:' . $text . ';">' . $pname . '</td>
			<td class="ls-email-col-key" style="padding:12px 14px;">
				<span style="display:inline-block; padding:6px 10px; background:' . $code_bg . '; color:' . $code_fg . '; border:1px solid ' . $code_border . '; border-radius:6px; font:13px/18px ui-monospace,Menlo,Consolas,monospace;">' . $key_e . '</span>
			</td>
			<td class="ls-email-col-download" align="center" style="padding:12px 14px;">
				<a href="' . esc_url( $download ) . '" target="_blank" rel="noopener noreferrer" style="display:inline-block; padding:8px 14px; text-decoration:none; border-radius:6px; border:1px solid ' . $brand . '; color:' . $brand . '; font:13px Arial,sans-serif;">' . esc_html__( 'Download', 'licensesender' ) . '</a>
			</td>
			<td class="ls-email-col-guide" align="center" style="padding:12px 14px;">
				<a href="' . esc_url( $guide ) . '" target="_blank" rel="noopener noreferrer" style="display:inline-block; padding:8px 14px; text-decoration:none; border-radius:6px; background:' . $accent . '; color:#fff; font:13px Arial,sans-serif;">' . esc_html__( 'Guide', 'licensesender' ) . '</a>
			</td>
		</tr>';
	}

	private static function render_html( array $context ): string {
		$template = plugin_dir_path( __FILE__ ) . 'templates/email/license-email-template.php';
		ob_start();
		extract( $context, EXTR_SKIP );
		include $template;
		return (string) ob_get_clean();
	}

	private static function render_plain_text( array $context ): string {
		$lines = array(
			sprintf( 'Hi %s,', $context['customer_name'] ),
			'',
			$context['headline'],
			$context['intro'],
			'',
			sprintf( 'Order #%s — %s', $context['order_number'], $context['order_date'] ),
			str_repeat( '-', 40 ),
		);

		// Strip HTML rows to simple text (rough).
		$text_rows = wp_strip_all_tags( str_replace( '</tr>', "\n", $context['rows_html'] ) );
		$lines[]   = preg_replace( '/\s+/', ' ', trim( $text_rows ) );
		$lines[]   = '';
		$lines[]   = sprintf( __( 'Need help? Contact us at %s', 'licensesender' ), $context['support_email'] );
		$lines[]   = $context['site_name'] . ' — ' . home_url();

		return implode( "\n", array_filter( $lines ) );
	}

	private static function build_headers( string $site_name ): array {
		$sender_name  = get_option( 'lship_email_sender_name', $site_name );
		$sender_email = get_option( 'lship_email_sender_email', get_option( 'admin_email' ) );

		return array(
			'From: ' . sanitize_text_field( $sender_name ) . ' <' . sanitize_email( $sender_email ) . '>',
		);
	}

	private static function dispatch_mail( string $to, string $subject, string $html, string $text, array $headers ): bool {
		$boundary = 'ls_' . wp_generate_password( 16, false );
		$body     = "--{$boundary}\r\n";
		$body    .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
		$body    .= $text . "\r\n\r\n";
		$body    .= "--{$boundary}\r\n";
		$body    .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
		$body    .= $html . "\r\n\r\n";
		$body    .= "--{$boundary}--";

		$mail_headers   = $headers;
		$mail_headers[] = 'MIME-Version: 1.0';
		$mail_headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

		if ( function_exists( 'wc_mail' ) ) {
			return (bool) wc_mail( $to, $subject, $body, $mail_headers );
		}

		return (bool) wp_mail( $to, $subject, $body, $mail_headers );
	}

	private static function mark_order_email_sent( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$order->update_meta_data( self::ORDER_META_SENT, 'yes' );
			$order->save();
		}

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'ls_cached_licenses',
			array( 'email_sent' => 1 ),
			array( 'order_id' => $order_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	private static function log_failure( int $order_id, string $email ): void {
		$message = sprintf( 'licensesender: failed to send license email for order %d to %s', $order_id, $email );
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $message, array( 'source' => 'licensesender' ) );
		} else {
			error_log( $message );
		}
	}
}
