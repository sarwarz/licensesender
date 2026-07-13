<?php
/**
 * Resolve mapped WooCommerce products for wholesale checkout (no shadow products).
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Wholesale_Catalog {

	const CART_FLAG = 'ls_wholesale';
	const CART_SKU  = 'ls_wholesale_sku';

	public static function init() {
		add_action( 'pre_get_posts', array( __CLASS__, 'exclude_from_admin_product_list' ) );
		add_action( 'woocommerce_product_query', array( __CLASS__, 'exclude_from_storefront_query' ) );
		add_action( 'template_redirect', array( __CLASS__, 'block_wholesale_product_pages' ) );
		add_filter( 'woocommerce_product_is_visible', array( __CLASS__, 'filter_product_is_visible' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_trash_legacy_shadow_products' ) );
	}

	/**
	 * Hide auto-created wholesale products from WooCommerce → Products admin list.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function exclude_from_admin_product_list( $query ) {
		if ( ! is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}

		global $pagenow;
		if ( $pagenow !== 'edit.php' || $query->get( 'post_type' ) !== 'product' ) {
			return;
		}

		// Allow intentional access: Products → filter with ls_wholesale=1.
		if ( isset( $_GET['ls_wholesale'] ) && (string) wp_unslash( $_GET['ls_wholesale'] ) === '1' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$meta_query = $query->get( 'meta_query' );
		if ( ! is_array( $meta_query ) ) {
			$meta_query = array();
		}

		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => '_ls_wholesale_product',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_ls_wholesale_product',
				'value'   => 'yes',
				'compare' => '!=',
			),
		);

		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Keep wholesale products out of shop / category / search loops.
	 *
	 * @param WC_Query $q WooCommerce query.
	 */
	public static function exclude_from_storefront_query( $q ) {
		$meta_query = (array) $q->get( 'meta_query' );
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => '_ls_wholesale_product',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_ls_wholesale_product',
				'value'   => 'yes',
				'compare' => '!=',
			),
		);
		$q->set( 'meta_query', $meta_query );
	}

	/**
	 * Block single-product pages for wholesale-only WC products.
	 */
	public static function block_wholesale_product_pages() {
		if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product_id = get_queried_object_id();
		if ( ! $product_id || ! self::is_wholesale_product( $product_id ) ) {
			return;
		}

		// Admins can still preview via direct URL while editing.
		if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_product', $product_id ) ) {
			return;
		}

		wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
		exit;
	}

	/**
	 * @param bool $visible    Whether product is visible.
	 * @param int  $product_id Product ID.
	 * @return bool
	 */
	public static function filter_product_is_visible( $visible, $product_id ) {
		if ( self::is_wholesale_product( $product_id ) ) {
			return false;
		}
		return $visible;
	}

	/**
	 * Find a WooCommerce product/variation already mapped to a LicenseSender SKU.
	 *
	 * Prefers shop products with `_ls_mapped_product` (not legacy shadow products).
	 *
	 * @param string $sku Catalog SKU.
	 * @return int Product or variation ID, or 0.
	 */
	public static function find_mapped_wc_product_by_sku( $sku ) {
		$sku = sanitize_text_field( $sku );
		if ( $sku === '' || ! function_exists( 'wc_get_product' ) ) {
			return 0;
		}

		$base_query = array(
			'post_type'              => array( 'product', 'product_variation' ),
			'post_status'            => array( 'publish', 'private' ),
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$not_shadow = array(
			'relation' => 'OR',
			array(
				'key'     => '_ls_wholesale_product',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_ls_wholesale_product',
				'value'   => 'yes',
				'compare' => '!=',
			),
		);

		// Prefer LS-enabled mapped retail products.
		$query = new WP_Query(
			array_merge(
				$base_query,
				array(
					'meta_query' => array(
						'relation' => 'AND',
						array(
							'key'   => '_ls_mapped_product',
							'value' => $sku,
						),
						array(
							'key'   => '_ls_enabled',
							'value' => 'yes',
						),
						$not_shadow,
					),
				)
			)
		);

		if ( ! empty( $query->posts[0] ) ) {
			return (int) $query->posts[0];
		}

		// Fall back to any non-shadow product mapped to this SKU.
		$query = new WP_Query(
			array_merge(
				$base_query,
				array(
					'meta_query' => array(
						'relation' => 'AND',
						array(
							'key'   => '_ls_mapped_product',
							'value' => $sku,
						),
						$not_shadow,
					),
				)
			)
		);

		if ( ! empty( $query->posts[0] ) ) {
			return (int) $query->posts[0];
		}

		// Legacy: previously auto-created shadow product (still usable until trashed).
		$query = new WP_Query(
			array_merge(
				$base_query,
				array(
					'meta_query' => array(
						array(
							'key'   => '_ls_wholesale_sku',
							'value' => $sku,
						),
						array(
							'key'   => '_ls_wholesale_product',
							'value' => 'yes',
						),
					),
				)
			)
		);

		return ! empty( $query->posts[0] ) ? (int) $query->posts[0] : 0;
	}

	/**
	 * @deprecated 1.0.2 Use find_mapped_wc_product_by_sku().
	 *
	 * @param string $sku SKU.
	 * @return int
	 */
	public static function find_wc_product_by_sku( $sku ) {
		return self::find_mapped_wc_product_by_sku( $sku );
	}

	/**
	 * Resolve a catalog item to an existing mapped WooCommerce product (never creates one).
	 *
	 * @param array $item  Catalog product payload.
	 * @param bool  $force Unused; kept for call-site compatibility.
	 * @return int|WP_Error WooCommerce product or variation ID.
	 */
	public static function resolve_wc_product( array $item, $force = false ) {
		unset( $force );

		if ( ! function_exists( 'wc_get_product' ) ) {
			return new WP_Error( 'woocommerce_missing', __( 'WooCommerce is required.', 'licensesender' ) );
		}

		$sku = (string) ( $item['sku'] ?? '' );
		if ( $sku === '' ) {
			return new WP_Error( 'missing_sku', __( 'Product SKU is missing.', 'licensesender' ) );
		}

		$product_id = self::find_mapped_wc_product_by_sku( $sku );
		if ( ! $product_id ) {
			return new WP_Error(
				'unmapped_sku',
				sprintf(
					/* translators: %s: catalog SKU */
					__( 'No WooCommerce product is mapped to SKU "%s". Open the product in WooCommerce, enable LicenseSender, and set the mapped product to this SKU.', 'licensesender' ),
					$sku
				)
			);
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'product_missing', __( 'Mapped WooCommerce product could not be loaded.', 'licensesender' ) );
		}

		return (int) $product_id;
	}

	/**
	 * @deprecated 1.0.2 Use resolve_wc_product(). Kept for older call sites; no longer creates products.
	 *
	 * @param array $item  Catalog product payload.
	 * @param bool  $force Unused.
	 * @return int|WP_Error
	 */
	public static function ensure_wc_product( array $item, $force = false ) {
		return self::resolve_wc_product( $item, $force );
	}

	/**
	 * Parent + variation IDs suitable for WC()->cart->add_to_cart().
	 *
	 * @param int $product_or_variation_id Product or variation ID.
	 * @return array{product_id:int,variation_id:int}|WP_Error
	 */
	public static function get_add_to_cart_ids( $product_or_variation_id ) {
		$product = wc_get_product( (int) $product_or_variation_id );
		if ( ! $product ) {
			return new WP_Error( 'product_missing', __( 'Mapped WooCommerce product could not be loaded.', 'licensesender' ) );
		}

		if ( $product->is_type( 'variation' ) ) {
			return array(
				'product_id'   => (int) $product->get_parent_id(),
				'variation_id' => (int) $product->get_id(),
			);
		}

		return array(
			'product_id'   => (int) $product->get_id(),
			'variation_id' => 0,
		);
	}

	/**
	 * Cart item data that marks a line as wholesale (distinct from the same retail product).
	 *
	 * @param string $sku Catalog SKU.
	 * @return array<string, string>
	 */
	public static function wholesale_cart_item_data( $sku ) {
		return array(
			self::CART_FLAG => 'yes',
			self::CART_SKU  => sanitize_text_field( (string) $sku ),
		);
	}

	/**
	 * Whether a cart line is a wholesale purchase.
	 *
	 * @param array $cart_item Cart item.
	 */
	public static function is_wholesale_cart_item( $cart_item ) {
		if ( ! empty( $cart_item[ self::CART_FLAG ] ) && $cart_item[ self::CART_FLAG ] === 'yes' ) {
			return true;
		}

		$product_id = isset( $cart_item['variation_id'] ) && (int) $cart_item['variation_id'] > 0
			? (int) $cart_item['variation_id']
			: (int) ( $cart_item['product_id'] ?? 0 );

		return $product_id > 0 && self::is_wholesale_product( $product_id );
	}

	/**
	 * Catalog SKU for a wholesale cart line.
	 *
	 * @param array $cart_item Cart item.
	 * @return string
	 */
	public static function get_cart_item_wholesale_sku( $cart_item ) {
		if ( ! empty( $cart_item[ self::CART_SKU ] ) ) {
			return sanitize_text_field( (string) $cart_item[ self::CART_SKU ] );
		}

		$product_id = isset( $cart_item['variation_id'] ) && (int) $cart_item['variation_id'] > 0
			? (int) $cart_item['variation_id']
			: (int) ( $cart_item['product_id'] ?? 0 );

		if ( ! $product_id ) {
			return '';
		}

		$sku = (string) get_post_meta( $product_id, '_ls_wholesale_sku', true );
		if ( $sku !== '' ) {
			return $sku;
		}

		return (string) get_post_meta( $product_id, '_ls_mapped_product', true );
	}

	/**
	 * One-time trash of legacy auto-created wholesale shadow products.
	 */
	public static function maybe_trash_legacy_shadow_products() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( get_option( 'ls_wholesale_shadows_trashed', '' ) === 'yes' ) {
			return;
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => array( 'publish', 'private', 'draft' ),
				'posts_per_page'         => 100,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => '_ls_wholesale_product',
						'value' => 'yes',
					),
				),
			)
		);

		foreach ( $query->posts as $product_id ) {
			wp_trash_post( (int) $product_id );
		}

		update_option( 'ls_wholesale_shadows_trashed', 'yes', false );
	}

	/**
	 * Ensure a media library attachment exists for the wholesale dummy image.
	 *
	 * @return int Attachment ID or 0 on failure.
	 */
	public static function get_or_create_placeholder_attachment_id() {
		$attachment_id = (int) get_option( 'ls_wholesale_placeholder_attachment_id', 0 );
		if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) {
			return $attachment_id;
		}

		$file = dirname( __DIR__ ) . '/public/images/wholesale-product-placeholder.png';
		if ( ! file_exists( $file ) ) {
			$file = dirname( __DIR__ ) . '/public/images/wholesale-product-placeholder.svg';
		}
		if ( ! file_exists( $file ) ) {
			return 0;
		}

		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp = wp_tempnam( basename( $file ) );
		if ( ! $tmp || ! copy( $file, $tmp ) ) {
			return 0;
		}

		$file_array = array(
			'name'     => basename( $file ),
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, 0, __( 'Wholesale product placeholder', 'licensesender' ) );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			return 0;
		}

		update_option( 'ls_wholesale_placeholder_attachment_id', (int) $attachment_id, false );

		return (int) $attachment_id;
	}

	/**
	 * Assign the dummy image when a wholesale product has no thumbnail.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function maybe_assign_placeholder_thumbnail( $product_id ) {
		$product_id = (int) $product_id;
		if ( ! $product_id || has_post_thumbnail( $product_id ) ) {
			return;
		}

		$attachment_id = self::get_or_create_placeholder_attachment_id();
		if ( $attachment_id ) {
			set_post_thumbnail( $product_id, $attachment_id );
		}
	}

	public static function is_wholesale_product( $product ) {
		if ( ! $product instanceof WC_Product ) {
			$product = wc_get_product( $product );
		}
		if ( ! $product ) {
			return false;
		}

		return get_post_meta( $product->get_id(), '_ls_wholesale_product', true ) === 'yes';
	}

	/**
	 * URL for the wholesale product dummy image.
	 *
	 * @return string
	 */
	public static function get_placeholder_image_url() {
		$url = plugins_url(
			'public/images/wholesale-product-placeholder.png',
			dirname( __DIR__ ) . '/licensesender.php'
		);

		/**
		 * Filter wholesale product placeholder image URL.
		 *
		 * @param string $url Placeholder image URL.
		 */
		return (string) apply_filters( 'ls_wholesale_placeholder_image_url', $url );
	}

	/**
	 * HTML for the wholesale product dummy image.
	 *
	 * @param string               $size Image size slug.
	 * @param array<string, mixed> $attr Image attributes.
	 * @return string
	 */
	public static function get_placeholder_image_html( $size = 'woocommerce_thumbnail', $attr = array() ) {
		$dimensions = function_exists( 'wc_get_image_size' ) ? wc_get_image_size( $size ) : array();
		$width      = ! empty( $dimensions['width'] ) ? (int) $dimensions['width'] : 300;
		$height     = ! empty( $dimensions['height'] ) ? (int) $dimensions['height'] : 300;
		$default    = array(
			'class'    => 'woocommerce-placeholder wp-post-image ls-wholesale-placeholder-image',
			'alt'      => __( 'Wholesale product', 'licensesender' ),
			'loading'  => 'lazy',
			'decoding' => 'async',
			'width'    => $width,
			'height'   => $height,
		);
		$attr       = wp_parse_args( is_array( $attr ) ? $attr : array(), $default );
		$url        = esc_url( self::get_placeholder_image_url() );

		$html = sprintf(
			'<img src="%1$s" alt="%2$s" class="%3$s" width="%4$d" height="%5$d" loading="%6$s" decoding="%7$s" />',
			$url,
			esc_attr( (string) $attr['alt'] ),
			esc_attr( (string) $attr['class'] ),
			(int) $attr['width'],
			(int) $attr['height'],
			esc_attr( (string) $attr['loading'] ),
			esc_attr( (string) $attr['decoding'] )
		);

		/**
		 * Filter wholesale product placeholder image HTML.
		 *
		 * @param string               $html Placeholder HTML.
		 * @param string               $size Image size.
		 * @param array<string, mixed> $attr Image attributes.
		 */
		return (string) apply_filters( 'ls_wholesale_placeholder_image_html', $html, $size, $attr );
	}

	/**
	 * Replace WooCommerce empty thumbnail with wholesale dummy image.
	 *
	 * @param string      $image       Image HTML.
	 * @param WC_Product  $product     Product.
	 * @param string      $size        Size.
	 * @param array       $attr        Attributes.
	 * @param bool        $placeholder Whether placeholder was requested.
	 * @param string|int  $image_id    Image ID.
	 * @return string
	 */
	public static function filter_product_image( $image, $product, $size = 'woocommerce_thumbnail', $attr = array(), $placeholder = true, $image_id = '' ) {
		unset( $image_id );

		if ( ! $placeholder || ! $product instanceof WC_Product ) {
			return $image;
		}

		if ( ! self::is_wholesale_product( $product ) ) {
			return $image;
		}

		if ( has_post_thumbnail( $product->get_id() ) ) {
			return $image;
		}

		$parent_id = wp_get_post_parent_id( $product->get_id() );
		if ( $parent_id && has_post_thumbnail( $parent_id ) ) {
			return $image;
		}

		return self::get_placeholder_image_html( $size, $attr );
	}

	/**
	 * Whether the cart contains only wholesale catalog products.
	 */
	public static function cart_has_only_wholesale_products() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! self::is_wholesale_cart_item( $cart_item ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether a wholesale SKU (or legacy shadow product) is still in the live API catalog.
	 *
	 * @param int         $product_id WooCommerce product ID (legacy).
	 * @param string|null $sku        Explicit SKU when using mapped retail products.
	 */
	public static function is_product_in_live_catalog( $product_id, $sku = null ) {
		if ( $sku === null || $sku === '' ) {
			$sku = (string) get_post_meta( (int) $product_id, '_ls_wholesale_sku', true );
			if ( $sku === '' ) {
				$sku = (string) get_post_meta( (int) $product_id, '_ls_mapped_product', true );
			}
		}

		$sku = sanitize_text_field( (string) $sku );
		if ( $sku === '' ) {
			return false;
		}

		$catalog = LS_Wholesale::get_catalog();
		if ( empty( $catalog['success'] ) ) {
			return false;
		}

		return null !== LS_Wholesale::get_catalog_product_by_sku( $sku, $catalog );
	}

	/**
	 * Whether the current cart contains at least one wholesale product.
	 */
	public static function cart_has_wholesale_product() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( self::is_wholesale_cart_item( $cart_item ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Total quantity of wholesale products in the current cart.
	 */
	public static function get_cart_wholesale_quantity() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0;
		}

		$total = 0;

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! self::is_wholesale_cart_item( $cart_item ) ) {
				continue;
			}

			$total += max( 0, (int) ( $cart_item['quantity'] ?? 0 ) );
		}

		return $total;
	}
}

LS_Wholesale_Catalog::init();
