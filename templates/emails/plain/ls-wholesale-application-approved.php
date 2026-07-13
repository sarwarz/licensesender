<?php
/**
 * Wholesale application approved (customer, plain text).
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

esc_html_e( 'Your wholesale application has been approved. You now have access to our wholesale catalog and pricing.', 'licensesender' );
echo "\n\n";
echo esc_html__( 'Browse wholesale catalog', 'licensesender' ) . ': ' . esc_url( $catalog_url ) . "\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
