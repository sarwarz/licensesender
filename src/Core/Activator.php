<?php

namespace LicenseSender\Core;

use LicenseSender\Support\AccountEndpoint;
use LicenseSender\Licenses\MyKeysEndpoint;
use LicenseSender\Orders\WebhookReceiver;
use LicenseSender\Wholesale\Wholesale;

defined( 'ABSPATH' ) || exit;

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
class Activator {

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

		Wholesale::create_applications_table();
		Wholesale::register_role();

		update_option( 'licensesender_db_version', LICENSESENDER_VERSION );

		if ( class_exists( MyKeysEndpoint::class ) ) {
			MyKeysEndpoint::activate();
		}

		if ( class_exists( AccountEndpoint::class ) ) {
			AccountEndpoint::activate();
		}

		// Always open the setup wizard after (re)activation.
		update_option( 'ls_setup_complete', 'no', false );
		update_option( 'lship_do_activation_redirect', '1', false );
		set_transient( 'ls_activation_redirect', '1', 5 * MINUTE_IN_SECONDS );

		// Incoming webhook secret for license.replaced events
		WebhookReceiver::ensure_secret();

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

		$activated = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d' ) : gmdate( 'Y-m-d' );
		add_option( 'lship_activated_at', $activated );
		add_option( 'lship_delivery_start_date', $activated );

		/* =========================
		   API — never blank an existing key
		========================= */
		add_option( 'lship_api_key', '' );

		// License email: a WooCommerce email (WooCommerce → Settings → Emails → License keys); off until turned on.

		/* =========================
		   Design system
		========================= */
		add_option( 'ls_brand', '#4f46e5' );
		add_option( 'ls_radius', 'md' );
		add_option( 'ls_color_mode', 'auto' );
		// Key delivery texts (ls_sw_*) start empty: the built-in, translatable text shows until the store types its own.
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
