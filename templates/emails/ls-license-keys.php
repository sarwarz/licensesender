<?php
/**
 * License keys email (HTML), in the store's WooCommerce email style.
 *
 * Override by copying to yourtheme/woocommerce/emails/ls-license-keys.php.
 *
 * @package Licensesender
 *
 * @var WC_Order|null                    $order
 * @var array<int, array<string, mixed>> $entries [ name, keys[], download, guide ]
 * @var string                           $email_heading
 * @var string                           $additional_content
 * @var WC_Email                         $email
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );

$first_name = $order ? $order->get_billing_first_name() : '';
$key_total  = 0;
foreach ( $entries as $entry ) {
	$key_total += count( (array) $entry['keys'] );
}
?>

<p>
	<?php
	if ( $first_name !== '' ) {
		/* translators: %s: customer first name */
		printf( esc_html__( 'Hi %s,', 'licensesender' ), esc_html( $first_name ) );
	} else {
		esc_html_e( 'Hi there,', 'licensesender' );
	}
	?>
</p>

<p>
	<?php
	if ( $order ) {
		echo esc_html(
			sprintf(
				/* translators: 1: order number, 2: number of keys */
				_n( 'Thanks for your order #%1$s. Here is your license key.', 'Thanks for your order #%1$s. Here are your %2$d license keys.', $key_total, 'licensesender' ),
				$order->get_order_number(),
				$key_total
			)
		);
	} else {
		esc_html_e( 'This is a test of the license keys email, with sample keys.', 'licensesender' );
	}
	?>
</p>

<?php foreach ( $entries as $entry ) : ?>
	<table cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:0 0 18px;border:1px solid #e5e7eb;border-radius:6px;border-collapse:separate;">
		<tr>
			<td style="padding:12px 14px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">
				<strong><?php echo esc_html( (string) $entry['name'] ); ?></strong>
			</td>
		</tr>
		<tr>
			<td style="padding:12px 14px;">
				<?php foreach ( (array) $entry['keys'] as $key ) : ?>
					<p style="margin:0 0 8px;padding:8px 10px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:4px;font-family:Consolas,Monaco,'Courier New',monospace;font-size:14px;word-break:break-all;"><?php echo esc_html( (string) $key ); ?></p>
				<?php endforeach; ?>
				<?php if ( ! empty( $entry['download'] ) || ! empty( $entry['guide'] ) ) : ?>
					<p style="margin:8px 0 0;">
						<?php if ( ! empty( $entry['download'] ) ) : ?>
							<a class="link" href="<?php echo esc_url( (string) $entry['download'] ); ?>"><?php esc_html_e( 'Download', 'licensesender' ); ?></a>
						<?php endif; ?>
						<?php if ( ! empty( $entry['download'] ) && ! empty( $entry['guide'] ) ) : ?>
							&nbsp;·&nbsp;
						<?php endif; ?>
						<?php if ( ! empty( $entry['guide'] ) ) : ?>
							<a class="link" href="<?php echo esc_url( (string) $entry['guide'] ); ?>"><?php esc_html_e( 'Activation guide', 'licensesender' ); ?></a>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
	</table>
<?php endforeach; ?>

<?php if ( $order && $order->get_customer_id() ) : ?>
	<p>
		<?php esc_html_e( 'You can also find your keys any time under My account → My keys.', 'licensesender' ); ?>
	</p>
<?php endif; ?>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
