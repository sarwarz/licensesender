<div class="postbox ls-tab-form" style="max-width: 1000px; padding: 20px;">
    <h2 class="wp-heading-inline"><?php _e( 'Design Settings', 'license-shipper' ); ?></h2>
    <div id="ls-description">
        <p><?php _e( 'These settings allow you to customize the appearance.', 'license-shipper' ); ?></p>
    </div>

    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('ls_save_settings_nonce'); ?>
        <input type="hidden" name="action" value="ls_save_settings">
        <input type="hidden" name="tab" value="design">

        <?php
        // Defaults (used in placeholders/descriptions)
        $defaults = [
          'ls_brand'        => '#4f46e5', // indigo-600
          'ls_brand_2'      => '#6366f1', // indigo-500
          'ls_ring'         => '#6366f1', // indigo-500 (we'll add alpha in CSS)
          'ls_code_bg'      => '#1e1e2e',
          'ls_code_fg'      => '#cdd6f4',
          'ls_code_border'  => '#313244',
          'ls_code_accent'  => '#89b4fa',
        ];

        $opt = function($key) use ($defaults){
          $val = get_option($key, '');
          return esc_attr($val !== '' ? $val : $defaults[$key]);
        };
        ?>

        <table class="form-table">
          <!-- Brand gradient: start -->
          <tr>
            <th scope="row">
              <label for="ls_brand"><?php esc_html_e('Primary Color', 'license-shipper'); ?></label>
            </th>
            <td>
              <input id="ls_brand" name="ls_brand" class="my-color-field" type="text"
                     value="<?php echo $opt('ls_brand'); ?>"
                     data-default-color="<?php echo esc_attr($defaults['ls_brand']); ?>" />
              <p class="description">
                <?php esc_html_e('Primary brand color (gradient start) used by buttons and highlights.', 'license-shipper'); ?>
              </p>
            </td>
          </tr>

          <!-- Brand gradient: end -->
          <tr>
            <th scope="row">
              <label for="ls_brand_2"><?php esc_html_e('Primary Color (Secondary)', 'license-shipper'); ?></label>
            </th>
            <td>
              <input id="ls_brand_2" name="ls_brand_2" class="my-color-field" type="text"
                     value="<?php echo $opt('ls_brand_2'); ?>"
                     data-default-color="<?php echo esc_attr($defaults['ls_brand_2']); ?>" />
              <p class="description">
                <?php esc_html_e('Secondary brand color (gradient end).', 'license-shipper'); ?>
              </p>
            </td>
          </tr>

          <!-- Focus ring base (alpha added in CSS output) -->
          <tr>
            <th scope="row">
              <label for="ls_ring"><?php esc_html_e('Focus Ring Color', 'license-shipper'); ?></label>
            </th>
            <td>
              <input id="ls_ring" name="ls_ring" class="my-color-field" type="text"
                     value="<?php echo $opt('ls_ring'); ?>"
                     data-default-color="<?php echo esc_attr($defaults['ls_ring']); ?>" />
              <p class="description">
                <?php esc_html_e('Used for keyboard focus outlines. (Transparency applied automatically)', 'license-shipper'); ?>
              </p>
            </td>
          </tr>

          <!-- Code block colors (SweetAlert keys) -->
          <tr>
            <th scope="row">
              <label for="ls_code_bg"><?php esc_html_e('Code Background', 'license-shipper'); ?></label>
            </th>
            <td>
              <input id="ls_code_bg" name="ls_code_bg" class="my-color-field" type="text"
                     value="<?php echo $opt('ls_code_bg'); ?>"
                     data-default-color="<?php echo esc_attr($defaults['ls_code_bg']); ?>" />
              <p class="description"><?php esc_html_e('Background of license key blocks.', 'license-shipper'); ?></p>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="ls_code_fg"><?php esc_html_e('Code Text', 'license-shipper'); ?></label>
            </th>
            <td>
              <input id="ls_code_fg" name="ls_code_fg" class="my-color-field" type="text"
                     value="<?php echo $opt('ls_code_fg'); ?>"
                     data-default-color="<?php echo esc_attr($defaults['ls_code_fg']); ?>" />
              <p class="description"><?php esc_html_e('Text color of license key blocks.', 'license-shipper'); ?></p>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="ls_code_border"><?php esc_html_e('Code Border', 'license-shipper'); ?></label>
            </th>
            <td>
              <input id="ls_code_border" name="ls_code_border" class="my-color-field" type="text"
                     value="<?php echo $opt('ls_code_border'); ?>"
                     data-default-color="<?php echo esc_attr($defaults['ls_code_border']); ?>" />
              <p class="description"><?php esc_html_e('Border color of license key blocks.', 'license-shipper'); ?></p>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="ls_code_accent"><?php esc_html_e('Code Accent', 'license-shipper'); ?></label>
            </th>
            <td>
              <input id="ls_code_accent" name="ls_code_accent" class="my-color-field" type="text"
                     value="<?php echo $opt('ls_code_accent'); ?>"
                     data-default-color="<?php echo esc_attr($defaults['ls_code_accent']); ?>" />
              <p class="description"><?php esc_html_e('Accent used for selection/glows in code blocks.', 'license-shipper'); ?></p>
            </td>
          </tr>
          <tr>
          <th scope="row"><label for="ls_success"><?php esc_html_e('View Key Color (Start)', 'license-shipper'); ?></label></th>
          <td>
            <input id="ls_success" name="ls_success" class="my-color-field" type="text"
                   value="<?php echo esc_attr(get_option('ls_success', '#059669')); ?>"
                   data-default-color="#059669" />
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="ls_success_2"><?php esc_html_e('View Key Color (End)', 'license-shipper'); ?></label></th>
          <td>
            <input id="ls_success_2" name="ls_success_2" class="my-color-field" type="text"
                   value="<?php echo esc_attr(get_option('ls_success_2', '#10b981')); ?>"
                   data-default-color="#10b981" />
          </td>
        </tr>


          <tr>
            <th scope="row"></th>
            <td><?php submit_button(__('Save Settings', 'license-shipper')); ?></td>
          </tr>
        </table>

    </form>
</div>
