<?php
defined('ABSPATH') || exit;

class License_Shipper_Api {

    /**
     * Get API base URL from options
     */
    protected static function get_api_base_url() {
        return trailingslashit(get_option('lship_api_base_url', 'https://app.licenseshipper.com/api/'));
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
                'message' => __('API key is missing. Please set it in License Shipper settings.', 'license-shipper'),
                'http_code' => 0,
            ];
        }

        if (empty(trim(static::get_api_base_url())) || strpos($api_url, '://') === false) {
            return [
                'success' => false,
                'message' => __('API base URL is missing or invalid. Please set it in License Shipper settings.', 'license-shipper'),
                'http_code' => 0,
            ];
        }

        $payload = [
            'sku'         => $params['sku'] ?? '',
            'quantity'    => isset($params['quantity']) ? (int) $params['quantity'] : 1,
            'order_id'    => $params['order_id'] ?? '',
            'order_email' => $params['email'] ?? '',
            'source'      => $params['source'] ?? 'license-shipper',
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
                'message'   => $raw_body ?: __('Unexpected non-JSON response from API.', 'license-shipper'),
                'http_code' => $http_code,
            ];
        }

        // Handle any non-2xx or explicit failure
        if ($http_code < 200 || $http_code >= 300 || empty($data['success'])) {
            // Build validation error text if present
            $error_text = $data['message'] ?? __('Unknown API error.', 'license-shipper');

            if (!empty($data['errors']) && is_array($data['errors'])) {
                $messages = [];
                foreach ($data['errors'] as $field => $msgs) {
                    foreach ((array) $msgs as $msg) {
                        $messages[] = ucfirst($field) . ': ' . $msg;
                    }
                }
                if ($messages) {
                    $error_text = implode('<br>', $messages);
                }
            }

            return [
                'success'   => false,
                'message'   => $error_text,
                'meta'      => $data['meta'] ?? [],      // <-- includes reason/scope/block_id if blocked
                'html'      => $data['html'] ?? null,    // <-- if server sent ready-made HTML
                'http_code' => $http_code,
            ];
        }

        // Success
        return [
            'success'   => true,
            'licenses'  => $data['data']['licenses'] ?? [],
            'product'   => $data['data']['product']  ?? [],
            'meta'      => $data['meta']             ?? [],  // keep symmetry
            'http_code' => $http_code,
        ];
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
                'message' => __('API key is missing. Please set it in License Shipper settings.', 'license-shipper'),
            ];
        }

        if (empty(trim(static::get_api_base_url())) || strpos($product_url, '://') === false) {
            return [
                'success' => false,
                'message' => __('API base URL is missing or invalid. Please set it in License Shipper settings.', 'license-shipper'),
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
     * Ping API server to test connection and auth
     */
    public static function ping() {
        $ping_url = static::get_api_base_url() . 'ping';
        $api_key  = static::get_api_key();

        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('API key is missing. Please set it in License Shipper settings.', 'license-shipper'),
            ];
        }

        if (empty(trim(static::get_api_base_url())) || strpos($ping_url, '://') === false) {
            return [
                'success' => false,
                'message' => __('API base URL is missing or invalid. Please set it in License Shipper settings.', 'license-shipper'),
            ];
        }

        $response = wp_remote_get($ping_url, [
            'headers' => [
                'X-API-KEY' => $api_key,
            ],
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Ping request failed: ' . $response->get_error_message(),
            ];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body      = wp_remote_retrieve_body($response);
        $data      = json_decode($body, true);

        if ($http_code !== 200 || !isset($data['success']) || !$data['success']) {
            return [
                'success' => false,
                'message' => $data['message'] ?? 'Ping failed or invalid response.',
                'meta'    => $data['meta'] ?? [],
            ];
        }

        return [
            'success' => true,
            'message' => $data['message'] ?? 'API responded successfully.',
            'data'    => $data['data'] ?? [],
            'meta'    => $data['meta'] ?? [],
        ];
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
                'message'   => __( 'API key is missing. Please set it in License Shipper settings.', 'license-shipper' ),
                'http_code' => 0,
            ];
        }

        if ( empty( trim( $base ) ) || strpos( $base, '://' ) === false ) {
            return [
                'success'   => false,
                'message'   => __( 'API base URL is missing or invalid. Please set it in License Shipper settings.', 'license-shipper' ),
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
                'message'   => $raw_body ?: __( 'Unexpected non-JSON response from API.', 'license-shipper' ),
                'http_code' => $http_code,
            ];
        }

        if ( $http_code < 200 || $http_code >= 300 || empty( $data['success'] ) ) {
            $error_text = $data['message'] ?? __( 'Unknown API error.', 'license-shipper' );
            if ( ! empty( $data['errors'] ) && is_array( $data['errors'] ) ) {
                $messages = [];
                foreach ( $data['errors'] as $field => $msgs ) {
                    foreach ( (array) $msgs as $msg ) {
                        $messages[] = ucfirst( $field ) . ': ' . $msg;
                    }
                }
                if ( $messages ) {
                    $error_text = implode( '<br>', $messages );
                }
            }
            return [
                'success'   => false,
                'message'   => $error_text,
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
                'message'   => __( 'No available license found for this SKU.', 'license-shipper' ),
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
                'message'   => __( 'Invalid order ID.', 'license-shipper' ),
                'http_code' => 400,
            ];
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return [
                'success'   => false,
                'message'   => __( 'Order not found.', 'license-shipper' ),
                'http_code' => 404,
            ];
        }

        if ( ! ls_is_order_license_ready( $order ) ) {
            return [
                'success'   => false,
                'message'   => __( 'License is not ready yet for this order.', 'license-shipper' ),
                'meta'      => [
                    'reason' => 'order_not_completed_by_ls_delivery_system',
                ],
                'http_code' => 403,
            ];
        }

        return true;
    }




}
