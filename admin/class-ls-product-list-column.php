<?php
defined('ABSPATH') || exit;

class LS_Product_List_License_Column {

    public function __construct() {
        add_filter('manage_edit-product_columns', [$this, 'add_column'], 20);
        add_action('manage_product_posts_custom_column', [$this, 'render_column'], 10, 2);
        add_filter('manage_edit-product_sortable_columns', [$this, 'sortable_column']);
    }

    /**
     * Add column header
     */
    public function add_column($columns) {
        $new_columns = [];

        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;

            if ($key === 'sku') {
                $new_columns['ls_map_status'] = __('Product Mapped', 'license-shipper');
            }
        }

        return $new_columns;
    }

    /**
     * Render column content
     */
    public function render_column($column, $post_id) {

        if ($column !== 'ls_map_status') {
            return;
        }

        $product = wc_get_product($post_id);
        if (!$product) {
            return;
        }

        // Simple product
        if ($product->is_type('simple')) {
            echo $this->get_status_icon(
                get_post_meta($post_id, '_ls_enabled', true),
                get_post_meta($post_id, '_ls_mapped_product', true)
            );
            return;
        }

        // Variable product
        if ($product->is_type('variable')) {
            if ( get_option( 'lship_enable_variation_support', 'no' ) !== 'yes' ) {
                echo $this->get_status_icon(
                    get_post_meta( $post_id, '_ls_enabled', true ),
                    get_post_meta( $post_id, '_ls_mapped_product', true )
                );
                return;
            }

            $mapped = 0;
            $total  = 0;

            foreach ($product->get_children() as $variation_id) {
                $enabled = get_post_meta($variation_id, '_ls_enabled', true);
                $mapped_product = get_post_meta($variation_id, '_ls_mapped_product', true);

                if ($enabled === 'yes') {
                    $total++;
                    if (!empty($mapped_product)) {
                        $mapped++;
                    }
                }
            }

            if ($total === 0) {
                echo $this->icon_not_mapped();
            } elseif ($mapped === $total) {
                echo $this->icon_mapped();
            } else {
                echo $this->icon_partial();
            }
        }
    }

    /**
     * Dashicon helpers
     */
    private function icon_mapped() {
        return '<span class="dashicons dashicons-yes-alt" title="Mapped" style="color:#46b450;font-size:18px;"></span>';
    }

    private function icon_not_mapped() {
        return '<span class="dashicons dashicons-no-alt" title="Not mapped" style="color:#dc3232;font-size:18px;"></span>';
    }

    private function icon_partial() {
        return '<span class="dashicons dashicons-warning" title="Partially mapped" style="color:#ffb900;font-size:18px;"></span>';
    }

    /**
     * Status resolver
     */
    private function get_status_icon($enabled, $mapped_product) {
        if ($enabled === 'yes' && !empty($mapped_product)) {
            return $this->icon_mapped();
        }
        return $this->icon_not_mapped();
    }

    /**
     * Make column sortable (optional)
     */
    public function sortable_column($columns) {
        $columns['ls_map_status'] = 'ls_map_status';
        return $columns;
    }
}

new LS_Product_List_License_Column();
