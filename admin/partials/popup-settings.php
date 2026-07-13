<?php
defined( 'ABSPATH' ) || exit;

$popup = LS_Admin_Service::get_popup_settings();
?>
<div class="postbox ls-tab-form" style="max-width: 1000px; padding: 20px;">
	<h2 class="wp-heading-inline"><?php esc_html_e( 'Pop-up settings', 'licensesender' ); ?></h2>
	<p><?php esc_html_e( 'Customize confirm, bulk-fetch, and license key dialogs customers see when retrieving keys.', 'licensesender' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'ls_save_settings_nonce' ); ?>
		<input type="hidden" name="action" value="ls_save_settings">
		<input type="hidden" name="tab" value="popup">

		<h3><?php esc_html_e( 'Get Key confirmation', 'licensesender' ); ?></h3>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="ls_sw_confirm_title"><?php esc_html_e( 'Title', 'licensesender' ); ?></label></th>
				<td><input id="ls_sw_confirm_title" name="ls_sw_confirm_title" type="text" class="regular-text" value="<?php echo esc_attr( $popup['ls_sw_confirm_title'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="ls_sw_confirm_text"><?php esc_html_e( 'Message', 'licensesender' ); ?></label></th>
				<td>
					<textarea id="ls_sw_confirm_text" name="ls_sw_confirm_text" class="large-text" rows="3"><?php echo esc_textarea( $popup['ls_sw_confirm_text'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Use {product} for the product name.', 'licensesender' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ls_sw_confirm_btn"><?php esc_html_e( 'Confirm button', 'licensesender' ); ?></label></th>
				<td><input id="ls_sw_confirm_btn" name="ls_sw_confirm_btn" type="text" class="regular-text" value="<?php echo esc_attr( $popup['ls_sw_confirm_btn'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="ls_sw_cancel_btn"><?php esc_html_e( 'Cancel button', 'licensesender' ); ?></label></th>
				<td><input id="ls_sw_cancel_btn" name="ls_sw_cancel_btn" type="text" class="regular-text" value="<?php echo esc_attr( $popup['ls_sw_cancel_btn'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="ls_sw_confirm_color"><?php esc_html_e( 'Confirm color', 'licensesender' ); ?></label></th>
				<td><input id="ls_sw_confirm_color" name="ls_sw_confirm_color" type="color" value="<?php echo esc_attr( $popup['ls_sw_confirm_color'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="ls_sw_cancel_color"><?php esc_html_e( 'Cancel color', 'licensesender' ); ?></label></th>
				<td><input id="ls_sw_cancel_color" name="ls_sw_cancel_color" type="color" value="<?php echo esc_attr( $popup['ls_sw_cancel_color'] ); ?>"></td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Bulk fetch', 'licensesender' ); ?></h3>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="ls_sw_bulk_title"><?php esc_html_e( 'Title', 'licensesender' ); ?></label></th>
				<td><input id="ls_sw_bulk_title" name="ls_sw_bulk_title" type="text" class="regular-text" value="<?php echo esc_attr( $popup['ls_sw_bulk_title'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="ls_sw_bulk_text"><?php esc_html_e( 'Message', 'licensesender' ); ?></label></th>
				<td><textarea id="ls_sw_bulk_text" name="ls_sw_bulk_text" class="large-text" rows="3"><?php echo esc_textarea( $popup['ls_sw_bulk_text'] ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="ls_sw_bulk_confirm_btn"><?php esc_html_e( 'Confirm button', 'licensesender' ); ?></label></th>
				<td><input id="ls_sw_bulk_confirm_btn" name="ls_sw_bulk_confirm_btn" type="text" class="regular-text" value="<?php echo esc_attr( $popup['ls_sw_bulk_confirm_btn'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="ls_sw_bulk_cancel_btn"><?php esc_html_e( 'Cancel button', 'licensesender' ); ?></label></th>
				<td><input id="ls_sw_bulk_cancel_btn" name="ls_sw_bulk_cancel_btn" type="text" class="regular-text" value="<?php echo esc_attr( $popup['ls_sw_bulk_cancel_btn'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="ls_sw_bulk_done_title"><?php esc_html_e( 'Success title', 'licensesender' ); ?></label></th>
				<td><input id="ls_sw_bulk_done_title" name="ls_sw_bulk_done_title" type="text" class="regular-text" value="<?php echo esc_attr( $popup['ls_sw_bulk_done_title'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="ls_sw_bulk_done_text"><?php esc_html_e( 'Success message', 'licensesender' ); ?></label></th>
				<td><textarea id="ls_sw_bulk_done_text" name="ls_sw_bulk_done_text" class="large-text" rows="3"><?php echo esc_textarea( $popup['ls_sw_bulk_done_text'] ); ?></textarea></td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'License keys modal', 'licensesender' ); ?></h3>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="ls_sw_view_title"><?php esc_html_e( 'Single key title', 'licensesender' ); ?></label></th>
				<td><input id="ls_sw_view_title" name="ls_sw_view_title" type="text" class="regular-text" value="<?php echo esc_attr( $popup['ls_sw_view_title'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="ls_sw_view_title_many"><?php esc_html_e( 'Multiple keys title', 'licensesender' ); ?></label></th>
				<td><input id="ls_sw_view_title_many" name="ls_sw_view_title_many" type="text" class="regular-text" value="<?php echo esc_attr( $popup['ls_sw_view_title_many'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="ls_sw_view_copy_all"><?php esc_html_e( 'Copy all button', 'licensesender' ); ?></label></th>
				<td><input id="ls_sw_view_copy_all" name="ls_sw_view_copy_all" type="text" class="regular-text" value="<?php echo esc_attr( $popup['ls_sw_view_copy_all'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="ls_sw_view_close"><?php esc_html_e( 'Close button', 'licensesender' ); ?></label></th>
				<td><input id="ls_sw_view_close" name="ls_sw_view_close" type="text" class="regular-text" value="<?php echo esc_attr( $popup['ls_sw_view_close'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"></th>
				<td><?php submit_button( __( 'Save Settings', 'licensesender' ) ); ?></td>
			</tr>
		</table>
	</form>
</div>
