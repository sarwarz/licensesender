<?php
defined( 'ABSPATH' ) || exit;

class Ls_Licensesender_Order_Delivery_Status {

	public static function init() {
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_delivery_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_delivery_column_legacy' ), 10, 2 );

		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'add_delivery_column' ), 20 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'render_delivery_column_hpos' ), 10, 2 );

		add_action( 'admin_head', array( __CLASS__, 'admin_styles' ) );
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

		self::render_delivery_icon( wc_get_order( $order_id ) );
	}

	public static function render_delivery_column_hpos( $column, $order ) {
		if ( $column !== 'ls_delivery_status' ) {
			return;
		}

		self::render_delivery_icon( $order instanceof WC_Order ? $order : wc_get_order( $order ) );
	}

	private static function render_delivery_icon( $order ) {
		if ( ! $order instanceof WC_Order ) {
			echo '—';
			return;
		}

		if ( ! ls_order_has_licensable_products( $order ) ) {
			echo '—';
			return;
		}

		$order_id       = $order->get_id();
		$expected_total = ls_count_expected_license_keys( $order );
		$fetched_total  = ls_count_fetched_license_keys( $order_id );

		if ( $expected_total <= 0 ) {
			echo '—';
			return;
		}

		if ( $fetched_total >= $expected_total ) {
			$title = __( 'All license keys delivered', 'licensesender' );
			echo '<span class="dashicons dashicons-yes-alt ls-delivery-complete" title="' . esc_attr( $title ) . '"></span>';
			return;
		}

		if ( $fetched_total > 0 ) {
			$title = sprintf(
				/* translators: 1: fetched count, 2: expected count */
				__( 'Partial delivery: %1$d / %2$d keys', 'licensesender' ),
				$fetched_total,
				$expected_total
			);
			echo '<span class="dashicons dashicons-marker ls-delivery-partial" title="' . esc_attr( $title ) . '"></span>';
			return;
		}

		echo '<span class="dashicons dashicons-warning ls-delivery-pending" title="' . esc_attr__( 'License pending', 'licensesender' ) . '"></span>';
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
				color: red;
				font-size: 18px;
				font-weight: bold;
			}
		</style>
		<?php
	}
}

Ls_Licensesender_Order_Delivery_Status::init();
