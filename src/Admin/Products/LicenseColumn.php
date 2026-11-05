<?php

namespace LicenseSender\Admin\Products;

use LicenseSender\Core\Api;

defined( 'ABSPATH' ) || exit;

/**
 * "License delivery" column on Products: whether each product delivers LicenseSender keys,
 * and which LicenseSender SKU it is linked to.
 */
class LicenseColumn {

	const COLUMN = 'ls_map_status';

	/** LicenseSender SKUs are cached this long for the product list. */
	const SKU_CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/** @var array<string, true>|false|null null = not loaded, false = LicenseSender unreachable. */
	private static $saas_skus = null;

	public function __construct() {
		add_filter( 'manage_edit-product_columns', array( $this, 'add_column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-product_sortable_columns', array( $this, 'sortable_column' ) );
		add_action( 'pre_get_posts', array( $this, 'sort_by_status' ) );
		add_action( 'admin_head-edit.php', array( $this, 'print_styles' ) );
	}

	public function add_column( $columns ) {
		$new_columns = array();
		$label       = sprintf(
			'<span class="ls-col-head"><span class="dashicons dashicons-admin-network" aria-hidden="true"></span>%s</span>',
			esc_html__( 'License delivery', 'licensesender' )
		);

		foreach ( $columns as $key => $title ) {
			$new_columns[ $key ] = $title;
			if ( $key === 'sku' ) {
				$new_columns[ self::COLUMN ] = $label;
			}
		}

		if ( ! isset( $new_columns[ self::COLUMN ] ) ) {
			$new_columns[ self::COLUMN ] = $label;
		}

		return $new_columns;
	}

	public function render_column( $column, $post_id ) {
		if ( $column !== self::COLUMN ) {
			return;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return;
		}

		if ( $product->is_type( 'variable' ) && get_option( 'lship_enable_variation_support', 'no' ) === 'yes' ) {
			echo $this->variable_status( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
			return;
		}

		echo $this->status( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
			get_post_meta( $post_id, '_ls_enabled', true ) === 'yes',
			trim( (string) get_post_meta( $post_id, '_ls_mapped_product', true ) )
		);
	}

	/**
	 * One product (or a variable product without per-variation mapping).
	 */
	private function status( $enabled, $sku ) {
		if ( ! $enabled ) {
			return $this->badge( 'off', 'minus', __( 'Not linked', 'licensesender' ), '', __( 'This product does not deliver LicenseSender keys. Turn it on in the product’s LicenseSender tab.', 'licensesender' ) );
		}

		if ( $sku === '' ) {
			return $this->badge( 'warn', 'warning', __( 'Choose product', 'licensesender' ), '', __( 'LicenseSender is on, but no LicenseSender product is selected. Customers can’t get keys yet.', 'licensesender' ) );
		}

		$known = $this->sku_status( $sku );
		if ( $known === null ) {
			/* translators: %s: LicenseSender SKU */
			return $this->badge( 'unknown', 'update', __( 'Linked', 'licensesender' ), $sku, sprintf( __( 'Linked to %s. LicenseSender could not be reached to confirm it.', 'licensesender' ), $sku ) );
		}
		if ( ! $known ) {
			/* translators: %s: LicenseSender SKU */
			return $this->badge( 'error', 'dismiss', __( 'SKU not found', 'licensesender' ), $sku, sprintf( __( '“%s” no longer exists in LicenseSender. Choose another product so customers can get keys.', 'licensesender' ), $sku ) );
		}

		/* translators: %s: LicenseSender SKU */
		return $this->badge( 'ok', 'yes-alt', __( 'Linked', 'licensesender' ), $sku, sprintf( __( 'Delivers keys from LicenseSender product %s.', 'licensesender' ), $sku ) );
	}

	private function variable_status( \WC_Product $product ) {
		$total   = 0;
		$linked  = 0;
		$missing = 0;
		$unknown = false;

		foreach ( $product->get_children() as $variation_id ) {
			++$total;
			if ( get_post_meta( $variation_id, '_ls_enabled', true ) !== 'yes' ) {
				continue;
			}
			$sku = trim( (string) get_post_meta( $variation_id, '_ls_mapped_product', true ) );
			$ok  = $sku !== '' ? $this->sku_status( $sku ) : false;
			if ( $ok === null ) {
				$unknown = true;
				++$linked;
			} elseif ( $ok ) {
				++$linked;
			} else {
				++$missing;
			}
		}

		/* translators: 1: variations linked, 2: all variations */
		$counts = sprintf( __( '%1$d of %2$d variations', 'licensesender' ), $linked, $total );

		if ( $linked === 0 && $missing === 0 ) {
			return $this->badge( 'off', 'minus', __( 'Not linked', 'licensesender' ), '', __( 'No variation delivers LicenseSender keys.', 'licensesender' ) );
		}
		if ( $missing > 0 ) {
			return $this->badge( 'warn', 'warning', __( 'Needs attention', 'licensesender' ), $counts, __( 'Some variations have LicenseSender on but no valid product selected.', 'licensesender' ) );
		}

		return $this->badge( $unknown ? 'unknown' : 'ok', 'yes-alt', __( 'Linked', 'licensesender' ), $counts, __( 'Variations delivering LicenseSender keys.', 'licensesender' ) );
	}

	private function badge( $tone, $icon, $label, $detail, $title ) {
		return sprintf(
			'<span class="ls-map ls-map--%1$s" title="%2$s"><span class="ls-map__pill"><span class="dashicons dashicons-%3$s" aria-hidden="true"></span>%4$s</span>%5$s</span>',
			esc_attr( $tone ),
			esc_attr( $title ),
			esc_attr( $icon ),
			esc_html( $label ),
			$detail !== '' ? '<code class="ls-map__sku">' . esc_html( $detail ) . '</code>' : ''
		);
	}

	/**
	 * @return bool|null true = exists in LicenseSender, false = not found, null = couldn't check.
	 */
	private function sku_status( $sku ) {
		$skus = $this->get_saas_skus();

		return $skus === false ? null : isset( $skus[ $sku ] );
	}

	/**
	 * LicenseSender SKUs, cached briefly so the product list doesn't call the API every load.
	 *
	 * @return array<string, true>|false
	 */
	private function get_saas_skus() {
		if ( self::$saas_skus !== null ) {
			return self::$saas_skus;
		}

		$cache_key = 'ls_product_skus_' . md5( (string) get_option( 'lship_api_key', '' ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return self::$saas_skus = $cached;
		}

		$response = class_exists( Api::class ) ? Api::fetch_product_list() : array();
		if ( empty( $response['success'] ) || ! isset( $response['products'] ) || ! is_array( $response['products'] ) ) {
			return self::$saas_skus = false;
		}

		$skus = array();
		foreach ( $response['products'] as $product ) {
			$sku = isset( $product['sku'] ) ? trim( (string) $product['sku'] ) : '';
			if ( $sku !== '' ) {
				$skus[ $sku ] = true;
			}
		}

		set_transient( $cache_key, $skus, self::SKU_CACHE_TTL );

		return self::$saas_skus = $skus;
	}

	public function sortable_column( $columns ) {
		$columns[ self::COLUMN ] = self::COLUMN;
		return $columns;
	}

	/**
	 * Sort by the column: products delivering keys first (or last), then by LicenseSender SKU.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function sort_by_status( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'product' || $query->get( 'orderby' ) !== self::COLUMN ) {
			return;
		}

		$order = strtoupper( (string) $query->get( 'order' ) ) === 'ASC' ? 'ASC' : 'DESC';
		$query->set(
			'meta_query',
			array(
				'relation'   => 'OR',
				'ls_enabled' => array( 'key' => '_ls_enabled', 'compare' => 'EXISTS' ),
				array( 'key' => '_ls_enabled', 'compare' => 'NOT EXISTS' ),
			)
		);
		$query->set( 'orderby', array( 'ls_enabled' => $order, 'title' => 'ASC' ) );
	}

	public function print_styles() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->id !== 'edit-product' ) {
			return;
		}
		?>
		<style id="ls-map-column">
			.column-ls_map_status { width: 160px; }
			.ls-col-head { display: inline-flex; align-items: center; gap: 4px; }
			.ls-col-head .dashicons { font-size: 16px; width: 16px; height: 16px; color: #646970; }
			.ls-map { display: inline-flex; flex-direction: column; align-items: flex-start; gap: 4px; }
			.ls-map__pill { display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px 2px 6px; border-radius: 999px; font-size: 12px; font-weight: 500; line-height: 20px; white-space: nowrap; }
			.ls-map__pill .dashicons { font-size: 15px; width: 15px; height: 15px; }
			.ls-map__sku { padding: 1px 6px; font-size: 11px; color: #50575e; background: #f0f0f1; border-radius: 4px; }
			.ls-map--ok .ls-map__pill { color: #00703c; background: #e6f4ea; }
			.ls-map--unknown .ls-map__pill { color: #2c3338; background: #f0f0f1; }
			.ls-map--warn .ls-map__pill { color: #8a5300; background: #fcf3e3; }
			.ls-map--error .ls-map__pill { color: #b32d2e; background: #fcf0f1; }
			.ls-map--off .ls-map__pill { color: #646970; background: transparent; padding-left: 0; }
		</style>
		<?php
	}
}
