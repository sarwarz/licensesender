<?php
defined('ABSPATH') || exit;

class Licensesender_Product_Tab {

	public function __construct() {
		
		add_filter('woocommerce_product_data_tabs', [$this, 'add_license_tab']);
		add_action('woocommerce_product_data_panels', [$this, 'render_license_tab_content']);
		add_action('woocommerce_process_product_meta', [$this, 'save_license_tab_fields']);

		$variation_support = get_option('lship_enable_variation_support', 'no');
		if ($variation_support === 'yes') {
		    add_action('woocommerce_product_after_variable_attributes', [$this, 'variation_license_fields'], 10, 3);
			add_action('woocommerce_save_product_variation', [$this, 'save_variation_license_fields'], 10, 2);
		}

	}

	public function add_license_tab($tabs) {
		$tabs['licensesender'] = [
			'label'    => __('LicenseSender', 'licensesender'),
			'target'   => 'licensesender_product_data',
			'class'    => [],
			'priority' => 60,
		];
		return $tabs;
	}

	public function render_license_tab_content() {
		global $post;

		$mapped_value = get_post_meta($post->ID, '_ls_mapped_product', true);
		$is_enabled   = get_post_meta($post->ID, '_ls_enabled', true);

		// Call the API to get product list
		$api_response = Licensesender_Api::fetch_product_list();
		$products     = $api_response['success'] ? $api_response['products'] : [];

		?>
		<div id="licensesender_product_data" class="panel woocommerce_options_panel" style="display:none;">

			<p class="form-field">
				<label for="ls_enabled"><?php _e('Enable LicenseSender', 'licensesender'); ?></label>
				<input type="checkbox" name="ls_enabled" id="ls_enabled" class="checkbox" value="yes" <?php checked($is_enabled, 'yes'); ?> />
				<span class="description"><?php _e('Enable this option to allow license key delivery via LicenseSender for this product.', 'licensesender'); ?></span>
			</p>


			<p class="form-field ls-mapped-product-field" id="ls_mapped_product_field"<?php echo $is_enabled === 'yes' ? '' : ' style="display:none;"'; ?>>
				<label for="ls_mapped_product"><?php _e('Product Mapping', 'licensesender'); ?></label>
				<select id="ls_mapped_product" name="ls_mapped_product" class="select2" style="width: 50%;">
					<option value=""><?php _e('Select LicenseSender Product', 'licensesender'); ?></option>
					<?php foreach ($products as $product): 
						$value = esc_attr($product['sku']);
						$label = esc_html($product['name'] . ' (' . $product['sku'] . ')');
						$selected = selected($mapped_value, $value, false);
						echo "<option value='{$value}' {$selected}>{$label}</option>";
					endforeach; ?>
				</select>
				<span class="woocommerce-help-tip" data-tip="<?php echo esc_attr__('Associate this WooCommerce product with an external API product using either the product name or SKU.', 'licensesender'); ?>"></span>
			</p>

			<?php if (!$api_response['success']): ?>
				<p class="ls-mapped-product-field" style="color: red;<?php echo $is_enabled === 'yes' ? '' : ' display:none;'; ?>">
					<?php echo esc_html($api_response['message']); ?>
				</p>
			<?php endif; ?>

		</div>
		<?php
	}



	public function save_license_tab_fields($post_id) {
		update_post_meta($post_id, '_ls_enabled', isset($_POST['ls_enabled']) ? 'yes' : 'no');

		if (isset($_POST['ls_mapped_product'])) {
			update_post_meta($post_id, '_ls_mapped_product', sanitize_text_field($_POST['ls_mapped_product']));
		}
	}


	public function variation_license_fields($loop, $variation_data, $variation) {
	    $mapped_value = get_post_meta($variation->ID, '_ls_mapped_product', true);
	    $is_enabled   = get_post_meta($variation->ID, '_ls_enabled', true);

	    // API product list
	    $api_response = Licensesender_Api::fetch_product_list();
	    $products     = $api_response['success'] ? $api_response['products'] : [];

	    ?>
	    <div class="ls-variation-license-fields">
	        <p class="form-row form-row-full">
	        	<label><?php _e('Enable LicenseSender', 'licensesender'); ?></label>
		        <label class="checkbox-label">
		            <input type="checkbox" class="ls-enabled-variation" name="ls_enabled[<?php echo esc_attr($loop); ?>]" value="yes" <?php checked($is_enabled, 'yes'); ?> />
		            <?php _e('Enable this variation for license delivery.', 'licensesender'); ?>
		        </label>
	        </p>

	        <p class="form-row form-row-full ls-mapped-variation-field"<?php echo $is_enabled === 'yes' ? '' : ' style="display:none;"'; ?>>
	        	<label><?php _e('Mapped License Product', 'licensesender'); ?></label>
		        <select name="ls_mapped_product[<?php echo esc_attr($loop); ?>]" class="wc-enhanced-select ls_mapped_variation_product" style="width:100%;" >
		            <option value=""><?php _e('Select LicenseSender Product', 'licensesender'); ?></option>
		            <?php foreach ($products as $product): 
		                $value = esc_attr($product['sku']);
		                $label = esc_html($product['name'] . ' (' . $product['sku'] . ')');
		                $selected = selected($mapped_value, $value, false);
		                echo "<option value='{$value}' {$selected}>{$label}</option>";
		            endforeach; ?>
		        </select>
	        </p>
	    </div>
	    <?php
	}


	public function save_variation_license_fields($variation_id, $i) {
	    $enabled_values = isset($_POST['ls_enabled']) ? $_POST['ls_enabled'] : [];
	    $mapped_values  = isset($_POST['ls_mapped_product']) ? $_POST['ls_mapped_product'] : [];

	    $enabled = isset($enabled_values[$i]) && $enabled_values[$i] === 'yes' ? 'yes' : 'no';
	    $mapped  = isset($mapped_values[$i]) ? sanitize_text_field($mapped_values[$i]) : '';

	    update_post_meta($variation_id, '_ls_enabled', $enabled);
	    update_post_meta($variation_id, '_ls_mapped_product', $mapped);
	}







}

new Licensesender_Product_Tab();
