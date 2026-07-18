<?php
defined('ABSPATH') || exit;

class Licensesender_Api {

    /**
     * Get API base URL (hardcoded constant).
     */
    protected static function get_api_base_url() {
        return trailingslashit( defined( 'LICENSESENDER_API_BASE_URL' ) ? LICENSESENDER_API_BASE_URL : 'https://licensesender.com/api/' );
    }

    /**
     * Get API key from options
     */
    protected static function get_api_key() {
        return get_option('lship_api_key', '');
    }

    /**
     * Fetch licenses from external API
     */
    public static function fetch_license($params = []) {

        //  Order completion gate
        if ( ! empty( $params['order_id'] ) ) {
            $allowed = static::can_fetch_license_for_order( (int) $params['order_id'] );

            if ( $allowed !== true ) {
                return $allowed; // structured error
            }
        }

        $api_url = trailingslashit(static::get_api_base_url()) . 'license/fetch';
        $api_key = static::get_api_key();

        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('API key is missing. Please set it in licensesender settings.', 'licensesender'),
                'http_code' => 0,
            ];
        }

        if (empty(trim(static::get_api_base_url())) || strpos($api_url, '://') === false) {
            return [
                'success' => false,
                'message' => __('API base URL is missing or invalid. Please set it in licensesender settings.', 'licensesender'),
                'http_code' => 0,
            ];
        }

        $payload = [
            'sku'         => $params['sku'] ?? '',
            'quantity'    => isset($params['quantity']) ? (int) $params['quantity'] : 1,
            'order_id'    => $params['order_id'] ?? '',
            'order_email' => $params['email'] ?? '',
            'source'      => $params['source'] ?? 'licensesender',
        ];

        $response = wp_remote_post($api_url, [
            'headers' => [
                'X-API-KEY'    => $api_key,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return [
                'success'   => false,
                'message'   => 'Request failed: ' . $response->get_error_message(),
                'http_code' => 0,
            ];
        }

        $http_code = (int) wp_remote_retrieve_response_code($response);
        $raw_body  = wp_remote_retrieve_body($response);
        $data      = json_decode($raw_body, true);

        // If not JSON, surface raw body
        if (!is_array($data)) {
            return [
                'success'   => false,
                'message'   => $raw_body ?: __('Unexpected non-JSON response from API.', 'licensesender'),
                'http_code' => $http_code,
            ];
        }

        // Handle any non-2xx or explicit failure
        if ($http_code < 200 || $http_code >= 300 || empty($data['success'])) {
            // Build validation error text if present
            $error_text = $data['message'] ?? __('Unknown API error.', 'licensesender');

            if (!empty($data['errors']) && is_array($data['errors'])) {
                $messages = [];
                foreach ($data['errors'] as $field => $msgs) {
                    foreach ((array) $msgs as $msg) {
                        $messages[] = ucfirst($field) . ': ' . $msg;
                    }
                }
                if ($messages) {
                    $error_text = implode( "\n", array_map( 'wp_strip_all_tags', $messages ) );
                }
            }

            $safe_html = null;
            if ( ! empty( $data['html'] ) && is_string( $data['html'] ) ) {
                $safe_html = wp_kses_post( $data['html'] );
            }

            return [
                'success'   => false,
                'message'   => wp_strip_all_tags( (string) $error_text ),
                'meta'      => $data['meta'] ?? [],      // <-- includes reason/scope/block_id if blocked
                'html'      => $safe_html,
                'http_code' => $http_code,
            ];
        }

        // Success
        return array(
            'success'   => true,
            'licenses'  => $data['data']['licenses'] ?? [],
            'product'   => $data['data']['product']  ?? [],
            'meta'      => $data['meta']             ?? [],
            'http_code' => $http_code,
        );
    }

    /**
     * Authenticated POST request.
     *
     * @param string               $path Relative API path.
     * @param array<string, mixed> $body Request body.
     * @return array<string, mixed>
     */
    protected static function request_post( $path, array $body = array() ) {
        $api_key = static::get_api_key();

        if ( empty( $api_key ) ) {
            return array(
                'success'   => false,
                'message'   => __( 'API key is missing. Please set it in licensesender settings.', 'licensesender' ),
                'http_code' => 0,
            );
        }

        $base = static::get_api_base_url();
        if ( empty( trim( $base ) ) || strpos( $base, '://' ) === false ) {
            return array(
                'success'   => false,
                'message'   => __( 'API base URL is missing or invalid. Please set it in licensesender settings.', 'licensesender' ),
                'http_code' => 0,
            );
        }

        $url = trailingslashit( $base ) . ltrim( $path, '/' );

        $response = wp_remote_post(
            $url,
            array(
                'headers' => array(
                    'X-API-KEY'    => $api_key,
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ),
                'body'    => wp_json_encode( $body ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return array(
                'success'   => false,
                'message'   => 'Request failed: ' . $response->get_error_message(),
                'http_code' => 0,
            );
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        $raw_body  = wp_remote_retrieve_body( $response );
        $data      = json_decode( $raw_body, true );

        if ( ! is_array( $data ) ) {
            return array(
                'success'   => false,
                'message'   => $raw_body ?: __( 'Unexpected non-JSON response from API.', 'licensesender' ),
                'http_code' => $http_code,
            );
        }

        if ( $http_code < 200 || $http_code >= 300 || empty( $data['success'] ) ) {
            return array(
                'success'   => false,
                'message'   => $data['message'] ?? __( 'API request failed.', 'licensesender' ),
                'meta'      => $data['meta'] ?? array(),
                'data'      => $data['data'] ?? array(),
                'errors'    => $data['errors'] ?? array(),
                'http_code' => $http_code,
            );
        }

        return array(
            'success'   => true,
            'message'   => $data['message'] ?? '',
            'data'      => $data['data'] ?? array(),
            'meta'      => $data['meta'] ?? array(),
            'http_code' => $http_code,
        );
    }

    /**
     * Push a completed WooCommerce order to the SaaS app (creates/updates ShopOrder).
     *
     * @param array<string, mixed> $payload Order payload.
     * @return array<string, mixed>
     */
    public static function ingest_order( array $payload ) {
        return static::request_post( 'orders/ingest', $payload );
    }

    /**
     * List licenses assigned to a WooCommerce order.
     *
     * @param int                  $order_id WooCommerce order ID.
     * @param string               $email    Customer email.
     * @param array<string, mixed> $args     Optional query args.
     * @return array<string, mixed>
     */
    public static function list_licenses_for_order( $order_id, $email = '', array $args = array() ) {
        $query = array(
            'order_id' => (string) $order_id,
            'per_page' => isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 100,
        );

        if ( $email !== '' ) {
            $query['order_email'] = sanitize_email( $email );
        }

        if ( isset( $args['status'] ) && $args['status'] !== '' && $args['status'] !== null ) {
            $query['status'] = (int) $args['status'];
        }

        $response = static::request_get( 'licenses', $query );
        if ( empty( $response['success'] ) ) {
            return $response;
        }

        $licenses = $response['data']['licenses'] ?? array();
        if ( ! is_array( $licenses ) ) {
            $licenses = array();
        }

        $response['licenses'] = $licenses;
        return $response;
    }

    /**
     * Fetch a single license with full key.
     *
     * @param int $license_id Remote license ID.
     * @return array<string, mixed>
     */
    public static function get_license( $license_id ) {
        $license_id = (int) $license_id;
        if ( ! $license_id ) {
            return array(
                'success'   => false,
                'message'   => __( 'Invalid license ID.', 'licensesender' ),
                'http_code' => 400,
            );
        }

        $response = static::request_get( 'licenses/' . $license_id );
        if ( empty( $response['success'] ) ) {
            return $response;
        }

        $response['license'] = $response['data'] ?? array();
        return $response;
    }

    /**
     * Fetch product details from API.
     *
     * @param int $product_id Licensesender product ID.
     * @return array<string, mixed>
     */
    public static function get_product( $product_id ) {
        $product_id = (int) $product_id;
        if ( ! $product_id ) {
            return array(
                'success'   => false,
                'message'   => __( 'Invalid product ID.', 'licensesender' ),
                'http_code' => 400,
            );
        }

        $response = static::request_get( 'products/' . $product_id );
        if ( empty( $response['success'] ) ) {
            return $response;
        }

        $response['product'] = $response['data'] ?? array();
        return $response;
    }

    /**
     * Report/replace a license key via API.
     *
     * @param array<string, mixed> $params Report payload.
     * @return array<string, mixed>
     */
    public static function report_license( array $params ) {
        $body = array(
            'key'      => (string) ( $params['key'] ?? '' ),
            'order_id' => (string) ( $params['order_id'] ?? '' ),
            'reason'   => sanitize_key( (string) ( $params['reason'] ?? 'dead_key' ) ),
            'mode'     => sanitize_key( (string) ( $params['mode'] ?? 'auto' ) ),
        );

        if ( ! empty( $params['notes'] ) ) {
            $body['notes'] = sanitize_textarea_field( (string) $params['notes'] );
        }

        $response = static::request_post( 'license/report', $body );
        if ( empty( $response['success'] ) ) {
            return $response;
        }

        $response['report'] = $response['data'] ?? array();
        return $response;
    }

    /**
     * Submit a plugin feature request to the SaaS admin inbox.
     *
     * @param array<string, mixed> $params Request payload.
     * @return array<string, mixed>
     */
    public static function submit_feature_request( array $params ) {
        $body = array(
            'title'   => sanitize_text_field( (string) ( $params['title'] ?? '' ) ),
            'message' => sanitize_textarea_field( (string) ( $params['message'] ?? '' ) ),
        );

        if ( ! empty( $params['site_url'] ) ) {
            $body['site_url'] = esc_url_raw( (string) $params['site_url'] );
        } else {
            $body['site_url'] = esc_url_raw( home_url( '/' ) );
        }

        $body['plugin_version'] = defined( 'LICENSESENDER_VERSION' )
            ? (string) LICENSESENDER_VERSION
            : '';

        return static::request_post( 'feature-requests', $body );
    }


    /**
     * Fetch product list from API
     */
    public static function fetch_product_list() {
        $product_url = static::get_api_base_url() . 'products';
        $api_key     = static::get_api_key();

        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('API key is missing. Please set it in licensesender settings.', 'licensesender'),
            ];
        }

        if (empty(trim(static::get_api_base_url())) || strpos($product_url, '://') === false) {
            return [
                'success' => false,
                'message' => __('API base URL is missing or invalid. Please set it in licensesender settings.', 'licensesender'),
            ];
        }

        $response = wp_remote_get($product_url, [
            'headers' => [
                'X-API-KEY' => $api_key,
            ],
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Product fetch failed: ' . $response->get_error_message(),
            ];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body      = wp_remote_retrieve_body($response);
        $data      = json_decode($body, true);

        if ($http_code !== 200 || !isset($data['success']) || !$data['success']) {
            return [
                'success' => false,
                'message' => $data['message'] ?? 'Unable to fetch products.',
            ];
        }

        return [
            'success'  => true,
            'products' => $data['data']['products'] ?? [],
            'message'  => $data['message'] ?? '',
            'count'    => $data['meta']['count'] ?? count($data['data']['products'] ?? []),
        ];
    }

    /**
     * Perform an authenticated GET request against the Licensesender API.
     *
     * @param string $path Relative API path (e.g. ping, subscription).
     * @param array  $args Optional query args.
     * @return array
     */
    protected static function request_get( $path, array $args = array() ) {
        $api_key = static::get_api_key();

        if ( empty( $api_key ) ) {
            return array(
                'success'   => false,
                'message'   => __( 'API key is missing. Please set it in licensesender settings.', 'licensesender' ),
                'http_code' => 0,
            );
        }

        $base = static::get_api_base_url();
        if ( empty( trim( $base ) ) || strpos( $base, '://' ) === false ) {
            return array(
                'success'   => false,
                'message'   => __( 'API base URL is missing or invalid. Please set it in licensesender settings.', 'licensesender' ),
                'http_code' => 0,
            );
        }

        $url = trailingslashit( $base ) . ltrim( $path, '/' );
        if ( ! empty( $args ) ) {
            $url = add_query_arg( $args, $url );
        }

        $response = wp_remote_get(
            $url,
            array(
                'headers' => array(
                    'X-API-KEY' => $api_key,
                    'Accept'    => 'application/json',
                ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return array(
                'success'   => false,
                'message'   => 'Request failed: ' . $response->get_error_message(),
                'http_code' => 0,
            );
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        $raw_body  = wp_remote_retrieve_body( $response );
        $data      = json_decode( $raw_body, true );

        if ( ! is_array( $data ) ) {
            return array(
                'success'   => false,
                'message'   => $raw_body ?: __( 'Unexpected non-JSON response from API.', 'licensesender' ),
                'http_code' => $http_code,
            );
        }

        if ( $http_code < 200 || $http_code >= 300 || empty( $data['success'] ) ) {
            return array(
                'success'   => false,
                'message'   => $data['message'] ?? __( 'API request failed.', 'licensesender' ),
                'meta'      => $data['meta'] ?? array(),
                'data'      => $data['data'] ?? array(),
                'http_code' => $http_code,
            );
        }

        return array(
            'success'   => true,
            'message'   => $data['message'] ?? '',
            'data'      => $data['data'] ?? array(),
            'meta'      => $data['meta'] ?? array(),
            'http_code' => $http_code,
        );
    }

    /**
     * Normalize subscription/account fields from Licensesender API payloads.
     *
     * @param array $payload API `data` object.
     * @return array<string, mixed>
     */
    protected static function normalize_subscription_payload( array $payload ) {
        if ( empty( $payload ) ) {
            return array();
        }

        $account      = is_array( $payload['account'] ?? null ) ? $payload['account'] : array();
        $api_key      = is_array( $payload['api_key'] ?? null ) ? $payload['api_key'] : array();
        $subscription = is_array( $payload['subscription'] ?? null ) ? $payload['subscription'] : array();
        $plan         = is_array( $payload['plan'] ?? null ) ? $payload['plan'] : array();
        $limits       = is_array( $payload['limits'] ?? null ) ? $payload['limits'] : array();
        $usage        = is_array( $payload['usage'] ?? null ) ? $payload['usage'] : array();

        // Licensesender nests plan details under data.plan.{name,code}.
        $plan_name = '';
        if ( ! empty( $plan['name'] ) ) {
            $plan_name = (string) $plan['name'];
        } elseif ( ! empty( $plan['code'] ) ) {
            $plan_name = (string) $plan['code'];
        }

        $status = (string) ( $subscription['status'] ?? '' );
        if ( $status === '' && isset( $subscription['is_active'] ) ) {
            $status = $subscription['is_active'] ? 'active' : 'inactive';
        }

        $renews_at = (string) ( $subscription['renews_at'] ?? '' );
        if ( $renews_at === '' && ! empty( $subscription['ends_at'] ) ) {
            $renews_at = (string) $subscription['ends_at'];
        }
        if ( $renews_at === '' && ! empty( $subscription['trial_ends_at'] ) ) {
            $renews_at = (string) $subscription['trial_ends_at'];
        }

        $monthly_quota = isset( $limits['requests_per_month'] ) ? (int) $limits['requests_per_month'] : null;
        $monthly_used  = isset( $usage['requests_this_month'] ) ? (int) $usage['requests_this_month'] : null;
        $monthly_remaining = isset( $usage['requests_remaining'] ) ? (int) $usage['requests_remaining'] : null;
        $monthly_quota_unlimited = $monthly_quota === 0;

        if ( $monthly_quota_unlimited ) {
            $monthly_remaining = null;
        } elseif ( $monthly_remaining === null && $monthly_quota !== null && $monthly_used !== null ) {
            $monthly_remaining = max( 0, $monthly_quota - $monthly_used );
        }

        $product_limit = isset( $limits['products'] ) ? (int) $limits['products'] : null;
        $api_key_limit = isset( $limits['api_keys'] ) ? (int) $limits['api_keys'] : null;
        $rpm_limit     = isset( $limits['rpm'] ) ? (int) $limits['rpm'] : null;

        return array_filter(
            array(
                'plan'              => $plan_name,
                'plan_code'         => ! empty( $plan['code'] ) ? (string) $plan['code'] : '',
                'plan_price_month'  => isset( $plan['price_month'] ) ? (float) $plan['price_month'] : null,
                'plan_price_year'   => isset( $plan['price_year'] ) ? (float) $plan['price_year'] : null,
                'status'            => $status,
                'expires_at'        => $renews_at,
                'payment_method'    => ! empty( $subscription['payment_method'] ) ? (string) $subscription['payment_method'] : '',
                'monthly_quota'           => $monthly_quota,
                'monthly_quota_unlimited' => $monthly_quota_unlimited,
                'monthly_used'            => $monthly_used,
                'monthly_remaining'       => $monthly_remaining,
                'product_limit'     => $product_limit,
                'api_key_limit'     => $api_key_limit,
                'rpm_limit'         => $rpm_limit,
                'email'             => ! empty( $account['email'] ) ? (string) $account['email'] : '',
                'account_name'      => ! empty( $account['name'] ) ? (string) $account['name'] : '',
                'api_key_name'      => ! empty( $api_key['name'] ) ? (string) $api_key['name'] : '',
                'api_key_project'   => ! empty( $api_key['project'] ) ? (string) $api_key['project'] : '',
                'api_key_last_used' => ! empty( $api_key['last_used_at'] ) ? (string) $api_key['last_used_at'] : '',
            ),
            static function ( $value ) {
                return $value !== null && $value !== '';
            }
        );
    }

    /**
     * Fetch subscription/account details for the authenticated API key.
     *
     * @param bool $force_refresh Skip cached value.
     * @return array
     */
    public static function get_subscription_details( $force_refresh = false ) {
        $cache_key = 'ls_api_subscription_details';
        if ( ! $force_refresh ) {
            $cached = get_transient( $cache_key );
            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        $result = array(
            'success'     => false,
            'connected'   => false,
            'message'     => '',
            'server_time' => '',
            'subscription'=> array(),
            'product_count' => null,
        );

        $subscription = array();
        $sub_response   = static::request_get( 'subscription' );

        if ( ! empty( $sub_response['success'] ) && is_array( $sub_response['data'] ?? null ) ) {
            $subscription = static::normalize_subscription_payload( $sub_response['data'] );
            $result['message'] = $sub_response['message'] ?? '';
            if ( ! empty( $sub_response['meta']['server_time'] ) ) {
                $result['server_time'] = (string) $sub_response['meta']['server_time'];
            }
        }

        $ping = static::request_get( 'ping' );
        if ( ! empty( $ping['success'] ) ) {
            $result['success']   = true;
            $result['connected'] = true;

            if ( empty( $result['message'] ) ) {
                $result['message'] = $ping['message'] ?? __( 'API connection successful.', 'licensesender' );
            }

            if ( empty( $result['server_time'] ) ) {
                $result['server_time'] = (string) ( $ping['data']['server_time'] ?? $ping['meta']['server_time'] ?? '' );
            }
        } else {
            $result['message'] = $ping['message'] ?? __( 'Unable to connect to the API.', 'licensesender' );
            set_transient( $cache_key, $result, 2 * MINUTE_IN_SECONDS );
            return $result;
        }

        if ( ! empty( $subscription['product_limit'] ) ) {
            $result['product_limit'] = (int) $subscription['product_limit'];
        }

        $products = static::fetch_product_list();
        if ( ! empty( $products['success'] ) ) {
            $result['product_count'] = isset( $products['count'] ) ? (int) $products['count'] : count( $products['products'] ?? array() );
        }

        if ( ! empty( $subscription ) ) {
            $result['subscription'] = $subscription;
        }

        set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );
        return $result;
    }

    /**
     * Ping API server to test connection and auth
     */
    public static function ping() {
        $response = static::request_get( 'ping' );

        if ( empty( $response['success'] ) ) {
            return array(
                'success' => false,
                'message' => $response['message'] ?? __( 'Ping failed or invalid response.', 'licensesender' ),
                'meta'    => $response['meta'] ?? array(),
            );
        }

        delete_transient( 'ls_api_subscription_details' );

        return array(
            'success' => true,
            'message' => $response['message'] ?? 'API responded successfully.',
            'data'    => $response['data'] ?? array(),
            'meta'    => $response['meta'] ?? array(),
        );
    }

    /**
     * Fetch licenses by SKU from the API (paginated)
     *
     * @param string $sku
     * @param array  $args  [
     *   'status'    => 0|1|2,        // optional; 0=available, 1=assigned, 2=redeemed
     *   'per_page'  => int,          // default 1
     *   'page'      => int,          // default 1
     *   'sort'      => 'field,dir',  // optional; e.g. 'created_at,desc'
     *   'timeout'   => int           // default 30
     * ]
     * @return array {
     *   success: bool,
     *   message?: string,
     *   licenses?: array,
     *   meta?: array,
     *   http_code: int
     * }
     */
    public static function fetch_licenses_by_sku( $sku, array $args = [] ) {
        $base  = trailingslashit( static::get_api_base_url() );
        $key   = static::get_api_key();

        if ( empty( $key ) ) {
            return [
                'success'   => false,
                'message'   => __( 'API key is missing. Please set it in licensesender settings.', 'licensesender' ),
                'http_code' => 0,
            ];
        }

        if ( empty( trim( $base ) ) || strpos( $base, '://' ) === false ) {
            return [
                'success'   => false,
                'message'   => __( 'API base URL is missing or invalid. Please set it in licensesender settings.', 'licensesender' ),
                'http_code' => 0,
            ];
        }

        $endpoint = $base . 'products/sku/' . rawurlencode( (string) $sku ) . '/licenses';

        // Build query
        $query = [
            'per_page' => isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 1,
            'page'     => isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1,
        ];
        if ( isset( $args['status'] ) && $args['status'] !== '' && $args['status'] !== null ) {
            $query['status'] = (int) $args['status'];
        }
        if ( ! empty( $args['sort'] ) ) {
            $query['sort'] = (string) $args['sort']; // e.g. id,desc or created_at,desc
        }

        $url = add_query_arg( $query, $endpoint );

        $response = wp_remote_get( $url, [
            'headers' => [
                'X-API-KEY'    => $key,
                'Accept'       => 'application/json',
            ],
            'timeout' => isset( $args['timeout'] ) ? (int) $args['timeout'] : 30,
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success'   => false,
                'message'   => 'Request failed: ' . $response->get_error_message(),
                'http_code' => 0,
            ];
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        $raw_body  = wp_remote_retrieve_body( $response );
        $data      = json_decode( $raw_body, true );

        if ( ! is_array( $data ) ) {
            return [
                'success'   => false,
                'message'   => $raw_body ?: __( 'Unexpected non-JSON response from API.', 'licensesender' ),
                'http_code' => $http_code,
            ];
        }

        if ( $http_code < 200 || $http_code >= 300 || empty( $data['success'] ) ) {
            $error_text = $data['message'] ?? __( 'Unknown API error.', 'licensesender' );
            if ( ! empty( $data['errors'] ) && is_array( $data['errors'] ) ) {
                $messages = [];
                foreach ( $data['errors'] as $field => $msgs ) {
                    foreach ( (array) $msgs as $msg ) {
                        $messages[] = ucfirst( $field ) . ': ' . $msg;
                    }
                }
                if ( $messages ) {
                    $error_text = implode( "\n", array_map( 'wp_strip_all_tags', $messages ) );
                }
            }
            return [
                'success'   => false,
                'message'   => wp_strip_all_tags( (string) $error_text ),
                'meta'      => $data['meta'] ?? [],
                'http_code' => $http_code,
            ];
        }

        return [
            'success'   => true,
            'licenses'  => $data['data']['licenses'] ?? [],
            'meta'      => $data['meta'] ?? [],
            'http_code' => $http_code,
        ];
    }

    /**
     * Convenience: Fetch exactly ONE AVAILABLE license for a SKU
     * (status=0, per_page=1). Returns the license array or a structured error.
     *
     * @param string $sku
     * @param array  $args Optional: override 'sort' (e.g. 'created_at,asc') or 'timeout'
     * @return array {
     *   success: bool,
     *   license?: array,   // the single license item
     *   message?: string,
     *   meta?: array,
     *   http_code: int
     * }
     */
    public static function fetch_one_available_license_by_sku( $sku, array $args = [] ) {
        $result = static::fetch_licenses_by_sku( $sku, array_merge( [
            'status'   => 0,              // available
            'per_page' => 1,
            'page'     => 1,
            'sort'     => $args['sort'] ?? 'id,asc', // earliest first (change as you want)
        ], $args ) );

        if ( empty( $result['success'] ) ) {
            return $result; // propagate error shape
        }

        $licenses = $result['licenses'] ?? [];
        if ( empty( $licenses ) ) {
            return [
                'success'   => false,
                'message'   => __( 'No available license found for this SKU.', 'licensesender' ),
                'meta'      => $result['meta'] ?? [],
                'http_code' => $result['http_code'] ?? 200,
            ];
        }

        // Return the first (and only) license
        return [
            'success'   => true,
            'license'   => $licenses[0],
            'meta'      => $result['meta'] ?? [],
            'http_code' => $result['http_code'] ?? 200,
        ];
    }


    /**
     * Check whether order is allowed to fetch licenses
     *
     * @param int $order_id
     * @return true|array  true if allowed, error array otherwise
     */
    protected static function can_fetch_license_for_order( $order_id ) {

        if ( empty( $order_id ) ) {
            return [
                'success'   => false,
                'message'   => __( 'Invalid order ID.', 'licensesender' ),
                'http_code' => 400,
            ];
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return [
                'success'   => false,
                'message'   => __( 'Order not found.', 'licensesender' ),
                'http_code' => 404,
            ];
        }

        if ( ! ls_is_order_license_ready( $order ) ) {
            return [
                'success'   => false,
                'message'   => __( 'License is not ready yet for this order.', 'licensesender' ),
                'meta'      => [
                    'reason' => 'order_not_completed_by_ls_delivery_system',
                ],
                'http_code' => 403,
            ];
        }

        return true;
    }

    /**
     * Whether a wholesale catalog item should be listed.
     *
     * Products returned by /wholesale/catalog are included unless explicitly disabled.
     *
     * @param array $product Raw API product row.
     */
    protected static function is_wholesale_product_listed( array $product ) {
        if ( ! array_key_exists( 'wholesale_enabled', $product ) ) {
            return true;
        }

        $enabled = $product['wholesale_enabled'];
        if ( $enabled === false || $enabled === 0 || $enabled === '0' || $enabled === 'false' ) {
            return false;
        }

        return (bool) $enabled;
    }

    /**
     * Extract product rows from a wholesale catalog API response.
     *
     * @param array $response Parsed API response.
     * @return array<int, array<string, mixed>>
     */
    protected static function extract_wholesale_products( array $response ) {
        if ( isset( $response['data']['products'] ) && is_array( $response['data']['products'] ) ) {
            return $response['data']['products'];
        }

        if ( isset( $response['products'] ) && is_array( $response['products'] ) ) {
            return $response['products'];
        }

        if ( isset( $response['data'] ) && is_array( $response['data'] ) && array_is_list( $response['data'] ) ) {
            return $response['data'];
        }

        return array();
    }

    /**
     * Fetch wholesale product catalog.
     *
     * @return array
     */
    public static function fetch_wholesale_catalog() {
        $response = static::request_get( 'wholesale/catalog' );

        if ( empty( $response['success'] ) ) {
            return array(
                'success' => false,
                'message' => $response['message'] ?? __( 'Unable to fetch wholesale catalog.', 'licensesender' ),
                'meta'    => $response['meta'] ?? array(),
            );
        }

        $products = array();
        foreach ( static::extract_wholesale_products( $response ) as $product ) {
            if ( ! is_array( $product ) || ! static::is_wholesale_product_listed( $product ) ) {
                continue;
            }

            $sku = (string) ( $product['sku'] ?? '' );
            if ( $sku === '' ) {
                continue;
            }

            $products[] = LS_Wholesale::normalize_catalog_product( $product );
        }

        $meta_count = isset( $response['meta']['count'] ) ? (int) $response['meta']['count'] : count( $products );

        return array(
            'success'  => true,
            'message'  => $response['message'] ?? '',
            'products' => $products,
            'tier'     => (string) ( $response['meta']['tier'] ?? 'default' ),
            'count'    => max( $meta_count, count( $products ) ),
        );
    }

    /**
     * Build headers for support endpoints (optional customer ticket token).
     *
     * @param string $access_token Ticket access token.
     * @param string $customer_email Verified shop customer email for proxy replies.
     * @return array<string, string>
     */
    protected static function support_headers( $access_token = '', $customer_email = '' ) {
        $headers = array(
            'X-API-KEY' => static::get_api_key(),
            'Accept'    => 'application/json',
        );

        $access_token = trim( (string) $access_token );
        if ( $access_token !== '' ) {
            $headers['X-Ticket-Access-Token'] = $access_token;
            $headers['X-Ticket-Token']        = $access_token;
            $headers['Authorization']         = 'Bearer ' . $access_token;
        }

        $customer_email = sanitize_email( (string) $customer_email );
        if ( $customer_email !== '' && $access_token === '' ) {
            $headers['X-Customer-Email'] = $customer_email;
        }

        return $headers;
    }

    /**
     * Build a multipart/form-data body for support uploads.
     *
     * @param array<string, mixed> $fields Form fields.
     * @param array<int, array>    $files  Uploaded files.
     * @return array{body:string,boundary:string}
     */
    protected static function build_multipart_body( array $fields, array $files ) {
        $boundary = 'lsform-' . wp_generate_password( 16, false );
        $payload  = '';

        foreach ( $fields as $name => $value ) {
            if ( is_array( $value ) ) {
                continue;
            }
            $payload .= '--' . $boundary . "\r\n";
            $payload .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
            $payload .= (string) $value . "\r\n";
        }

        foreach ( $files as $file ) {
            if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
                continue;
            }

            $filename = ! empty( $file['name'] ) ? sanitize_file_name( wp_basename( (string) $file['name'] ) ) : basename( $file['tmp_name'] );
            $filename = str_replace( array( '"', "\r", "\n" ), '', $filename );
            if ( $filename === '' ) {
                $filename = 'attachment';
            }
            $type     = ! empty( $file['type'] ) ? (string) $file['type'] : 'application/octet-stream';
            $content  = file_get_contents( $file['tmp_name'] );

            $payload .= '--' . $boundary . "\r\n";
            $payload .= 'Content-Disposition: form-data; name="attachments[]"; filename="' . $filename . '"' . "\r\n";
            $payload .= 'Content-Type: ' . $type . "\r\n\r\n";
            $payload .= $content . "\r\n";
        }

        $payload .= '--' . $boundary . "--\r\n";

        return array(
            'body'     => $payload,
            'boundary' => $boundary,
        );
    }

    /**
     * Multipart POST for support ticket create/reply.
     *
     * @param string               $path         Relative API path.
     * @param array<string, mixed> $fields       Form fields.
     * @param array<int, array>    $files        Uploaded files.
     * @param string               $access_token Optional ticket token.
     * @param string               $customer_email Optional verified shop customer email.
     * @return array<string, mixed>
     */
    protected static function request_multipart_post( $path, array $fields, array $files = array(), $access_token = '', $customer_email = '', $chat_token = '' ) {
        $api_key = static::get_api_key();

        if ( empty( $api_key ) ) {
            return array(
                'success'   => false,
                'message'   => __( 'API key is missing. Please set it in licensesender settings.', 'licensesender' ),
                'http_code' => 0,
            );
        }

        $base = static::get_api_base_url();
        if ( empty( trim( $base ) ) || strpos( $base, '://' ) === false ) {
            return array(
                'success'   => false,
                'message'   => __( 'API base URL is missing or invalid. Please set it in licensesender settings.', 'licensesender' ),
                'http_code' => 0,
            );
        }

        $url = trailingslashit( $base ) . ltrim( $path, '/' );

        $multipart = static::build_multipart_body( $fields, $files );
        $headers = static::support_headers( $access_token, $customer_email );
        if ( trim( (string) $chat_token ) !== '' ) {
            $headers['X-Chat-Session-Token'] = trim( (string) $chat_token );
        }
        $headers['Content-Type'] = 'multipart/form-data; boundary=' . $multipart['boundary'];

        $response  = wp_remote_post(
            $url,
            array(
                'headers' => $headers,
                'body'    => $multipart['body'],
                'timeout' => 60,
            )
        );

        if ( is_wp_error( $response ) ) {
            return array(
                'success'   => false,
                'message'   => 'Request failed: ' . $response->get_error_message(),
                'http_code' => 0,
            );
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        $raw_body  = wp_remote_retrieve_body( $response );
        $data      = json_decode( $raw_body, true );

        if ( ! is_array( $data ) ) {
            return array(
                'success'   => false,
                'message'   => $raw_body ?: __( 'Unexpected non-JSON response from API.', 'licensesender' ),
                'http_code' => $http_code,
            );
        }

        if ( $http_code < 200 || $http_code >= 300 || empty( $data['success'] ) ) {
            return array(
                'success'   => false,
                'message'   => $data['message'] ?? __( 'API request failed.', 'licensesender' ),
                'meta'      => $data['meta'] ?? array(),
                'data'      => $data['data'] ?? array(),
                'errors'    => $data['errors'] ?? array(),
                'http_code' => $http_code,
            );
        }

        return array(
            'success'   => true,
            'message'   => $data['message'] ?? '',
            'data'      => $data['data'] ?? array(),
            'meta'      => $data['meta'] ?? array(),
            'http_code' => $http_code,
        );
    }

    /**
     * GET request with optional support ticket token header.
     *
     * @param string               $path         Relative API path.
     * @param array<string, mixed> $args         Query args.
     * @param string               $access_token Optional ticket token.
     * @return array<string, mixed>
     */
    protected static function request_support_get( $path, array $args = array(), $access_token = '' ) {
        $api_key = static::get_api_key();

        if ( empty( $api_key ) ) {
            return array(
                'success'   => false,
                'message'   => __( 'API key is missing. Please set it in licensesender settings.', 'licensesender' ),
                'http_code' => 0,
            );
        }

        $base = static::get_api_base_url();
        if ( empty( trim( $base ) ) || strpos( $base, '://' ) === false ) {
            return array(
                'success'   => false,
                'message'   => __( 'API base URL is missing or invalid. Please set it in licensesender settings.', 'licensesender' ),
                'http_code' => 0,
            );
        }

        $url = trailingslashit( $base ) . ltrim( $path, '/' );
        if ( ! empty( $args ) ) {
            $url = add_query_arg( $args, $url );
        }

        $response = wp_remote_get(
            $url,
            array(
                'headers' => static::support_headers( $access_token ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return array(
                'success'   => false,
                'message'   => 'Request failed: ' . $response->get_error_message(),
                'http_code' => 0,
            );
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        $raw_body  = wp_remote_retrieve_body( $response );
        $data      = json_decode( $raw_body, true );

        if ( ! is_array( $data ) ) {
            return array(
                'success'   => false,
                'message'   => $raw_body ?: __( 'Unexpected non-JSON response from API.', 'licensesender' ),
                'http_code' => $http_code,
            );
        }

        if ( $http_code < 200 || $http_code >= 300 || empty( $data['success'] ) ) {
            return array(
                'success'   => false,
                'message'   => $data['message'] ?? __( 'API request failed.', 'licensesender' ),
                'meta'      => $data['meta'] ?? array(),
                'data'      => $data['data'] ?? array(),
                'http_code' => $http_code,
            );
        }

        return array(
            'success'   => true,
            'message'   => $data['message'] ?? '',
            'data'      => $data['data'] ?? array(),
            'meta'      => $data['meta'] ?? array(),
            'http_code' => $http_code,
        );
    }

    /**
     * List support tickets for the connected shop.
     *
     * @param array<string, mixed> $args Query args.
     * @return array<string, mixed>
     */
    public static function list_support_tickets( array $args = array() ) {
        $query = array();

        if ( ! empty( $args['status'] ) ) {
            $query['status'] = sanitize_key( (string) $args['status'] );
        }

        if ( ! empty( $args['per_page'] ) ) {
            $query['per_page'] = max( 1, min( 100, (int) $args['per_page'] ) );
        }

        $response = static::request_get( 'support/tickets', $query );
        if ( empty( $response['success'] ) ) {
            return $response;
        }

        $tickets = $response['data']['tickets'] ?? $response['data'] ?? array();
        if ( ! is_array( $tickets ) ) {
            $tickets = array();
        }

        $response['tickets'] = $tickets;
        return $response;
    }

    /**
     * Merchant view of a support ticket (includes internal notes).
     *
     * @param string $ticket_number Ticket number.
     * @return array<string, mixed>
     */
    public static function get_support_ticket( $ticket_number ) {
        $ticket_number = sanitize_text_field( (string) $ticket_number );

        if ( $ticket_number === '' ) {
            return array(
                'success'   => false,
                'message'   => __( 'Missing ticket number.', 'licensesender' ),
                'http_code' => 400,
            );
        }

        $response = static::request_get( 'support/tickets/' . rawurlencode( $ticket_number ) );
        if ( empty( $response['success'] ) ) {
            return $response;
        }

        $ticket = $response['data']['ticket'] ?? $response['data'] ?? array();
        if ( ! is_array( $ticket ) ) {
            $ticket = array();
        }

        $response['ticket'] = $ticket;
        return $response;
    }

    /**
     * Create a customer support ticket.
     *
     * @param array<string, mixed> $fields Ticket payload.
     * @param array<int, array>    $files  Uploaded files.
     * @return array<string, mixed>
     */
    public static function create_support_ticket( array $fields, array $files = array() ) {
        $body = array(
            'customer_name'  => (string) ( $fields['customer_name'] ?? '' ),
            'customer_email' => sanitize_email( (string) ( $fields['customer_email'] ?? '' ) ),
            'subject'        => (string) ( $fields['subject'] ?? '' ),
            'message'        => (string) ( $fields['message'] ?? '' ),
        );

        if ( ! empty( $fields['category'] ) ) {
            $body['category'] = sanitize_key( (string) $fields['category'] );
        }
        if ( ! empty( $fields['priority'] ) ) {
            $body['priority'] = sanitize_key( (string) $fields['priority'] );
        }
        if ( ! empty( $fields['order_id'] ) ) {
            $body['order_id'] = (string) $fields['order_id'];
        }
        if ( ! empty( $fields['license_key'] ) ) {
            $body['license_key'] = sanitize_text_field( (string) $fields['license_key'] );
        }

        $response = static::request_multipart_post( 'support/tickets', $body, $files );
        if ( empty( $response['success'] ) ) {
            return $response;
        }

        $payload          = is_array( $response['data'] ?? null ) ? $response['data'] : array();
        $response['ticket'] = isset( $payload['ticket'] ) && is_array( $payload['ticket'] )
            ? array_merge( $payload, $payload['ticket'] )
            : $payload;

        return $response;
    }

    /**
     * Customer-visible conversation for a ticket.
     *
     * @param string $ticket_number Ticket number.
     * @param string $access_token  Customer access token.
     * @return array<string, mixed>
     */
    public static function get_support_conversation( $ticket_number, $access_token ) {
        $ticket_number = sanitize_text_field( (string) $ticket_number );
        $access_token  = trim( (string) $access_token );

        if ( $ticket_number === '' || $access_token === '' ) {
            return array(
                'success'   => false,
                'message'   => __( 'Missing ticket credentials.', 'licensesender' ),
                'http_code' => 400,
            );
        }

        $response = static::request_support_get(
            'support/tickets/' . rawurlencode( $ticket_number ) . '/conversation',
            array(),
            $access_token
        );

        if ( empty( $response['success'] ) ) {
            return $response;
        }

        $messages = $response['data']['messages'] ?? $response['data']['conversation'] ?? $response['data'] ?? array();
        if ( ! is_array( $messages ) ) {
            $messages = array();
        }

        $response['messages'] = $messages;
        return $response;
    }

    /**
     * Customer reply to a support ticket.
     *
     * @param string               $ticket_number Ticket number.
     * @param string               $access_token  Customer access token.
     * @param string               $message       Reply message.
     * @param array<int, array>    $files         Uploaded files.
     * @return array<string, mixed>
     */
    public static function reply_support_ticket( $ticket_number, $access_token, $message, array $files = array() ) {
        $ticket_number = sanitize_text_field( (string) $ticket_number );
        $access_token  = trim( (string) $access_token );
        $message       = trim( (string) $message );

        if ( $ticket_number === '' || $access_token === '' || $message === '' ) {
            return array(
                'success'   => false,
                'message'   => __( 'Missing reply data.', 'licensesender' ),
                'http_code' => 400,
            );
        }

        return static::request_multipart_post(
            'support/tickets/' . rawurlencode( $ticket_number ) . '/reply',
            array(
                'message'             => $message,
                'access_token'        => $access_token,
                'ticket_access_token' => $access_token,
            ),
            $files,
            $access_token
        );
    }

    /**
     * Customer reply via connected shop (logged-in customer, no portal token).
     *
     * @param string               $ticket_number Ticket number.
     * @param string               $customer_email Customer email.
     * @param string               $message Reply message.
     * @param array<int, array>    $files Uploaded files.
     * @return array<string, mixed>
     */
    public static function reply_support_ticket_as_customer( $ticket_number, $customer_email, $message, array $files = array() ) {
        $ticket_number  = sanitize_text_field( (string) $ticket_number );
        $customer_email = sanitize_email( (string) $customer_email );
        $message        = trim( (string) $message );

        if ( $ticket_number === '' || $customer_email === '' || $message === '' ) {
            return array(
                'success'   => false,
                'message'   => __( 'Missing reply data.', 'licensesender' ),
                'http_code' => 400,
            );
        }

        return static::request_multipart_post(
            'support/tickets/' . rawurlencode( $ticket_number ) . '/reply',
            array(
                'message'        => $message,
                'customer_email' => $customer_email,
            ),
            $files,
            '',
            $customer_email
        );
    }

    /**
     * Shop-verified customer reply fallback when no portal token is stored.
     *
     * @param string               $ticket_number Ticket number.
     * @param string               $customer_email Customer email.
     * @param string               $customer_name Customer name.
     * @param string               $message Reply message.
     * @param array<int, array>    $files Uploaded files.
     * @return array<string, mixed>
     */
    public static function reply_support_ticket_for_shop_customer( $ticket_number, $customer_email, $customer_name, $message, array $files = array() ) {
        $ticket_number  = sanitize_text_field( (string) $ticket_number );
        $customer_email = sanitize_email( (string) $customer_email );
        $customer_name  = sanitize_text_field( (string) $customer_name );
        $message        = trim( (string) $message );

        if ( $ticket_number === '' || $customer_email === '' || $message === '' ) {
            return array(
                'success'   => false,
                'message'   => __( 'Missing reply data.', 'licensesender' ),
                'http_code' => 400,
            );
        }

        return static::request_multipart_post(
            'support/tickets/' . rawurlencode( $ticket_number ) . '/staff-reply',
            array(
                'message'        => $message,
                'customer_email' => $customer_email,
                'customer_name'  => $customer_name,
                'author_type'    => 'customer',
                'is_internal'    => '0',
            ),
            $files,
            '',
            $customer_email
        );
    }

    /**
     * Start a SaaS chat session.
     *
     * @param array<string, mixed> $fields Session fields.
     * @return array<string, mixed>
     */
    public static function chat_start( array $fields = array() ) {
        return static::request_post( 'chat/sessions', $fields );
    }

    /**
     * Send a chat message.
     *
     * @param int                  $session_id Session ID.
     * @param string               $token      Public session token.
     * @param array<string, mixed> $fields     Message fields.
     * @return array<string, mixed>
     */
    public static function chat_send( $session_id, $token, array $fields, array $files = array() ) {
        if ( $files !== array() ) {
            return static::request_multipart_post(
                'chat/sessions/' . absint( $session_id ) . '/messages',
                $fields,
                $files,
                '',
                '',
                (string) $token
            );
        }

        return static::request_chat(
            'POST',
            'chat/sessions/' . absint( $session_id ) . '/messages',
            $fields,
            (string) $token
        );
    }

    /**
     * Poll chat messages.
     *
     * @param int    $session_id Session ID.
     * @param string $token      Public session token.
     * @param int    $since_id   Only messages after this ID.
     * @return array<string, mixed>
     */
    public static function chat_poll( $session_id, $token, $since_id = 0 ) {
        $path = 'chat/sessions/' . absint( $session_id ) . '/messages';
        if ( $since_id > 0 ) {
            $path .= '?since_id=' . absint( $since_id );
        }

        return static::request_chat( 'GET', $path, array(), (string) $token );
    }

    /**
     * Update visitor typing state.
     *
     * @param int                  $session_id Session ID.
     * @param string               $token      Public session token.
     * @param array<string, mixed> $fields     Typing fields.
     * @return array<string, mixed>
     */
    public static function chat_typing( $session_id, $token, array $fields = array() ) {
        return static::request_chat(
            'POST',
            'chat/sessions/' . absint( $session_id ) . '/typing',
            $fields,
            (string) $token
        );
    }

    /**
     * Escalate chat to a support ticket.
     *
     * @param int                  $session_id Session ID.
     * @param string               $token      Public session token.
     * @param array<string, mixed> $fields     Escalate fields.
     * @return array<string, mixed>
     */
    public static function chat_escalate( $session_id, $token, array $fields = array() ) {
        return static::chat_handoff( $session_id, $token, $fields );
    }

    /**
     * Request a live human agent (first-claim queue).
     *
     * @param int                  $session_id Session ID.
     * @param string               $token      Public session token.
     * @param array<string, mixed> $fields     Handoff fields.
     * @return array<string, mixed>
     */
    public static function chat_handoff( $session_id, $token, array $fields = array() ) {
        return static::request_chat(
            'POST',
            'chat/sessions/' . absint( $session_id ) . '/handoff',
            $fields,
            (string) $token
        );
    }

    /**
     * Issue a short-lived visitor broadcast credential.
     *
     * @param int    $session_id Session ID.
     * @param string $token      Public session token.
     * @return array<string, mixed>
     */
    public static function chat_broadcast_credential( $session_id, $token ) {
        return static::request_chat(
            'POST',
            'chat/sessions/' . absint( $session_id ) . '/broadcast-credential',
            array(),
            (string) $token
        );
    }

    /**
     * Authorize a visitor private channel for Reverb/Pusher.
     *
     * @param string               $token  Public session token (API key auth still applied).
     * @param array<string, mixed> $fields Auth fields.
     * @return array<string, mixed>
     */
    public static function chat_broadcast_auth( $token, array $fields ) {
        return static::request_chat(
            'POST',
            'chat/broadcasting/auth',
            $fields,
            (string) $token
        );
    }

    /**
     * Close a chat session.
     *
     * @param int    $session_id Session ID.
     * @param string $token      Public session token.
     * @return array<string, mixed>
     */
    public static function chat_close( $session_id, $token ) {
        return static::request_chat(
            'POST',
            'chat/sessions/' . absint( $session_id ) . '/close',
            array(),
            (string) $token
        );
    }

    /**
     * Send feedback for an assistant message.
     *
     * @param int                  $session_id Session ID.
     * @param string               $token      Public session token.
     * @param array<string, mixed> $fields     Feedback fields.
     * @return array<string, mixed>
     */
    public static function chat_feedback( $session_id, $token, array $fields = array() ) {
        return static::request_chat(
            'POST',
            'chat/sessions/' . absint( $session_id ) . '/feedback',
            $fields,
            (string) $token
        );
    }

    /**
     * Chat request helper (API key + session token).
     *
     * @param string               $method HTTP method.
     * @param string               $path   API path.
     * @param array<string, mixed> $body   JSON body for POST.
     * @param string               $token  Session public token.
     * @return array<string, mixed>
     */
    protected static function request_chat( $method, $path, array $body = array(), $token = '' ) {
        $api_key = static::get_api_key();

        if ( empty( $api_key ) ) {
            return array(
                'success'   => false,
                'message'   => __( 'API key is missing. Please set it in licensesender settings.', 'licensesender' ),
                'http_code' => 0,
            );
        }

        $base = static::get_api_base_url();
        if ( empty( trim( $base ) ) || strpos( $base, '://' ) === false ) {
            return array(
                'success'   => false,
                'message'   => __( 'API base URL is missing or invalid. Please set it in licensesender settings.', 'licensesender' ),
                'http_code' => 0,
            );
        }

        $url = trailingslashit( $base ) . ltrim( $path, '/' );
        $method = strtoupper( (string) $method );

        $headers = array(
            'X-API-KEY' => $api_key,
            'Accept'    => 'application/json',
        );

        if ( $token !== '' ) {
            $headers['X-Chat-Session-Token'] = $token;
        }

        $args = array(
            'method'  => $method,
            'headers' => $headers,
            'timeout' => 45,
        );

        if ( $method === 'POST' ) {
            $headers['Content-Type'] = 'application/json';
            $args['headers'] = $headers;
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return array(
                'success'   => false,
                'message'   => 'Request failed: ' . $response->get_error_message(),
                'http_code' => 0,
            );
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        $raw_body  = wp_remote_retrieve_body( $response );
        $data      = json_decode( $raw_body, true );

        if ( ! is_array( $data ) ) {
            return array(
                'success'   => false,
                'message'   => $raw_body ?: __( 'Unexpected non-JSON response from API.', 'licensesender' ),
                'http_code' => $http_code,
            );
        }

        if ( $http_code < 200 || $http_code >= 300 || empty( $data['success'] ) ) {
            return array(
                'success'   => false,
                'message'   => $data['message'] ?? __( 'API request failed.', 'licensesender' ),
                'meta'      => $data['meta'] ?? array(),
                'data'      => $data['data'] ?? array(),
                'errors'    => $data['errors'] ?? array(),
                'http_code' => $http_code,
            );
        }

        return array(
            'success'   => true,
            'message'   => $data['message'] ?? '',
            'data'      => $data['data'] ?? array(),
            'meta'      => $data['meta'] ?? array(),
            'http_code' => $http_code,
        );
    }
}
