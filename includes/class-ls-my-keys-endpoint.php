<?php
/**
 * LicenseShipper – "My Keys" WooCommerce My Account endpoint
 *
 * Text domain: license-shipper
 */

if ( ! defined('ABSPATH') ) exit;

class LS_My_Keys_Endpoint {

    const ENDPOINT   = 'my-keys';
    const MENU_TITLE = 'My Keys';
    const PER_PAGE   = 8; // orders per page (like Woo Orders table)

    public static function init() {
        // Endpoint + query var
        add_action('init', [__CLASS__, 'add_endpoint'], 0);
        add_filter('query_vars', [__CLASS__, 'add_query_var']);

        // Menu + content hook
        add_filter('woocommerce_account_menu_items', [__CLASS__, 'inject_menu_item']);
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', [__CLASS__, 'render_endpoint']);

        // AJAX
        add_action('wp_ajax_licenseshipper_get_key', [__CLASS__, 'ajax_get_key']);
        add_action('wp_ajax_nopriv_licenseshipper_get_key', [__CLASS__, 'ajax_get_key']);

        // View cached keys (plural)
        add_action('wp_ajax_licenseshipper_view_key', [__CLASS__, 'ajax_view_key']);
        add_action('wp_ajax_nopriv_licenseshipper_view_key', [__CLASS__, 'ajax_view_key']);
    }

    /** Call on plugin activation */
    public static function activate() {
        self::add_endpoint();
        flush_rewrite_rules();
    }

    /** Optional: call on deactivation */
    public static function deactivate() {
        flush_rewrite_rules();
    }

    public static function add_endpoint() {
        // Enables /my-account/my-keys/ and /my-account/my-keys/{page}/
        add_rewrite_endpoint(self::ENDPOINT, EP_ROOT | EP_PAGES);
    }

    public static function add_query_var($vars) {
        $vars[] = self::ENDPOINT;
        return $vars;
    }

    public static function inject_menu_item($items) {
        // Insert after Orders if present
        $new = [];
        $inserted = false;
        foreach ($items as $key => $label) {
            $new[$key] = $label;
            if ($key === 'orders') {
                $new[self::ENDPOINT] = __(self::MENU_TITLE, 'license-shipper');
                $inserted = true;
            }
        }
        if (!$inserted) {
            $new[self::ENDPOINT] = __(self::MENU_TITLE, 'license-shipper');
        }
        return $new;
    }

    public static function render_endpoint() {
        if (! is_user_logged_in()) {
            echo '<p>' . esc_html__('You must be logged in to view this page.', 'license-shipper') . '</p>';
            return;
        }

        $user_id = get_current_user_id();

        // Read page from endpoint value like /my-account/my-keys/2/; fallback to ?p=2.
        $endpoint_val = get_query_var(self::ENDPOINT);
        $paged        = max(1, absint( $endpoint_val ?: ( $_GET['p'] ?? 1 ) ));

        // Orders-style pagination (fast + native)
        $q = wc_get_orders([
            'customer_id' => $user_id,
            'status'      => ['wc-completed', 'wc-processing', 'wc-on-hold'],
            'paginate'    => true,
            'limit'       => self::PER_PAGE,
            'paged'       => $paged,
            'return'      => 'objects',
            'orderby'     => 'date',
            'order'       => 'DESC',
        ]);

        $orders       = $q && ! empty($q->orders) ? $q->orders : [];
        $total_pages  = (int)($q->max_num_pages ?? 1);

        echo '<div class="ls-my-keys-wrap">';
        echo '<h3 class="ls-title">' . esc_html__(self::MENU_TITLE, 'license-shipper') . '</h3>';
        echo '<p class="ls-subtitle">' . esc_html__('Access your purchased products and retrieve keys.', 'license-shipper') . '</p>';

        if (empty($orders)) {
            echo '<div class="woocommerce-message">' . esc_html__('No products found from your orders yet.', 'license-shipper') . '</div>';
            echo '</div>';
            return;
        }

        // Flatten the items from the orders on THIS page only
        $items = self::flatten_items_from_orders($orders);

        // Mobile: cards
        echo '<div class="ls-grid-cards">';
        foreach ($items as $row) self::render_card($row);
        echo '</div>';

        // Desktop: table
        echo '<div class="ls-table-wrap">';
        echo '<table class="shop_table shop_table_responsive ls-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Order', 'license-shipper') . '</th>';
        echo '<th>' . esc_html__('Product', 'license-shipper') . '</th>';
        echo '<th>' . esc_html__('Qty', 'license-shipper') . '</th>';
        echo '<th class="ls-actions-col">' . esc_html__('Action', 'license-shipper') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($items as $row) self::render_table_row($row);
        echo '</tbody></table></div>';

        // Pagination (endpoint-style links)
        if ($total_pages > 1) {
            $base = wc_get_page_permalink('myaccount'); // My Account page
            echo '<nav class="ls-pagination">';

            if ($paged > 1) {
                echo '<a class="prev" href="' . esc_url( wc_get_endpoint_url(self::ENDPOINT, $paged - 1, $base) ) . '">&laquo; ' . esc_html__('Previous', 'license-shipper') . '</a>';
            }
            for ($i = 1; $i <= $total_pages; $i++) {
                $class = $i === $paged ? ' class="current"' : '';
                echo '<a' . $class . ' href="' . esc_url( wc_get_endpoint_url(self::ENDPOINT, $i, $base) ) . '">' . (int)$i . '</a>';
            }
            if ($paged < $total_pages) {
                echo '<a class="next" href="' . esc_url( wc_get_endpoint_url(self::ENDPOINT, $paged + 1, $base) ) . '">' . esc_html__('Next', 'license-shipper') . ' &raquo;</a>';
            }
            echo '</nav>';
        }

        echo '</div>'; // .ls-my-keys-wrap
    }

    /** Flatten current page's order items for rendering */
    protected static function flatten_items_from_orders(array $orders): array {
        $rows = [];
        foreach ($orders as $order) {
            /** @var WC_Order $order */
            foreach ($order->get_items('line_item') as $item) {
                /** @var WC_Order_Item_Product $item */
                $product    = $item->get_product();
                $product_id = $product ? $product->get_id() : (int)$item->get_product_id();

                if ( ! ls_is_license_shipper_enabled($product_id) ) continue;


                $name       = $item->get_name();
                $qty        = (int)$item->get_quantity();
                $thumb      = $product ? $product->get_image('woocommerce_thumbnail', ['loading' => 'lazy']) : wc_placeholder_img('woocommerce_thumbnail');

                $rows[] = [
                  'order_id'      => $order->get_id(),
                  'order_number'  => $order->get_order_number(),
                  'order_url'     => $order->get_view_order_url(),
                  'product_id'    => $product_id,
                  'product_name'  => $name,
                  'qty'           => $qty,
                  'thumb_html'    => $thumb,
                  'billing_email' => $order->get_billing_email(), // <— add this
                ];
            }
        }
        // Ensure newest orders first (stable)
        usort($rows, fn($a,$b) => $b['order_id'] <=> $a['order_id']);
        return $rows;
    }

    /** Renderers */
    protected static function render_table_row($r) {
        $order_link = sprintf('<a href="%s">%s</a>', esc_url($r['order_url']), '#' . esc_html($r['order_number']));
        echo '<tr>';
        echo '<td data-title="' . esc_attr__('Order', 'license-shipper') . '">' . $order_link . '</td>';
        echo '<td data-title="' . esc_attr__('Product', 'license-shipper') . '">' . esc_html($r['product_name']) . '</td>';
        echo '<td data-title="' . esc_attr__('Qty', 'license-shipper') . '">' . (int)$r['qty'] . '</td>';
        echo '<td class="ls-actions">';
        self::render_actions($r);
        echo '</td>';
        echo '</tr>';
    }

    protected static function render_card($r) {
        echo '<div class="ls-card">';
        echo '  <div class="ls-card__media">' . $r['thumb_html'] . '</div>';
        echo '  <div class="ls-card__body">';
        echo '    <div class="ls-card__title">' . esc_html($r['product_name']) . '</div>';
        echo '    <div class="ls-card__meta">';
        echo '      <span class="ls-chip">#' . esc_html($r['order_number']) . '</span>';
        echo '      <span class="ls-chip">×' . (int)$r['qty'] . '</span>';
        echo '    </div>';
        echo '    <div class="ls-card__actions">';
        self::render_actions($r);
        echo '    </div>';
        echo '  </div>';
        echo '</div>';
    }

    protected static function render_actions($r) {
         $order = wc_get_order( (int) $r['order_id'] );

         if ( ! self::is_order_license_ready( (int) $r['order_id'] ) ) {
            if ( $order && ! $order->has_status( 'completed' ) ) {
                echo '<span class="ls-status ls-pending" title="' .
                     esc_attr__( 'Licenses are available after the order is completed.', 'license-shipper' ) .
                     '">' .
                     esc_html__( 'Pending completion', 'license-shipper' ) .
                     '</span>';
                return;
            }

            echo '<span class="ls-status ls-unmanaged" title="' .
                 esc_attr__( 'This order was completed by another system. License Shipper did not process this order, so licenses cannot be delivered.', 'license-shipper' ) .
                 '">' .
                 esc_html__( 'Undeliverable', 'license-shipper' ) .
                 '</span>';
            return;
        }


        $attrs = sprintf(
          ' data-product-name="%s" data-order-id="%d" data-product-id="%d" data-qty="%d" data-email="%s" ',
          esc_attr( (string) $r['product_name'] ),
          (int)$r['order_id'],
          (int)$r['product_id'],
          (int)$r['qty'],
          esc_attr( (string) $r['billing_email'] )
        );

        $count    = self::cached_license_count( (int) $r['order_id'], (int) $r['product_id'] );
        $expected = $order ? ls_count_expected_keys_for_product_in_order( $order, (int) $r['product_id'] ) : (int) $r['qty'];

        if ( $count >= $expected && $count > 0 ) {
            printf(
                '<button type="button" class="button ls-btn-view-key"%s data-product-name="%s" data-nonce="%s">%s</button>',
                $attrs,
                esc_attr($r['product_name']),
                esc_attr( wp_create_nonce('ls_view_key') ),
                sprintf( esc_html__('View Keys', 'license-shipper'))
            );
        } else {
            printf(
                '<button type="button" class="button ls-btn-get-key"%s data-nonce="%s">%s</button>',
                $attrs,
                esc_attr( wp_create_nonce('ls_get_key') ),
                esc_html__('Get Keys', 'license-shipper')
            );
        }


    }

    /** === Cache helpers (now plural-aware) === */

    protected static function cached_license_count(int $order_id, int $product_id): int {
        global $wpdb;
        $table = $wpdb->prefix . 'ls_cached_licenses';
        $sql   = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE order_id = %d AND product_id = %d AND fetched = 1",
            $order_id, $product_id
        );
        return (int) $wpdb->get_var($sql);
    }

    /**
     * Get ALL cached licenses for an order+product (latest first).
     * Returns an array of rows: [ ['key_value'=>..., 'download_link'=>..., 'activation_guide'=>..., 'sku'=>..., 'email'=>..., 'source'=>...], ... ]
     */
    protected static function get_cached_licenses(int $order_id, int $product_id): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ls_cached_licenses';
        $sql   = $wpdb->prepare(
            "SELECT id, key_value, download_link, activation_guide, sku, email, source
             FROM {$table}
             WHERE order_id = %d AND product_id = %d AND fetched = 1
             ORDER BY id DESC",
            $order_id, $product_id
        );
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    protected static function first_non_empty_meta(array $rows): array {
        $meta = ['download_link'=>'','activation_guide'=>'','sku'=>'','email'=>'','source'=>''];
        foreach ($rows as $r) {
            foreach ($meta as $k => $_) {
                if ($meta[$k] === '' && !empty($r[$k])) {
                    $meta[$k] = (string)$r[$k];
                }
            }
        }
        return $meta;
    }

    /** === AJAX: view cached keys (plural) === */
    public static function ajax_view_key() {
        check_ajax_referer('ls_view_key', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in.', 'license-shipper'), 'code' => 'unauth'], 403);
        }

        $user_id    = get_current_user_id();
        $order_id   = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

        if (!$order_id || !$product_id) {
            wp_send_json_error(['message' => __('Invalid request.', 'license-shipper'), 'code' => 'bad_req'], 400);
        }

        $order = wc_get_order($order_id);
        if (!$order || (int) $order->get_user_id() !== (int) $user_id) {
            wp_send_json_error(['message' => __('Order not found or not yours.', 'license-shipper'), 'code' => 'forbidden'], 403);
        }

        $rows = self::get_cached_licenses($order_id, $product_id);
        if (empty($rows)) {
            wp_send_json_error(['message' => __('No saved keys found.', 'license-shipper'), 'code' => 'not_found'], 404);
        }

        // Build per-row + meta guide links
        $meta = [
            'download_link'    => '',
            'activation_guide' => '',
            'sku'              => '',
            'email'            => '',
            'source'           => '',
            'guide_url'        => '',
        ];

        $keys = [];
        foreach ($rows as $i => $r) {
            // Fill first non-empty meta fields
            foreach (['download_link','activation_guide','sku','email','source'] as $k) {
                if ($meta[$k] === '' && !empty($r[$k])) {
                    $meta[$k] = (string) $r[$k];
                }
            }

            // Secure per-key guide URL with a key-specific nonce
            $key_id    = (int) $r['id'];
            $guide_url = ls_activation_guide_download_url( $key_id, $order );

            if ($i === 0) {
                $meta['guide_url'] = $guide_url; // convenient “one button” in the modal
            }

            $keys[] = [
                'id'               => $key_id,
                'key_value'        => (string) $r['key_value'],
                'download_link'    => (string) ($r['download_link'] ?? ''),
                'activation_guide' => (string) ($r['activation_guide'] ?? ''),
                'sku'              => (string) ($r['sku'] ?? ''),
                'email'            => (string) ($r['email'] ?? ''),
                'source'           => (string) ($r['source'] ?? ''),
                'guide_url'        => $guide_url,
            ];
        }

        wp_send_json_success([
            'count' => count($rows),
            'keys'  => $keys,
            'meta'  => $meta,
        ]);
    }



    public static function ajax_get_key() {
        check_ajax_referer('ls_get_key', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in.', 'license-shipper')], 403);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ls_cached_licenses';

        $user_id      = get_current_user_id();
        $order_id     = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $product_id   = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $posted_email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $posted_qty   = isset($_POST['qnty'])  ? absint($_POST['qnty'])          : 0;

        if (!$order_id || !$product_id) {
            wp_send_json_error(['message' => __('Invalid request.', 'license-shipper')], 400);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => __('Order not found.', 'license-shipper')], 404);
        }

        // Ownership + email
        $order_email = (string) $order->get_billing_email();
        if ($user_id && (int) $order->get_user_id() !== (int) $user_id) {
            wp_send_json_error(['message' => __('This order does not belong to you.', 'license-shipper')], 403);
        }
        if ($posted_email && strcasecmp($posted_email, $order_email) !== 0) {
            wp_send_json_error(['message' => __('Unauthorized access to order.', 'license-shipper')], 403);
        }
        $email = $posted_email ?: $order_email;

        // Match product (variation first) and qty from order
        $used_product_id = 0;
        $line_qty        = 0;
        foreach ($order->get_items() as $item) {
            $pid = (int) $item->get_product_id();
            $vid = (int) $item->get_variation_id();
            if ($vid && $vid === $product_id) {
                $used_product_id = $vid;
                $line_qty       += (int) $item->get_quantity();
            } elseif (!$vid && $pid === $product_id) {
                $used_product_id = $pid;
                $line_qty       += (int) $item->get_quantity();
            }
        }
        if (!$used_product_id) {
            wp_send_json_error(['message' => __('Product not found in this order.', 'license-shipper')], 404);
        }

        $expected_qty = ls_count_expected_keys_for_product_in_order( $order, $used_product_id );

        // === CACHE FIRST (complete only) ===
        $rows = self::get_cached_licenses( $order_id, $used_product_id );

        // Helper to build payload (adds id + signed guide_url)
        $build_payload = function( array $rows ) use ( $order ) {
            $meta = [
                'download_link'    => '',
                'activation_guide' => '',
                'sku'              => '',
                'email'            => '',
                'source'           => '',
                'guide_url'        => '', // first key’s signed URL for convenience
            ];
            $keys = [];
            foreach ($rows as $i => $r) {
                // Fill meta with first non-empty values
                foreach (['download_link','activation_guide','sku','email','source'] as $k) {
                    if ($meta[$k] === '' && !empty($r[$k])) {
                        $meta[$k] = (string) $r[$k];
                    }
                }
                // Build a signed, per-key guide URL
                $key_id   = isset( $r['id'] ) ? (int) $r['id'] : 0;
                $gurl     = $key_id ? ls_activation_guide_download_url( $key_id, $order ) : '';

                if ($i === 0 && $gurl) {
                    $meta['guide_url'] = $gurl;
                }

                $keys[] = [
                    'id'               => $key_id,
                    'key_value'        => (string) ($r['key_value'] ?? ''),
                    'download_link'    => (string) ($r['download_link'] ?? ''),
                    'activation_guide' => (string) ($r['activation_guide'] ?? ''),
                    'sku'              => (string) ($r['sku'] ?? ''),
                    'email'            => (string) ($r['email'] ?? ''),
                    'source'           => (string) ($r['source'] ?? ''),
                    'guide_url'        => $gurl, // per-key URL (useful if you render buttons per key)
                ];
            }
            return ['count' => count($rows), 'keys' => $keys, 'meta' => $meta];
        };

        // Completed orders only
        if ( ! in_array( $order->get_status(), array( 'completed' ), true ) ) {
            wp_send_json_error( array( 'message' => __( 'License can only be fetched for completed orders.', 'license-shipper' ) ), 400 );
        }

        if ( count( $rows ) >= $expected_qty && $expected_qty > 0 ) {
            $payload = $build_payload( $rows );
            wp_send_json_success( $payload );
        }

        $need = max( 1, $expected_qty - count( $rows ) );

        // Product settings
        if ( ! ls_is_license_shipper_enabled( $used_product_id ) ) {
            wp_send_json_error(['message' => __('License Shipper is not enabled for this product.', 'license-shipper')], 400);
        }
        $mapped_sku = ls_get_mapped_sku( $used_product_id );
        if (empty($mapped_sku)) {
            wp_send_json_error(['message' => __('This product does not have a mapped SKU.', 'license-shipper')], 400);
        }

        // Call vendor API
        if (!class_exists('License_Shipper_Api') || !method_exists('License_Shipper_Api', 'fetch_license')) {
            wp_send_json_error(['message' => __('License API is not available.', 'license-shipper')], 500);
        }

        $result = License_Shipper_Api::fetch_license([
            'sku'      => $mapped_sku,
            'quantity' => $need,
            'order_id' => $order_id,
            'email'    => $email,
            'source'   => sanitize_title(get_bloginfo('name')),
        ]);

        if (empty($result['success'])) {
            $msg    = $result['message'] ?? __('Request failed', 'license-shipper');
            $scope  = $result['meta']['scope']  ?? '';
            $reason = $result['meta']['reason'] ?? '';
            if ($reason) { $msg .= ' ' . sprintf(__('Reason: %s', 'license-shipper'), $reason); }
            if ($scope)  { $msg .= ' ' . sprintf(__('[%s block]', 'license-shipper'), ucfirst($scope)); }
            wp_send_json_error([
                'message'   => $msg,
                'http_code' => $result['http_code'] ?? null,
                'meta'      => $result['meta'] ?? [],
            ], 422);
        }

        $licenses     = is_array($result['licenses'] ?? null) ? $result['licenses'] : [];
        $product_info = is_array($result['product']  ?? null) ? $result['product']  : [];

        $links = ls_get_license_product_links( $used_product_id );
        $final_download_link = $links['download_link'] ?: ( $product_info['download_link'] ?? '' );
        $final_guide_link    = $links['activation_guide'] ?: ( $product_info['activation_guide'] ?? '' );

        // Insert into cache
        foreach ($licenses as $license) {
            $keyVal = isset($license['key']) ? trim((string) $license['key']) : '';
            if ($keyVal === '') { continue; }
            $wpdb->insert(
                $table,
                [
                    'order_id'         => $order_id,
                    'product_id'       => $used_product_id,
                    'sku'              => $mapped_sku,
                    'email'            => $email,
                    'key_value'        => $keyVal,
                    'download_link'    => $final_download_link,
                    'activation_guide' => $final_guide_link,
                    'source'           => 'api',
                    'fetched'          => 1,
                ],
                ['%d','%d','%s','%s','%s','%s','%s','%s','%d']
            );
        }

        // Build final payload from (fresh) cache for consistent shape + signed URLs
        $rows    = self::get_cached_licenses($order_id, $used_product_id);
        $payload = $build_payload($rows);

        wp_send_json_success($payload);
    }


    /**
     * Check if license actions are allowed for an order
     */
    protected static function is_order_license_ready( int $order_id ): bool {
        return ls_is_order_license_ready( $order_id );
    }



    



   



}

// Bootstrap
LS_My_Keys_Endpoint::init();
