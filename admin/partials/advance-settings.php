<?php
/**
 * Advanced settings: webhooks + SSO.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

$webhook_url    = class_exists( 'LS_Webhook_Receiver' ) ? LS_Webhook_Receiver::get_webhook_url() : '';
$webhook_secret = class_exists( 'LS_Webhook_Receiver' ) ? LS_Webhook_Receiver::ensure_secret() : (string) get_option( 'lship_webhook_secret', '' );
$webhook_ready  = $webhook_url !== '' && $webhook_secret !== '';
$sso_enabled    = get_option( 'lship_sso_enabled', 'no' ) === 'yes';
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

<div class="postbox ls-tab-form" style="max-width: 1000px; padding: 20px;">
	<h2 class="wp-heading-inline"><?php esc_html_e( 'Single Sign-On (SSO)', 'licensesender' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Open your LicenseSender dashboard from the WordPress admin bar using a shared token from LicenseSender → Settings → Security.', 'licensesender' ); ?>
	</p>

	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'ls_save_settings_nonce' ); ?>
		<input type="hidden" name="action" value="ls_save_settings">
		<input type="hidden" name="tab" value="advance">

		<table class="form-table">
			<tr>
				<th scope="row" class="titledesc"><?php esc_html_e( 'Enable SSO Login', 'licensesender' ); ?></th>
				<td class="forminp">
					<input type="hidden" name="lship_sso_enabled" value="no">
					<label for="lship_sso_enabled">
						<input
							type="checkbox"
							id="lship_sso_enabled"
							name="lship_sso_enabled"
							value="yes"
							<?php checked( $sso_enabled ); ?>
						>
						<?php esc_html_e( 'Show LicenseSender SSO shortcut in the WordPress admin bar.', 'licensesender' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<div id="ls-sso-fields" style="<?php echo $sso_enabled ? '' : 'display:none;'; ?>">
			<table class="form-table">
				<tr>
					<th scope="row" class="titledesc"><label for="lship_sso_token"><?php esc_html_e( 'SSO Access Token', 'licensesender' ); ?></label></th>
					<td class="forminp">
						<input
							type="password"
							class="regular-text code"
							id="lship_sso_token"
							name="lship_sso_token"
							value="<?php echo esc_attr( get_option( 'lship_sso_token' ) ); ?>"
							autocomplete="new-password"
						>
						<p class="description"><?php esc_html_e( 'Paste the token generated in LicenseSender → Settings → Security.', 'licensesender' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row" class="titledesc"><label for="lship_sso_user_email"><?php esc_html_e( 'SSO User Email', 'licensesender' ); ?></label></th>
					<td class="forminp">
						<input
							type="email"
							class="regular-text"
							id="lship_sso_user_email"
							name="lship_sso_user_email"
							value="<?php echo esc_attr( get_option( 'lship_sso_user_email' ) ); ?>"
						>
						<p class="description"><?php esc_html_e( 'LicenseSender account email that will be signed in.', 'licensesender' ); ?></p>
					</td>
				</tr>
			</table>
			<div class="notice notice-info inline" style="margin: 0 0 16px;">
				<p><strong><?php esc_html_e( 'Setup checklist', 'licensesender' ); ?></strong></p>
				<ol style="margin-left: 1.25em;">
					<li><?php esc_html_e( 'Enable SSO in LicenseSender → Settings → Security and generate a token.', 'licensesender' ); ?></li>
					<li><?php esc_html_e( 'Paste that token and your LicenseSender account email above.', 'licensesender' ); ?></li>
					<li><?php esc_html_e( 'Save, then use the LicenseSender item in the WP admin bar.', 'licensesender' ); ?></li>
				</ol>
			</div>
		</div>

		<?php submit_button( __( 'Save Settings', 'licensesender' ) ); ?>
	</form>
</div>
<script>
(function () {
	var toggle = document.getElementById('lship_sso_enabled');
	var fields = document.getElementById('ls-sso-fields');
	if (!toggle || !fields) return;
	toggle.addEventListener('change', function () {
		fields.style.display = toggle.checked ? '' : 'none';
	});
})();
</script>
