<?php
/**
 * License keys email (plain text).
 *
 * @package Licensesender
 *
 * @var WC_Order|null                    $order
 * @var array<int, array<string, mixed>> $entries
 * @var string                           $email_heading
 * @var string                           $additional_content
 * @var WC_Email                         $email
 */

defined( 'ABSPATH' ) || exit;

echo "=================================================================\n";
echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n";
echo "=================================================================\n\n";

if ( $order ) {
	/* translators: %s: order number */
	echo esc_html( sprintf( __( 'Order #%s', 'licensesender' ), $order->get_order_number() ) ) . "\n\n";
} else {
	echo esc_html__( 'This is a test of the license keys email, with sample keys.', 'licensesender' ) . "\n\n";
}

foreach ( $entries as $entry ) {
	echo esc_html( (string) $entry['name'] ) . "\n";
	foreach ( (array) $entry['keys'] as $key ) {
		echo '  ' . esc_html( (string) $key ) . "\n";
	}
	if ( ! empty( $entry['download'] ) ) {
		echo '  ' . esc_html__( 'Download:', 'licensesender' ) . ' ' . esc_url_raw( (string) $entry['download'] ) . "\n";
	}
	if ( ! empty( $entry['guide'] ) ) {
		echo '  ' . esc_html__( 'Activation guide:', 'licensesender' ) . ' ' . esc_url_raw( (string) $entry['guide'] ) . "\n";
	}
	echo "\n";
}

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
