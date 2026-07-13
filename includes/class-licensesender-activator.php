<?php

/**
 * Fired during plugin activation
 *
 * @link       https://licensesender.com
 * @since      1.0.0
 *
 * @package    Licensesender
 * @subpackage Licensesender/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Licensesender
 * @subpackage Licensesender/includes
 * @author     licensesender <hello@licensesender.com>
 */
class Licensesender_Activator {

	/**
	 * Run on plugin activation.
	 *
	 * Options are seeded with add_option() only (never overwrite existing values).
	 *
	 * @since    1.0.0
	 */
	public static function activate() {

		self::ls_create_license_cache_table();
		self::ls_create_download_links_table();
		self::ls_create_activation_guides_table();

		require_once plugin_dir_path( __FILE__ ) . 'class-ls-wholesale.php';
		LS_Wholesale::create_applications_table();
		LS_Wholesale::register_role();

		update_option( 'licensesender_db_version', LICENSESENDER_VERSION );

		require_once plugin_dir_path( __FILE__ ) . 'class-ls-my-keys-endpoint.php';
		if ( class_exists( 'LS_My_Keys_Endpoint' ) ) {
			LS_My_Keys_Endpoint::activate();
		}

		require_once plugin_dir_path( __FILE__ ) . 'class-ls-support-endpoint.php';
		if ( class_exists( 'LS_Support_Endpoint' ) ) {
			LS_Support_Endpoint::activate();
		}

		// Always open the setup wizard after (re)activation.
		update_option( 'ls_setup_complete', 'no', false );
		update_option( 'lship_do_activation_redirect', '1', false );
		set_transient( 'ls_activation_redirect', '1', 5 * MINUTE_IN_SECONDS );

		// Incoming webhook secret for license.replaced events
		require_once plugin_dir_path( __FILE__ ) . 'class-ls-webhook-receiver.php';
		LS_Webhook_Receiver::ensure_secret();

		self::seed_default_options();
	}

	/**
	 * Seed defaults without clobbering an existing install (e.g. Reactivate).
	 */
	private static function seed_default_options() {
		/* =========================
		   General
		========================= */
		add_option( 'lship_autocomplete_order', 'yes' );
		add_option( 'lship_send_email_after_redeem', 'no' );

		/* =========================
		   API — never blank an existing key
		========================= */
		add_option( 'lship_api_key', '' );

		/* =========================
		   Email
		========================= */
		add_option( 'lship_email_send_mode', 'after_all' );
		add_option( 'lship_email_sender_name', get_bloginfo( 'name' ) );
		add_option( 'lship_email_sender_email', get_option( 'admin_email' ) );
		add_option( 'lship_email_subject', 'Your License Key' );
		add_option( 'lship_brand_color', '#4f46e5' );
		add_option( 'lship_accent_color', '#0EA5E9' );
		add_option(
			'lship_email_template',
			wp_kses_post(
				<<<HTML
	<p>Dear {customer_name},</p>

	<p>Thank you for your order <strong>#{order_id}</strong>. Below are your license details. Please keep them safe for future reference.</p>

	<table style="border-collapse: collapse; font-family: Arial, sans-serif;" border="1" width="100%" cellspacing="0" cellpadding="8">
	    <thead style="background-color: #f2f2f2;">
	        <tr>
	            <th align="left">Product</th>
	            <th align="left">License Key</th>
	            <th align="left">Download</th>
	            <th align="left">Activation Guide</th>
	        </tr>
	    </thead>
	    <tbody>
	        <tr>
	            <td>{product}</td>
	            <td><code>{key}</code></td>
	            <td><a href="{download_link}" target="_blank" rel="noopener">Download</a></td>
	            <td><a href="{instruction}" target="_blank" rel="noopener">View Guide</a></td>
	        </tr>
	    </tbody>
	</table>

	<p>If you have any questions or need further assistance, feel free to contact our support team.</p>

	<p>Best regards,<br>
	<strong>{vendor_name}</strong><br>
	<a href="{vendor_url}">{vendor_url}</a></p>
	HTML
			)
		);

		/* =========================
		   Design system
		========================= */
		add_option( 'ls_theme_preset', 'indigo' );
		add_option( 'ls_brand', '#4f46e5' );
		add_option( 'ls_accent', '#2563eb' );
		add_option( 'ls_radius', 'md' );
		add_option( 'ls_density', 'comfortable' );
		add_option( 'ls_code_style', 'dark' );
		add_option( 'ls_email_sync_brand', 'yes' );
		add_option( 'ls_brand_2', '#6366f1' );
		add_option( 'ls_ring', '#6366f1' );
		add_option( 'ls_success', '#059669' );
		add_option( 'ls_success_2', '#10b981' );
		add_option( 'ls_code_bg', '#1e1e2e' );
		add_option( 'ls_code_fg', '#cdd6f4' );
		add_option( 'ls_code_border', '#313244' );
		add_option( 'ls_code_accent', '#89b4fa' );

		/* =========================
		   UI text – SweetAlert “Ask first”
		========================= */
		add_option( 'ls_sw_confirm_title', __( 'Get license keys?', 'licensesender' ) );
		add_option( 'ls_sw_confirm_text', __( 'We will fetch your license keys for {product}. Continue?', 'licensesender' ) );
	}

	public static function ls_create_license_cache_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'ls_cached_licenses';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
	        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	        order_id BIGINT NOT NULL,
	        product_id BIGINT NOT NULL,
	        sku VARCHAR(255),
	        email VARCHAR(255),
	        key_value VARCHAR(191) NOT NULL,
	        remote_license_id BIGINT UNSIGNED NULL,
	        download_link TEXT,
	        activation_guide TEXT,
	        source VARCHAR(100),
	        fetched TINYINT(1) DEFAULT 1,
	        email_sent TINYINT(1) DEFAULT 0,
	        last_synced_at DATETIME NULL,
	        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	        PRIMARY KEY  (id),
	        KEY order_product_idx (order_id, product_id),
	        KEY email_sent_idx (email_sent),
	        KEY fetched_idx (fetched),
	        KEY remote_license_idx (remote_license_id)
	    ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create download links table
	 */
	public static function ls_create_download_links_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'ls_download_links';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
	        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	        product_id BIGINT NOT NULL,
	        link TEXT NOT NULL,
	        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	        PRIMARY KEY (id),
	        KEY product_idx (product_id)
	    ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create Activation Guides table
	 */
	public static function ls_create_activation_guides_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'ls_activation_guides';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
	        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	        product_id BIGINT NOT NULL,
	        type VARCHAR(50) NOT NULL DEFAULT 'text',
	        content LONGTEXT,
	        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	        PRIMARY KEY  (id),
	        KEY product_idx (product_id)
	    ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
