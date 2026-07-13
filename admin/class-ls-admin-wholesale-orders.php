<?php
/**
 * Admin UI for wholesale orders.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Admin_Wholesale_Orders {

	public static function init() {
		add_action( 'restrict_manage_posts', array( __CLASS__, 'render_filter_legacy' ), 20 );
		add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( __CLASS__, 'render_filter_hpos' ), 20 );

		add_filter( 'request', array( __CLASS__, 'filter_orders_legacy' ) );
		add_filter( 'woocommerce_shop_order_list_table_prepare_items_query_args', array( __CLASS__, 'filter_orders_hpos' ) );

		add_filter( 'woocommerce_admin_order_buyer_name', array( __CLASS__, 'append_wholesale_to_buyer_name' ), 20, 2 );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'render_order_badge' ) );
		add_action( 'admin_head', array( __CLASS__, 'admin_styles' ) );
	}

	public static function render_filter_legacy( $post_type ) {
		if ( 'shop_order' !== $post_type ) {
			return;
		}

		self::render_filter_dropdown();
	}

	public static function render_filter_hpos() {
		self::render_filter_dropdown();
	}

	private static function render_filter_dropdown() {
		$current = isset( $_GET['ls_wholesale_order'] ) ? sanitize_key( wp_unslash( $_GET['ls_wholesale_order'] ) ) : '';
		?>
		<label for="ls_wholesale_order" class="screen-reader-text"><?php esc_html_e( 'Filter by order type', 'licensesender' ); ?></label>
		<select name="ls_wholesale_order" id="ls_wholesale_order">
			<option value=""><?php esc_html_e( 'All order types', 'licensesender' ); ?></option>
			<option value="yes" <?php selected( $current, 'yes' ); ?>><?php esc_html_e( 'Wholesale orders', 'licensesender' ); ?></option>
			<option value="no" <?php selected( $current, 'no' ); ?>><?php esc_html_e( 'Regular orders', 'licensesender' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Current filter value: '', 'yes', or 'no'.
	 *
	 * @return string
	 */
	private static function get_filter_value() {
		if ( empty( $_GET['ls_wholesale_order'] ) ) {
			return '';
		}

		$value = sanitize_key( wp_unslash( $_GET['ls_wholesale_order'] ) );
		return in_array( $value, array( 'yes', 'no' ), true ) ? $value : '';
	}

	/**
	 * Meta query clauses for the wholesale/regular filter.
	 *
	 * @param string $value Filter value (yes|no).
	 * @return array<int, array<string, string>>
	 */
	private static function get_filter_meta_query( $value ) {
		if ( 'yes' === $value ) {
			return array(
				array(
					'key'   => LS_Wholesale_Orders::ORDER_META,
					'value' => 'yes',
				),
			);
		}

		if ( 'no' === $value ) {
			return array(
				'relation' => 'OR',
				array(
					'key'     => LS_Wholesale_Orders::ORDER_META,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => LS_Wholesale_Orders::ORDER_META,
					'value'   => 'yes',
					'compare' => '!=',
				),
			);
		}

		return array();
	}

	public static function filter_orders_legacy( $vars ) {
		global $typenow;

		if ( 'shop_order' !== $typenow ) {
			return $vars;
		}

		$value = self::get_filter_value();
		if ( '' === $value ) {
			return $vars;
		}

		$meta_query = isset( $vars['meta_query'] ) && is_array( $vars['meta_query'] ) ? $vars['meta_query'] : array();
		$filter     = self::get_filter_meta_query( $value );

		if ( empty( $filter ) ) {
			return $vars;
		}

		$vars['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta_query, array( $filter ) );

		return $vars;
	}

	public static function filter_orders_hpos( $args ) {
		$value = self::get_filter_value();
		if ( '' === $value ) {
			return $args;
		}

		$filter = self::get_filter_meta_query( $value );
		if ( empty( $filter ) ) {
			return $args;
		}

		if ( ! isset( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
			$args['meta_query'] = array();
		}

		$args['meta_query'][] = $filter;

		return $args;
	}

	/**
	 * Append "(Wholesale)" to the buyer name on the orders list.
	 *
	 * @param string        $buyer Buyer name.
	 * @param WC_Order|null $order Order object.
	 * @return string
	 */
	public static function append_wholesale_to_buyer_name( $buyer, $order ) {
		if ( ! $order instanceof WC_Order || ! ls_order_is_wholesale( $order ) ) {
			return $buyer;
		}

		$buyer = trim( (string) $buyer );
		$label = __( '(Wholesale)', 'licensesender' );

		if ( '' === $buyer ) {
			return $label;
		}

		if ( false !== strpos( $buyer, $label ) ) {
			return $buyer;
		}

		return $buyer . ' ' . $label;
	}

	public static function render_order_badge( $order ) {
		if ( ! $order instanceof WC_Order || ! ls_order_is_wholesale( $order ) ) {
			return;
		}

		$tier = (string) $order->get_meta( LS_Wholesale_Orders::TIER_META, true );
		?>
		<p class="form-field form-field-wide ls-wholesale-order-flag">
			<span class="ls-wholesale-order-badge"><?php esc_html_e( 'Wholesale order', 'licensesender' ); ?></span>
			<?php if ( $tier ) : ?>
				<span class="ls-wholesale-order-tier">
					<?php
					printf(
						/* translators: %s: wholesale pricing tier name */
						esc_html__( 'Pricing tier: %s', 'licensesender' ),
						esc_html( ucwords( str_replace( array( '-', '_' ), ' ', $tier ) ) )
					);
					?>
				</span>
			<?php endif; ?>
		</p>
		<?php
	}

	public static function admin_styles() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'edit-shop_order', 'shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
			return;
		}
		?>
		<style>
			.ls-wholesale-order-badge {
				display: inline-flex;
				align-items: center;
				padding: 3px 10px;
				border-radius: 999px;
				background: #eef2ff;
				color: #4338ca;
				font-size: 12px;
				font-weight: 700;
				line-height: 1.4;
				white-space: nowrap;
			}
			.ls-wholesale-order-flag {
				margin-top: 12px;
			}
			.ls-wholesale-order-tier {
				display: inline-block;
				margin-left: 8px;
				color: #64748b;
				font-size: 13px;
			}
			#ls_wholesale_order {
				float: left;
				margin-right: 6px;
				max-width: 180px;
			}
		</style>
		<?php
	}
}

LS_Admin_Wholesale_Orders::init();
