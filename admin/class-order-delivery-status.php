<?php
defined( 'ABSPATH' ) || exit;

class Ls_Licensesender_Order_Delivery_Status {

	public static function init() {
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_delivery_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_delivery_column_legacy' ), 10, 2 );

		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'add_delivery_column' ), 20 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'render_delivery_column_hpos' ), 10, 2 );

		add_action( 'admin_head', array( __CLASS__, 'admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_list_assets' ) );
		add_action( 'wp_ajax_ls_admin_refresh_delivery_column', array( __CLASS__, 'ajax_refresh_delivery_column' ) );
	}

	public static function add_delivery_column( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( $key === 'order_status' ) {
				$new_columns['ls_delivery_status'] = __( 'Delivery', 'licensesender' );
			}
		}

		return $new_columns;
	}

	public static function render_delivery_column_legacy( $column, $order_id ) {
		if ( $column !== 'ls_delivery_status' ) {
			return;
		}

		echo self::get_delivery_icon_html( wc_get_order( $order_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function render_delivery_column_hpos( $column, $order ) {
		if ( $column !== 'ls_delivery_status' ) {
			return;
		}

		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		echo self::get_delivery_icon_html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @param WC_Order|false|null $order Order.
	 */
	public static function get_delivery_icon_html( $order ): string {
		if ( ! $order instanceof WC_Order ) {
			return '—';
		}

		$status = function_exists( 'ls_get_order_license_delivery_status' )
			? ls_get_order_license_delivery_status( $order )
			: 'none';

		$order_id       = (int) $order->get_id();
		$expected_total = function_exists( 'ls_count_expected_license_keys' ) ? ls_count_expected_license_keys( $order ) : 0;
		$fetched_total  = function_exists( 'ls_count_fetched_license_keys' ) ? ls_count_fetched_license_keys( $order_id ) : 0;

		if ( $status === 'none' || $expected_total <= 0 ) {
			return '—';
		}

		if ( $status === 'complete' ) {
			$title = __( 'All license keys delivered', 'licensesender' );
			return '<span class="dashicons dashicons-yes-alt ls-delivery-complete" title="' . esc_attr( $title ) . '" data-ls-delivery="complete"></span>';
		}

		if ( $status === 'partial' ) {
			$title = sprintf(
				/* translators: 1: fetched count, 2: expected count */
				__( 'Partial delivery: %1$d / %2$d keys', 'licensesender' ),
				$fetched_total,
				$expected_total
			);
			return '<span class="dashicons dashicons-marker ls-delivery-partial" title="' . esc_attr( $title ) . '" data-ls-delivery="partial"></span>';
		}

		if ( $status === 'waiting' ) {
			$title = __( 'Waiting for order completion', 'licensesender' );
			return '<span class="dashicons dashicons-clock ls-delivery-waiting" title="' . esc_attr( $title ) . '" data-ls-delivery="waiting"></span>';
		}

		// Completed order with empty local cache — mark for background SaaS sync.
		$title = __( 'License pending — syncing…', 'licensesender' );
		return sprintf(
			'<span class="dashicons dashicons-warning ls-delivery-pending ls-delivery-needs-sync" title="%1$s" data-ls-delivery="pending" data-order-id="%2$d"></span>',
			esc_attr( $title ),
			$order_id
		);
	}

	public static function enqueue_list_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}

		$allowed = array( 'edit-shop_order', 'woocommerce_page_wc-orders' );
		if ( ! in_array( $screen->id, $allowed, true ) ) {
			return;
		}

		$handle = 'ls-order-delivery-column';
		wp_register_script( $handle, false, array( 'jquery' ), defined( 'LICENSESENDER_VERSION' ) ? LICENSESENDER_VERSION : '1.0.0', true );
		wp_enqueue_script( $handle );
		wp_localize_script(
			$handle,
			'lsDeliveryColumn',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ls_delivery_column' ),
				'i18n'    => array(
					'syncing' => __( 'Syncing license delivery…', 'licensesender' ),
				),
			)
		);

		wp_add_inline_script(
			$handle,
			<<<'JS'
(function ($) {
	function refreshOne($el) {
		var orderId = parseInt($el.data('order-id'), 10) || 0;
		if (!orderId || $el.data('lsSyncing')) {
			return $.Deferred().resolve().promise();
		}
		$el.data('lsSyncing', 1).attr('title', (window.lsDeliveryColumn && lsDeliveryColumn.i18n.syncing) || 'Syncing…');

		return $.post(lsDeliveryColumn.ajaxUrl, {
			action: 'ls_admin_refresh_delivery_column',
			_ajax_nonce: lsDeliveryColumn.nonce,
			order_id: orderId
		}).done(function (res) {
			if (res && res.success && res.data && res.data.html) {
				$el.replaceWith(res.data.html);
			} else {
				$el.removeClass('ls-delivery-needs-sync').data('lsSyncing', 0);
			}
		}).fail(function () {
			$el.removeClass('ls-delivery-needs-sync').data('lsSyncing', 0);
		});
	}

	function runQueue() {
		var $nodes = $('.ls-delivery-needs-sync').slice(0, 5);
		if (!$nodes.length) {
			return;
		}
		var chain = $.Deferred().resolve().promise();
		$nodes.each(function () {
			var $el = $(this);
			chain = chain.then(function () { return refreshOne($el); });
		});
		chain.always(function () {
			setTimeout(runQueue, 400);
		});
	}

	$(function () {
		if (!window.lsDeliveryColumn) {
			return;
		}
		runQueue();
	});
})(jQuery);
JS
		);
	}

	public static function ajax_refresh_delivery_column() {
		check_ajax_referer( 'ls_delivery_column', '_ajax_nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'licensesender' ) ) );
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'licensesender' ) ) );
		}

		if ( class_exists( 'LS_License_Cache' ) && $order->get_status() === 'completed' ) {
			$expected = ls_count_expected_license_keys( $order );
			$fetched  = ls_count_fetched_license_keys( $order_id );
			if ( $expected > 0 && $fetched < $expected ) {
				LS_License_Cache::sync_order_licenses( $order_id, true );

				// Still short after sync: ask SaaS fetch path (idempotent) per product.
				$fetched = ls_count_fetched_license_keys( $order_id );
				if ( $fetched < $expected && class_exists( 'Licensesender_Api' ) ) {
					foreach ( $order->get_items() as $item ) {
						$product_id = (int) ( $item->get_variation_id() ?: $item->get_product_id() );
						if ( ! ls_is_licensesender_enabled( $product_id ) ) {
							continue;
						}
						$sku = ls_get_mapped_sku( $product_id );
						if ( $sku === '' ) {
							continue;
						}
						$need = ls_count_expected_keys_for_product_in_order( $order, $product_id );
						$have = count( ls_get_cached_licenses_for_product( $order_id, $product_id ) );
						if ( $have >= $need ) {
							continue;
						}
						$api_qty = ls_api_quantity_for_product_fetch( $order, $product_id, $sku );
						$api     = Licensesender_Api::fetch_license(
							array(
								'sku'      => $sku,
								'quantity' => $api_qty,
								'order_id' => $order_id,
								'email'    => $order->get_billing_email(),
								'source'   => sanitize_title( get_bloginfo( 'name' ) ) ?: 'woocommerce',
							)
						);
						if ( ! empty( $api['success'] ) && ! empty( $api['licenses'] ) && is_array( $api['licenses'] ) ) {
							$links = ls_get_license_product_links( $product_id );
							$info  = is_array( $api['product'] ?? null ) ? $api['product'] : array();
							LS_License_Cache::save_fetched_licenses(
								$order_id,
								$product_id,
								$sku,
								$order->get_billing_email(),
								$api['licenses'],
								$links['download_link'] ?: ( $info['download_link'] ?? '' ),
								$links['activation_guide'] ?: ( $info['activation_guide'] ?? '' ),
								'list-sync'
							);
						}
					}
				}
			}
		}

		if ( function_exists( 'ls_refresh_order_license_delivery_meta' ) ) {
			ls_refresh_order_license_delivery_meta( $order );
			$order = wc_get_order( $order_id );
		}

		wp_send_json_success(
			array(
				'html'   => self::get_delivery_icon_html( $order ),
				'status' => function_exists( 'ls_get_order_license_delivery_status' ) ? ls_get_order_license_delivery_status( $order ) : '',
			)
		);
	}

	public static function admin_styles() {
		?>
		<style>
			.wp-list-table .column-ls_delivery_status {
				width: 80px;
				text-align: center;
			}
			.ls-delivery-complete {
				color: #46b450;
				font-size: 18px;
				font-weight: bold;
			}
			.ls-delivery-partial {
				color: #dba617;
				font-size: 18px;
				font-weight: bold;
			}
			.ls-delivery-pending {
				color: #d63638;
				font-size: 18px;
				font-weight: bold;
			}
			.ls-delivery-waiting {
				color: #8c8f94;
				font-size: 18px;
				font-weight: bold;
			}
			.ls-delivery-needs-sync {
				opacity: 0.75;
			}
		</style>
		<?php
	}
}

Ls_Licensesender_Order_Delivery_Status::init();
