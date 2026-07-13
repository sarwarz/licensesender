<?php
if ( ! defined('ABSPATH') ) exit;

class LS_Admin_Licenses_Datatables {

    private static $slug = 'ls-licensesender';

    // Call this early (plugins_loaded)
    public static function boot() {

        add_action('wp_ajax_ls_licenses_dt', [__CLASS__, 'ajax_dt']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);

        add_action('wp_ajax_ls_fetch_license_by_sku', [__CLASS__, 'ajax_fetch_license_by_sku']);
    }

    // Keep this to set the page slug used by enqueue() gating and render()
    public static function init_for_menu($menu_slug) {
        self::$slug = $menu_slug ?: self::$slug;
    }

    /** Enqueue DataTables + our CSS/JS only on our screen */
    public static function enqueue( $hook ) {
        
        if ( LS_Admin_Service::uses_react_admin() ) {
            return;
        }

        if ( empty($_GET['page']) || ! in_array( $_GET['page'], array( self::$slug, 'ls-licensesender-edit', 'ls-licensesender-report' ), true ) ) {
            return;
        }


        // --- 3rd-party DataTables CSS (local) ---
        $base_url = plugin_dir_url(__FILE__);
        $css_path = $base_url . 'css/datatables/';
        $js_path  = $base_url . 'js/datatables/';

        wp_register_style('dt-core',       $css_path . 'dataTables.min.css', [], '1.13.8');
        wp_register_style('dt-buttons',    $css_path . 'buttons.dataTables.min.css', [], '2.4.2');
        wp_register_style('dt-responsive', $css_path . 'responsive.dataTables.min.css', [], '2.5.0');

        wp_enqueue_style('dt-core');
        wp_enqueue_style('dt-buttons');
        wp_enqueue_style('dt-responsive');

        // --- 3rd-party DataTables JS (local) ---
        wp_register_script('dt-core',        $js_path . 'dataTables.min.js', ['jquery'], '1.13.8', true);
        wp_register_script('dt-buttons',     $js_path . 'dataTables.buttons.min.js', ['dt-core'], '2.4.2', true);
        wp_register_script('dt-buttons-h5',  $js_path . 'buttons.html5.min.js', ['dt-buttons'], '2.4.2', true);
        wp_register_script('dt-buttons-pr',  $js_path . 'buttons.print.min.js', ['dt-buttons'], '2.4.2', true);
        wp_register_script('dt-responsive',  $js_path . 'dataTables.responsive.min.js', ['dt-core'], '2.5.0', true);

        wp_enqueue_script('dt-core');
        wp_enqueue_script('dt-buttons');
        wp_enqueue_script('dt-buttons-h5');
        wp_enqueue_script('dt-buttons-pr');
        wp_enqueue_script('dt-responsive');


        $base_dir = plugin_dir_path(__FILE__);
        $base_url = plugin_dir_url(__FILE__);

        // --- Our CSS/JS (separate files) ---
        

        $css_rel  = 'css/ls-licenses-dt.css';
        $js_rel   = 'js/ls-licenses-dt.js';

        wp_enqueue_style(
            'ls-licenses-dt-css',
            $base_url . $css_rel,
            ['dt-core','dt-buttons','dt-responsive'],
            file_exists($base_dir . $css_rel) ? filemtime($base_dir . $css_rel) : '1.0.0'
        );

        wp_enqueue_script(
            'ls-licenses-dt-js',
            $base_url . $js_rel,
            ['dt-responsive','dt-buttons-h5','dt-buttons-pr'],
            file_exists($base_dir . $js_rel) ? filemtime($base_dir . $js_rel) : '1.0.0',
            true
        );

        // Data for JS
        wp_localize_script('ls-licenses-dt-js', 'LSLicensesDT', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ls_dt'),
        ]);

        
    }

    /** Admin page renderer */
    public static function render() {
        if ( LS_Admin_Service::uses_react_admin() ) {
            LS_Admin_Assets::render_shell( 'licenses' );
            return;
        }

        $stats = LS_Admin_Service::get_license_stats();
        $total    = (int) ( $stats['total'] ?? 0 );
        $orders   = (int) ( $stats['orders'] ?? 0 );
        $products = (int) ( $stats['products'] ?? 0 );
        $emails   = (int) ( $stats['emails'] ?? 0 );
        ?>
        <div class="wrap" id="ls-dt-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('License Keys', 'licensesender'); ?></h1>
            <hr class="wp-header-end">

            <!-- Summary cards -->
            <div class="ls-cards">
                <div class="ls-card">
                    <div class="ls-card__label"><?php esc_html_e('Total Keys','licensesender');?></div>
                    <div class="ls-card__value"><?php echo number_format_i18n($total);?></div>
                </div>
                <div class="ls-card">
                    <div class="ls-card__label"><?php esc_html_e('Orders','licensesender');?></div>
                    <div class="ls-card__value"><?php echo number_format_i18n($orders);?></div>
                </div>
                <div class="ls-card">
                    <div class="ls-card__label"><?php esc_html_e('LS Products','licensesender');?></div>
                    <div class="ls-card__value"><?php echo number_format_i18n($products);?></div>
                </div>
                <div class="ls-card">
                    <div class="ls-card__label"><?php esc_html_e('Unique Emails','licensesender');?></div>
                    <div class="ls-card__value"><?php echo number_format_i18n($emails);?></div>
                </div>
            </div>

            <!-- DataTable -->
            <table id="ls-licenses" class="display stripe" style="width:100%">
                <thead>
                <tr>
                    <th>id</th>
                    <th><?php esc_html_e('License Key','licensesender'); ?></th>
                    <th><?php esc_html_e('Order ID','licensesender'); ?></th>
                    <th><?php esc_html_e('Product','licensesender'); ?></th>
                    <th><?php esc_html_e('SKU','licensesender'); ?></th>
                    <th><?php esc_html_e('Email','licensesender'); ?></th>
                    <th><?php esc_html_e('Sold Date','licensesender'); ?></th>
                    <th><?php esc_html_e('Action','licensesender'); ?></th>
                </tr>
                </thead>
            </table>
        </div>
        <?php
    }

    /** DataTables server-side handler */
    public static function ajax_dt() {
        // --- Nonce: accept multiple keys to avoid 400s ---
        $nonce = '';
        if ( isset($_POST['nonce']) )          $nonce = sanitize_text_field( wp_unslash($_POST['nonce']) );
        elseif ( isset($_POST['security']) )   $nonce = sanitize_text_field( wp_unslash($_POST['security']) );
        elseif ( isset($_POST['_ajax_nonce']) )$nonce = sanitize_text_field( wp_unslash($_POST['_ajax_nonce']) );

        if ( ! wp_verify_nonce( $nonce, 'ls_dt' ) ) {
            wp_send_json_error( [ 'message' => 'Bad or missing nonce' ], 403 );
        }

        if ( ! current_user_can('manage_woocommerce') && ! current_user_can('manage_options') ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
        }

        wp_send_json( LS_Admin_Service::list_licenses_datatables( $_POST ) );
    }



    public static function render_edit() {
        if ( LS_Admin_Service::uses_react_admin() ) {
            LS_Admin_Assets::render_shell( 'licenses' );
            return;
        }

        if ( ! current_user_can('manage_woocommerce') && ! current_user_can('manage_options') ) {
            wp_die( esc_html__('You do not have permission to access this page.', 'licensesender'), 403 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ls_cached_licenses';

        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if (!$id) {
            echo '<div class="notice notice-error"><p>'.esc_html__('Invalid license ID.', 'licensesender').'</p></div>';
            return;
        }

        // Handle save first (so the UI shows updated data)
        if ( ! empty($_POST['ls_edit_nonce']) && wp_verify_nonce($_POST['ls_edit_nonce'], 'ls_edit_license') ) {
            $data = [
                'key_value'       => isset($_POST['key_value']) ? sanitize_text_field(wp_unslash($_POST['key_value'])) : '',
                'email'           => isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '',
                'sku'             => isset($_POST['sku']) ? sanitize_text_field(wp_unslash($_POST['sku'])) : '',
                'download_link'   => isset($_POST['download_link']) ? esc_url_raw(wp_unslash($_POST['download_link'])) : '',
                'activation_guide'=> isset($_POST['activation_guide']) ? wp_kses_post(wp_unslash($_POST['activation_guide'])) : '',
                'source'          => isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : '',
            ];

            $wpdb->update(
                $table,
                $data,
                [ 'id' => $id ],
                [ '%s','%s','%s','%s','%s','%s' ],
                [ '%d' ]
            );

            echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('License updated successfully.', 'licensesender').'</p></div>';
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id));
        if (!$row) {
            echo '<div class="notice notice-error"><p>'.esc_html__('License not found.', 'licensesender').'</p></div>';
            return;
        }

        // Helpful links
        $order_link   = $row->order_id ? admin_url('post.php?post='.$row->order_id.'&action=edit') : '';
        $product_link = $row->product_id ? get_edit_post_link($row->product_id) : '';

        ?>
        <div class="wrap ls-edit-wrap">

          <!-- Header Card -->
          <div class="ls-header-card">
              <h1>Edit License</h1>
              <p class="ls-subtitle"><?php esc_html_e('Update license details and customer info.','licensesender'); ?></p>
          </div>

          <!-- Form Card -->
          <form method="post" class="ls-card ls-form">
              <?php wp_nonce_field('ls_edit_license','ls_edit_nonce'); ?>
              <input type="hidden" name="id" value="<?php echo esc_attr($row->id); ?>">

              <div class="ls-field">
                  <label for="key_value">License Key</label>
                  <div class="ls-input-with-btn">
                      <input type="text" id="key_value" name="key_value" value="<?php echo esc_attr($row->key_value); ?>">
                  </div>
              </div>


              <div class="ls-field">
                  <label for="download_link">Download Link</label>
                  <input type="url" id="download_link" name="download_link" value="<?php echo esc_url($row->download_link ?: ''); ?>">
              </div>

              <div class="ls-field">
                  <label for="email">Customer Email</label>
                  <input type="email" id="email" name="email" value="<?php echo esc_attr($row->email); ?>" readonly>
              </div>

              <div class="ls-field">
                  <label for="sku">SKU</label>
                  <input type="text" id="sku" name="sku" value="<?php echo esc_attr($row->sku); ?>" readonly>
              </div>

              <div class="ls-field">
                  <label for="order_id">Order ID</label>
                  <input type="text" id="order_id" value="<?php echo (int)$row->order_id; ?>" readonly>
              </div>

              <?php 
                $product_name = '';
                if ( !empty($row->product_id) ) {
                    $product = wc_get_product($row->product_id);
                    if ( $product ) {
                        $product_name = $product->get_name();
                    }
                }

               ?>
              <div class="ls-field">
                <label for="product_id">Product</label>
                <input type="text" id="product_id" 
                       value="<?php echo (int)$row->product_id . ( $product_name ? ' – ' . esc_html($product_name) : '' ); ?>" 
                       readonly>
              </div>

              <div class="ls-field">
                  <label for="created_at">Sold Date</label>
                  <input type="text" id="created_at" value="<?php echo esc_html($row->created_at); ?>" readonly>
              </div>

              

              <!-- Sticky Footer -->
              <div class="ls-actionbar">
                  <a href="<?php echo esc_url(admin_url('admin.php?page=ls-licensesender')); ?>" class="button ls-back-btn">
                      ← <?php esc_html_e('Back','licensesender'); ?>
                  </a>
                  <button type="submit" class="button button-primary ls-save-btn">
                        <?php esc_html_e('Save Changes','licensesender'); ?>
                  </button>
              </div>
          </form>
        </div>


        <?php
    }

    public static function render_report() {
        if ( LS_Admin_Service::uses_react_admin() ) {
            LS_Admin_Assets::render_shell( 'licenses' );
            return;
        }

        if ( ! current_user_can('manage_woocommerce') && ! current_user_can('manage_options') ) {
            wp_die( esc_html__('You do not have permission to access this page.', 'licensesender'), 403 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ls_cached_licenses';

        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if (!$id) {
            echo '<div class="notice notice-error"><p>'.esc_html__('Invalid license ID.', 'licensesender').'</p></div>';
            return;
        }

        if ( ! empty($_POST['ls_report_nonce']) && wp_verify_nonce($_POST['ls_report_nonce'], 'ls_report_license') ) {
            $result = LS_Admin_Service::report_license(
                $id,
                array(
                    'reason' => isset($_POST['reason']) ? sanitize_key( wp_unslash($_POST['reason']) ) : 'dead_key',
                    'mode'   => isset($_POST['mode']) ? sanitize_key( wp_unslash($_POST['mode']) ) : 'auto',
                    'notes'  => isset($_POST['notes']) ? sanitize_textarea_field( wp_unslash($_POST['notes']) ) : '',
                )
            );

            if ( is_wp_error( $result ) ) {
                echo '<div class="notice notice-error"><p>'.esc_html( $result->get_error_message() ).'</p></div>';
            } else {
                $report = is_array( $result['report'] ?? null ) ? $result['report'] : array();
                $msg    = ! empty( $report['replacement_key'] )
                    ? sprintf( __( 'Key replaced: %s', 'licensesender' ), $report['replacement_key'] )
                    : __( 'License reported successfully.', 'licensesender' );
                echo '<div class="notice notice-success is-dismissible"><p>'.esc_html( $msg ).'</p></div>';
            }
        }

        $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id) );
        if ( ! $row ) {
            echo '<div class="notice notice-error"><p>'.esc_html__('License not found.', 'licensesender').'</p></div>';
            return;
        }

        $product_name = '';
        if ( !empty($row->product_id) ) {
            $p = wc_get_product($row->product_id);
            if ($p) $product_name = $p->get_name();
        }

        $reasons = array(
            'dead_key'           => __( 'Dead key', 'licensesender' ),
            'activation_failed'  => __( 'Activation failed', 'licensesender' ),
            'invalid_key'        => __( 'Invalid key', 'licensesender' ),
            'customer_request'   => __( 'Customer request', 'licensesender' ),
            'other'              => __( 'Other', 'licensesender' ),
        );
        ?>
        <div class="wrap ls-change-wrap">
          <div class="ls-header-card">
            <h1><?php esc_html_e('Report Key','licensesender'); ?></h1>
            <p class="ls-subtitle"><?php esc_html_e('Report a dead or issue key to LicenseSender for auto or manual replacement.','licensesender'); ?></p>
          </div>

          <form method="post" class="ls-card ls-form">
            <?php wp_nonce_field('ls_report_license','ls_report_nonce'); ?>

            <div class="ls-field">
              <label><?php esc_html_e('Current License','licensesender'); ?></label>
              <input type="text" value="<?php echo esc_attr($row->key_value); ?>" readonly>
            </div>

            <div class="ls-field">
              <label><?php esc_html_e('Order','licensesender'); ?></label>
              <input type="text" value="#<?php echo esc_attr( (string) $row->order_id ); ?>" readonly>
            </div>

            <div class="ls-field">
              <label><?php esc_html_e('Product','licensesender'); ?></label>
              <input type="text" value="<?php
                echo (int)$row->product_id . ( $product_name ? ' – ' . esc_html($product_name) : '' );
              ?>" readonly>
            </div>

            <div class="ls-field">
              <label for="ls-report-reason"><?php esc_html_e('Reason','licensesender'); ?></label>
              <select id="ls-report-reason" name="reason">
                <?php foreach ( $reasons as $value => $label ) : ?>
                  <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="ls-field">
              <label for="ls-report-mode"><?php esc_html_e('Mode','licensesender'); ?></label>
              <select id="ls-report-mode" name="mode">
                <option value="auto"><?php esc_html_e('Auto (replace now if available)','licensesender'); ?></option>
                <option value="manual"><?php esc_html_e('Manual (log issue for later)','licensesender'); ?></option>
              </select>
            </div>

            <div class="ls-field">
              <label for="ls-report-notes"><?php esc_html_e('Notes (optional)','licensesender'); ?></label>
              <textarea id="ls-report-notes" name="notes" rows="5" placeholder="<?php esc_attr_e('Customer reported activation failed','licensesender'); ?>"></textarea>
            </div>

            <div class="ls-actionbar">
              <a href="<?php echo esc_url( admin_url('admin.php?page=ls-licensesender') ); ?>" class="button ls-back-btn">← <?php esc_html_e('Back','licensesender'); ?></a>
              <button type="submit" class="button button-primary ls-save-btn"><?php esc_html_e('Report Key','licensesender'); ?></button>
            </div>
          </form>
        </div>
        <?php
    }


    public static function ajax_fetch_license_by_sku() {
        if ( ! current_user_can('manage_woocommerce') && ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ( ! wp_verify_nonce($nonce, 'ls_dt') ) {
            wp_send_json_error(['message' => 'Bad nonce'], 403);
        }

        $sku = isset($_POST['sku']) ? sanitize_text_field(wp_unslash($_POST['sku'])) : '';
        if ( $sku === '' ) {
            wp_send_json_error(['message' => 'Missing SKU'], 400);
        }

        // Optional: allow client to pass sort (defaults to 'id,asc') and timeout.
        $sort    = isset($_POST['sort']) ? sanitize_text_field(wp_unslash($_POST['sort'])) : 'id,asc';
        $timeout = isset($_POST['timeout']) ? (int) $_POST['timeout'] : 30;

        // Ask API for exactly ONE available license for this SKU
        $api_res = Licensesender_Api::fetch_one_available_license_by_sku($sku, [
            'sort'    => $sort,    // e.g., 'created_at,asc' or 'id,asc'
            'timeout' => $timeout,
        ]);

        if ( empty($api_res['success']) ) {
            $code = ! empty($api_res['http_code']) ? (int) $api_res['http_code'] : 500;
            wp_send_json_error([
                'message' => ! empty($api_res['message']) ? (string) $api_res['message'] : 'Failed to fetch license.',
                'meta'    => isset($api_res['meta']) ? $api_res['meta'] : [],
            ], $code);
        }

        $license = isset($api_res['license']) && is_array($api_res['license']) ? $api_res['license'] : [];

        // Map possible key fields from API: prefer full key_value, then key, then masked_key (fallback).
        $key_value = '';
        if ( isset($license['key_value']) ) {
            $key_value = (string) $license['key_value'];
        } elseif ( isset($license['key']) ) {
            $key_value = (string) $license['key'];
        } elseif ( isset($license['masked_key']) ) {
            $key_value = (string) $license['masked_key']; // fallback if API only returns masked in this endpoint
        }

        if ( $key_value === '' ) {
            wp_send_json_error(['message' => 'No license found for this SKU'], 404);
        }

        // Download link / activation guide may come from license or be absent.
        $download_link    = isset($license['download_link'])    ? (string) $license['download_link']    : '';
        $activation_guide = isset($license['activation_guide']) ? (string) $license['activation_guide'] : '';

        wp_send_json_success([
            'key_value'        => $key_value,
            'download_link'    => $download_link,
            'activation_guide' => $activation_guide,
            'license'          => $license,                 // full raw license object (optional but handy)
            'meta'             => $api_res['meta'] ?? [],   // pass-through meta
        ]);
    }











}
