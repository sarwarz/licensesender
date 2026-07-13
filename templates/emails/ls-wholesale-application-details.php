<?php
/**
 * Wholesale application details for email templates.
 *
 * @package Licensesender
 *
 * @var array<string, mixed> $application
 * @var bool                 $plain_text
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $application ) || ! is_array( $application ) ) {
	return;
}

$fields = array(
	'company_name'   => __( 'Company', 'licensesender' ),
	'business_email' => __( 'Business email', 'licensesender' ),
	'phone'          => __( 'Phone', 'licensesender' ),
	'messenger_link' => __( 'Messenger', 'licensesender' ),
	'website'        => __( 'Website', 'licensesender' ),
);

if ( ! empty( $application['message'] ) ) {
	$fields['message'] = __( 'Message', 'licensesender' );
}

if ( ! empty( $application['admin_note'] ) ) {
	$fields['admin_note'] = __( 'Admin note', 'licensesender' );
}

if ( $plain_text ) :
	foreach ( $fields as $key => $label ) {
		$value = (string) ( $application[ $key ] ?? '' );
		if ( $value === '' ) {
			continue;
		}
		if ( $key === 'message' || $key === 'admin_note' ) {
			echo esc_html( $label ) . ":\n" . esc_html( $value ) . "\n\n";
			continue;
		}
		echo esc_html( $label ) . ': ' . esc_html( $value ) . "\n";
	}
	if ( ! empty( $application['created_at'] ) ) {
		echo esc_html__( 'Submitted', 'licensesender' ) . ': ' . esc_html( $application['created_at'] ) . "\n";
	}
else :
	?>
	<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
		<tbody>
		<?php foreach ( $fields as $key => $label ) : ?>
			<?php
			$value = (string) ( $application[ $key ] ?? '' );
			if ( $value === '' ) {
				continue;
			}
			?>
			<tr>
				<th class="td" scope="row" style="text-align:left; vertical-align:middle;"><?php echo esc_html( $label ); ?></th>
				<td class="td" style="text-align:left; vertical-align:middle;">
					<?php
					if ( $key === 'business_email' ) {
						echo '<a href="mailto:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>';
					} elseif ( $key === 'website' ) {
						echo '<a href="' . esc_url( $value ) . '">' . esc_html( $value ) . '</a>';
					} elseif ( $key === 'message' || $key === 'admin_note' ) {
						echo wp_kses_post( nl2br( esc_html( $value ) ) );
					} else {
						echo esc_html( $value );
					}
					?>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php if ( ! empty( $application['created_at'] ) ) : ?>
			<tr>
				<th class="td" scope="row" style="text-align:left; vertical-align:middle;"><?php esc_html_e( 'Submitted', 'licensesender' ); ?></th>
				<td class="td" style="text-align:left; vertical-align:middle;"><?php echo esc_html( $application['created_at'] ); ?></td>
			</tr>
		<?php endif; ?>
		</tbody>
	</table>
	<?php
endif;
