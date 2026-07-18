<?php
/**
 * Live chat settings partial.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="postbox ls-tab-form" style="max-width: 1000px; padding: 20px;">
	<h2 class="wp-heading-inline"><?php esc_html_e( 'Live Chat', 'licensesender' ); ?></h2>
	<p><?php esc_html_e( 'Show a floating AI chat widget on your storefront. Messages are proxied through WordPress — visitors never see your API key.', 'licensesender' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'ls_save_settings_nonce' ); ?>
		<input type="hidden" name="action" value="ls_save_settings">
		<input type="hidden" name="tab" value="chat">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable live chat', 'licensesender' ); ?></th>
				<td>
					<input type="hidden" name="lship_chat_enabled" value="no">
					<label>
						<input type="checkbox" name="lship_chat_enabled" value="yes" <?php checked( get_option( 'lship_chat_enabled', 'no' ), 'yes' ); ?>>
						<?php esc_html_e( 'Show the floating chat widget sitewide (requires a valid API key and chat enabled in LicenseSender SaaS).', 'licensesender' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Require email before chat', 'licensesender' ); ?></th>
				<td>
					<input type="hidden" name="lship_chat_require_email" value="no">
					<label>
						<input type="checkbox" name="lship_chat_require_email" value="yes" <?php checked( get_option( 'lship_chat_require_email', 'no' ), 'yes' ); ?>>
						<?php esc_html_e( 'Ask guests for an email before starting a session.', 'licensesender' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Widget brand color', 'licensesender' ); ?></th>
				<td>
					<?php $chat_color = (string) get_option( 'lship_chat_color', get_option( 'ls_brand', '#0f766e' ) ); ?>
					<input type="color" name="lship_chat_color" value="<?php echo esc_attr( $chat_color ? $chat_color : '#0f766e' ); ?>">
					<p class="description"><?php esc_html_e( 'Used for the launcher, header, and assistant bubbles.', 'licensesender' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Launcher style', 'licensesender' ); ?></th>
				<td>
					<?php $launcher_style = get_option( 'lship_chat_launcher_style', 'icon' ); ?>
					<fieldset>
						<label style="display:block;margin-bottom:8px;">
							<input type="radio" name="lship_chat_launcher_style" value="icon" <?php checked( $launcher_style, 'icon' ); ?>>
							<?php esc_html_e( 'Icon only — circular chat button', 'licensesender' ); ?>
						</label>
						<label style="display:block;">
							<input type="radio" name="lship_chat_launcher_style" value="label" <?php checked( $launcher_style, 'label' ); ?>>
							<?php esc_html_e( 'Chat with us — icon with message label', 'licensesender' ); ?>
						</label>
					</fieldset>
					<p class="description"><?php esc_html_e( 'Choose how the floating chat button appears on your storefront.', 'licensesender' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lship_chat_title"><?php esc_html_e( 'Widget title', 'licensesender' ); ?></label></th>
				<td>
					<input type="text" name="lship_chat_title" id="lship_chat_title" class="regular-text" value="<?php echo esc_attr( (string) get_option( 'lship_chat_title', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Chat with us', 'licensesender' ); ?>">
					<p class="description"><?php esc_html_e( 'Header shown before a chat starts. During chat, it changes to “AI Assistant” or the joined human agent’s name.', 'licensesender' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lship_chat_welcome"><?php esc_html_e( 'Welcome message', 'licensesender' ); ?></label></th>
				<td>
					<textarea name="lship_chat_welcome" id="lship_chat_welcome" rows="3" class="large-text"><?php echo esc_textarea( (string) get_option( 'lship_chat_welcome', '' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Optional storefront override. If empty, the SaaS welcome message is used after the session starts.', 'licensesender' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save chat settings', 'licensesender' ) ); ?>
	</form>
</div>
