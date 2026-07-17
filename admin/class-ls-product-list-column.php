<?php
defined( 'ABSPATH' ) || exit;

class LS_Product_List_License_Column {

	/** @var array|null|false null=not loaded, false=API failed, array=sku=>true */
	private static $saas_skus = null;

	public function __construct() {
		add_filter( 'manage_edit-product_columns', array( $this, 'add_column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-product_sortable_columns', array( $this, 'sortable_column' ) );
	}

	/**
	 * Add column header
	 */
	public function add_column( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( $key === 'sku' ) {
				$new_columns['ls_map_status'] = __( 'Product Mapped', 'licensesender' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render column content
	 */
	public function render_column( $column, $post_id ) {
		if ( $column !== 'ls_map_status' ) {
			return;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return;
		}

		if ( $product->is_type( 'simple' ) ) {
			echo $this->get_status_icon(
				get_post_meta( $post_id, '_ls_enabled', true ),
				get_post_meta( $post_id, '_ls_mapped_product', true )
			);
			return;
		}

		if ( $product->is_type( 'variable' ) ) {
			if ( get_option( 'lship_enable_variation_support', 'no' ) !== 'yes' ) {
				echo $this->get_status_icon(
					get_post_meta( $post_id, '_ls_enabled', true ),
					get_post_meta( $post_id, '_ls_mapped_product', true )
				);
				return;
			}

			$valid   = 0;
			$stale   = 0;
			$enabled = 0;

			foreach ( $product->get_children() as $variation_id ) {
				$is_enabled = get_post_meta( $variation_id, '_ls_enabled', true );
				$mapped     = trim( (string) get_post_meta( $variation_id, '_ls_mapped_product', true ) );

				if ( $is_enabled !== 'yes' ) {
					continue;
				}

				$enabled++;

				if ( $mapped === '' ) {
					continue;
				}

				if ( $this->sku_exists_in_saas( $mapped ) ) {
					$valid++;
				} else {
					$stale++;
				}
			}

			if ( $enabled === 0 ) {
				echo $this->icon_not_mapped();
			} elseif ( $valid === $enabled && $stale === 0 ) {
				echo $this->icon_mapped();
			} elseif ( $valid > 0 || $stale > 0 ) {
				echo $this->icon_partial();
			} else {
				echo $this->icon_enabled_only();
			}
		}
	}

	private function icon_mapped( $sku = '' ) {
		$title = $sku
			? sprintf(
				/* translators: %s: LicenseSender SKU */
				__( 'Mapped to LicenseSender SKU: %s', 'licensesender' ),
				$sku
			)
			: __( 'Mapped', 'licensesender' );

		return '<span class="dashicons dashicons-yes-alt" title="' . esc_attr( $title ) . '" style="color:#46b450;font-size:18px;"></span>';
	}

	private function icon_not_mapped() {
		return '<span class="dashicons dashicons-no-alt" title="' . esc_attr__( 'Not mapped (enable LicenseSender and choose a product)', 'licensesender' ) . '" style="color:#dc3232;font-size:18px;"></span>';
	}

	private function icon_enabled_only() {
		return '<span class="dashicons dashicons-warning" title="' . esc_attr__( 'LicenseSender enabled, but no product mapping selected', 'licensesender' ) . '" style="color:#ffb900;font-size:18px;"></span>';
	}

	private function icon_stale( $sku = '' ) {
		$title = $sku
			? sprintf(
				/* translators: %s: LicenseSender SKU stored in WordPress */
				__( 'Saved mapping “%s” was not found in LicenseSender — re-map the product', 'licensesender' ),
				$sku
			)
			: __( 'Saved mapping not found in LicenseSender — re-map the product', 'licensesender' );

		return '<span class="dashicons dashicons-warning" title="' . esc_attr( $title ) . '" style="color:#ffb900;font-size:18px;"></span>';
	}

	private function icon_partial() {
		return '<span class="dashicons dashicons-warning" title="' . esc_attr__( 'Partially mapped', 'licensesender' ) . '" style="color:#ffb900;font-size:18px;"></span>';
	}

	/**
	 * Green only when enabled, a SKU is stored, and that SKU exists in LicenseSender.
	 */
	private function get_status_icon( $enabled, $mapped_product ) {
		$mapped_product = is_string( $mapped_product ) ? trim( $mapped_product ) : '';

		if ( $enabled === 'yes' && $mapped_product !== '' ) {
			if ( $this->sku_exists_in_saas( $mapped_product ) ) {
				return $this->icon_mapped( $mapped_product );
			}

			return $this->icon_stale( $mapped_product );
		}

		if ( $enabled === 'yes' ) {
			return $this->icon_enabled_only();
		}

		return $this->icon_not_mapped();
	}

	/**
	 * @return bool true if SKU is present in LicenseSender product list
	 */
	private function sku_exists_in_saas( $sku ) {
		$skus = $this->get_saas_skus();

		// If API is unavailable, do not show a false green — treat as unverified/stale.
		if ( $skus === false ) {
			return false;
		}

		return isset( $skus[ $sku ] );
	}

	/**
	 * Fetch LicenseSender SKUs once per admin list request.
	 *
	 * @return array<string,true>|false
	 */
	private function get_saas_skus() {
		if ( self::$saas_skus !== null ) {
			return self::$saas_skus;
		}

		if ( ! class_exists( 'Licensesender_Api' ) ) {
			self::$saas_skus = false;
			return self::$saas_skus;
		}

		$response = Licensesender_Api::fetch_product_list();

		if ( empty( $response['success'] ) || empty( $response['products'] ) || ! is_array( $response['products'] ) ) {
			self::$saas_skus = false;
			return self::$saas_skus;
		}

		$skus = array();
		foreach ( $response['products'] as $product ) {
			$sku = isset( $product['sku'] ) ? trim( (string) $product['sku'] ) : '';
			if ( $sku !== '' ) {
				$skus[ $sku ] = true;
			}
		}

		self::$saas_skus = $skus;
		return self::$saas_skus;
	}

	public function sortable_column( $columns ) {
		$columns['ls_map_status'] = 'ls_map_status';
		return $columns;
	}
}

new LS_Product_List_License_Column();
