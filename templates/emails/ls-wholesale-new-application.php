<?php
/**
 * New wholesale application (admin).
 *
 * @package Licensesender
 *
 * @var array<string, mixed> $application
 * @var string               $email_heading
 * @var string               $additional_content
 * @var bool                 $sent_to_admin
 * @var bool                 $plain_text
 * @var WC_Email             $email
 * @var string               $admin_review_url
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php esc_html_e( 'A new wholesale application was submitted.', 'licensesender' ); ?></p>

<?php
wc_get_template(
	'emails/ls-wholesale-application-details.php',
	array(
		'application' => $application,
		'plain_text'  => false,
	),
	'',
	plugin_dir_path( dirname( __DIR__ ) ) . 'templates/'
);
?>

<p>
	<a class="link" href="<?php echo esc_url( $admin_review_url ); ?>">
		<?php esc_html_e( 'Review application', 'licensesender' ); ?>
	</a>
</p>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
