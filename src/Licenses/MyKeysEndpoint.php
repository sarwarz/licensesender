<?php

namespace LicenseSender\Licenses;

use LicenseSender\Admin\AdminService;
use LicenseSender\Core\DesignSystem;

defined( 'ABSPATH' ) || exit;

class MyKeysEndpoint {

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

        // AJAX (logged-in only: every action needs the customer's own order)
        add_action('wp_ajax_licensesender_get_key', [__CLASS__, 'ajax_get_key']);
        add_action('wp_ajax_licensesender_view_key', [__CLASS__, 'ajax_view_key']);
        add_action('wp_ajax_ls_my_keys_list', [__CLASS__, 'ajax_list']);
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
                $new[self::ENDPOINT] = __(self::MENU_TITLE, 'licensesender');
                $inserted = true;
            }
        }
        if (!$inserted) {
            $new[self::ENDPOINT] = __(self::MENU_TITLE, 'licensesender');
        }
        return $new;
    }

    public static function render_endpoint() {
        if (! is_user_logged_in()) {
            echo '<p>' . esc_html__('You must be logged in to view this page.', 'licensesender') . '</p>';
            return;
        }

        // Read page from endpoint value like /my-account/my-keys/2/; fallback to ?p=2.
        $endpoint_val = get_query_var(self::ENDPOINT);
        $paged        = max(1, absint( $endpoint_val ?: ( $_GET['p'] ?? 1 ) ));

        self::enqueue_app( $paged );

        // React app (build/public/my-keys.js) renders into a Shadow DOM so the theme can't restyle it.
        // The placeholder shows until it boots, or explains if it never does.
        ?>
        <div class="ls-my-keys-app" data-theme="<?php echo esc_attr( DesignSystem::color_mode() ); ?>">
            <div class="ls-my-keys-loading" role="status" style="padding:48px 16px;text-align:center;color:#64748b;font:14px/1.5 system-ui,-apple-system,'Segoe UI',sans-serif;">
                <?php esc_html_e( 'Loading your keys…', 'licensesender' ); ?>
            </div>
            <noscript><p><?php esc_html_e( 'Please enable JavaScript to view your license keys.', 'licensesender' ); ?></p></noscript>
        </div>
        <script>
        setTimeout(function () {
            if (window.LSSupportBooted) return;
            var el = document.querySelector('.ls-my-keys-app .ls-my-keys-loading');
            if (el) el.textContent = <?php echo wp_json_encode( __( 'Your keys could not load on this page. Please reload the page or try again later.', 'licensesender' ) ); ?>;
        }, 10000);
        </script>
        <?php
    }

    /**
     * Enqueue the app with its settings and the first page of keys (no loading spinner on first view).
     */
    private static function enqueue_app( int $paged ) {
        $tokens = class_exists( DesignSystem::class ) ? DesignSystem::resolve_tokens() : array();

        $config = array(
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'ls_my_keys' ),
            'viewNonce'     => wp_create_nonce( 'ls_view_key' ),
            'getNonce'      => wp_create_nonce( 'ls_get_key' ),
            'brand'         => (string) ( $tokens['brand'] ?? '#4f46e5' ),
            'radiusCard'    => (string) ( $tokens['radius_card'] ?? '14px' ),
            'radiusControl' => (string) ( $tokens['radius_ctl'] ?? '10px' ),
            'shopUrl'       => function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : '',
            'initial'       => self::get_page_data( get_current_user_id(), $paged ),
            'i18n'          => self::app_strings(),
        );

        wp_enqueue_script( 'ls-my-keys-app' );
        wp_add_inline_script( 'ls-my-keys-app', 'window.LSMyKeysApp = ' . wp_json_encode( $config ) . ';', 'before' );
    }

    /** AJAX: another page of the customer's keys. */
    public static function ajax_list() {
        check_ajax_referer( 'ls_my_keys', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'licensesender' ) ), 403 );
        }

        $page   = max( 1, absint( $_POST['page'] ?? 1 ) );
        $search = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
        wp_send_json_success( self::get_page_data( get_current_user_id(), $page, $search ) );
    }

    /**
     * One page of the customer's license products, newest order first.
     *
     * @return array{items: array<int, array<string, mixed>>, page: int, total_pages: int}
     */
    public static function get_page_data( int $user_id, int $paged, string $search = '' ): array {
        // customer_id 0 would mean guest orders (other people's); never list those.
        if ( ! $user_id || ! function_exists( 'wc_get_orders' ) ) {
            return array( 'items' => array(), 'page' => 1, 'total_pages' => 1 );
        }

        $search = trim( $search );
        if ( $search !== '' ) {
            return self::search_page_data( $user_id, $paged, $search );
        }

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

        $orders = $q && ! empty( $q->orders ) ? $q->orders : array();

        return array(
            'items'       => self::items_from_orders( $orders ),
            'page'        => $paged,
            'total_pages' => max( 1, (int) ( $q->max_num_pages ?? 1 ) ),
        );
    }

    /**
     * Search by product name or order number across the customer's orders (newest 200),
     * paging the matching lines. WooCommerce can't filter orders by item name, so we
     * filter the lines here.
     */
    protected static function search_page_data( int $user_id, int $paged, string $search ): array {
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'status'      => ['wc-completed', 'wc-processing', 'wc-on-hold'],
            'limit'       => 200,
            'return'      => 'objects',
            'orderby'     => 'date',
            'order'       => 'DESC',
        ]);

        // Every word must appear (in any order) in the product name or order number:
        // "win 11" matches "Windows 11 Professional", "#38" matches order 38.
        $lower = static function ( string $value ): string {
            return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
        };
        $words = array_filter( preg_split( '/\s+/', $lower( $search ) ) ?: array() );
        $items = array_values(
            array_filter(
                self::items_from_orders( is_array( $orders ) ? $orders : array() ),
                static function ( $row ) use ( $words, $lower ) {
                    $haystack = $lower( $row['product_name'] ) . ' #' . $lower( (string) $row['order_number'] );
                    foreach ( $words as $word ) {
                        if ( ! str_contains( $haystack, $word ) ) {
                            return false;
                        }
                    }
                    return true;
                }
            )
        );

        $total_pages = max( 1, (int) ceil( count( $items ) / self::PER_PAGE ) );
        $paged       = min( $paged, $total_pages );

        return array(
            'items'       => array_slice( $items, ( $paged - 1 ) * self::PER_PAGE, self::PER_PAGE ),
            'page'        => $paged,
            'total_pages' => $total_pages,
        );
    }

    /**
     * License-enabled order lines with what the customer can do with each.
     *
     * state: ready (keys saved), fetch (can get keys), pending (order not completed),
     * blocked (order can't receive LicenseSender keys).
     */
    public static function items_from_orders( array $orders ): array {
        $rows = [];
        foreach ($orders as $order) {
            /** @var \WC_Order $order */
            $can_fetch = ls_is_order_license_ready( $order );

            foreach ($order->get_items('line_item') as $item) {
                /** @var \WC_Order_Item_Product $item */
                $product    = $item->get_product();
                $product_id = $product ? $product->get_id() : (int) $item->get_product_id();

                if ( ! ls_is_licensesender_enabled( $product_id ) ) {
                    continue;
                }

                $count    = self::cached_license_count( (int) $order->get_id(), (int) $product_id );
                $expected = ls_count_expected_keys_for_product_in_order( $order, (int) $product_id );

                if ( ! $can_fetch && $count <= 0 ) {
                    $state = $order->has_status( 'completed' ) ? 'blocked' : 'pending';
                } elseif ( $count > 0 && ( $count >= $expected || ! $can_fetch ) ) {
                    $state = 'ready';
                } elseif ( $can_fetch ) {
                    $state = 'fetch';
                } else {
                    $state = 'blocked';
                }

                $note = '';
                if ( 'pending' === $state ) {
                    $note = __( 'Keys become available once the order is completed.', 'licensesender' );
                } elseif ( 'blocked' === $state ) {
                    $note = ls_order_delivery_block_message( $order );
                }

                $thumb = self::product_thumb( $product );

                $rows[] = [
                    'order_id'     => (int) $order->get_id(),
                    'order_number' => (string) $order->get_order_number(),
                    'order_url'    => (string) $order->get_view_order_url(),
                    'order_date'   => $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '',
                    'product_id'   => (int) $product_id,
                    'product_name' => (string) $item->get_name(),
                    'qty'          => (int) $item->get_quantity(),
                    'thumb'        => $thumb,
                    'state'        => $state,
                    'key_count'    => $count,
                    'note'         => $note,
                ];
            }
        }
        // Ensure newest orders first (stable)
        usort($rows, fn($a,$b) => $b['order_id'] <=> $a['order_id']);
        return $rows;
    }

    /**
     * Product image for the list: the product's own, then the parent's (variations often
     * have none). Without a real image the app shows a branded key icon, which reads far
     * better than WooCommerce's gray placeholder.
     *
     * @param \WC_Product|false|null $product Product.
     */
    protected static function product_thumb( $product ): string {
        $image_id = 0;
        if ( $product instanceof \WC_Product ) {
            $image_id = (int) $product->get_image_id();
            if ( ! $image_id && $product->get_parent_id() ) {
                $parent   = wc_get_product( $product->get_parent_id() );
                $image_id = $parent ? (int) $parent->get_image_id() : 0;
            }
        }

        return $image_id ? (string) wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';
    }

    /**
     * UI strings for My Keys and the order page keys section, with the texts the store
     * customised under Settings → Key delivery texts (only those filled in).
     *
     * @return array<string, string>
     */
    public static function app_strings(): array {
        $custom = class_exists( AdminService::class ) ? AdminService::get_key_delivery_texts() : array();

        return array_merge( self::default_strings(), $custom );
    }

    /**
     * @return array<string, string>
     */
    private static function default_strings(): array {
        return array(
            'title'            => __( 'My keys', 'licensesender' ),
            'lead'             => __( 'Your license keys from your orders. View, copy or fetch them any time.', 'licensesender' ),
            'stateReady'       => __( 'Keys ready', 'licensesender' ),
            'stateFetch'       => __( 'Ready to deliver', 'licensesender' ),
            'statePending'     => __( 'Pending completion', 'licensesender' ),
            'stateBlocked'     => __( 'Unavailable', 'licensesender' ),
            'oneKey'           => __( '1 key', 'licensesender' ),
            /* translators: 1: keys delivered, 2: keys in the order */
            'keysOf'           => __( '%d of %d keys', 'licensesender' ),
            /* translators: %d: number of keys */
            'keysCount'        => __( '%d keys', 'licensesender' ),
            'viewKeys'         => __( 'View keys', 'licensesender' ),
            'hideKeys'         => __( 'Hide keys', 'licensesender' ),
            'getKeys'          => __( 'Get keys', 'licensesender' ),
            'confirmTitle'     => __( 'Get license keys?', 'licensesender' ),
            /* translators: {product} is replaced with the product name */
            'confirmText'      => __( 'We will fetch your license keys for {product}. Continue?', 'licensesender' ),
            'confirmBtn'       => __( 'Yes, get keys', 'licensesender' ),
            'cancelBtn'        => __( 'Cancel', 'licensesender' ),
            'viewTitle'        => __( 'Your license key', 'licensesender' ),
            'viewTitleMany'    => __( 'Your license keys', 'licensesender' ),
            'copyAll'          => __( 'Copy all', 'licensesender' ),
            'copy'             => __( 'Copy', 'licensesender' ),
            'copied'           => __( 'Copied', 'licensesender' ),
            /* translators: %s: email address */
            'sentTo'           => __( 'Sent to %s', 'licensesender' ),
            'searchOrders'     => __( 'Search product or order…', 'licensesender' ),
            /* translators: %s: search text */
            'noResults'        => __( 'Nothing matches "%s"', 'licensesender' ),
            'noResultsHint'    => __( 'Try the order number (e.g. 1042) or part of the product name.', 'licensesender' ),
            'downloadTxt'      => __( 'Download .txt', 'licensesender' ),
            /* translators: 1: keys delivered, 2: keys in the order */
            'partialDelivery'  => __( '%d of %d keys delivered. The rest will be ready soon — try Get keys again later.', 'licensesender' ),
            'searchKeys'       => __( 'Search keys…', 'licensesender' ),
            'clearSearch'      => __( 'Clear search', 'licensesender' ),
            /* translators: 1: matching keys, 2: total keys */
            'showingKeys'      => __( '%d of %d', 'licensesender' ),
            'noKeyMatch'       => __( 'No keys match your search.', 'licensesender' ),
            'downloadSoftware' => __( 'Download software', 'licensesender' ),
            'activationGuide'  => __( 'Activation guide', 'licensesender' ),
            /* translators: %s: order number */
            'orderShort'       => __( 'Order %s', 'licensesender' ),
            /* translators: %d: quantity */
            'qty'              => __( 'Qty %d', 'licensesender' ),
            'emptyTitle'       => __( 'No license keys yet', 'licensesender' ),
            'emptyHint'        => __( 'Keys from your orders appear here once they are ready.', 'licensesender' ),
            'browseShop'       => __( 'Browse the shop', 'licensesender' ),
            /* translators: 1: current page, 2: total pages */
            'pageOf'           => __( 'Page %d of %d', 'licensesender' ),
            'prev'             => __( 'Previous', 'licensesender' ),
            'next'             => __( 'Next', 'licensesender' ),
            'dismiss'          => __( 'Dismiss', 'licensesender' ),
            'error'            => __( 'Something went wrong. Please try again.', 'licensesender' ),
            'networkError'     => __( 'Could not reach the server. Check your connection and try again.', 'licensesender' ),
            'sessionExpired'   => __( 'Your session has expired. Please reload the page.', 'licensesender' ),
        );
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

    /** === AJAX: view saved keys === */
    public static function ajax_view_key() {
        check_ajax_referer('ls_view_key', 'nonce');
        KeyDelivery::send( KeyDelivery::view( self::own_order(), absint( $_POST['product_id'] ?? 0 ) ) );
    }

    /** === AJAX: get keys (fetches missing ones) === */
    public static function ajax_get_key() {
        check_ajax_referer('ls_get_key', 'nonce');
        $order = self::own_order();

        // An email, when sent, must match the order (legacy clients post it).
        $posted_email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        if ( $posted_email && strcasecmp( $posted_email, (string) $order->get_billing_email() ) !== 0 ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized access to order.', 'licensesender' ) ), 403 );
        }

        KeyDelivery::send( KeyDelivery::fetch( $order, absint( $_POST['product_id'] ?? 0 ) ) );
    }

    /**
     * The posted order, if it belongs to the logged-in customer; otherwise ends the request.
     */
    private static function own_order(): \WC_Order {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'licensesender' ), 'code' => 'unauth' ), 403 );
        }

        $order_id   = absint( $_POST['order_id'] ?? 0 );
        $product_id = absint( $_POST['product_id'] ?? 0 );
        if ( ! $order_id || ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'licensesender' ), 'code' => 'bad_req' ), 400 );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order || (int) $order->get_user_id() !== get_current_user_id() ) {
            wp_send_json_error( array( 'message' => __( 'Order not found or not yours.', 'licensesender' ), 'code' => 'forbidden' ), 403 );
        }

        return $order;
    }
}
