<div class="postbox ls-tab-form" style="max-width: 1000px; padding: 20px;">
    <h2 class="wp-heading-inline"><?php _e('E-Mail Settings', 'licensesender'); ?></h2>
    <div id="ls-description">
        <p><?php _e('Configure the email settings used for license delivery. One email is sent after all license keys for the order are fetched.', 'licensesender'); ?></p>
    </div>

    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('ls_save_settings_nonce'); ?>
        <input type="hidden" name="action" value="ls_save_settings">
        <input type="hidden" name="tab" value="email">

        <table class="form-table">
            <tr>
                <th><label for="lship_email_sender_name"><?php _e('Sender Name', 'licensesender'); ?></label></th>
                <td>
                    <input type="text" name="lship_email_sender_name" id="lship_email_sender_name" class="regular-text" value="<?php echo esc_attr(get_option('lship_email_sender_name')); ?>">
                    <p class="description"><?php _e('This name will appear as the sender in outgoing emails to customers.', 'licensesender'); ?></p>
                </td>
            </tr>

            <tr>
                <th><label for="lship_email_sender_email"><?php _e('Sender Email Address', 'licensesender'); ?></label></th>
                <td>
                    <input type="email" name="lship_email_sender_email" id="lship_email_sender_email" class="regular-text" value="<?php echo esc_attr(get_option('lship_email_sender_email')); ?>">
                    <p class="description"><?php _e('The email address that will appear as the sender in customer emails. Leave blank to use the default site email or SMTP sender.', 'licensesender'); ?></p>
                </td>
            </tr>

            <tr>
                <th><label for="lship_email_subject"><?php _e('Default E-Mail Subject', 'licensesender'); ?></label></th>
                <td>
                    <input type="text" name="lship_email_subject" id="lship_email_subject" class="regular-text" value="<?php echo esc_attr(get_option('lship_email_subject')); ?>">
                </td>
            </tr>

            <tr>
                <th><label for="lship_email_subject_single"><?php _e('Subject (single key)', 'licensesender'); ?></label></th>
                <td>
                    <input type="text" name="lship_email_subject_single" id="lship_email_subject_single" class="regular-text" value="<?php echo esc_attr(get_option('lship_email_subject_single')); ?>">
                </td>
            </tr>

            <tr>
                <th><label for="lship_email_subject_bulk"><?php _e('Subject (multiple keys)', 'licensesender'); ?></label></th>
                <td>
                    <input type="text" name="lship_email_subject_bulk" id="lship_email_subject_bulk" class="regular-text" value="<?php echo esc_attr(get_option('lship_email_subject_bulk')); ?>">
                </td>
            </tr>

            <tr>
                <th><label for="lship_email_preheader"><?php _e('Inbox Preheader', 'licensesender'); ?></label></th>
                <td>
                    <input type="text" name="lship_email_preheader" id="lship_email_preheader" class="regular-text" value="<?php echo esc_attr(get_option('lship_email_preheader')); ?>">
                    <p class="description"><?php _e('Short preview line shown in the inbox before the email is opened.', 'licensesender'); ?></p>
                </td>
            </tr>

            <tr>
                <th><label for="lship_email_intro_single"><?php _e('Intro (single key)', 'licensesender'); ?></label></th>
                <td>
                    <textarea name="lship_email_intro_single" id="lship_email_intro_single" class="large-text" rows="3"><?php echo esc_textarea(get_option('lship_email_intro_single')); ?></textarea>
                </td>
            </tr>

            <tr>
                <th><label for="lship_email_intro_bulk"><?php _e('Intro (multiple keys)', 'licensesender'); ?></label></th>
                <td>
                    <textarea name="lship_email_intro_bulk" id="lship_email_intro_bulk" class="large-text" rows="3"><?php echo esc_textarea(get_option('lship_email_intro_bulk')); ?></textarea>
                </td>
            </tr>

            <tr>
                <th><label for="lship_support_email"><?php _e('Support Email Address', 'licensesender'); ?></label></th>
                <td>
                    <input type="email" name="lship_support_email" id="lship_support_email" class="regular-text" value="<?php echo esc_attr(get_option('lship_support_email')); ?>">
                    <p class="description">
                        <?php _e(
                            'This email address will be used for customer support inquiries. Customers may see or use this address when responding to license-related emails or requesting help.',
                            'licensesender'
                        ); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th><label for="ls_test_email"><?php _e('Send test e-mail to', 'licensesender'); ?></label></th>
                <td>
                    <input type="email" name="ls_test_email" id="ls_test_email" class="regular-text" value="<?php echo esc_attr(get_option('admin_email')); ?>">
                    <select id="ls_test_email_mode" style="margin-left: 8px;">
                        <option value="bulk"><?php _e('Multiple keys preview', 'licensesender'); ?></option>
                        <option value="single"><?php _e('Single key preview', 'licensesender'); ?></option>
                    </select>
                    <button style="margin-top: 5px" type="button" class="button" id="ls_send_test_email"><?php _e('Send', 'licensesender'); ?></button>
                    <p class="description" id="ls_test_email_result"><?php _e('A test email will be sent using the production license template.', 'licensesender'); ?></p>
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
