<?php
/**
 * Legacy Design settings (React Design tab is preferred).
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

$presets = class_exists( 'LS_Design_System' ) ? LS_Design_System::presets() : array();
$preset  = class_exists( 'LS_Design_System' ) ? LS_Design_System::get_preset_id() : 'indigo';
$brand   = (string) get_option( 'ls_brand', '#4f46e5' );
$accent  = (string) get_option( 'ls_accent', '#2563eb' );
?>
<div class="postbox ls-tab-form" style="max-width: 1000px; padding: 20px;">
	<h2 class="wp-heading-inline"><?php esc_html_e( 'Design system', 'licensesender' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Choose a theme pack and brand colors. Success, warning, and key-block colors are generated automatically for My Keys, orders, support, wholesale, and email.', 'licensesender' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'ls_save_settings_nonce' ); ?>
		<input type="hidden" name="action" value="ls_save_settings">
		<input type="hidden" name="tab" value="design">

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Theme', 'licensesender' ); ?></th>
				<td>
					<select name="ls_theme_preset" id="ls_theme_preset">
						<?php foreach ( $presets as $id => $pack ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $preset, $id ); ?>>
								<?php echo esc_html( $pack['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Primary brand', 'licensesender' ); ?></th>
				<td><input type="color" name="ls_brand" value="<?php echo esc_attr( $brand ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Accent', 'licensesender' ); ?></th>
				<td><input type="color" name="ls_accent" value="<?php echo esc_attr( $accent ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Corner radius', 'licensesender' ); ?></th>
				<td>
					<select name="ls_radius">
						<?php foreach ( array( 'sm' => 'Soft sharp', 'md' => 'Rounded', 'lg' => 'Extra rounded' ) as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( get_option( 'ls_radius', 'md' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Density', 'licensesender' ); ?></th>
				<td>
					<select name="ls_density">
						<option value="comfortable" <?php selected( get_option( 'ls_density', 'comfortable' ), 'comfortable' ); ?>><?php esc_html_e( 'Comfortable', 'licensesender' ); ?></option>
						<option value="compact" <?php selected( get_option( 'ls_density', 'comfortable' ), 'compact' ); ?>><?php esc_html_e( 'Compact', 'licensesender' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'License key block', 'licensesender' ); ?></th>
				<td>
					<select name="ls_code_style">
						<option value="dark" <?php selected( get_option( 'ls_code_style', 'dark' ), 'dark' ); ?>><?php esc_html_e( 'Dark', 'licensesender' ); ?></option>
						<option value="light" <?php selected( get_option( 'ls_code_style', 'dark' ), 'light' ); ?>><?php esc_html_e( 'Light', 'licensesender' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Match email branding', 'licensesender' ); ?></th>
				<td>
					<label>
						<input type="hidden" name="ls_email_sync_brand" value="no">
						<input type="checkbox" name="ls_email_sync_brand" value="yes" <?php checked( get_option( 'ls_email_sync_brand', 'yes' ), 'yes' ); ?>>
						<?php esc_html_e( 'Use brand & accent in customer emails', 'licensesender' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Email logo URL', 'licensesender' ); ?></th>
				<td>
					<input type="url" class="regular-text" name="lship_email_logo" value="<?php echo esc_attr( (string) get_option( 'lship_email_logo', '' ) ); ?>">
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Save Settings', 'licensesender' ) ); ?>
	</form>
</div>
