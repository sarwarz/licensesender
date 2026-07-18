<?php
/**
 * Support settings partial (legacy admin UI).
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

$pages = LS_Admin_Service::get_page_choices();
?>
<div class="postbox ls-tab-form" style="max-width: 1000px; padding: 20px;">
	<h2 class="wp-heading-inline"><?php esc_html_e( 'Support Settings', 'licensesender' ); ?></h2>
	<p><?php esc_html_e( 'Enable customer support tickets and link the storefront pages.', 'licensesender' ); ?></p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom: 24px;">
	<?php wp_nonce_field( 'ls_generate_support_pages' ); ?>
	<input type="hidden" name="action" value="ls_generate_support_pages">
	<?php submit_button( __( 'Generate support pages', 'licensesender' ), 'secondary', 'submit', false ); ?>
	<p class="description"><?php esc_html_e( 'Creates the open ticket and manage tickets pages with the correct shortcodes.', 'licensesender' ); ?></p>
</form>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'ls_save_settings_nonce' ); ?>
	<input type="hidden" name="action" value="ls_save_settings">
	<input type="hidden" name="tab" value="support">

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable support', 'licensesender' ); ?></th>
			<td>
				<input type="hidden" name="lship_support_enabled" value="no">
				<label>
					<input type="checkbox" name="lship_support_enabled" value="yes" <?php checked( get_option( 'lship_support_enabled', 'yes' ), 'yes' ); ?>>
					<?php esc_html_e( 'Allow customers to open and manage support tickets via shortcodes.', 'licensesender' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="lship_support_open_page_id"><?php esc_html_e( 'Open ticket page', 'licensesender' ); ?></label></th>
			<td>
				<select name="lship_support_open_page_id" id="lship_support_open_page_id">
					<option value="0"><?php esc_html_e( '— Select —', 'licensesender' ); ?></option>
					<?php foreach ( $pages as $page ) : ?>
						<option value="<?php echo esc_attr( $page['id'] ); ?>" <?php selected( (string) get_option( 'lship_support_open_page_id', '' ), $page['id'] ); ?>>
							<?php echo esc_html( $page['title'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Page containing [ls_support_open]', 'licensesender' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="lship_support_manage_page_id"><?php esc_html_e( 'Manage tickets page', 'licensesender' ); ?></label></th>
			<td>
				<select name="lship_support_manage_page_id" id="lship_support_manage_page_id">
					<option value="0"><?php esc_html_e( '— Select —', 'licensesender' ); ?></option>
					<?php foreach ( $pages as $page ) : ?>
						<option value="<?php echo esc_attr( $page['id'] ); ?>" <?php selected( (string) get_option( 'lship_support_manage_page_id', '' ), $page['id'] ); ?>>
							<?php echo esc_html( $page['title'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Page containing [ls_support_manage]', 'licensesender' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Login / register via My Account', 'licensesender' ); ?></th>
			<td>
				<input type="hidden" name="lship_support_auth_my_account" value="no">
				<label>
					<input type="checkbox" name="lship_support_auth_my_account" value="yes" <?php checked( get_option( 'lship_support_auth_my_account', 'yes' ), 'yes' ); ?>>
					<?php esc_html_e( 'Send customers to WooCommerce My Account to log in or register (recommended when using captcha plugins).', 'licensesender' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Turn this off only if you want login/register forms embedded on the support page.', 'licensesender' ); ?></p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save support settings', 'licensesender' ) ); ?>
</form>
</div>
