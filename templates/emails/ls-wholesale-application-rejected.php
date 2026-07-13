<?php
/**
 * Wholesale application rejected (customer).
 *
 * @package Licensesender
 *
 * @var array<string, mixed> $application
 * @var string               $email_heading
 * @var string               $additional_content
 * @var bool                 $sent_to_admin
 * @var bool                 $plain_text
 * @var WC_Email             $email
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );

$company = (string) ( $application['company_name'] ?? '' );
?>

<p>
	<?php
	if ( $company !== '' ) {
		/* translators: %s: company name */
		printf( esc_html__( 'Hello %s,', 'licensesender' ), esc_html( $company ) );
	} else {
		esc_html_e( 'Hello,', 'licensesender' );
	}
	?>
</p>

<p><?php esc_html_e( 'Thank you for your interest in our wholesale program. After reviewing your application, we are unable to approve wholesale access at this time.', 'licensesender' ); ?></p>

<?php if ( ! empty( $application['admin_note'] ) ) : ?>
	<p><strong><?php esc_html_e( 'Note from our team:', 'licensesender' ); ?></strong></p>
	<p><?php echo wp_kses_post( nl2br( esc_html( (string) $application['admin_note'] ) ) ); ?></p>
<?php endif; ?>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
