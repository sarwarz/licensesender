<?php
/**
 * Wholesale application approved (customer).
 *
 * @package Licensesender
 *
 * @var array<string, mixed> $application
 * @var string               $email_heading
 * @var string               $additional_content
 * @var bool                 $sent_to_admin
 * @var bool                 $plain_text
 * @var WC_Email             $email
 * @var string               $catalog_url
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

<p><?php esc_html_e( 'Your wholesale application has been approved. You now have access to our wholesale catalog and pricing.', 'licensesender' ); ?></p>

<p>
	<a class="link" href="<?php echo esc_url( $catalog_url ); ?>">
		<?php esc_html_e( 'Browse wholesale catalog', 'licensesender' ); ?>
	</a>
</p>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
