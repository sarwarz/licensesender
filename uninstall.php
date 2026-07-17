<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'ls_cached_licenses',
	$wpdb->prefix . 'ls_download_links',
	$wpdb->prefix . 'ls_activation_guides',
	$wpdb->prefix . 'ls_wholesale_applications',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

if ( get_role( 'ls_wholesale' ) ) {
	remove_role( 'ls_wholesale' );
}

$options = array(
	'lship_do_activation_redirect',
	'ls_setup_complete',
	'lship_autocomplete_order',
	'lship_send_email_after_redeem',
	'lship_enable_variation_support',
	'lship_enable_manage_downloads',
	'lship_enable_manage_activation_guides',
	'lship_api_key',
	'lship_email_send_mode',
	'lship_email_sender_name',
	'lship_email_sender_email',
	'lship_email_subject',
	'lship_email_template',
	'lship_support_email',
	'lship_support_enabled',
	'lship_support_open_page_id',
	'lship_support_manage_page_id',
	'lship_support_my_account',
	'lship_chat_enabled',
	'lship_chat_require_email',
	'lship_chat_welcome',
	'lship_chat_color',
	'lship_sso_enabled',
	'lship_sso_token',
	'lship_sso_user_email',
	'lship_webhook_secret',
	'lship_wholesale_enabled',
	'lship_wholesale_catalog_per_page',
	'lship_wholesale_low_stock_threshold',
	'lship_wholesale_min_order_quantity',
	'lship_wholesale_allow_backorders',
	'lship_wholesale_apply_page_id',
	'lship_wholesale_catalog_page_id',
	'lship_wholesale_notify_email',
	'lship_wholesale_payment_mode',
	'lship_wholesale_payment_gateways',
	'ls_wholesale_placeholder_attachment_id',
	'ls_brand',
	'ls_brand_2',
	'ls_theme_preset',
	'ls_accent',
	'ls_radius',
	'ls_density',
	'ls_code_style',
	'ls_email_sync_brand',
	'ls_ring',
	'ls_success',
	'ls_success_2',
	'ls_code_bg',
	'ls_code_fg',
	'ls_code_border',
	'ls_code_accent',
	'ls_sw_confirm_title',
	'ls_sw_confirm_text',
	'licensesender_db_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

delete_transient( 'ls_wholesale_catalog_v2' );

flush_rewrite_rules();
