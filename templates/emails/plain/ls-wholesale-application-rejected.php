<?php
/**
 * Wholesale application rejected (customer, plain text).
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

$company = (string) ( $application['company_name'] ?? '' );

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

if ( $company !== '' ) {
	/* translators: %s: company name */
	printf( esc_html__( 'Hello %s,', 'licensesender' ), esc_html( $company ) );
} else {
	esc_html_e( 'Hello,', 'licensesender' );
}
echo "\n\n";

esc_html_e( 'Thank you for your interest in our wholesale program. After reviewing your application, we are unable to approve wholesale access at this time.', 'licensesender' );
echo "\n\n";

if ( ! empty( $application['admin_note'] ) ) {
	echo esc_html__( 'Note from our team:', 'licensesender' ) . "\n";
	echo esc_html( (string) $application['admin_note'] ) . "\n\n";
}

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
