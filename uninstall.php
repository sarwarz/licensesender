<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'ls_cached_licenses',
	$wpdb->prefix . 'ls_download_links',
	$wpdb->prefix . 'ls_activation_guides',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

$options = array(
	'lship_do_activation_redirect',
	'lship_autocomplete_order',
	'lship_send_email_after_redeem',
	'lship_enable_variation_support',
	'lship_enable_manage_downloads',
	'lship_enable_manage_activation_guides',
	'lship_api_key',
	'lship_api_base_url',
	'lship_email_send_mode',
	'lship_email_sender_name',
	'lship_email_sender_email',
	'lship_email_subject',
	'lship_email_template',
	'lship_support_email',
	'lship_sso_enabled',
	'lship_sso_token',
	'lship_sso_user_email',
	'ls_brand',
	'ls_brand_2',
	'ls_ring',
	'ls_success',
	'ls_success_2',
	'ls_code_bg',
	'ls_code_fg',
	'ls_code_border',
	'ls_code_accent',
	'ls_sw_confirm_title',
	'ls_sw_confirm_text',
	'license_shipper_db_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

flush_rewrite_rules();
