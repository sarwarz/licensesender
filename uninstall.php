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
	'ls_chat_widget',
	'ls_admin_ui_version',
	'lship_autocomplete_order',
	'lship_delivery_start_date',
	'lship_activated_at',
	'lship_guide_download_secret',
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
	'lship_email_sender',
	'lship_email_subject_single',
	'lship_email_subject_bulk',
	'lship_email_preheader',
	'lship_email_intro_single',
	'lship_email_intro_bulk',
	'lship_email_logo',
	'lship_brand_color',
	'lship_accent_color',
	'ls_license_email_imported',
	'woocommerce_ls_license_keys_settings',
	'lship_support_enabled',
	'lship_support_open_page_id',
	'lship_support_manage_page_id',
	'lship_support_my_account',
	'lship_support_auth_my_account',
	'lship_chat_enabled',
	'lship_chat_color',
	// Legacy appearance options (removed from settings UI; still deleted on uninstall).
	'lship_chat_require_email',
	'lship_chat_title',
	'lship_chat_welcome',
	'lship_chat_launcher_style',
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
	'lship_wholesale_auto_approve',
	'lship_wholesale_auto_approve_group',
	'lship_wholesale_hold_minutes',
	// Dropping the applications table above means its version must go too, or a reinstall
	// would think the table exists and never create it.
	'ls_wholesale_db_version',
	'ls_wholesale_catalog_version',
	'ls_wholesale_portal_rewrite',
	'ls_wholesale_placeholder_attachment_id',
	'ls_brand',
	'ls_brand_2',
	'ls_color_mode',
	// Retired design options (theme packs, accent, density, key block style).
	'ls_theme_preset',
	'ls_blue_600',
	'ls_blue_500',
	'ls_amber_500',
	'ls_amber_400',
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
	// Key texts (and the retired pop-up options that shared the ls_sw_ prefix).
	'ls_sw_confirm_title',
	'ls_sw_confirm_text',
	'ls_sw_confirm_btn',
	'ls_sw_cancel_btn',
	'ls_sw_view_title',
	'ls_sw_view_title_many',
	'ls_sw_view_copy_all',
	'ls_sw_confirm_color',
	'ls_sw_cancel_color',
	'ls_sw_bulk_title',
	'ls_sw_bulk_text',
	'ls_sw_bulk_confirm_btn',
	'ls_sw_bulk_cancel_btn',
	'ls_sw_bulk_done_title',
	'ls_sw_bulk_done_text',
	'ls_sw_view_close',
	'licensesender_db_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

delete_transient( 'ls_wholesale_catalog_v2' );
delete_transient( 'ls_wholesale_groups' );
// Buyers' wholesale groups.
delete_metadata( 'user', 0, '_ls_wholesale_group', '', true );
foreach ( array( 'ls_wholesale_cancel_unpaid_order', 'ls_send_license_email_event', 'ls_push_order_to_saas', 'ls_backfill_orders_to_saas' ) as $ls_hook ) {
	wp_unschedule_hook( $ls_hook );
}
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'ls_wholesale_cancel_unpaid_order' );
}

flush_rewrite_rules();
