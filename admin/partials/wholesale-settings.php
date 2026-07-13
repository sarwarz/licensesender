<?php
defined( 'ABSPATH' ) || exit;

$pages = LS_Admin_Service::get_page_choices();
$payment_gateways = LS_Admin_Service::get_payment_gateway_choices();
$payment_mode     = LS_Wholesale::get_payment_mode();
$selected_gateways = LS_Wholesale::get_custom_payment_gateways();
?>
<div class="postbox ls-tab-form" style="max-width: 1000px; padding: 20px;">
	<h2 class="wp-heading-inline"><?php esc_html_e( 'Wholesale Settings', 'licensesender' ); ?></h2>
	<p><?php esc_html_e( 'Configure wholesale catalog behavior, storefront pages, and application notifications.', 'licensesender' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom: 16px;">
		<?php wp_nonce_field( 'ls_generate_wholesale_pages' ); ?>
		<input type="hidden" name="action" value="ls_generate_wholesale_pages">
		<?php submit_button( __( 'Generate wholesale pages', 'licensesender' ), 'secondary', 'submit', false ); ?>
		<p class="description"><?php esc_html_e( 'Creates the wholesale application and catalog pages with the correct shortcodes.', 'licensesender' ); ?></p>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'ls_save_settings_nonce' ); ?>
		<input type="hidden" name="action" value="ls_save_settings">
		<input type="hidden" name="tab" value="wholesale">

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable wholesale', 'licensesender' ); ?></th>
				<td>
					<input type="hidden" name="lship_wholesale_enabled" value="no">
					<label>
						<input type="checkbox" name="lship_wholesale_enabled" value="yes" <?php checked( get_option( 'lship_wholesale_enabled', 'yes' ), 'yes' ); ?>>
						<?php esc_html_e( 'Allow wholesale applications and catalog ordering.', 'licensesender' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Products per page', 'licensesender' ); ?></th>
				<td>
					<input type="number" min="1" max="100" class="small-text" name="lship_wholesale_catalog_per_page" value="<?php echo esc_attr( get_option( 'lship_wholesale_catalog_per_page', 10 ) ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Low stock threshold', 'licensesender' ); ?></th>
				<td>
					<input type="number" min="1" class="small-text" name="lship_wholesale_low_stock_threshold" value="<?php echo esc_attr( get_option( 'lship_wholesale_low_stock_threshold', 10 ) ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Minimum order quantity', 'licensesender' ); ?></th>
				<td>
					<input type="number" min="0" class="small-text" name="lship_wholesale_min_order_quantity" value="<?php echo esc_attr( get_option( 'lship_wholesale_min_order_quantity', 0 ) ); ?>">
					<p class="description"><?php esc_html_e( 'Minimum total wholesale units required to checkout. Use 0 to disable.', 'licensesender' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Allow backorders', 'licensesender' ); ?></th>
				<td>
					<input type="hidden" name="lship_wholesale_allow_backorders" value="no">
					<label>
						<input type="checkbox" name="lship_wholesale_allow_backorders" value="yes" <?php checked( get_option( 'lship_wholesale_allow_backorders', 'no' ), 'yes' ); ?>>
						<?php esc_html_e( 'Allow ordering when keys are out of stock or insufficient. Restock later, then deliver.', 'licensesender' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Apply page', 'licensesender' ); ?></th>
				<td>
					<select name="lship_wholesale_apply_page_id">
						<?php foreach ( $pages as $page ) : ?>
							<option value="<?php echo esc_attr( $page['id'] ); ?>" <?php selected( (string) get_option( 'lship_wholesale_apply_page_id', '' ), $page['id'] ); ?>>
								<?php echo esc_html( $page['title'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Catalog page', 'licensesender' ); ?></th>
				<td>
					<select name="lship_wholesale_catalog_page_id">
						<?php foreach ( $pages as $page ) : ?>
							<option value="<?php echo esc_attr( $page['id'] ); ?>" <?php selected( (string) get_option( 'lship_wholesale_catalog_page_id', '' ), $page['id'] ); ?>>
								<?php echo esc_html( $page['title'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Wholesale payment methods', 'licensesender' ); ?></th>
				<td>
					<select name="lship_wholesale_payment_mode">
						<option value="all" <?php selected( $payment_mode, 'all' ); ?>><?php esc_html_e( 'All payment methods', 'licensesender' ); ?></option>
						<option value="wallet" <?php selected( $payment_mode, 'wallet' ); ?>><?php esc_html_e( 'TeraWallet preferred', 'licensesender' ); ?></option>
						<option value="custom" <?php selected( $payment_mode, 'custom' ); ?>><?php esc_html_e( 'Custom payment methods', 'licensesender' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Applies when a wholesale customer checks out with wholesale catalog products. With TeraWallet active: full balance pays via wallet, partial balance is applied automatically with another method for the rest, and zero balance uses other methods only.', 'licensesender' ); ?></p>
					<?php if ( ! empty( $payment_gateways ) ) : ?>
						<fieldset style="margin-top: 12px;">
							<legend class="screen-reader-text"><span><?php esc_html_e( 'Custom payment methods', 'licensesender' ); ?></span></legend>
							<?php foreach ( $payment_gateways as $gateway ) : ?>
								<label style="display:block; margin-bottom: 6px;">
									<input
										type="checkbox"
										name="lship_wholesale_payment_gateways[]"
										value="<?php echo esc_attr( $gateway['id'] ); ?>"
										<?php checked( in_array( $gateway['id'], $selected_gateways, true ) ); ?>
									>
									<?php echo esc_html( $gateway['title'] ); ?>
									<?php if ( ! empty( $gateway['is_wallet'] ) ) : ?>
										<span class="description"><?php esc_html_e( '(TeraWallet)', 'licensesender' ); ?></span>
									<?php endif; ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'New application email', 'licensesender' ); ?></th>
				<td>
					<input type="email" class="regular-text" name="lship_wholesale_notify_email" value="<?php echo esc_attr( get_option( 'lship_wholesale_notify_email', '' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email', '' ) ); ?>">
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'licensesender' ) ); ?>
	</form>
</div>
