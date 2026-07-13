<div class="postbox ls-tab-form" style="max-width: 1000px; padding: 20px;">
    <h2 class="wp-heading-inline"><?php _e( 'General Settings', 'licensesender' ); ?></h2>
     <div id="ls-description">
        <p>These options determine the behavior and operation of the plugin.</p>
    </div>
    <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('ls_save_settings_nonce'); ?>
        <input type="hidden" name="action" value="ls_save_settings">
        <input type="hidden" name="tab" value="general">

        <table class="form-table">
            <tr>
                <th scope="row" class="titledesc">Auto-complete orders</th>
                <td class="forminp forminp-checkbox ">
                   <fieldset>
                      <legend class="screen-reader-text"><span>Auto-complete orders</span></legend>
                      <label for="lship_autocomplete_order">
                            <input name="lship_autocomplete_order" id="lship_autocomplete_order" type="checkbox" <?php checked(get_option('lship_autocomplete_order'), 'yes'); ?>>  
                            Automatically completes orders after successful payments.
                      </label> 
                      <p class="description ">If you enable this option, orders will be marked as completed immediately after successful payment—useful for instant delivery of digital products.</p>
                   </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row" class="titledesc"><?php _e('Send Email After Redemption', 'licensesender'); ?></th>
                <td class="forminp forminp-checkbox">
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span><?php _e('Send Email After Redemption', 'licensesender'); ?></span>
                        </legend>
                        <label for="lship_send_email_after_redeem">
                            <input name="lship_send_email_after_redeem" id="lship_send_email_after_redeem" type="checkbox" <?php checked(get_option('lship_send_email_after_redeem'), 'yes'); ?>>
                            <?php _e('Send an email to the customer after successful redemption.', 'licensesender'); ?>
                        </label>
                        <p class="description">
                            <?php _e('If enabled, the plugin will automatically send a confirmation email with license details after a successful voucher redemption.', 'licensesender'); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>
			<tr>
				<th scope="row" class="titledesc">
					<?php _e('Enable Variation Support', 'licensesender'); ?>
					<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('If enabled, variation-level license fields will appear on variable products.', 'licensesender'); ?>"></span>
				</th>

				<td class="forminp">
					<fieldset class="ls-fieldset">
						<legend class="screen-reader-text">
							<span><?php _e('Enable Variation Support', 'licensesender'); ?></span>
						</legend>

						<div class="ls-setting-line">
							<!-- Hidden fallback so "no" is saved when unchecked -->
							<input type="hidden" name="lship_enable_variation_support" value="no">

							<input
								type="checkbox"
								id="lship_enable_variation_support"
								name="lship_enable_variation_support"
								value="yes"
								<?php checked(get_option('lship_enable_variation_support', 'no'), 'yes'); ?>
								class="ls-toggle-input"
							/>
							<label class="ls-toggle" for="lship_enable_variation_support" aria-hidden="true">
								<span class="ls-toggle-handle"></span>
							</label>

							<label class="ls-toggle-label" for="lship_enable_variation_support">
								<?php _e('Turn on variation-level product mapping fields for variable products.', 'licensesender'); ?>
							</label>
						</div>

						<p class="description">
							<?php _e('When enabled, Product mapping fields will be available for each variation of a variable product.', 'licensesender'); ?>
						</p>
					</fieldset>
				</td>
			</tr>
			<tr class="ls-section-header">
				<th colspan="2">
					<h3 style="margin: 20px 0 5px;">
						<?php _e('Content Management', 'licensesender'); ?>
					</h3>
					<p class="description" style="margin: 0;">
						<?php _e(
							'Control downloadable files and activation guides available for your products.',
							'licensesender'
						); ?>
					</p>
				</th>
			</tr>

            <tr>
                <th scope="row" class="titledesc">
                    <?php _e('Enable Manage Download Links', 'licensesender'); ?>
                </th>
                <td class="forminp forminp-checkbox">
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span><?php _e('Enable Manage Download Links', 'licensesender'); ?></span>
                        </legend>

                        <label for="lship_enable_manage_downloads">
                            <input name="lship_enable_manage_downloads" id="lship_enable_manage_downloads" type="checkbox" 
                                   value="yes" <?php checked(get_option('lship_enable_manage_downloads'), 'yes'); ?>>
                            <?php _e('Enable the "Manage Download Links" section in the admin area.', 'licensesender'); ?>
                        </label>

                        <p class="description">
                            <?php _e('When enabled, administrators can manage product download links directly from the Plugin admin interface.', 'licensesender'); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>
            <tr>
                <th scope="row" class="titledesc">
                    <?php _e('Enable Manage Activation Guides', 'licensesender'); ?>
                </th>
                <td class="forminp forminp-checkbox">
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span><?php _e('Enable Manage Activation Guides', 'licensesender'); ?></span>
                        </legend>

                        <label for="lship_enable_manage_activation_guides">
                            <input name="lship_enable_manage_activation_guides"
                                   id="lship_enable_manage_activation_guides"
                                   type="checkbox"
                                   value="yes"
                                   <?php checked(get_option('lship_enable_manage_activation_guides'), 'yes'); ?>>
                            <?php _e('Enable the "Manage Activation Guides" section in the admin area.', 'licensesender'); ?>
                        </label>

                        <p class="description">
                            <?php _e('When enabled, administrators can create, edit, and manage activation guides (text or PDF) for each product directly from the plugin admin panel.', 'licensesender'); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row"></th>
                <td>
                    <?php submit_button(__('Save Settings', 'licensesender')); ?>
                </td>
            </tr>
        </table>    
    </form>
</div>