<?php
/**
 * Advanced settings: webhooks.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

$webhook_url    = class_exists( 'LS_Webhook_Receiver' ) ? LS_Webhook_Receiver::get_webhook_url() : '';
$webhook_secret = class_exists( 'LS_Webhook_Receiver' ) ? LS_Webhook_Receiver::ensure_secret() : (string) get_option( 'lship_webhook_secret', '' );
$webhook_ready  = $webhook_url !== '' && $webhook_secret !== '';
?>
<div class="postbox ls-tab-form" style="max-width: 1000px; padding: 20px; margin-bottom: 16px;">
	<div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom: 8px;">
		<div>
			<h2 class="wp-heading-inline" style="margin:0;"><?php esc_html_e( 'Incoming webhook', 'licensesender' ); ?></h2>
			<p class="description" style="margin-top:6px;">
				<?php esc_html_e( 'Share these credentials with LicenseSender (Shops → WordPress plugin webhook). When a license is replaced, WordPress updates the local key cache automatically.', 'licensesender' ); ?>
			</p>
		</div>
		<span style="display:inline-flex; align-items:center; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:600; white-space:nowrap; <?php echo $webhook_ready ? 'background:#dcfce7;color:#166534;' : 'background:#fef3c7;color:#92400e;'; ?>">
			<?php echo $webhook_ready ? esc_html__( 'Ready', 'licensesender' ) : esc_html__( 'Setup required', 'licensesender' ); ?>
		</span>
	</div>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="lship_webhook_url"><?php esc_html_e( 'Webhook URL', 'licensesender' ); ?></label></th>
			<td>
				<input type="text" class="large-text code" id="lship_webhook_url" readonly value="<?php echo esc_attr( $webhook_url ); ?>" onclick="this.select();">
				<p class="description"><?php esc_html_e( 'Paste into LicenseSender shop webhook settings.', 'licensesender' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="lship_webhook_secret"><?php esc_html_e( 'Webhook secret', 'licensesender' ); ?></label></th>
			<td>
				<input type="text" class="large-text code" id="lship_webhook_secret" readonly value="<?php echo esc_attr( $webhook_secret ); ?>" onclick="this.select();">
				<p class="description">
					<?php
					echo wp_kses(
						__( 'Requests must include <code>X-LS-Signature: sha256=&lt;hmac&gt;</code> over the raw JSON body.', 'licensesender' ),
						array( 'code' => array() )
					);
					?>
				</p>
			</td>
		</tr>
	</table>
</div>
