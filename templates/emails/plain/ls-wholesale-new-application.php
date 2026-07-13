<?php
/**
 * New wholesale application (admin, plain text).
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

esc_html_e( 'A new wholesale application was submitted.', 'licensesender' );
echo "\n\n";

wc_get_template(
	'emails/ls-wholesale-application-details.php',
	array(
		'application' => $application,
		'plain_text'  => true,
	),
	'',
	plugin_dir_path( dirname( __DIR__ ) ) . 'templates/'
);

echo "\n" . esc_html__( 'Review application', 'licensesender' ) . ': ' . esc_url( $admin_review_url ) . "\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
