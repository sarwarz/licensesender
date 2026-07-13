<div class="postbox ls-tab-form" style="max-width: 1000px; padding: 20px;">
    <h2 class="wp-heading-inline"><?php _e( 'API Settings', 'licensesender' ); ?></h2>
    <div id="ls-description">
        <p><?php _e( 'The following options control how the API functions within the plugin.', 'licensesender' ); ?></p>
    </div>
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('ls_save_settings_nonce'); ?>
        <input type="hidden" name="action" value="ls_save_settings">
        <input type="hidden" name="tab" value="api">

        <table class="form-table">
            <tr>
                <th><label for="lship_api_key"><?php _e('API Key', 'licensesender'); ?></label></th>
                <td>
                    <input type="text" name="lship_api_key" id="lship_api_key" class="regular-text" value="<?php echo esc_attr(get_option('lship_api_key')); ?>">
                    <p class="description"><?php _e('Enter your API key provided by the license server. This key is required for authenticating API requests.', 'licensesender'); ?></p>
                </td>
            </tr>

            <tr>
                <th><label for="ls_ping_api"><?php _e('Ping API', 'licensesender'); ?></label></th>
                <td>
                    <input type="button" name="ls_ping_api" id="ls_ping_api" class="button button-secondary" value="<?php _e('Ping API', 'licensesender'); ?>">
                    <p class="description"><?php _e('Click to test the connection with your API endpoint using the provided API key.', 'licensesender'); ?></p>
                    <p class="description" id="ls_ping_api_result"></p>
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