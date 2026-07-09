<?php

/**
 * Fired during plugin activation
 *
 * @link       https://licenseshipper.com
 * @since      1.0.0
 *
 * @package    License_Shipper
 * @subpackage License_Shipper/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    License_Shipper
 * @subpackage License_Shipper/includes
 * @author     License Shipper <hello@licenseshipper.com>
 */
class License_Shipper_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {

	    self::ls_create_license_cache_table();
	    self::ls_create_download_links_table();
	    self::ls_create_activation_guides_table();

	    update_option( 'license_shipper_db_version', LICENSE_SHIPPER_VERSION );

	    require_once plugin_dir_path( __FILE__ ) . 'class-ls-my-keys-endpoint.php';
	    if ( class_exists( 'LS_My_Keys_Endpoint' ) ) {
	        LS_My_Keys_Endpoint::activate();
	    }

	    // Redirect to settings on first activation
	    update_option('lship_do_activation_redirect', true);


	    /* =========================
	       General
	    ========================= */
	    update_option('lship_autocomplete_order', 'yes');
	    update_option('lship_send_email_after_redeem', 'no');

	    /* =========================
	       API
	    ========================= */
	    update_option('lship_api_key', '');
	    update_option('lship_api_base_url', 'https://app.licenseshipper.com/api/');

	    /* =========================
	       Email
	    ========================= */
	    
	    update_option('lship_email_send_mode', 'after_all');
	    update_option('lship_email_sender_name', get_bloginfo('name'));
	    update_option('lship_email_sender_email', get_option('admin_email'));
	    update_option('lship_email_subject', 'Your License Key');
	    update_option('lship_brand_color', '#4f46e5');
	    update_option('lship_accent_color', '#0EA5E9');
	    update_option('lship_email_template', wp_kses_post(<<<HTML
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
	HTML));

	    /* =========================
	       Design – brand & code colors
	       (used to output :root CSS vars)
	       ========================= */
	    update_option('ls_brand',        '#4f46e5'); // brand start
	    update_option('ls_brand_2',      '#6366f1'); // brand end
	    update_option('ls_ring',         '#6366f1'); // base (alpha added when outputting)
	    update_option('ls_success',      '#059669'); // View Key start
	    update_option('ls_success_2',    '#10b981'); // View Key end
	    update_option('ls_code_bg',      '#1e1e2e');
	    update_option('ls_code_fg',      '#cdd6f4');
	    update_option('ls_code_border',  '#313244');
	    update_option('ls_code_accent',  '#89b4fa');

	    /* =========================
	       UI text – SweetAlert “Ask first”
	       ========================= */
	    update_option('ls_sw_confirm_title', __('Get license keys?', 'license-shipper'));
	    update_option('ls_sw_confirm_text',  __('We will fetch your license keys for {product}. Continue?', 'license-shipper'));
	}



	public static function ls_create_license_cache_table() {
	    global $wpdb;

	    $table_name = $wpdb->prefix . 'ls_cached_licenses';
	    $charset_collate = $wpdb->get_charset_collate();

	    $sql = "CREATE TABLE $table_name (
	        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	        order_id BIGINT NOT NULL,
	        product_id BIGINT NOT NULL,
	        sku VARCHAR(255),
	        email VARCHAR(255),
	        key_value VARCHAR(191) NOT NULL,
	        download_link TEXT,
	        activation_guide TEXT,
	        source VARCHAR(100),
	        fetched TINYINT(1) DEFAULT 1,
	        email_sent TINYINT(1) DEFAULT 0,
	        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	        PRIMARY KEY  (id),
	        KEY order_product_idx (order_id, product_id),
	        KEY email_sent_idx (email_sent),
	        KEY fetched_idx (fetched)
	    ) $charset_collate;";

	    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	    dbDelta($sql);
	}


	/**
	 * Create download links table
	 */
	public static function ls_create_download_links_table() {
	    global $wpdb;

	    $table_name = $wpdb->prefix . 'ls_download_links';
	    $charset_collate = $wpdb->get_charset_collate();

	    $sql = "CREATE TABLE $table_name (
	        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	        product_id BIGINT NOT NULL,
	        link TEXT NOT NULL,
	        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	        PRIMARY KEY (id),
	        KEY product_idx (product_id)
	    ) $charset_collate;";

	    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	    dbDelta($sql);
	}

	/**
	 * Create Activation Guides table
	 */
	public static function ls_create_activation_guides_table() {
	    global $wpdb;

	    $table_name = $wpdb->prefix . 'ls_activation_guides';
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

	    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	    dbDelta($sql);
	}

	





}
