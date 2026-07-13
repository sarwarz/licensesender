<?php
defined('ABSPATH') || exit;

class Ls_Admin_Activation_Display {

    
    public static function output() {
        if ( LS_Admin_Service::uses_react_admin() ) {
            $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
            if ( $action === 'add' || $action === 'edit' ) {
                LS_Admin_Assets::render_shell( 'activation-guides' );
                return;
            }
            LS_Admin_Assets::render_shell( 'activation-guides' );
            return;
        }

        global $wpdb;

        // Handle Add/Edit view
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        if ($action === 'add' || $action === 'edit') {
            self::render_form($action);
            return;
        }

        $ajax_url = admin_url('admin-ajax.php');
        ?>
        <div class="wrap" id="ls-activation-wrapper">
            <h1 class="wp-heading-inline"><?php _e('Manage Activation Guides', 'licensesender'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=ls-activation-guides&action=add'); ?>" 
               class="page-title-action"><?php _e('Add New', 'licensesender'); ?></a>
            <hr class="wp-header-end">

            <table id="ls-activation-guides-table" class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'licensesender'); ?></th>
                        <th><?php _e('Product', 'licensesender'); ?></th>
                        <th><?php _e('Type', 'licensesender'); ?></th>
                        <th><?php _e('Created At', 'licensesender'); ?></th>
                        <th><?php _e('Actions', 'licensesender'); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <?php
    }

    /**
     * Render Add/Edit Form
     */
    private static function render_form($action) {
        global $wpdb;

        $is_edit = ($action === 'edit');
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $table = $wpdb->prefix . 'ls_activation_guides';

        // Fetch existing record
        $record = $is_edit
            ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A)
            : null;

        // Extract stored data
        $selected_product = $record['product_id'] ?? '';
        $selected_type    = $record['type'] ?? 'text';
        
        $record = $record ?? []; // ensure $record is at least an empty array

        $stored_content = !empty($record['content']) ? maybe_unserialize($record['content']) : [];
        $html_content   = is_array($stored_content) ? ($stored_content['html'] ?? '') : ($record['content'] ?? '');
        $pdf_link       = is_array($stored_content) ? ($stored_content['pdf'] ?? '') : '';


        // Fetch products
        $products = function_exists('wc_get_products')
            ? wc_get_products(['limit' => -1, 'orderby' => 'title', 'order' => 'ASC', 'status' => ['publish']])
            : [];

        ?>
        <div class="wrap" id="ls-activation-form-wrapper">
            <h1 class="wp-heading-inline">
                <?php echo $is_edit ? __('Edit Activation Guide', 'licensesender') : __('Add New Activation Guide', 'licensesender'); ?>
            </h1>
            <a href="<?php echo admin_url('admin.php?page=ls-activation-guides'); ?>" class="page-title-action">
                ← <?php _e('Back to list', 'licensesender'); ?>
            </a>
            <hr class="wp-header-end">

            <form id="ls-activation-guide-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('ls_activation_guide_nonce'); ?>
                <input type="hidden" name="action" value="ls_save_activation_guide">
                <input type="hidden" name="id" value="<?php echo esc_attr($record['id'] ?? ''); ?>">

                <table class="form-table">
                    <tr>
                        <th><label for="product_id"><?php _e('Product', 'licensesender'); ?></label></th>
                        <td>
                            <select name="product_id" id="product_id" style="width:100%;" required>
                                <option value=""><?php _e('Select a product', 'licensesender'); ?></option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo esc_attr($product->get_id()); ?>" 
                                        <?php selected($selected_product, $product->get_id()); ?>>
                                        <?php echo esc_html($product->get_name()); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="type"><?php _e('Activation Type', 'licensesender'); ?></label></th>
                        <td>
                            <select name="type" id="type" required>
                                <option value="text" <?php selected($selected_type, 'text'); ?>>Plain Text</option>
                                <option value="pdf" <?php selected($selected_type, 'pdf'); ?>>PDF Upload</option>
                            </select>
                        </td>
                    </tr>

                    <!-- Text editor -->
                    <tr id="text-content-row">
                        <th><label for="content"><?php _e('Activation Guide Content', 'licensesender'); ?></label></th>
                        <td>
                            <?php
                            wp_editor($html_content, 'content', [
                                'textarea_name' => 'content',
                                'media_buttons' => true,
                                'textarea_rows' => 8,
                            ]);
                            ?>
                        </td>
                    </tr>

                    <!-- PDF upload -->
                    <tr id="pdf-upload-row" style="display:none;">
                        <th><label for="pdf_file"><?php _e('Upload PDF', 'licensesender'); ?></label></th>
                        <td>
                            <input type="file" name="pdf_file" id="pdf_file" accept="application/pdf">
                            <?php if ($pdf_link): ?>
                                <p>
                                    <a href="<?php echo esc_url($pdf_link); ?>" target="_blank" class="button button-secondary">
                                        <?php _e('View Current PDF', 'licensesender'); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Save', 'licensesender'); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=ls-activation-guides'); ?>" class="button">
                        <?php _e('Cancel', 'licensesender'); ?>
                    </a>
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            function toggleFields() {
                const type = $('#type').val();
                if (type === 'pdf') {
                    $('#pdf-upload-row').show();
                    $('#text-content-row').hide();
                } else {
                    $('#pdf-upload-row').hide();
                    $('#text-content-row').show();
                }
            }
            toggleFields();
            $('#type').on('change', toggleFields);

            $('#product_id').select2({
                placeholder: 'Select a product...',
                width: '100%'
            });
        });
        </script>
        <?php
    }

}
