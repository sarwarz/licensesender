<?php
defined('ABSPATH') || exit;

class Ls_Admin_Download_Display {

    public static function output() {
        if ( LS_Admin_Service::uses_react_admin() ) {
            LS_Admin_Assets::render_shell( 'download-links' );
            return;
        }

        global $wpdb;
        // Fetch all WooCommerce products
        $products = function_exists('wc_get_products')
            ? wc_get_products([
                'limit' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
                'status' => ['publish'],
            ])
            : [];

        ?>
        <div class="wrap" id="ls-download-wrapper">
            <h1 class="wp-heading-inline"><?php _e('Manage Download Links', 'licensesender'); ?></h1>
            <a href="#" class="page-title-action" id="ls-add-link-btn"><?php _e('Add New', 'licensesender'); ?></a>
            <hr class="wp-header-end">

            <table id="ls-download-links-table" class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'licensesender'); ?></th>
                        <th><?php _e('Product', 'licensesender'); ?></th>
                        <th><?php _e('Download Link', 'licensesender'); ?></th>
                        <th><?php _e('Actions', 'licensesender'); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <!-- Modal -->
        <div id="ls-download-modal" style="display:none;">
            <form id="ls-download-form">
                <input type="hidden" name="id" id="ls-id">

                <table class="form-table">
                    <tr>
                        <th><label for="ls-product-id"><?php _e('Product', 'licensesender'); ?></label></th>
                        <td>
                            <select name="product_id" id="ls-product-id" style="width:100%;" required>
                                <option value=""><?php _e('Select a product', 'licensesender'); ?></option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo esc_attr($product->get_id()); ?>">
                                        <?php echo esc_html($product->get_name()); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ls-link"><?php _e('Download Link', 'licensesender'); ?></label></th>
                        <td><input type="url" name="link" id="ls-link" class="regular-text" required></td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Save', 'licensesender'); ?></button>
                    <button type="button" class="button ls-modal-close"><?php _e('Cancel', 'licensesender'); ?></button>
                </p>
            </form>
        </div>

        <?php
        
    }
}
